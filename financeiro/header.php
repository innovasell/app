<?php
$modulos_permitidos = ['financeiro'];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Validação básica do usuário e do acesso... caso necessário
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro - Innovasell Cloud</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables Bootstrap 5 Style -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- DataTables Responsive Style -->
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <!-- Dropzone (Optional but good for drag & drop visual flair) -->
    
    <style>
        body {
            background-color: #f0f4f8;
            font-family: 'Montserrat', 'Segoe UI', sans-serif;
        }

        /* Navbar & branding */
        .navbar-custom {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e6ed;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            padding: 0.5rem 1.5rem;
        }
        .navbar-brand-img {
            height: 35px;
            margin-right: 15px;
        }
        .nav-link {
            color: #495057;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .nav-link:hover {
            color: #2c5e29;
            background-color: #f8f9fa;
        }
        .nav-link.active {
            color: #ffffff !important;
            background-color: #40883c;
        }

        /* Page Headers */
        .page-header {
            background: linear-gradient(135deg, #0a1e42 0%, #1a365d 100%);
            color: white;
            padding: 1.5rem 1.5rem;
            margin: -1.5rem -1.5rem 1.5rem -1.5rem;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
        }
        .breadcrumb-item a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
        }
        .breadcrumb-item.active {
            color: white;
        }
        .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255,255,255,0.4);
        }

        /* Cards and Stats */
        .card-stat {
            border: none;
            border-radius: 10px;
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            overflow: hidden;
            position: relative;
        }
        .card-stat:hover {
            transform: translateY(-3px);
        }
        .card-stat .card-body {
            padding: 1.5rem;
            position: relative;
            z-index: 1;
        }
        .card-stat .card-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 4rem;
            opacity: 0.15;
            color: white;
        }
        .card-stat .card-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .card-stat .card-label {
            font-size: 0.9rem;
            font-weight: 500;
            opacity: 0.9;
        }
        .bg-primary-custom { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
        .bg-success-custom { background: linear-gradient(135deg, #40883c 0%, #2c5e29 100%); }
        .bg-warning-custom { background: linear-gradient(135deg, #f59f00 0%, #d9480f 100%); }
        .bg-danger-custom { background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); }

        /* Tables DataTables */
        .table-responsive {
            background-color: #ffffff;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        table.dataTable {
            margin-top: 1rem !important;
            margin-bottom: 1rem !important;
            font-size: 0.9rem;
        }
        table.dataTable thead th {
            background-color: #0a1e42;
            color: white;
            padding: 0.8rem;
            border-bottom: 2px solid #0a1e42;
        }
        table.dataTable tbody td {
            vertical-align: middle;
            padding: 0.75rem;
        }
        .badge {
            font-weight: 600;
            padding: 0.4em 0.6em;
        }
        .badge-innovasell {
            background-color: #0047fa;
            color: white;
        }
        
        .upload-area {
            border: 2px dashed #40883c;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background-color: #f8f9fa;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        .upload-area:hover, .upload-area.dragover {
            background-color: #e9ecef;
            border-color: #2c5e29;
            box-shadow: 0 0 10px rgba(64,136,60,0.2);
        }
        .upload-area i {
            font-size: 3rem;
            color: #40883c;
            margin-bottom: 1rem;
        }
        .upload-area input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-custom mb-4">
    <div class="container-fluid">
        <!-- Logo - Adjust path if needed depending on standard in innovasell app/ -->
        <a class="navbar-brand text-success fw-bold d-flex align-items-center" href="../">
            <i class="fas fa-chart-pie me-2"></i> Innovasell Cloud
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= ($pagina_ativa === 'dashboard') ? 'active' : '' ?>" href="../">Dashboard Admin</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($pagina_ativa === 'financeiro_index') ? 'active' : '' ?>" href="index.php">
                        <i class="fas fa-file-invoice-dollar me-1"></i> NF-e Proc.
                    </a>
                </li>
                <!-- Can append other modules later-->
            </ul>
            <div class="d-flex align-items-center">
                <span class="text-muted small me-3"><i class="fas fa-user-circle me-1"></i> Sistema</span>
                <a href="../logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </div>
</nav>
