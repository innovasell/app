<?php
/**
 * API para obter estatísticas do dashboard
 * 
 * Retorna totais por categoria, top clientes, etc.
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$response = ['success' => false, 'message' => '', 'data' => []];

try {
    $stats = [];

    // Total Geral
    $result = $conn->query("SELECT SUM(total) as total_geral, COUNT(*) as total_registros FROM viagem_express_expenses");
    $row = $result->fetch_assoc();
    $stats['total_geral'] = floatval($row['total_geral'] ?? 0);
    $stats['total_registros'] = intval($row['total_registros'] ?? 0);

    // Total por Categoria
    $result = $conn->query("
        SELECT categoria_despesa, SUM(total) as total, COUNT(*) as qtd 
        FROM viagem_express_expenses 
        GROUP BY categoria_despesa
        ORDER BY total DESC
    ");
    $stats['por_categoria'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['por_categoria'][] = [
            'categoria' => $row['categoria_despesa'],
            'total' => floatval($row['total']),
            'quantidade' => intval($row['qtd'])
        ];
    }

    // Top 10 Clientes
    $result = $conn->query("
        SELECT cliente, SUM(total) as total, COUNT(*) as qtd 
        FROM viagem_express_expenses 
        WHERE cliente IS NOT NULL AND cliente != ''
        GROUP BY cliente 
        ORDER BY total DESC 
        LIMIT 10
    ");
    $stats['top_clientes'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['top_clientes'][] = [
            'cliente' => $row['cliente'],
            'total' => floatval($row['total']),
            'quantidade' => intval($row['qtd'])
        ];
    }

    // Top 10 Passageiros
    $result = $conn->query("
        SELECT passageiro, SUM(total) as total, COUNT(*) as qtd 
        FROM viagem_express_expenses 
        WHERE passageiro IS NOT NULL AND passageiro != ''
        GROUP BY passageiro 
        ORDER BY total DESC 
        LIMIT 10
    ");
    $stats['top_passageiros'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['top_passageiros'][] = [
            'passageiro' => $row['passageiro'],
            'total' => floatval($row['total']),
            'quantidade' => intval($row['qtd'])
        ];
    }

    // Trend Mensal (últimos 12 meses)
    $result = $conn->query("
        SELECT 
            DATE_FORMAT(dt_emissao, '%Y-%m') as mes,
            SUM(total) as total,
            COUNT(*) as qtd
        FROM viagem_express_expenses 
        WHERE dt_emissao >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY mes
        ORDER BY mes ASC
    ");
    $stats['trend_mensal'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['trend_mensal'][] = [
            'mes' => $row['mes'],
            'total' => floatval($row['total']),
            'quantidade' => intval($row['qtd'])
        ];
    }

    $response['success'] = true;
    $response['data'] = $stats;
    $response['message'] = 'Estatísticas carregadas com sucesso.';

} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
?>