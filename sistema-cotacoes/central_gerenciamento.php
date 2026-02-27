<?php
session_start();

// Verificar permissão de admin
if (!isset($_SESSION['representante_email']) || $_SESSION['grupo'] !== 'admin') {
    // Se não for admin, redireciona ou mostra erro
    // Ajuste: verificar se header.php já tem essa lógica de grupo. 
    // Como acabamos de criar, pode ser que a sessão antiga não tenha 'grupo'.
    // Ideal: Forçar relogin ou recarregar sessão.
    // Por enquanto, verificamos também o flag antigo 'admin' para compatibilidade durante migração
    if (!isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
        echo "<div class='container py-5'><div class='alert alert-danger'>Acesso negado. Apenas administradores podem acessar esta página.</div></div>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Gerenciamento - H Hansen</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts: Montserrat -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f4f6f9;
        }

        .hover-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .hover-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
        }
    </style>
</head>

<body>
    <?php require_once 'header.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-primary"><i class="fas fa-cogs me-2"></i>Central de Gerenciamento</h2>
                <p class="text-muted">Área administrativa para controle de usuários e permissões do sistema.</p>
            </div>
        </div>

        <div class="row g-4">
            <!-- Card Gerenciar Usuários -->
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0 hover-card">
                    <div class="card-body text-center p-5">
                        <div class="mb-4">
                            <i class="fas fa-users-cog fa-4x text-primary"></i>
                        </div>
                        <h3 class="card-title h4 mb-3">Gerenciar Usuários</h3>
                        <p class="card-text text-muted mb-4">
                            Cadastre novos usuários, edite perfis, redefina senhas e defina os grupos de acesso
                            (Administrador, Gestor, Geral).
                        </p>
                        <a href="gerenciar_usuarios.php" class="btn btn-primary btn-lg px-4 rounded-pill">
                            Acessar <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card Customizar Menus -->
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0 hover-card">
                    <div class="card-body text-center p-5">
                        <div class="mb-4">
                            <i class="fas fa-list-alt fa-4x text-success"></i>
                        </div>
                        <h3 class="card-title h4 mb-3">Customizar Menus</h3>
                        <p class="card-text text-muted mb-4">
                            Defina quais grupos de usuários podem visualizar e acessar cada item do menu principal do
                            sistema.
                        </p>
                        <a href="gerenciar_menus.php" class="btn btn-success btn-lg px-4 rounded-pill">
                            Acessar <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>