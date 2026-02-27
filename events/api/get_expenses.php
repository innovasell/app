<?php
/**
 * API para buscar despesas com filtros
 * 
 * Retorna lista filtrada de despesas para o dashboard
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$response = ['success' => false, 'message' => '', 'data' => []];

try {
    // Parâmetros de filtro (via GET)
    $categoria = $_GET['categoria'] ?? '';
    $dataInicio = $_GET['data_inicio'] ?? '';
    $dataFim = $_GET['data_fim'] ?? '';
    $cliente = $_GET['cliente'] ?? '';
    $passageiro = $_GET['passageiro'] ?? '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // Monta a query base
    $sql = "SELECT * FROM viagem_express_expenses WHERE 1=1";
    $params = [];
    $types = '';

    // Aplica filtros
    if (!empty($categoria) && $categoria !== 'Todas') {
        $sql .= " AND categoria_despesa = ?";
        $params[] = $categoria;
        $types .= 's';
    }

    if (!empty($dataInicio)) {
        $sql .= " AND dt_emissao >= ?";
        $params[] = $dataInicio;
        $types .= 's';
    }

    if (!empty($dataFim)) {
        $sql .= " AND dt_emissao <= ?";
        $params[] = $dataFim;
        $types .= 's';
    }

    if (!empty($cliente)) {
        $sql .= " AND cliente LIKE ?";
        $params[] = "%$cliente%";
        $types .= 's';
    }

    if (!empty($passageiro)) {
        $sql .= " AND passageiro LIKE ?";
        $params[] = "%$passageiro%";
        $types .= 's';
    }

    // Ordena por data de emissão (mais recentes primeiro)
    $sql .= " ORDER BY dt_emissao DESC, id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    // Prepara e executa
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $expenses = [];
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }

    $stmt->close();

    $response['success'] = true;
    $response['data'] = $expenses;
    $response['message'] = count($expenses) . ' registros encontrados.';

} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
?>