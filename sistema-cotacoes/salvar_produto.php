<?php
session_start();
require_once 'conexao.php';

// Segurança: Apenas administradores podem salvar produtos
//if (!isset($_SESSION['representante_email']) || !isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
//    header("Location: incluir_produto.php?erro=" . urlencode("Acesso não autorizado."));
//    exit();
//}

// Validação básica dos dados recebidos
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['codigo']) || empty($_POST['produto'])) {
    header("Location: incluir_produto.php?erro=" . urlencode("Dados do formulário inválidos."));
    exit();
}

// Coleta e sanitização dos dados
$codigo = trim($_POST['codigo']);
$produto = trim($_POST['produto']);
$unidade = trim($_POST['unidade']);
$ncm = trim($_POST['ncm']);
$ipi = filter_input(INPUT_POST, 'ipi', FILTER_VALIDATE_FLOAT);
$origem = filter_input(INPUT_POST, 'origem', FILTER_VALIDATE_INT);

// Verificação para garantir que os valores numéricos são válidos
if ($ipi === false || $origem === false) {
    header("Location: incluir_produto.php?erro=" . urlencode("Valores de IPI ou Origem inválidos."));
    exit();
}

try {
    // Prepara a query para evitar SQL Injection
    $sql = "INSERT INTO cot_estoque (codigo, produto, unidade, origem, ncm, ipi) 
            VALUES (:codigo, :produto, :unidade, :origem, :ncm, :ipi)";
    
    $stmt = $pdo->prepare($sql);

    // Executa a inserção
    $stmt->execute([
        ':codigo'  => $codigo,
        ':produto' => $produto,
        ':unidade' => $unidade,
        ':origem'  => $origem,
        ':ncm'     => $ncm,
        ':ipi'     => $ipi
    ]);

    // Redireciona de volta para a página de inclusão com mensagem de sucesso
    header("Location: incluir_produto.php?sucesso=1&produto_nome=" . urlencode($produto));
    exit();

} catch (PDOException $e) {
    // Em caso de erro, redireciona com a mensagem de erro
    // O código 1062 é para "entrada duplicada" (ex: código de produto já existe)
    if ($e->errorInfo[1] == 1062) {
        $erro_msg = "Já existe um produto com este código.";
    } else {
        $erro_msg = "Erro de banco de dados: " . $e->getMessage();
    }
    header("Location: incluir_produto.php?erro=" . urlencode($erro_msg));
    exit();
}
?>