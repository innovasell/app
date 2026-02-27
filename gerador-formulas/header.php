<?php
// Se não houver sessão, redireciona para o login.
// session_start() deve estar no topo de cada página que usa o header.
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Sistema de Formulações</title>
    <style>
  /* Textareas auto-ajustáveis com visual de input Bootstrap */
  .form-control[data-autogrow]{
    overflow: hidden;       /* sem barra */
    resize: none;           /* usuário não “puxa” manualmente */
    min-height: calc(1.5em + .75rem + 2px); /* altura de um input padrão */
  }
</style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand" href="pesquisar_formulas.php"><i class="bi bi-droplet-half"></i> Formularium</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="pesquisar_formulas.php"><i class="bi bi-search"></i> Pesquisar</a></li>
        <li class="nav-item"><a class="nav-link" href="criar_formula.php"><i class="bi bi-plus-circle"></i> Nova Fórmula</a></li>
        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
            <li class="nav-item"><a class="nav-link" href="cadastro.php"><i class="bi bi-person-plus"></i> Cadastrar Utilizador</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['user_nome']); ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<main>