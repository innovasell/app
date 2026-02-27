<?php
/**
 * API para processar upload de arquivo CSV do VIAGEM EXPRESS
 * Versão com conexão centralizada - SEM credenciais hardcoded
 */

// Limpa qualquer buffer existente e inicia novo
while (ob_get_level())
    ob_end_clean();
ob_start();

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => '', 'data' => []];

try {
    // Usa conexão centralizada - SEM credenciais hardcoded!
    require_once __DIR__ . '/../conexao.php';
    // Agora temos $conn (mysqli) disponível

    // Verifica se o arquivo foi enviado
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Nenhum arquivo foi enviado ou houve erro no upload.');
    }

    $file = $_FILES['csv_file'];
    $filePath = $file['tmp_name'];

    // Verifica extensão
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileExtension !== 'csv') {
        throw new Exception('O arquivo deve ser do tipo CSV.');
    }

    // Gera batch ID
    $batchId = 'BATCH_' . date('YmdHis') . '_' . uniqid();

    // Abre o arquivo
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Não foi possível abrir o arquivo CSV.');
    }

    $lineCount = 0;
    $importedCount = 0;
    $skippedCount = 0;
    $categoryCounts = [
        'Passagem Aérea' => 0,
        'Hotel' => 0,
        'Seguro' => 0,
        'Transporte' => 0,
        'Outros' => 0,
        'Não Categorizado' => 0
    ];

    // Lê header
    $delimiter = ';'; // Padrão
    $header = fgetcsv($handle, 0, $delimiter);

    // Verifica se é Uber (delimitador pode ser vírgula em alguns casos)
    // Se o header tiver apenas 1 coluna e parecer CSV, tentamos vírgula
    if (count($header) == 1 && (strpos($header[0], ',') !== false || strpos($header[0], 'ID da viagem') !== false)) {
        rewind($handle);
        $delimiter = ',';
        $header = fgetcsv($handle, 0, $delimiter);
    }

    // Detecta tipo de arquivo
    $isUber = false;
    // Verifica colunas típicas da Uber
    $uberColumns = ['Data da solicitação', 'Endereço de partida', 'Endereço de destino', 'Valor total: BRL'];
    foreach ($uberColumns as $col) {
        $found = false;
        foreach ($header as $h) {
            if (stripos($h, $col) !== false) {
                $found = true;
                break;
            }
        }
        if ($found) {
            $isUber = true;
            break;
        }
    }

    if (!$header) {
        fclose($handle);
        throw new Exception('O arquivo CSV está vazio ou mal formatado.');
    }

    // Mapeamento de colunas Uber (índices)
    $uberMap = [];
    if ($isUber) {
        foreach ($header as $index => $colName) {
            $colName = trim($colName);
            // Mapeia colunas importantes pelo nome
            if (stripos($colName, 'Data da solicitação (local)') !== false)
                $uberMap['data'] = $index;
            if (stripos($colName, 'Nome') !== false && !isset($uberMap['nome']))
                $uberMap['nome'] = $index; // Evita pegar "Nome do parceiro" se vier antes, mas Nome geralmente é col 11
            if ($colName === 'Nome')
                $uberMap['nome'] = $index; // Match exato preferencial
            if (stripos($colName, 'Sobrenome') !== false)
                $uberMap['sobrenome'] = $index;
            if (stripos($colName, 'Endereço de partida') !== false)
                $uberMap['origem'] = $index;
            if (stripos($colName, 'Endereço de destino') !== false)
                $uberMap['destino'] = $index;
            if (stripos($colName, 'Valor total: BRL') !== false)
                $uberMap['total'] = $index;
            if (stripos($colName, 'Fatura de preço da Uber n.º') !== false)
                $uberMap['fatura'] = $index;
        }
    }

    // Funções helper
    function parseDate($dateStr, $isUber = false)
    {
        if (empty($dateStr))
            return null;

        // Formato Uber: 2026-02-12 15:30 ou 12/02/2026 (MM/DD/YYYY)
        if (strpos($dateStr, '-') !== false) {
            // Tenta YYYY-MM-DD
            $parts = explode(' ', $dateStr);
            return $parts[0];
        }

        $parts = explode('/', $dateStr);
        if (count($parts) === 3) {
            if ($isUber) {
                // Uber: MM/DD/YYYY -> YYYY-MM-DD
                return sprintf('%04d-%02d-%02d', $parts[2], $parts[0], $parts[1]);
            }
            // Padrão: DD/MM/YYYY -> YYYY-MM-DD
            return sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);
        }
        return null;
    }

    function parseDecimal($value)
    {
        if (empty($value))
            return 0.00;
        // Remove R$, espaços
        $value = str_replace([' ', 'R$', '[', ']'], '', $value);

        // Se tiver vírgula e ponto, assume formato brasileiro (1.234,56)
        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            $value = str_replace('.', '', $value); // Remove milhar
            $value = str_replace(',', '.', $value); // Troca decimal
        } elseif (strpos($value, ',') !== false) {
            // Apenas vírgula (1234,56)
            $value = str_replace(',', '.', $value);
        }

        return floatval($value);
    }

    function categorizarDespesa($produto, $isUber = false)
    {
        if ($isUber)
            return 'Transporte'; // Uber agora vai para Transporte

        $produto = strtoupper($produto);
        if (preg_match('/(AÉREO|AEREO|PASSAGEM|FLIGHT|VOO)/i', $produto)) {
            return 'Passagem Aérea';
        }
        if (preg_match('/(HOTEL|HOSPEDAGEM|ACCOMMODATION|HOTELARIA)/i', $produto)) {
            return 'Hotel';
        }
        if (preg_match('/(SEGURO|INSURANCE)/i', $produto)) {
            return 'Seguro';
        }
        if (!empty($produto)) {
            return 'Outros';
        }
        return 'Não Categorizado';
    }

    // Captura o número da fatura do POST (usado como fallback/padrão)
    $numFaturaPost = $_POST['num_fatura'] ?? '';
    if (empty($numFaturaPost) && !$isUber) {
        throw new Exception('Número da fatura não informado.');
    }

    // SQL com campos evento_visita e num_fatura
    $sql = "INSERT INTO viagem_express_expenses (
        cod_cliente, cliente, dt_emissao, passageiro, produto, 
        total, categoria_despesa, categoria_auto, batch_id, num_fatura, evento_visita
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        fclose($handle);
        throw new Exception('Erro ao preparar statement: ' . $conn->error);
    }

    // Processa linhas
    while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
        $lineCount++;

        // Pula linhas vazias
        if (empty(implode('', $row))) {
            $skippedCount++;
            continue;
        }

        $codCliente = '';
        $cliente = ''; // Uber não tem cliente específico, talvez empresa?
        $dtEmissao = null;
        $passageiro = '';
        $produto = '';
        $total = 0.0;
        $categoria = 'Não Categorizado';
        $numFatura = $numFaturaPost;
        $eventoVisita = null;

        if ($isUber) {
            // Processamento UBER
            $idxData = $uberMap['data'] ?? -1;
            $idxNome = $uberMap['nome'] ?? -1;
            $idxSobrenome = $uberMap['sobrenome'] ?? -1;
            $idxOrigem = $uberMap['origem'] ?? -1;
            $idxDestino = $uberMap['destino'] ?? -1;
            $idxTotal = $uberMap['total'] ?? -1;
            $idxFatura = $uberMap['fatura'] ?? -1;

            if ($idxData >= 0)
                $dtEmissao = parseDate($row[$idxData], true);

            $nome = $idxNome >= 0 ? ($row[$idxNome] ?? '') : '';
            $sobrenome = $idxSobrenome >= 0 ? ($row[$idxSobrenome] ?? '') : '';
            $passageiro = trim("$nome $sobrenome");
            if (empty($passageiro))
                $passageiro = 'Funcionário Uber';

            $origem = $idxOrigem >= 0 ? ($row[$idxOrigem] ?? '') : '';
            $destino = $idxDestino >= 0 ? ($row[$idxDestino] ?? '') : '';

            // Corrige caracteres estranhos se houver
            $origem = mb_convert_encoding($origem, 'UTF-8', 'UTF-8');
            $destino = mb_convert_encoding($destino, 'UTF-8', 'UTF-8');

            $produto = "Uber: $origem > $destino";
            // Limita tamanho
            if (strlen($produto) > 250)
                $produto = substr($produto, 0, 247) . '...';

            if ($idxTotal >= 0)
                $total = parseDecimal($row[$idxTotal]);

            $categoria = 'Transporte';

            // Fatura do CSV se existir e não for vazia
            if ($idxFatura >= 0 && !empty($row[$idxFatura])) {
                $numFatura = $row[$idxFatura];
            }

            // Se não tiver data válida (ex: linhas de rodapé), pula
            if (!$dtEmissao) {
                // Tenta ver se é linha válida de dados
                if ($total > 0) {
                    // Data falhou mas tem total? Tenta pegar data atual ou logar erro
                    // Por hora, pula se não tem data
                }
                if ($lineCount > 1) { // Só conta como skipped se não for header repetido ou linha vazia
                    $skippedCount++;
                }
                continue;
            }

        } else {
            // Processamento VIAGEM EXPRESS (Padrão Antigo)
            $codCliente = $row[0] ?? '';
            $cliente = $row[1] ?? '';
            $dtEmissao = parseDate($row[2] ?? '', false);
            $passageiro = $row[10] ?? '';  // CAMPO 10 = passageiro
            $produto = $row[11] ?? '';
            $total = parseDecimal($row[31] ?? 0);
            $categoria = categorizarDespesa($produto);
        }

        $categoriaAuto = 1;

        if ($dtEmissao) { // Só insere se tiver data
            $categoryCounts[$categoria] = ($categoryCounts[$categoria] ?? 0) + 1;

            $stmt->bind_param(
                'sssssdsssss',
                $codCliente,
                $cliente,
                $dtEmissao,
                $passageiro,
                $produto,
                $total,
                $categoria,
                $categoriaAuto,
                $batchId,
                $numFatura,
                $eventoVisita
            );

            if ($stmt->execute()) {
                $importedCount++;
            } else {
                $skippedCount++;
                // Silently ignore or log specific errors
                // error_log("Erro linha DB: " . $stmt->error);
            }
        } else {
            $skippedCount++;
        }
    }

    fclose($handle);
    $stmt->close();
    $conn->close();

    $response['success'] = true;
    $response['message'] = "Importação concluída com sucesso!";
    $response['data'] = [
        'batch_id' => $batchId,
        'total_lines' => $lineCount,
        'imported' => $importedCount,
        'skipped' => $skippedCount,
        'categories' => $categoryCounts
    ];

} catch (Exception $e) {
    error_log("ERRO UPLOAD: " . $e->getMessage());
    $response['message'] = 'Erro: ' . $e->getMessage();
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

while (ob_get_level())
    ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;