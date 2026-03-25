<?php
/**
 * header.php — Módulo Comissões
 * Padrão visual idêntico ao sistema-cotacoes/header.php
 * Definir $pagina_ativa antes de incluir. Ex: $pagina_ativa = 'validacao';
 */
if (!isset($pagina_ativa)) $pagina_ativa = '';

$paginaTitulo = [
    'dashboard'   => 'Dashboard',
    'upload'      => 'Upload NFs',
    'comissoes'   => 'Cálculo de Comissões',
    'validacao'   => 'Validação de Lotes',
    'lote'        => 'Detalhes do Lote',
    'config_cfop' => 'Configurar CFOPs',
    'validador'   => 'Validador de Comissão',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($paginaTitulo[$pagina_ativa] ?? 'Comissões') ?> — Innovasell</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Font Awesome (mesmo do sistema-cotacoes) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts: Montserrat (mesmo do sistema-cotacoes) -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ======================================================
           PADRÃO VISUAL — Sistema de Comissões / H Hansen
           Baseado no sistema-cotacoes/header.php
           ====================================================== */

        body {
            background-color: #f0f4f8;
            font-family: 'Montserrat', 'Segoe UI', sans-serif;
        }

        /* ── Navbar ─────────────────────────────────────────── */
        .navbar-custom {
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            font-family: 'Montserrat', sans-serif;
            padding-top: 12px;
            padding-bottom: 12px;
        }

        .navbar-brand img {
            height: 46px;
            width: auto;
        }

        .navbar-custom .nav-link {
            color: #40883c;
            font-weight: 600;
            font-size: 13px;
            margin: 0 6px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .navbar-custom .nav-link:hover,
        .navbar-custom .nav-link.active,
        .navbar-custom .nav-link:focus {
            color: #2c5e29;
            background-color: rgba(64, 136, 60, 0.1);
            border-radius: 5px;
        }

        /* ── Dropdown ───────────────────────────────────────── */
        .dropdown-menu {
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        .dropdown-item {
            color: #555;
            font-size: 13px;
            padding: 9px 18px;
            font-family: 'Montserrat', sans-serif;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #40883c;
        }

        .dropdown-item.active,
        .dropdown-item:active {
            background-color: #40883c;
            color: #fff;
        }

        /* ── Cards Padrão ───────────────────────────────────── */
        .card-stat {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        .card-stat .card-icon {
            font-size: 2rem;
            opacity: 0.85;
        }
        .card-stat .card-value {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .card-stat .card-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.8;
        }

        /* ── Badges ─────────────────────────────────────────── */
        .badge-aprovacao { background-color: #dc3545; color: #fff; }
        .badge-teto      { background-color: #e5a100; color: #fff; }
        .badge-sem-lista { background-color: #6c757d; color: #fff; }
        .badge-ok        { background-color: #198754; color: #fff; }

        /* ── Tabelas ─────────────────────────────────────────── */
        .table thead th {
            background-color: #0a1e42;
            color: #fff;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border: none;
        }
        .table tbody tr:hover {
            background-color: rgba(64, 136, 60, 0.05);
        }

        /* ── Page Header ─────────────────────────────────────── */
        .page-header {
            background: linear-gradient(135deg, #0a1e42 0%, #0047fa 100%);
            color: #fff;
            padding: 24px 32px;
            margin-bottom: 24px;
            border-radius: 0 0 16px 16px;
        }
        .page-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0;
        }
        .page-header .breadcrumb-item,
        .page-header .breadcrumb-item a {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.7);
        }
        .page-header .breadcrumb-item.active {
            color: #fff;
        }
        .page-header .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255,255,255,0.5);
        }

        /* ── Responsivo ─────────────────────────────────────── */
        @media (max-width: 1400px) {
            .navbar-custom { padding: 6px 0; }
            .navbar-custom .nav-link { font-size: 11px; margin: 0 2px; }
            .navbar-brand img { height: 36px; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light navbar-custom sticky-top">
    <div class="container-fluid px-4">
        <!-- Logo — usa o mesmo asset do sistema-cotacoes -->
        <a class="navbar-brand" href="index.php">
            <img src="../sistema-cotacoes/assets/LOGO.svg" alt="H Hansen"
                 onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML+='<span class=\'fw-bold text-success\' style=\'font-family:Montserrat;font-size:1.1rem\'>H Hansen</span>'">
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navComissoes">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navComissoes">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link <?= $pagina_ativa === 'dashboard' ? 'active' : '' ?>" href="index.php">
                        <i class="fas fa-chart-line me-1"></i> Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $pagina_ativa === 'upload' ? 'active' : '' ?>" href="upload.php">
                        <i class="fas fa-file-upload me-1"></i> Upload NFs
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $pagina_ativa === 'comissoes' ? 'active' : '' ?>" href="comissoes.php">
                        <i class="fas fa-calculator me-1"></i> Calcular
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $pagina_ativa === 'validacao' ? 'active' : '' ?>" href="validacao.php">
                        <i class="fas fa-tasks me-1"></i> Lotes / Validação
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $pagina_ativa === 'config_cfop' ? 'active' : '' ?>" href="config_cfop.php">
                        <i class="fas fa-sliders-h me-1"></i> CFOPs
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $pagina_ativa === 'validador' ? 'active' : '' ?>" href="validador_comissao.php">
                        <i class="fas fa-search-dollar me-1"></i> Validador
                    </a>
                </li>

            </ul>

            <!-- Lado direito: link de volta ao sistema principal -->
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link text-muted" href="../" title="Voltar ao início">
                        <i class="fas fa-home me-1"></i> Início
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
