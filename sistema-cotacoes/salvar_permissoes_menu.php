<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json');

// Check admin permission
if (!isset($_SESSION['grupo']) || $_SESSION['grupo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$input = file_get_contents('php://input');
$permissoes = json_decode($input, true);

if (!$permissoes) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Limpar tabela (ou melhor, usar REPLACE/INSERT ON DUPLICATE)
    // Vamos iterar e atualizar um por um
    $stmt = $pdo->prepare("INSERT INTO cot_menu_permissoes (menu_key, grupos_permitidos) VALUES (:key, :groups) 
                           ON DUPLICATE KEY UPDATE grupos_permitidos = :groups");

    foreach ($permissoes as $key => $groupsArray) {
        $groupsArray = array_unique($groupsArray); // Remover duplicatas se houver
        $stmt->execute([
            ':key' => $key,
            ':groups' => json_encode(array_values($groupsArray))
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro DB: ' . $e->getMessage()]);
}
?>