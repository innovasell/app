<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';

try {
    // Verifica se a coluna 'nome' existe (compatibilidade antes do setup ser rodado)
    $hasNome = false;
    try {
        $pdo->query("SELECT nome FROM com_commission_batches LIMIT 1");
        $hasNome = true;
    } catch (Exception $ex) {
        $hasNome = false;
    }

    $nomeCol = $hasNome ? "b.nome," : "NULL as nome,";

    $stmt = $pdo->query("
        SELECT 
            b.id,
            $nomeCol
            b.periodo,
            b.created_at,
            COUNT(i.id) as item_count,
            COALESCE(SUM(i.venda_net), 0) as total_venda_net,
            COALESCE(SUM(i.valor_comissao), 0) as total_comissoes,
            COALESCE(SUM(i.lista_nao_encontrada), 0) as sem_lista
        FROM com_commission_batches b
        LEFT JOIN com_commission_items i ON b.id = i.batch_id
        GROUP BY b.id
        ORDER BY b.id DESC
        LIMIT 30
    ");
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($batches as &$b) {
        $b['formatted_date'] = date('d/m/Y H:i', strtotime($b['created_at']));
        $b['batch_id'] = $b['id'];
        $b['pending_count'] = (int)$b['sem_lista'];
        $b['missing_sellers'] = 0;
    }

    ob_end_clean();
    echo json_encode(['success' => true, 'data' => $batches]);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'data' => []]);
}
