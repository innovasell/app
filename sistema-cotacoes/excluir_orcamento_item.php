<?php
require_once 'conexao.php';

$id = $_GET['id'] ?? null;
$num = $_GET['num'] ?? null;

// Allow '0' as a valid ID (though distinct, check for null/empty string)
if (($id !== null && $id !== '') && $num) {
    try {
        $stmt = $pdo->prepare("DELETE FROM cot_cotacoes_importadas WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: atualizar_orcamento.php?num=" . urlencode($num) . "&msg=ItemExcluido");
        exit;
    } catch (PDOException $e) {
        die("Erro ao excluir: " . $e->getMessage());
    }
} else {
    die("Parâmetros inválidos.");
}
