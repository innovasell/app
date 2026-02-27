<?php
// 24 hours = 86400 seconds
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

session_start();
require_once 'conexao.php';

$email = $_POST['email'] ?? '';

// Verifica se o e-mail existe na tabela cot_representante
$sql = "SELECT * FROM cot_representante WHERE email = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if ($usuario) {
    $_SESSION['representante_email'] = $usuario['email'];
    $_SESSION['representante_nome'] = $usuario['nome'];
    $_SESSION['representante_sobrenome'] = $usuario['sobrenome'];
    $_SESSION['admin'] = $usuario['admin']; // 0 ou 1
    header("Location: bi.php");
    exit();
} else {
    echo "<script>alert('Email não encontrado.'); window.location.href='index.html';</script>";
}
?>