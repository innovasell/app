<?php
$token_valido = "INNOVASELL_CADASTRO_INICIAL";

if (!isset($_GET['token']) || $_GET['token'] !== $token_valido) {
  die("Link expirado ou inválido.");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Cadastro de Representante</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <h3 class="mb-4">Cadastro de Representante</h3>
    <form action="salvar_usuario.php" method="POST">
      <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">
      <div class="mb-3">
        <label>Nome</label>
        <input type="text" name="nome" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Sobrenome</label>
        <input type="text" name="sobrenome" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>E-mail</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Senha</label>
        <input type="password" name="senha" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary">Cadastrar</button>
    </form>
  </div>
</body>
</html>
