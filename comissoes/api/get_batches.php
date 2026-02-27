<?php
require_once '../db.php';

header('Content-Type: application/json');

try {
    // Group by batch_id to get summary
    $sql = "SELECT 
                batch_id, 
                MAX(created_at) as imported_at, 
                COUNT(*) as item_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN seller_name IS NULL OR seller_name = '' THEN 1 ELSE 0 END) as missing_sellers
            FROM com_imported_items 
            GROUP BY batch_id 
            ORDER BY batch_id DESC";

    // Note: created_at might not exist if we didn't add it, using MAX(id) or if we rely on batch_id timestamp.
    // Let's check schema. We don't have created_at in the CREATE statement previously shown?
    // Batch ID is YmdHis, so we can format it.

    $stmt = $pdo->query($sql);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for display
    foreach ($batches as &$batch) {
        // defined batch_id as YmdHis e.g. 20250125180000
        $raw = $batch['batch_id'];
        if (strlen($raw) == 14) {
            $dt = DateTime::createFromFormat('YmdHis', $raw);
            $batch['formatted_date'] = $dt ? $dt->format('d/m/Y H:i:s') : $raw;
        } else {
            $batch['formatted_date'] = $raw;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $batches
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
