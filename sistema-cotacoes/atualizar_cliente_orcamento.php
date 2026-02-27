<?php
require_once 'conexao.php';
header('Content-Type: application/json');

$num_orcamento = $_POST['num_orcamento'] ?? '';
$razao_social = $_POST['razao_social'] ?? '';
$uf = $_POST['uf'] ?? '';

if (!$num_orcamento || !$razao_social) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

try {
    // Update all items belonging to this budget with new Client and UF
    $sql = "UPDATE cot_cotacoes_importadas SET `RAZÃO SOCIAL` = ?, `UF` = ? WHERE `NUM_ORCAMENTO` = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$razao_social, $uf, $num_orcamento]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>