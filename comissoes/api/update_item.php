<?php
require_once '../db.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id']) || !isset($input['field']) || !isset($input['value'])) {
        throw new Exception("Dados inválidos.");
    }

    $id = $input['id'];
    $field = $input['field'];
    $value = $input['value'];

    // Allowed fields to edit
    $allowedFields = ['packaging_validated', 'cost_price', 'average_term'];

    if (!in_array($field, $allowedFields)) {
        throw new Exception("Campo não permitido para edição.");
    }

    // Special logic: If packaging is changed, we might want to re-lookup the price? 
    // For now, simplicity: just update the value. 
    // If cost_price is updated manually, status implies validated?

    $sql = "UPDATE com_imported_items SET $field = :value WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':value' => $value, ':id' => $id]);

    // Check if item is now valid (has cost price)
    if ($field === 'cost_price' && $value > 0) {
        $pdo->prepare("UPDATE com_imported_items SET status = 'validated' WHERE id = :id")->execute([':id' => $id]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
