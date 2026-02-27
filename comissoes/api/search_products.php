<?php
require_once '../db.php';

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';

if (strlen($term) < 3) {
    echo json_encode([]);
    exit;
}

try {
    // Search by Code or Name
    // Limit to 20 results
    $stmt = $pdo->prepare("
        SELECT id, codigo, produto, embalagem, preco_net_usd 
        FROM cot_price_list 
        WHERE codigo LIKE :term OR produto LIKE :term 
        ORDER BY produto ASC 
        LIMIT 20
    ");

    $searchTerm = "%$term%";
    $stmt->execute([':term' => $searchTerm]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
