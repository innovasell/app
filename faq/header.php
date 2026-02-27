<?php 
// header.php
if (!defined('APP_VERSION')) exit; 
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Innova Wiki</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="style.css?v=<?= time() ?>" rel="stylesheet"> 
</head>
<body class="bg-light">

<div class="bg-white py-3 border-bottom text-center">
    <a href="?" class="text-decoration-none" title="Página Inicial">
        <img src="LOGO.png" alt="Innova Wiki" height="50" class="d-inline-block">
    </a>
</div>

<nav class="navbar navbar-expand-md navbar-light bg-white border-bottom sticky-top shadow-sm">
  <div class="container-fluid px-lg-5">
    
    <a class="navbar-brand fw-bold text-dark me-4" href="?">
      Innova Wiki
    </a>

    <button class="navbar-toggler border-0 me-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#filtersCanvas">
      <i class="bi bi-sliders"></i> <span class="ms-1 small fw-bold">Filtros</span>
    </button>

    <div class="collapse navbar-collapse" id="navbarContent">
      
      <form class="d-flex mx-auto my-2 my-md-0 gap-2 align-items-center" method="get" role="search" style="max-width: 700px; width: 100%;">
        <input type="hidden" name="action" value="home">
        
        <div class="input-group">
            <select class="form-select bg-light border-end-0" name="sf" style="max-width: 180px;">
                <option value="">Fornecedor (Todos)</option>
                <?php if(isset($suppliersMap)): foreach ($suppliersMap as $key => $info): ?>
                <option value="<?=h($key)?>" <?= (isset($sf) && $sf===$key?'selected':'') ?>><?=h($info['label'])?></option>
                <?php endforeach; endif; ?>
            </select>
            
            <input class="form-control border-start-0" type="search" name="q" placeholder="O que você procura?" value="<?= h($q ?? '') ?>">
            
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-search"></i>
            </button>
        </div>

        <?php if ((isset($q) && $q!=='') || (isset($pf) && $pf!=='') || (isset($sf) && $sf!=='')): ?>
            <a class="btn btn-outline-secondary" href="?" title="Limpar Filtros"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </form>

      <div class="d-flex align-items-center gap-2 ms-md-3">
        <?php if (is_logged()): ?>
            <div class="dropdown">
            <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle text-secondary"></i> 
                <span class="d-none d-lg-inline small fw-bold"><?=h(user()['name'])?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                <?php if (is_admin()): ?>
                <li><h6 class="dropdown-header">Administração</h6></li>
                <li><a class="dropdown-item" href="users.php"><i class="bi bi-people me-2"></i>Gerenciar Usuários</a></li>
                <li><a class="dropdown-item" href="import_faqs.php"><i class="bi bi-upload me-2"></i>Importar FAQs</a></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#newFaqModal"><i class="bi bi-plus-circle me-2"></i>Nova FAQ</a></li>
                <li><hr class="dropdown-divider"></li>
                <?php endif;?>
                <li><a class="dropdown-item text-danger" href="?action=logout"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
            </ul>
            </div>
        <?php else: ?>
            <button class="btn btn-dark btn-sm fw-semibold px-3" data-bs-toggle="modal" data-bs-target="#loginModal">
                Entrar
            </button>
        <?php endif;?>
      </div>

    </div>
  </div>
</nav>