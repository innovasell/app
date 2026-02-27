<?php
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $batchId = $_GET['batch_id'] ?? null;

    if (!$batchId) {
        // If no batch provided, try to find the latest batch
        $stmtLatest = $pdo->query("SELECT batch_id FROM com_imported_items ORDER BY id DESC LIMIT 1");
        $latest = $stmtLatest->fetch(PDO::FETCH_COLUMN);
        if ($latest) {
            $batchId = $latest;
        } else {
            throw new Exception("Nenhum lote encontrado.");
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM com_imported_items WHERE batch_id = :batch_id ORDER BY id ASC");
    $stmt->execute([':batch_id' => $batchId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'batch_id' => $batchId,
        'data' => $items
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
