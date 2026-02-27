<?php
// index.php - Dashboard Unificado (SSO)
session_start();
require_once 'site_conexao.php';

// Proteção de Login
if (!isset($_SESSION['sso_user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['sso_user'];
$acessos = $user['acessos'];

// Lista de Portais
$portais = [
    'expedicao' => [
        'titulo' => 'Portal de Expedição',
        'desc' => 'Rastreamento, envios e logística.',
        'icon' => 'bi-truck',
        'color' => 'bg-info',
        'perm' => 'expedicao',
        'target' => 'exp' // Pasta ou identificador
    ],
    'cotacoes' => [
        'titulo' => 'Portal de Cotações',
        'desc' => 'Orçamentos, vendas e CRM.',
        'icon' => 'bi-currency-dollar',
        'color' => 'bg-success',
        'perm' => 'cotacoes',
        'target' => 'sistema-cotacoes'
    ],
    'faq' => [
        'titulo' => 'InnovaWiki',
        'desc' => 'Base de conhecimento e tutoriais.',
        'icon' => 'bi-book-half',
        'color' => 'bg-warning',
        'perm' => 'faq',
        'target' => 'faq'
    ],
    'comissoes' => [
        'titulo' => 'Portal de Comissões',
        'desc' => 'Cálculos de comissões e performance.',
        'icon' => 'bi-wallet2',
        'color' => 'bg-primary',
        'perm' => 'comissoes',
        'target' => 'comissoes'
    ],
    'formulas' => [
        'titulo' => 'Formularium',
        'desc' => 'Engenharia química e formulações.',
        'icon' => 'bi-eyedropper',
        'color' => 'bg-danger',
        'perm' => 'formulas',
        'target' => 'gerador-formulas'
    ],
    'viagens' => [
        'titulo' => 'InnovaEvents',
        'desc' => 'Gestão de Despesas e Viagens.',
        'icon' => 'bi-airplane',
        'color' => 'bg-secondary',
        'perm' => 'viagens',
        'target' => 'events'
    ]
];

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Innovasell</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }

        .portal-card {
            border: none;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            height: 100%;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .portal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .icon-box {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.8rem;
            color: white;
            margin-bottom: 1rem;
        }

        .card-disabled {
            opacity: 0.6;
            cursor: not-allowed;
            filter: grayscale(100%);
        }

        .navbar-brand img {
            height: 30px;
            margin-right: 10px;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-5 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <img src="assets/img/logo.png" alt="Logo" class="bg-white rounded p-1"
                    onerror="this.style.display='none'">
                Innovasell Cloud
            </a>
            <div class="d-flex align-items-center text-white">
                <div class="me-3 text-end d-none d-md-block">
                    <div class="fw-bold">
                        <?= htmlspecialchars($user['nome'] . ' ' . $user['sobrenome']) ?>
                    </div>
                    <div class="small opacity-75">
                        <?= htmlspecialchars($user['email']) ?>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($user['admin'] == 1): ?>
                            <li><a class="dropdown-item" href="admin_users.php"><i
                                        class="bi bi-gear-fill me-2"></i>Gerenciar Acessos</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="change_password.php">Alterar Senha</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="logout_sso.php">Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="mb-4">
            <h2 class="fw-bold text-dark mb-1">Bem-vindo,
                <?= htmlspecialchars($user['nome']) ?>!
            </h2>
            <p class="text-muted">Selecione um portal abaixo para acessar suas ferramentas.</p>
        </div>

        <div class="row g-4">
            <?php foreach ($portais as $key => $portal):
                $hasAccess = true; // Por padrão liberado para teste, ou validar $acessos[$portal['perm']] == 1
                $hasAccess = (isset($acessos[$portal['perm']]) && $acessos[$portal['perm']] == 1);

                // Admin sempre tem acesso a cotacoes (regra legada)
                if ($key == 'cotacoes' && $user['admin'] == 1)
                    $hasAccess = true;
                ?>
                <div class="col-md-4 col-sm-6">
                    <?php if ($hasAccess): ?>
                        <a href="sso_redirect.php?target=<?= $key ?>" class="card portal-card shadow-sm p-4">
                            <div class="card-body">
                                <div class="icon-box <?= $portal['color'] ?>">
                                    <i class="bi <?= $portal['icon'] ?>"></i>
                                </div>
                                <h5 class="card-title fw-bold">
                                    <?= $portal['titulo'] ?>
                                </h5>
                                <p class="card-text text-muted small">
                                    <?= $portal['desc'] ?>
                                </p>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="card portal-card shadow-sm p-4 card-disabled" title="Acesso não autorizado">
                            <div class="card-body">
                                <div class="icon-box bg-secondary">
                                    <i class="bi <?= $portal['icon'] ?>"></i>
                                </div>
                                <h5 class="card-title fw-bold">
                                    <?= $portal['titulo'] ?> <i class="bi bi-lock-fill ms-1"></i>
                                </h5>
                                <p class="card-text text-muted small">Você não tem permissão para acessar este portal.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer class="mt-5 py-4 text-center text-muted border-top">
        <small>&copy;
            <?= date('Y') ?> Innovasell Cloud. Todos os direitos reservados.
        </small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>