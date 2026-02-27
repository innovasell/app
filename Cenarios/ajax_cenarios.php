<?php
// ajax_cenarios.php
require_once 'conexao.php';
require_once 'AnaliseConsumo.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    // 1. Endpoint Auxiliar Select2: Clientes
    if ($action === 'buscar_clientes') {
        $termo = $_GET['q'] ?? '';
        $sql = "SELECT id_maino as id, razao_social as text FROM clientes WHERE razao_social LIKE :t LIMIT 30";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['t' => "%$termo%"]);
        echo json_encode(['results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 2. Endpoint Auxiliar Select2: Produtos
    if ($action === 'buscar_produtos') {
        $termo = $_GET['q'] ?? '';
        $sql = "SELECT id_maino as id, CONCAT(codigo_interno, ' - ', descricao) as text FROM produtos WHERE descricao LIKE :t OR codigo_interno LIKE :t LIMIT 30";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['t' => "%$termo%"]);
        echo json_encode(['results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 3. Endpoint Cálculo dos Cards
    if ($action === 'calcular_kpis') {
        $cli = $_GET['cliente'] ?? 0;
        $prod = $_GET['produto'] ?? 0;

        if (!$cli || !$prod) {
            echo json_encode(['status' => 'error', 'message' => 'Selecione um cliente e um produto.']);
            exit;
        }
        
        $analise = new AnaliseConsumo($pdo);
        $kpis = $analise->calcularMetricas($cli, $prod);
        
        echo json_encode(['status' => 'success', 'data' => $kpis]);
        exit;
    }

    // 4. Endpoint Histórico Movimentações em Tabela
    if ($action === 'historico') {
        $cli = $_GET['cliente'] ?? 0;
        $prod = $_GET['produto'] ?? 0;

        if (!$cli || !$prod) {
            echo json_encode(['status' => 'success', 'data' => []]);
            exit;
        }

        $sql = "SELECT DATE_FORMAT(data, '%d/%m/%Y') as data_br, quantidade, valor_unitario, tipo 
                FROM movimentacoes 
                WHERE id_cliente = :cli AND id_produto = :prod 
                ORDER BY data DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['cli' => $cli, 'prod' => $prod]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $historico]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action inválida']);
?>
