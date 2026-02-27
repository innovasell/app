<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['representante_email'])) {
    echo json_encode(['erro' => 'Não autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erro' => 'Método não permitido']);
    exit();
}

$num = isset($_GET['num']) ? trim($_GET['num']) : '';

if (empty($num)) {
    echo json_encode(['erro' => 'Número do cenário não informado']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Verificar se o cenário existe
    $sqlCheck = "SELECT COUNT(*) FROM cot_cenarios_importacao WHERE num_cenario = :num";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([':num' => $num]);

    if ($stmtCheck->fetchColumn() == 0) {
        echo json_encode(['erro' => 'Cenário não encontrado']);
        exit();
    }

    // Excluir cenário (CASCADE irá excluir os itens automaticamente)
    $sql = "DELETE FROM cot_cenarios_importacao WHERE num_cenario = :num";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':num' => $num]);

    $pdo->commit();

    echo json_encode(['sucesso' => true]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['erro' => 'Erro ao excluir cenário: ' . $e->getMessage()]);
}
?>