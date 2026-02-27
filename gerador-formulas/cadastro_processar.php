<?php
session_start();
// Proteção: Apenas administradores podem cadastrar
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: index.php');
    exit();
}
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cadastro.php');
    exit();
}

$nome = $_POST['nome'] ?? '';
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';
$is_admin = intval($_POST['is_admin'] ?? 0);

if (empty($nome) || empty($email) || empty($senha)) {
    header('Location: cadastro.php?erro=Todos os campos são obrigatórios.');
    exit();
}

// Criptografa a senha
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, is_admin) VALUES (?, ?, ?, ?)");
$stmt->bind_param("sssi", $nome, $email, $senha_hash, $is_admin);

if ($stmt->execute()) {
    header('Location: cadastro.php?sucesso=1');
} else {
    // Verifica se o erro é de e-mail duplicado
    if ($conn->errno == 1062) {
        header('Location: cadastro.php?erro=Este e-mail já está cadastrado.');
    } else {
        header('Location: cadastro.php?erro=Ocorreu um erro ao cadastrar.');
    }
}
exit();
?>