<?php
session_start();
require_once 'conexao.php';

// Proteger apenas para admin
if (!isset($_SESSION['representante_email']) || $_SESSION['admin'] != 1) {
    header('Location: index.html');
    exit();
}

// Tratamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $sobrenome = $_POST['sobrenome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $admin = isset($_POST['admin']) ? 1 : 0;

    // Hash da senha
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    // Inserir no banco
    $sql = "INSERT INTO cot_representante (nome, sobrenome, email, senha, admin) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([$nome, $sobrenome, $email, $senhaHash, $admin]);
        $mensagem = "Usuário cadastrado com sucesso!";
    } catch (PDOException $e) {
        $mensagem = "Erro ao cadastrar: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Cadastrar Usuário</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
  <h2 class="mb-4">Cadastrar Novo Usuário</h2>

  <?php if (isset($mensagem)): ?>
    <div class="alert alert-info"> <?= $mensagem ?> </div>
  <?php endif; ?>

  <form method="POST">
    <div class="mb-3">
      <label for="nome" class="form-label">Nome</label>
      <input type="text" class="form-control" id="nome" name="nome" required>
    </div>
    <div class="mb-3">
      <label for="sobrenome" class="form-label">Sobrenome</label>
      <input type="text" class="form-control" id="sobrenome" name="sobrenome" required>
    </div>
    <div class="mb-3">
      <label for="email" class="form-label">Email</label>
      <input type="email" class="form-control" id="email" name="email" required>
    </div>
    <div class="mb-3">
      <label for="senha" class="form-label">Senha</label>
      <input type="password" class="form-control" id="senha" name="senha" required>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="admin" id="admin">
      <label class="form-check-label" for="admin">Administrador</label>
    </div>
    <button type="submit" class="btn btn-primary">Cadastrar</button>
    <a href="pesquisar.php" class="btn btn-secondary">Voltar</a>
  </form>
</body>
</html>