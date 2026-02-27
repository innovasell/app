<?php
require_once 'conexao.php';

$token_valido = "INNOVASELL_CADASTRO_INICIAL";

// Segurança
if (!isset($_POST['token']) || $_POST['token'] !== $token_valido) {
  die("Token inválido.");
}

$nome = trim($_POST['nome'] ?? '');
$sobrenome = trim($_POST['sobrenome'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$senha = password_hash($_POST['senha'], PASSWORD_DEFAULT); // senha segura

try {
  $stmt = $pdo->prepare("INSERT INTO cot_representante (nome, sobrenome, email, senha, admin) VALUES (?, ?, ?, ?, 0)");
  $stmt->execute([$nome, $sobrenome, $email, $senha]);

  echo "<h3>Cadastro realizado com sucesso!</h3>";
} catch (PDOException $e) {
  echo "<h3>Erro ao cadastrar: " . $e->getMessage() . "</h3>";
}
