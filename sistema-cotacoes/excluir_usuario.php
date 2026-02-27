<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['grupo']) || $_SESSION['grupo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$id = $_GET['id'] ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

try {
    // Soft Delete: Apenas inativa o usuário para manter integridade ref.
    $stmt = $pdo->prepare("UPDATE cot_representante SET ativo = 0 WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
}
?>