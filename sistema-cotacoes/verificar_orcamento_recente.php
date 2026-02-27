<?php
require_once 'conexao.php';
header('Content-Type: application/json');

$cliente = $_GET['cliente'] ?? '';

if (!$cliente) {
    echo json_encode(['exists' => false]);
    exit;
}

// Verifica orçamentos nos últimos 7 dias
$dataLimit = date('Y-m-d', strtotime('-7 days'));

try {
    $stmt = $pdo->prepare("
        SELECT NUM_ORCAMENTO, DATA, COTADO_POR 
        FROM cot_cotacoes_importadas 
        WHERE `RAZÃO SOCIAL` = :cliente 
          AND `DATA` >= :dataLimit 
          AND NUM_ORCAMENTO IS NOT NULL 
          AND NUM_ORCAMENTO != ''
        ORDER BY `DATA` DESC 
        LIMIT 1
    ");
    $stmt->execute([':cliente' => $cliente, ':dataLimit' => $dataLimit]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'exists' => true,
            'num_orcamento' => $result['NUM_ORCAMENTO'],
            'data' => date('d/m/Y', strtotime($result['DATA'])),
            'cotado_por' => $result['COTADO_POR']
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
