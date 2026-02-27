<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
$pagina_ativa = 'filtrar';

require_once 'header.php';
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
  header('Location: index.html');
  exit();
}

// Busca vendedores para o dropdown
$vendedores = [];
try {
  $stmt = $pdo->query("SELECT nome, sobrenome FROM cot_representante ORDER BY nome ASC");
  $vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Erro ao buscar vendedores: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pesquisar Cotações</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
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
            <h3 class="mb-0"><i class="bi bi-search me-2"></i>Pesquisar Cotações</h3>
            <p class="mb-0 opacity-75">Utilize os filtros abaixo para refinar sua busca</p>
          </div>

          <div class="card-body p-4">
            <div class="alert alert-danger text-center fw-bold small py-2 mb-4" role="alert">
              <i class="bi bi-shield-lock-fill me-1"></i> PROIBIDO O COMPARTILHAMENTO DESTA PESQUISA COM PESSOAS
              EXTERNAS
            </div>

            <form method="GET" action="pesquisar.php">

              <!-- Seção Cliente e Data -->
              <div class="section-title"><i class="bi bi-person-lines-fill me-2"></i>Dados Básicos</div>
              <div class="row g-3">
                <div class="col-md-5">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="razao_social" name="razao_social"
                      placeholder="Nome do Cliente">
                    <label for="razao_social">Cliente / Razão Social</label>
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-floating">
                    <input type="text" class="form-control text-uppercase" id="uf" name="uf" maxlength="2"
                      placeholder="UF">
                    <label for="uf">UF</label>
                  </div>
                </div>
                <div class="col-md-5">
                  <div class="form-floating">
                    <select class="form-select" id="cotado_por" name="cotado_por">
                      <option value="">Todos</option>
                      <?php foreach ($vendedores as $vendedor):
                        $valor = strtoupper(htmlspecialchars($vendedor['nome']));
                        $texto = htmlspecialchars(trim($vendedor['nome'] . ' ' . $vendedor['sobrenome']));
                        ?>
                        <option value="<?= $valor ?>"><?= $texto ?></option>
                      <?php endforeach; ?>
                    </select>
                    <label for="cotado_por">Cotado Por</label>
                  </div>
                </div>

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

              <!-- Seção Produto -->
              <div class="section-title"><i class="bi bi-box-seam me-2"></i>Informações do Produto</div>
              <div class="row g-3">
                <div class="col-md-3">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="codigo" name="codigo" placeholder="Cód.">
                    <label for="codigo">Código</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="produto" name="produto" placeholder="Nome do Produto">
                    <label for="produto">Produto</label>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="ncm" name="ncm" placeholder="NCM">
                    <label for="ncm">NCM</label>
                  </div>
                </div>
                <div class="col-md-12">
                  <button class="btn btn-outline-secondary btn-sm w-100" type="button" data-bs-toggle="collapse"
                    data-bs-target="#filtrosAvancados">
                    <i class="bi bi-sliders me-1"></i> Mostrar/Ocultar Detalhes Avançados
                  </button>
                </div>
              </div>

              <!-- Seção Avançada (Collapse) -->
              <div class="collapse" id="filtrosAvancados">
                <div class="section-title"><i class="bi bi-currency-dollar me-2"></i>Valores e Tributos</div>
                <div class="row g-3">
                  <div class="col-md-3">
                    <div class="form-floating">
                      <input type="text" class="form-control" name="origem" placeholder="Origem">
                      <label>Origem</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-floating">
                      <input type="text" class="form-control" name="volume" placeholder="Volume">
                      <label>Volume</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-floating">
                      <input type="text" class="form-control" name="embalagem" placeholder="Embalagem">
                      <label>Embalagem</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-floating">
                      <input type="text" class="form-control" name="disponibilidade" placeholder="Disponibilidade">
                      <label>Disponibilidade</label>
                    </div>
                  </div>

                  <div class="col-md-3">
                    <div class="form-floating">
                      <input type="text" class="form-control" name="ipi" placeholder="IPI %">
                      <label>IPI %</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-floating">
                      <input type="text" class="form-control" name="icms" placeholder="ICMS">
                      <label>ICMS</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-floating">
                      <input type="text" class="form-control" name="suframa" placeholder="SUFRAMA">
                      <label>SUFRAMA</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-floating">
                      <input type="text" class="form-control" name="suspensao_ipi" placeholder="Susp. IPI">
                      <label>Suspensão de IPI</label>
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="form-floating">
                      <input type="text" class="form-control" name="preco_net" placeholder="Preço Net">
                      <label>Preço Net (USD)</label>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-floating">
                      <input type="text" class="form-control" name="preco_full" placeholder="Preço Full">
                      <label>Preço Full (USD)</label>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-floating">
                      <input type="text" class="form-control" name="dolar" placeholder="Dólar">
                      <label>Dólar Cotado</label>
                    </div>
                  </div>

                  <div class="col-12">
                    <div class="form-floating">
                      <textarea class="form-control" name="observacoes" placeholder="Observações"
                        style="height: 100px"></textarea>
                      <label>Observações</label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="alert alert-light border mt-4 mb-0 small text-muted">
                <i class="bi bi-info-circle me-1"></i> <strong>Nota:</strong> Campos como SUFRAMA e Suspensão de IPI
                possuem vigência a partir de Fevereiro/2025.
              </div>

              <div class="d-grid gap-2 d-md-block text-end mt-4">
                <button type="reset" class="btn btn-light border me-md-2 px-4">Limpar</button>
                <button type="submit" class="btn btn-primary btn-search btn-lg shadow-sm">
                  <i class="bi bi-search me-2"></i>Buscar Cotações
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