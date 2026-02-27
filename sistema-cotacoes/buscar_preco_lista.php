<?php
require_once 'conexao.php';

header('Content-Type: application/json');

$codigo = $_GET['codigo'] ?? '';

if (empty($codigo)) {
    echo json_encode([]);
    exit;
}

// Extract first 9 digits. 
// Assuming code is alphanumeric but we only care about first 9 chars.
// If it contains dashes or dots, should we sanitize? User said "considere apenas os 9 digitos da esquerda".
// Let's take the first 9 chars of the string.
$codigoBase = substr($codigo, 0, 9);

try {
    $stmt = $pdo->prepare("SELECT fabricante, embalagem, preco_net_usd, classificacao, lead_time FROM cot_price_list WHERE codigo LIKE :cod ORDER BY embalagem ASC");
    // Using LIKE with wildcard just in case, or exact match on substring? 
    // User said "considere apenas os 9 digitos da esquerda para fazer a comparação".
    // Does the DB column `codigo` have 9 digits or full length? import script just saved it as is.
    // The CSV has "093004001" (9 digits). So if the user's system has 12 digits, we match the first 9 of input against the DB code.
    // IF the DB code is ALSO 9 digits, we act.

    // Logic: Input "123456789000". Base "123456789". 
    // Query: SELECT * FROM table WHERE codigo = '123456789'

    $stmt->execute([':cod' => $codigoBase]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (PDOException $e) {
    echo json_encode(['erro' => $e->getMessage()]);
}
?>