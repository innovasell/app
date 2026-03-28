<?php
/**
 * header.php — InnovaEvents
 * $active_event  (array com id, nome, status) → exibe barra de contexto do evento
 * PAGE_CURRENT   (string constante definida antes do include) → destaca item nav ativo
 */
require_once 'auth.php';
require_login();

if (!defined('PAGE_TITLE'))   define('PAGE_TITLE', 'InnovaEvents');
if (!defined('PAGE_CURRENT')) define('PAGE_CURRENT', '');
$version = '2.0';

$statusLabels = ['planejamento' => 'Planejamento', 'em_execucao' => 'Em Execução', 'encerrado' => 'Encerrado'];
$statusBadge  = ['planejamento' => 'bg-warning text-dark', 'em_execucao' => 'bg-success', 'encerrado' => 'bg-secondary'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(PAGE_TITLE) ?> — InnovaEvents</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css?v=<?= $version ?>" rel="stylesheet">
</head>
<body>

<!-- ── Navbar principal ───────────────────────────────────────────────────── -->
<nav class="navbar navbar-expand-md sticky-top inno-navbar">
    <div class="container-fluid px-4">

        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-calendar-event fs-5"></i>
            <span class="fw-bold">InnovaEvents</span>
        </a>

        <button class="navbar-toggler border-0" type="button"
                data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= PAGE_CURRENT === 'eventos' ? 'active' : '' ?>" href="index.php">
                        <i class="bi bi-grid-3x3-gap-fill"></i> Eventos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= PAGE_CURRENT === 'importar' ? 'active' : '' ?>" href="importar.php">
                        <i class="bi bi-cloud-upload-fill"></i> Importar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= PAGE_CURRENT === 'relatorios' ? 'active' : '' ?>" href="relatorios.php">
                        <i class="bi bi-file-earmark-bar-graph-fill"></i> Relatórios
                    </a>
                </li>
                <?php if (is_admin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= PAGE_CURRENT === 'usuarios' ? 'active' : '' ?>" href="usuarios.php">
                        <i class="bi bi-people-fill"></i> Usuários
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Usuário -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle d-flex align-items-center gap-2"
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
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <span class="dropdown-item-text text-muted small">
                            <i class="bi bi-envelope"></i> <?= htmlspecialchars(user()['email']) ?>
                        </span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/"><i class="bi bi-house"></i> Voltar ao Início</a></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<?php if (!empty($active_event)): ?>
<!-- ── Barra de contexto do evento ────────────────────────────────────────── -->
<div class="event-context-bar">
    <div class="container-fluid px-4 d-flex align-items-center justify-content-between flex-wrap gap-2 py-2">

        <!-- Breadcrumb + status -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="index.php" class="text-white text-decoration-none opacity-75 small">
                <i class="bi bi-arrow-left"></i> Eventos
            </a>
            <span class="text-white opacity-40">/</span>
            <span class="text-white fw-semibold"><?= htmlspecialchars($active_event['nome']) ?></span>
            <?php
                $st = $active_event['status'] ?? 'planejamento';
                $badgeCls = $statusBadge[$st] ?? 'bg-secondary';
                $badgeLbl = $statusLabels[$st] ?? $st;
            ?>
            <span class="badge <?= $badgeCls ?> small"><?= $badgeLbl ?></span>
        </div>

        <!-- Sub-nav do evento -->
        <?php $eid = (int)$active_event['id']; ?>
        <nav class="d-flex align-items-center gap-1 flex-wrap">
            <a href="evento.php?id=<?= $eid ?>"
               class="event-nav-link <?= PAGE_CURRENT === 'evento_overview' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Visão Geral
            </a>
            <a href="orcamento.php?event_id=<?= $eid ?>"
               class="event-nav-link <?= PAGE_CURRENT === 'evento_orcamento' ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i> Orçamento
            </a>
            <a href="despesas.php?event_id=<?= $eid ?>"
               class="event-nav-link <?= PAGE_CURRENT === 'evento_despesas' ? 'active' : '' ?>">
                <i class="bi bi-receipt-cutoff"></i> Despesas
            </a>
            <a href="importar.php?event_id=<?= $eid ?>"
               class="event-nav-link <?= PAGE_CURRENT === 'importar' ? 'active' : '' ?>">
                <i class="bi bi-cloud-upload"></i> Importar
            </a>
        </nav>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
