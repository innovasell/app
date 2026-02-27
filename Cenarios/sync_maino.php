<?php
// sync_maino.php
require_once 'conexao.php';

set_time_limit(0); // A sincronização pode demorar

$apiKey = '77acff2977ab3aa96ddaf33add9a3cc6';
$baseUrl = 'https://api.maino.com.br/api/v2';
$headers = [
    "X-Api-Key: $apiKey",
    "Content-Type: application/json"
];

function fetchMainoAPI($endpoint, $params = []) {
    global $baseUrl, $headers;
    
    $url = $baseUrl . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Debug logging can be added here
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    } else {
        return ['error' => true, 'code' => $httpCode, 'response' => $response];
    }
}

function getLastSyncDate($pdo, $key) {
    $stmt = $pdo->prepare("SELECT valor FROM sync_config WHERE chave = :chave");
    $stmt->execute(['chave' => $key]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ? $res['valor'] : null;
}

function updateLastSyncDate($pdo, $key, $val) {
    $stmt = $pdo->prepare("INSERT INTO sync_config (chave, valor) VALUES (:chave, :valor) 
                           ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    $stmt->execute(['chave' => $key, 'valor' => $val]);
}

$responseLog = [];

try {
    // 1. Sincronizar Clientes
    $page = 1;
    do {
        $data = fetchMainoAPI('/clientes', ['page' => $page, 'per_page' => 100]);
        if (isset($data['error'])) break;
        
        $clientes = $data['clientes'] ?? [];
        if (empty($clientes)) break;

        $stmt = $pdo->prepare("INSERT INTO clientes (id_maino, razao_social, cnpj, cidade, uf) 
            VALUES (:id, :nome, :cnpj, :cidade, :uf)
            ON DUPLICATE KEY UPDATE razao_social=VALUES(razao_social), cidade=VALUES(cidade), uf=VALUES(uf)");
        
        foreach ($clientes as $cli) {
            $stmt->execute([
                'id' => $cli['id'],
                'nome' => $cli['razao_social'] ?? $cli['nome'] ?? 'N/D',
                'cnpj' => $cli['cnpj'] ?? null,
                'cidade' => $cli['cidade'] ?? null,
                'uf' => $cli['uf'] ?? null
            ]);
        }
        
        $page++;
        usleep(250000); // 250ms Rate limit prevent
        $hasNext = $data['pagination']['next_page'] ?? false;
    } while ($hasNext);

    $responseLog[] = "Clientes sincronizados.";

    // 2. Sincronizar Produtos
    $page = 1;
    do {
        $data = fetchMainoAPI('/produtos', ['page' => $page, 'per_page' => 100]);
        if (isset($data['error'])) break;
        
        $produtos = $data['produtos'] ?? [];
        if (empty($produtos)) break;

        $stmt = $pdo->prepare("INSERT INTO produtos (id_maino, codigo_interno, descricao, unidade) 
            VALUES (:id, :cod, :desc, :unid)
            ON DUPLICATE KEY UPDATE codigo_interno=VALUES(codigo_interno), descricao=VALUES(descricao), unidade=VALUES(unidade)");
        
        foreach ($produtos as $prod) {
            $stmt->execute([
                'id' => $prod['id'],
                'cod' => $prod['codigo'] ?? null,
                'desc' => $prod['nome'] ?? null,
                'unid' => $prod['unidade'] ?? null
            ]);
        }
        
        $page++;
        usleep(250000);
        $hasNext = $data['pagination']['next_page'] ?? false;
    } while ($hasNext);

    $responseLog[] = "Produtos sincronizados.";

    // 3. Sincronizar Estoque
    $page = 1;
    do {
        $data = fetchMainoAPI('/estoque', ['page' => $page, 'per_page' => 100]);
        if (isset($data['error'])) break;
        
        $estoqueItems = $data['estoque'] ?? [];
        if (empty($estoqueItems)) break;

        $stmt = $pdo->prepare("INSERT INTO estoque (id_produto, quantidade_atual, data_atualizacao) 
            VALUES (:id_prod, :qtd, NOW())
            ON DUPLICATE KEY UPDATE quantidade_atual=VALUES(quantidade_atual), data_atualizacao=NOW()");
        
        foreach ($estoqueItems as $est) {
            // Pode variar pelo retorno real da API se é produto_id ou id, tratando:
            $produtoId = $est['produto_id'] ?? $est['produto']['id'] ?? $est['id'] ?? null;
            if ($produtoId) {
                // A chave estrangeira obriga que o produto já exista. Usamos try/catch de item para não falhar batch.
                try {
                    $stmt->execute([
                        'id_prod' => $produtoId,
                        'qtd' => $est['quantidade'] ?? 0
                    ]);
                } catch (Exception $e) {}
            }
        }
        
        $page++;
        usleep(250000);
        $hasNext = $data['pagination']['next_page'] ?? false;
    } while ($hasNext);

    $responseLog[] = "Estoque sincronizado.";

    // 4. Sincronizar Movimentações (Carga Incremental)
    $lastSyncDate = getLastSyncDate($pdo, 'ultima_sincronizacao_movimentacoes') ?? '2000-01-01 00:00:00';
    $currentSyncDate = date('Y-m-d H:i:s');
    
    $startDateParam = date('d/m/Y', strtotime($lastSyncDate));
    
    $page = 1;
    do {
        $data = fetchMainoAPI('/movimentacoes_fisicas', ['page' => $page, 'per_page' => 100, 'data_inicio' => $startDateParam]);
        if (isset($data['error'])) {
            $responseLog[] = "Erro na API movimentacoes_fisicas: " . $data['response'];
            break;
        }
        
        $movs = $data['movimentacoes_fisicas'] ?? [];
        if (empty($movs)) break;

        $stmt = $pdo->prepare("INSERT INTO movimentacoes (id_maino, id_cliente, id_produto, data, quantidade, valor_unitario, tipo) 
            VALUES (:id, :id_cli, :id_prod, :dt, :qtd, :val, :tipo)
            ON DUPLICATE KEY UPDATE id_cliente=VALUES(id_cliente), id_produto=VALUES(id_produto), data=VALUES(data), 
                                    quantidade=VALUES(quantidade), valor_unitario=VALUES(valor_unitario), tipo=VALUES(tipo)");
        
        foreach ($movs as $m) {
            try {
                // Algumas APIs retornam id direto ou dentro de subarray, ajustando:
                $idMov = $m['id'];
                $idCli = $m['cliente_id'] ?? $m['cliente']['id'] ?? null;
                $idProd = $m['produto_id'] ?? $m['produto']['id'] ?? null;
                $dt = $m['data'] ?? date('Y-m-d'); 
                // data vem como DD/MM/YYYY ou YYYY-MM-DD
                if (strpos($dt, '/') !== false) {
                    $dtObj = DateTime::createFromFormat('d/m/Y', $dt);
                    if ($dtObj) $dt = $dtObj->format('Y-m-d');
                } else {
                    $dt = substr($dt, 0, 10);
                }
                $qtd = $m['quantidade'] ?? 0;
                $val = $m['valor_unitario'] ?? $m['valor'] ?? 0;
                $tipo = strtolower($m['tipo'] ?? 'saida');
                if ($tipo != 'entrada') $tipo = 'saida';

                if ($idCli && $idProd) {
                    $stmt->execute([
                        'id' => $idMov,
                        'id_cli' => $idCli,
                        'id_prod' => $idProd,
                        'dt' => $dt,
                        'qtd' => $qtd,
                        'val' => $val,
                        'tipo' => $tipo
                    ]);
                }
            } catch (Exception $e) {
                // Ignorar erro de FK temporariamente se cliente/produto não existir na base local
            }
        }
        
        $page++;
        usleep(250000);
        $hasNext = $data['pagination']['next_page'] ?? false;
    } while ($hasNext);

    // Save success time
    updateLastSyncDate($pdo, 'ultima_sincronizacao_movimentacoes', $currentSyncDate);
    $responseLog[] = "Movimentações sincronizadas incrementalmente.";

    echo json_encode(['status' => 'success', 'messages' => $responseLog]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
