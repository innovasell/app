<?php
// header.php - Sistema de Gestão de Despesas de Viagens
require_once 'auth.php';
require_login(); // Protege todas as páginas

if (!defined('PAGE_TITLE'))
    define('PAGE_TITLE', 'Gestão de Despesas');
$version = time(); // Cache busting
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= PAGE_TITLE ?> - InnovaEvents</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css?v=<?= $version ?>">
</head>

<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-md navbar-light bg-white border-bottom shadow-sm sticky-top">
        <div class="container">

            <!-- Logo InnovaEvents -->
            <a class="navbar-brand fw-bold text-success d-flex align-items-center" href="index.php">
                <i class="bi bi-calendar-event fs-4 me-2"></i>
                <span>InnovaEvents</span>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">

                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active fw-bold' : '' ?>"
                            href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'importar.php' ? 'active fw-bold' : '' ?>"
                            href="importar.php">
                            <i class="bi bi-cloud-upload"></i> Importar Dados
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'relatorios.php' ? 'active fw-bold' : '' ?>"
                            href="relatorios.php">
                            <i class="bi bi-file-earmark-text"></i> Relatórios
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active fw-bold' : '' ?>"
                                href="usuarios.php">
                                <i class="bi bi-people"></i> Gerenciar Usuários
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Dropdown do Usuário -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2"
                        data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <span class="d-none d-md-inline"><?= htmlspecialchars(user()['name']) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li>
                            <h6 class="dropdown-header">
                                <?= htmlspecialchars(user()['name']) ?>
                                <?php if (is_admin()): ?>
                                    <span class="badge bg-success small ms-1">Admin</span>
                                <?php endif; ?>
                            </h6>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item text-muted small">
                                <i class="bi bi-envelope"></i> <?= htmlspecialchars(user()['email']) ?>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item" href="/">
                                <i class="bi bi-house"></i> Voltar ao Início
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Sair
                            </a>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </nav>