<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['representante_email'])) {
    echo json_encode(['erro' => 'Usuário não autenticado.']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$numCenario = isset($data['num_scenario']) ? $data['num_scenario'] : '';

if (empty($numCenario)) {
    echo json_encode(['erro' => 'Número do cenário não informado.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Buscar Cabeçalho do Cenário
    $stmt = $pdo->prepare("SELECT * FROM cot_cenarios_importacao WHERE num_cenario = :num");
    $stmt->execute([':num' => $numCenario]);
    $cenario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cenario) {
        throw new Exception("Cenário não encontrado.");
    }

    if ($cenario['oc_gerada'] == 1) {
        throw new Exception("Já existe uma Ordem de Compra gerada para este cenário.");
    }

    // 2. Buscar Itens do Cenário
    $stmtItens = $pdo->prepare("SELECT * FROM cot_cenarios_itens WHERE num_cenario = :num");
    $stmtItens->execute([':num' => $numCenario]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    if (empty($itens)) {
        throw new Exception("Cenário não possui itens.");
    }

    // 3. Agrupar Itens (Produto + Landed + Data Necessidade)
    // A chave de agrupamento será Produto + Landed + Deadline
    // Isso garante que entregas em datas diferentes fiquem em linhas separadas na OC
    $itensAgrupados = [];
    $clientesEnvolvidos = [];

    foreach ($itens as $item) {
        // Coletar clientes para OBS
        if (!empty($item['cliente'])) {
            $clientesEnvolvidos[] = $item['cliente'];
        }

        // Chave única para agrupamento
        // Usando o nome do produto, landed formatado e data necessidade
        $chave = $item['produto'] . '_' . (string) $item['landed_usd_kg'] . '_' . $item['data_necessidade'];

        if (!isset($itensAgrupados[$chave])) {
            $itensAgrupados[$chave] = [
                'codigo_produto' => $item['codigo_produto'],
                'produto' => $item['produto'],
                'unidade' => $item['unidade'],
                'landed_usd' => $item['landed_usd_kg'],
                'preco_venda_usd' => $item['preco_unit_venda_usd_kg'],
                'data_necessidade' => $item['data_necessidade'],
                'qtd_total' => 0
            ];
        }

        $itensAgrupados[$chave]['qtd_total'] += $item['qtd'];
    }

    // Limpar duplicatas de clientes
    $clientesEnvolvidos = array_unique($clientesEnvolvidos);
    $obs = "OC gerada a partir do Cenário: " . $numCenario . "\n";

    // Adicionar observações do cenário se existirem
    if (!empty($cenario['observacoes'])) {
        $obs .= "Obs Cenário: " . $cenario['observacoes'] . "\n";
    }

    $obs .= "Clientes envolvidos: " . implode(', ', $clientesEnvolvidos);

    // 4. Criar Ordem de Compra (Cabeçalho)
    $sqlOc = "INSERT INTO cot_pedidos_compra (id_fornecedor, fornecedor, criado_por, num_cenario_origem, obs, status, modal) 
              VALUES (:id_fornecedor, :fornecedor, :criado_por, :num_cenario, :obs, 'ABERTO', :modal)";
    $stmtOc = $pdo->prepare($sqlOc);
    $stmtOc->execute([
        ':id_fornecedor' => $cenario['id_fornecedor'],
        ':fornecedor' => $cenario['fornecedor'],
        ':criado_por' => $_SESSION['usuario_nome'] ?? $cenario['criado_por'], // Tenta pegar da sessao atual ou usa do cenario
        ':num_cenario' => $numCenario,
        ':obs' => $obs,
        ':modal' => $cenario['modal']
    ]);
    $idPedido = $pdo->lastInsertId();

    // 5. Inserir Itens da OC
    $sqlItemOc = "INSERT INTO cot_pedidos_compra_itens (id_pedido, codigo_produto, produto, qtd, unidade, landed_usd, preco_venda_usd, data_necessidade) 
                  VALUES (:id_pedido, :codigo, :produto, :qtd, :unidade, :landed, :preco_venda, :data_necessidade)";
    $stmtItemOc = $pdo->prepare($sqlItemOc);

    foreach ($itensAgrupados as $itemGroup) {
        $stmtItemOc->execute([
            ':id_pedido' => $idPedido,
            ':codigo' => $itemGroup['codigo_produto'],
            ':produto' => $itemGroup['produto'],
            ':qtd' => $itemGroup['qtd_total'],
            ':unidade' => $itemGroup['unidade'],
            ':landed' => $itemGroup['landed_usd'],
            ':preco_venda' => $itemGroup['preco_venda_usd'],
            ':data_necessidade' => !empty($itemGroup['data_necessidade']) ? $itemGroup['data_necessidade'] : null
        ]);
    }

    // 6. Atualizar Cenário (Marcar OC Gerada)
    $stmtUpdate = $pdo->prepare("UPDATE cot_cenarios_importacao SET oc_gerada = 1 WHERE num_cenario = :num");
    $stmtUpdate->execute([':num' => $numCenario]);

    $pdo->commit();

    echo json_encode(['sucesso' => true, 'id_pedido' => $idPedido, 'mensagem' => 'Ordem de Compra gerada com sucesso!']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['erro' => 'Erro ao gerar OC: ' . $e->getMessage()]);
}
?>