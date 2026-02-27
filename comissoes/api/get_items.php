<?php
require_once '../db.php';
header('Content-Type: application/json');

try {
    // Check if we need list of batches or items
    $action = $_GET['action'] ?? 'list_items';

    if ($action === 'list_batches') {
        // Return list of available batches
        // Group by batch_id, show date (min nfe_date? or batch_id timestamp), total items, status counts
        $sql = "SELECT 
                    batch_id,
                    MIN(created_at) as imported_at,
                    COUNT(*) as total_items,
                    SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated_count
                FROM com_imported_items 
                GROUP BY batch_id 
                ORDER BY batch_id DESC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } else {
        // Return items for a specific batch (or all if not specified, though grouping requested)
        $batchId = $_GET['batch_id'] ?? null;

        $sql = "SELECT * FROM com_imported_items";
        if ($batchId) {
            $sql .= " WHERE batch_id = :batch";
        }
        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        if ($batchId) {
            $stmt->execute([':batch' => $batchId]);
        } else {
            $stmt->execute();
        }

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
