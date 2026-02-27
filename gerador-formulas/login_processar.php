<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

if (empty($email) || empty($senha)) {
    header('Location: index.php?erro=Preencha todos os campos.');
    exit();
}

$stmt = $conn->prepare("SELECT id, nome, senha, is_admin FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $usuario = $result->fetch_assoc();
    
    // Verifica a senha criptografada
    if (password_verify($senha, $usuario['senha'])) {
        // Login bem-sucedido
        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['user_nome'] = $usuario['nome'];
        $_SESSION['is_admin'] = $usuario['is_admin'];
        
        header('Location: pesquisar_formulas.php');
        exit();
    }
}

// Se chegou até aqui, o login falhou
header('Location: index.php?erro=E-mail ou senha inválidos.');
exit();
?>