<?php
session_start();
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: gerenciar_produtos.php");
    exit();
}

$codigo = trim($_POST['codigo']);
$produto = trim($_POST['produto']);
$unidade = trim($_POST['unidade']);
$ncm = trim($_POST['ncm']);
$ipi = filter_input(INPUT_POST, 'ipi', FILTER_VALIDATE_FLOAT);
$origem = filter_input(INPUT_POST, 'origem', FILTER_VALIDATE_INT);

if (empty($codigo) || empty($produto) || $ipi === false || $origem === false) {
    header("Location: editar_produto.php?codigo=" . urlencode($codigo) . "&erro=" . urlencode("Dados inválidos. Verifique os campos."));
    exit();
}

try {
    $sql = "UPDATE cot_estoque SET 
            produto = :produto, 
            unidade = :unidade, 
            ncm = :ncm, 
            ipi = :ipi, 
            origem = :origem 
            WHERE codigo = :codigo";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':produto' => $produto,
        ':unidade' => $unidade,
        ':ncm' => $ncm,
        ':ipi' => $ipi,
        ':origem' => $origem,
        ':codigo' => $codigo
    ]);

    header("Location: gerenciar_produtos.php?sucesso=1");
    exit();

} catch (PDOException $e) {
    header("Location: editar_produto.php?codigo=" . urlencode($codigo) . "&erro=" . urlencode("Erro ao atualizar: " . $e->getMessage()));
    exit();
}
?>