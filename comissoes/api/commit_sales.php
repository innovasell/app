<?php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$batchId = $_POST['batch_id'] ?? null;
if (!$batchId) {
    echo json_encode(['success' => false, 'error' => 'Batch ID required']);
    exit;
}

try {
    // 1. Check if ANY item in THIS batch is NOT validated
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM com_imported_items WHERE batch_id = ? AND status != 'validated'");
    $stmtCount->execute([$batchId]);
    $pendingCount = $stmtCount->fetchColumn();

    if ($pendingCount > 0) {
        echo json_encode(['success' => false, 'error' => "Ainda existem $pendingCount itens pendentes de validação nesta importação."]);
        exit;
    }

    // 2. Get Date Range for THIS batch
    $stmtDate = $pdo->prepare("SELECT MIN(nfe_date) as min_date, MAX(nfe_date) as max_date FROM com_imported_items WHERE batch_id = ?");
    $stmtDate->execute([$batchId]);
    $dates = $stmtDate->fetch(PDO::FETCH_ASSOC);

    // 3. Move items to com_sales_base
    $sqlMove = "INSERT INTO com_sales_base 
                (nfe_number, nfe_date, cfop, product_code_9, product_name, packaging, quantity, unit_price, total_value, cost_price)
                SELECT 
                nfe_number, nfe_date, cfop, product_code_9, product_name, packaging_validated, quantity, unit_price, total_value, cost_price
                FROM com_imported_items WHERE batch_id = ? AND status = 'validated'";

    $pdo->prepare($sqlMove)->execute([$batchId]);

    // 4. Delete processed batch from com_imported_items (Done!)
    // We only delete THIS batch, leaving others intact
    $pdo->prepare("DELETE FROM com_imported_items WHERE batch_id = ?")->execute([$batchId]);

    echo json_encode([
        'success' => true,
        'message' => 'Vendas salvas com sucesso!',
        'dates' => $dates
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
