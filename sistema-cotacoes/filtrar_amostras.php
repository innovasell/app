<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
$pagina_ativa = 'pesquisar_amostras';

require_once 'header.php';
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
  header('Location: index.html');
  exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pesquisar Itens de Amostras</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    .filter-card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      background-color: #fff;
    }

    .filter-header {
      background: linear-gradient(45deg, #0d6efd, #0dcaf0);
      color: white;
      border-radius: 12px 12px 0 0;
      padding: 1.5rem;
    }

    .section-title {
      font-size: 0.9rem;
      color: #6c757d;
      font-weight: 600;
      text-transform: uppercase;
      border-bottom: 2px solid #f8f9fa;
      padding-bottom: 0.5rem;
      margin-bottom: 1rem;
      margin-top: 1.5rem;
    }

    .btn-search {
      padding: 0.8rem 2rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    body {
      background-color: #f8f9fa;
    }
  </style>
</head>

<body>
  <main class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="card filter-card">
          <div class="filter-header text-center">
            <h3 class="mb-0"><i class="bi bi-search me-2"></i>Pesquisar Amostras</h3>
            <p class="mb-0 opacity-75">Localize solicitações de amostra por produto, cliente ou status</p>
          </div>

          <div class="card-body p-4">
            <form method="GET" action="pesquisar_amostras.php">

              <!-- Seção Principal -->
              <div class="section-title"><i class="bi bi-funnel-fill me-2"></i>Filtros Principais</div>
              <div class="row g-3">
                <div class="col-md-8">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="produto" name="produto" placeholder="Nome ou Código">
                    <label for="produto">Produto (Nome ou Código)</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-floating">
                    <select class="form-select" id="status" name="status">
                      <option value="">Todos</option>
                      <option value="Pendente">Pendente</option>
                      <option value="Enviado">Enviado</option>
                      <option value="Cancelado">Cancelado</option>
                    </select>
                    <label for="status">Status</label>
                  </div>
                </div>
              </div>

              <!-- Seção Cliente e Representante -->
              <div class="section-title mt-4"><i class="bi bi-people-fill me-2"></i>Envolvidos</div>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="cliente_razao_social" name="cliente_razao_social"
                      placeholder="Cliente">
                    <label for="cliente_razao_social">Cliente (Razão Social)</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="responsavel_pedido" name="responsavel_pedido"
                      placeholder="Representante">
                    <label for="responsavel_pedido">Representante (E-mail)</label>
                  </div>
                </div>
              </div>

              <!-- Seção Datas -->
              <div class="section-title mt-4"><i class="bi bi-calendar-range me-2"></i>Período da Solicitação</div>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="date" class="form-control" id="data_inicial" name="data_inicial">
                    <label for="data_inicial">Data Inicial</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="date" class="form-control" id="data_final" name="data_final">
                    <label for="data_final">Data Final</label>
                  </div>
                </div>
              </div>

              <div class="d-grid gap-2 d-md-block text-end mt-5">
                <a href="filtrar_amostras.php" class="btn btn-light border me-md-2 px-4">
                  <i class="bi bi-eraser me-1"></i> Limpar
                </a>
                <button type="submit" class="btn btn-primary btn-search btn-lg shadow-sm">
                  <i class="bi bi-search me-2"></i>Pesquisar
                </button>
              </div>

            </form>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>