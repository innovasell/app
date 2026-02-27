<?php
session_start();
require_once 'conexao.php';

if (!isset($_GET['num_orcamento']) || empty($_GET['num_orcamento'])) {
    die('Número do orçamento não informado.');
}

$num = $_GET['num_orcamento'];

// Apaga os registros da tabela cot_cotacoes_importadas com base no número do orçamento
try {
    $stmt = $pdo->prepare("DELETE FROM cot_cotacoes_importadas WHERE NUM_ORCAMENTO = ?");
    $stmt->execute([$num]);

    // Redireciona de volta para a lista
    header('Location: consultar_orcamentos.php');
    exit;
} catch (PDOException $e) {
    echo "Erro ao excluir orçamento: " . $e->getMessage();
}
?>
