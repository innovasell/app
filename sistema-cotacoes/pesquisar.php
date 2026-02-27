<?php
require_once 'conexao.php';
session_start();

function formatarOrigem($codigoOrigem)
{
  $rotulos = [
    '0' => 'NACIONAL',
    '1' => 'IMPORTADO',
    '6' => 'LISTA CAMEX'
  ];
  return $rotulos[trim($codigoOrigem)] ?? htmlspecialchars($codigoOrigem);
}

require_once 'header.php';

if (!isset($_SESSION['representante_email'])) {
  header('Location: index.html');
  exit();
}

$limite = 25;
$pagina = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, [
  'options' => ['default' => 1, 'min_range' => 1]
]);
$offset = ($pagina - 1) * $limite;

$campos = [
  'suframa' => 'SUFRAMA',
  'razao_social' => 'RAZÃO SOCIAL',
  'uf' => 'UF',
  'data_inicial' => 'DATA',
  'data_final' => 'DATA',
  'codigo' => 'COD DO PRODUTO',
  'produto' => 'PRODUTO',
  'origem' => 'ORIGEM',
  'embalagem' => 'EMBALAGEM_KG',
  'ncm' => 'NCM',
  'volume' => 'VOLUME',
  'ipi' => 'IPI %',
  'preco_net' => 'PREÇO NET USD/KG',
  'icms' => 'ICMS',
  'preco_full' => 'PREÇO FULL USD/KG',
  'disponibilidade' => 'DISPONIBilidade',
  'cotado_por' => 'COTADO_POR',
  'dolar' => 'DOLAR COTADO',
  'suspensao_ipi' => 'SUSPENCAO_IPI',
  'observacoes' => 'OBSERVAÇÕES'
];

$filtros = [];
$parametros = [];

foreach ($campos as $campo => $coluna) {
  if (!empty($_GET[$campo])) {
    if ($campo === 'data_inicial') {
      $filtros[] = "`$coluna` >= :data_inicial";
      $parametros[':data_inicial'] = $_GET[$campo];
    } elseif ($campo === 'data_final') {
      $filtros[] = "`$coluna` <= :data_final";
      $parametros[':data_final'] = $_GET[$campo];
    } else {
      $filtros[] = "`$coluna` LIKE :$campo";
      $parametros[":{$campo}"] = "%" . $_GET[$campo] . "%";
    }
  }
}

$where = $filtros ? "WHERE " . implode(" AND ", $filtros) : "";
$orderBy = " ORDER BY `DATA` DESC, `NUM_ORCAMENTO` DESC ";

$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM cot_cotacoes_importadas $where $orderBy LIMIT :limite OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($parametros as $key => $value) {
  $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalResultados = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
$totalPaginas = ceil($totalResultados / $limite);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Resultados da Pesquisa</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap"
    rel="stylesheet">
  <style>
    .pagination .page-item.disabled .page-link {
      color: #6c757d;
      pointer-events: none;
    }

    .pagination .page-item.active .page-link {
      background-color: #0d6efd;
      border-color: #0d6efd;
      color: white;
    }

    table {
      width: 100% !important;
    }

    td,
    tr {
      font-family: "Montserrat", sans-serif;
      font-optical-sizing: auto;
      font-size: 10px;
      text-align: center;
      vertical-align: middle;
    }

    .tabela-orcamento th {
      font-weight: bold;
      font-size: 15px;
    }

    .tabela-orcamento td {
      font-size: 14px;
    }

    .action-buttons a {
      margin: 0 2px;
    }
  </style>
</head>

<body>
  <div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">Resultados da Pesquisa</h2>
      <div>
        <a href="pesquisar.php" class="btn btn-outline-secondary me-2">
          <i class="fas fa-eraser"></i> Limpar Filtros
        </a>
        <?php if (isset($_SESSION['admin']) && $_SESSION['admin'] == 1): ?>
          <a href="exportar_excel.php?<?= http_build_query($_GET) ?>" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Exportar Excel
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="alert alert-danger text-center fw-bold">
      PROIBIDO O COMPARTILHAMENTO DESTE DOCUMENTO COM PESSOAS EXTERNAS
    </div>

    <!-- Drill Down Tip -->
    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert">
      <i class="fas fa-lightbulb me-2 fs-5"></i>
      <div>
        <strong>Dica:</strong> Clique no nome do <strong>Cliente</strong> ou do <strong>Produto</strong> na tabela para
        filtrar rapidamente os resultados.
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <p>Total de resultados: <strong><?= $totalResultados ?></strong></p>

    <div class="table-responsive">
      <table class="table table-bordered table-striped tabela-orcamento">
        <thead>
          <tr>
            <th>DATA</th>
            <th>RAZÃO SOCIAL</th>
            <th>UF</th>
            <th>PRODUTO</th>
            <th>EMBALAGEM</th>
            <th>VOLUME</th>
            <th>IPI %</th>
            <th>ICMS</th>
            <th>PREÇO NET USD/KG</th>
            <th>PRICE LIST USD</th>
            <th>PREÇO FULL USD/KG</th>
            <th>MAIS INFORMAÇÕES</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($resultados as $linha):
            // Preparar dados para o modal
            $jsonData = json_encode($linha);
            $origemFormatada = formatarOrigem($linha['origem'] ?? $linha['ORIGEM'] ?? '');
            $precoNet = 'USD$ ' . number_format((float) $linha['PREÇO NET USD/KG'], 2, '.', ',');
            $precoFull = 'USD$ ' . number_format((float) $linha['PREÇO FULL USD/KG'], 2, '.', ',');
            $data = date('d/m/Y', strtotime($linha['DATA']));
            $ipiFormatado = number_format((float) $linha['IPI %'], 2, ',', '.') . '%';

            $icmsLimpo = str_replace(['%', ','], ['', '.'], $linha['ICMS']);
            $icmsFormatado = number_format((float) $icmsLimpo, 2, ',', '.') . '%';

            // Pega o valor salvo no BD. Se não existir, fica vazio ou traço.
            $valPriceList = $linha['PRICE LIST'] ?? null;
            $precoLista = $valPriceList ? 'USD$ ' . number_format((float) $valPriceList, 2, '.', ',') : '-';
            ?>
            <tr>
              <td><?= $data ?></td>
              <td>
                <a href="pesquisar.php?razao_social=<?= urlencode($linha['RAZÃO SOCIAL']) ?>"
                  class="text-decoration-none text-dark" title="Filtrar por este cliente">
                  <?= htmlspecialchars($linha['RAZÃO SOCIAL']) ?>
                </a>
              </td>
              <td><?= htmlspecialchars($linha['UF']) ?></td>
              <td>
                <a href="pesquisar.php?produto=<?= urlencode($linha['PRODUTO']) ?>"
                  class="text-decoration-none text-dark fw-bold" title="Filtrar por este produto">
                  <?= htmlspecialchars($linha['PRODUTO']) ?>
                </a>
              </td>
              <td><?= htmlspecialchars($linha['EMBALAGEM_KG']) ?></td>
              <td><?= htmlspecialchars($linha['VOLUME']) ?></td>
              <td><?= $ipiFormatado ?></td>
              <td><?= $icmsFormatado ?></td>
              <td><?= $precoNet ?></td>
              <td class="fw-bold text-primary"><?= $precoLista ?></td>
              <td><?= $precoFull ?></td>
              <td>
                <button type="button" class="btn btn-primary btn-sm btn-saber-mais" data-bs-toggle="modal"
                  data-bs-target="#modalDetalhes" data-dados='<?= htmlspecialchars($jsonData, ENT_QUOTES, 'UTF-8') ?>'
                  data-origem="<?= htmlspecialchars($origemFormatada) ?>" data-preco-net="<?= $precoNet ?>"
                  data-preco-full="<?= $precoFull ?>" data-data="<?= $data ?>" data-ipi="<?= $ipiFormatado ?>"
                  data-icms="<?= $icmsFormatado ?>"> Saber mais
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Modal Detalhes -->
    <!-- Modal Detalhes (Redesigned) -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-labelledby="modalDetalhesLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">

          <!-- Header Minimalista -->
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold text-success" id="modalDetalhesLabel"><i
                class="fas fa-info-circle me-2"></i>Detalhes da Cotação</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body pt-2 px-4 pb-4">

            <!-- Hero Product Section -->
            <div class="bg-light p-3 rounded mb-4 mt-3 border-start border-success border-4 shadow-sm">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="badge bg-success" style="font-size: 0.9rem;" id="modal-codigo">CODE</span>
                <span class="text-muted small"><i class="far fa-calendar-alt me-1"></i> <span
                    id="modal-data"></span></span>
              </div>
              <h4 class="mb-0 fw-bold text-dark mt-2" id="modal-produto" style="font-family: 'Montserrat', sans-serif;">
                Produto</h4>
            </div>

            <div class="row g-4">
              <!-- Coluna Esquerda: Dados Técnicos -->
              <div class="col-md-6">
                <h6 class="text-uppercase text-secondary fw-bold small mb-3 border-bottom pb-2"><i
                    class="fas fa-box-open me-2"></i> Especificações</h6>
                <ul class="list-unstyled text-secondary" style="font-size: 0.95rem;">
                  <li class="mb-2 d-flex justify-content-between"><span><strong>NCM:</strong></span> <span
                      id="modal-ncm" class="text-dark"></span></li>
                  <li class="mb-2 d-flex justify-content-between"><span><strong>Origem:</strong></span> <span
                      id="modal-origem" class="text-dark"></span></li>
                  <li class="mb-2 d-flex justify-content-between"><span><strong>Embalagem:</strong></span> <span
                      id="modal-embalagem" class="text-dark"></span></li>
                  <li class="mb-2 d-flex justify-content-between"><span><strong>Volume:</strong></span> <span
                      id="modal-volume" class="text-dark"></span></li>
                  <li class="mb-2 d-flex justify-content-between"><span><strong>Disponibilidade:</strong></span> <span
                      id="modal-disponibilidade" class="text-dark"></span></li>
                </ul>
              </div>

              <!-- Coluna Direita: Financeiro -->
              <div class="col-md-6">
                <h6 class="text-uppercase text-secondary fw-bold small mb-3 border-bottom pb-2"><i
                    class="fas fa-file-invoice-dollar me-2"></i> Valores e Taxas</h6>
                <ul class="list-unstyled" style="font-size: 0.95rem;">
                  <li class="mb-2 d-flex justify-content-between align-items-center bg-light p-2 rounded">
                    <span class="text-muted">Preço Net:</span>
                    <strong class="text-primary fs-5" id="modal-preco-net"></strong>
                  </li>
                  <li class="mb-2 d-flex justify-content-between align-items-center p-2">
                    <span class="text-muted">Preço Full:</span>
                    <strong class="text-dark" id="modal-preco-full"></strong>
                  </li>
                  <li class="mb-1 d-flex justify-content-between px-2"><span><strong>IPI:</strong></span> <span><span
                        id="modal-ipi"></span> <small class="text-muted">(<span
                          id="modal-suspensao-ipi"></span>)</small></span></li>
                  <li class="mb-1 d-flex justify-content-between px-2"><span><strong>ICMS:</strong></span> <span
                      id="modal-icms"></span></li>
                  <li class="d-flex justify-content-between px-2"><span><strong>Suframa:</strong></span> <span
                      id="modal-suframa"></span></li>
                </ul>
              </div>
            </div>

            <!-- Observações -->
            <div class="mt-4">
              <label class="fw-bold small text-muted mb-1"><i class="far fa-comment-alt me-1"></i> Observações</label>
              <div class="p-3 bg-light rounded text-secondary small border" id="modal-observacoes"
                style="min-height: 50px; font-style: italic;">
                <!-- JS popula aqui -->
              </div>
            </div>

            <!-- Meta Footer -->
            <div class="row mt-4 pt-2 text-muted small align-items-center">
              <div class="col-md-6"><i class="fas fa-user-circle me-1"></i> Cotado por: <strong id="modal-cotado-por"
                  class="text-dark"></strong></div>
              <div class="col-md-6 text-md-end"><i class="fas fa-dollar-sign me-1"></i> Dólar ref: <span
                  id="modal-dolar"></span></div>
            </div>

          </div>

          <!-- Footer Actions -->
          <div class="modal-footer bg-light border-0 py-3">
            <div class="d-flex w-100 justify-content-between align-items-center">
              <button type="button" class="btn btn-link text-muted text-decoration-none btn-sm"
                data-bs-dismiss="modal">Fechar</button>

              <div>
                <a href="#" id="btn-movimentacoes" target="_blank"
                  class="btn btn-outline-secondary btn-sm me-2 shadow-sm">
                  <i class="fas fa-history me-1"></i> Movimentações
                </a>
                <a href="#" id="btn-alterar" class="btn btn-warning btn-sm text-white me-2 shadow-sm">
                  <i class="fas fa-pencil-alt me-1"></i> Alterar
                </a>
                <a href="#" id="btn-pdf" target="_blank" class="btn btn-success shadow-sm">
                  <i class="fas fa-file-pdf me-1"></i> Gerar PDF
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var modalDetalhes = document.getElementById('modalDetalhes');
        if (modalDetalhes) {
          modalDetalhes.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var rawData = button.getAttribute('data-dados');
            var dados = JSON.parse(rawData);
            var origem = button.getAttribute('data-origem');
            var precoNet = button.getAttribute('data-preco-net');
            var precoFull = button.getAttribute('data-preco-full');
            var dataFormatada = button.getAttribute('data-data');

            var icmsFormatado = button.getAttribute('data-icms');

            // Preencher campos de texto
            modalDetalhes.querySelector('#modal-codigo').textContent = dados['COD DO PRODUTO'];
            modalDetalhes.querySelector('#modal-produto').textContent = dados['PRODUTO'];
            modalDetalhes.querySelector('#modal-ncm').textContent = dados['NCM'];
            modalDetalhes.querySelector('#modal-origem').textContent = origem;
            modalDetalhes.querySelector('#modal-embalagem').textContent = dados['EMBALAGEM_KG'];

            modalDetalhes.querySelector('#modal-preco-net').textContent = precoNet;
            modalDetalhes.querySelector('#modal-preco-full').textContent = precoFull;
            modalDetalhes.querySelector('#modal-ipi').textContent = dados['IPI %'];
            modalDetalhes.querySelector('#modal-suspensao-ipi').textContent = dados['SUSPENCAO_IPI'] ? dados['SUSPENCAO_IPI'] : 'Não';
            modalDetalhes.querySelector('#modal-icms').textContent = icmsFormatado;
            modalDetalhes.querySelector('#modal-suframa').textContent = dados['SUFRAMA'];

            modalDetalhes.querySelector('#modal-disponibilidade').textContent = dados['DISPONIBILIDADE'];
            modalDetalhes.querySelector('#modal-volume').textContent = dados['VOLUME'];
            // Observações pode não existir em todos os registros?
            modalDetalhes.querySelector('#modal-observacoes').textContent = dados['OBSERVAÇÕES'] || '';

            modalDetalhes.querySelector('#modal-data').textContent = dataFormatada;
            modalDetalhes.querySelector('#modal-cotado-por').textContent = dados['COTADO_POR'];
            modalDetalhes.querySelector('#modal-dolar').textContent = dados['DOLAR COTADO'];

            // Atualizar Botões
            // Visualizar movimentações
            var linkMovimentacoes = "https://app.maino.com.br/produto_estoques?utf8=✓&filtro=true&codigo="
              + encodeURIComponent(dados['COD DO PRODUTO'])
              + "&descricao=" + encodeURIComponent(dados['PRODUTO'])
              + "&commit=Filtrar";
            var btnMov = modalDetalhes.querySelector('#btn-movimentacoes');
            btnMov.href = linkMovimentacoes;

            // Alterar
            var linkAlterar = "atualizar_orcamento.php?num=" + encodeURIComponent(dados['NUM_ORCAMENTO']);
            var btnAlt = modalDetalhes.querySelector('#btn-alterar');
            btnAlt.href = linkAlterar;

            // PDF
            var linkPDF = "gerar_pdf_orcamento.php?num=" + encodeURIComponent(dados['NUM_ORCAMENTO']);
            var btnPDF = modalDetalhes.querySelector('#btn-pdf');
            btnPDF.href = linkPDF;
          });
        }
      });
    </script>


    <nav>
      <ul class="pagination justify-content-center">
        <?php if ($pagina > 1): ?>
          <li class="page-item"><a class="page-link"
              href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">Anterior</a></li>
        <?php endif; ?>
        <?php
        $maxLinks = 2;
        $start = max(1, $pagina - $maxLinks);
        $end = min($totalPaginas, $pagina + $maxLinks);
        if ($start > 1) {
          echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['pagina' => 1])) . '">1</a></li>';
          if ($start > 2)
            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        for ($i = $start; $i <= $end; $i++) {
          $active = $pagina === $i ? 'active' : '';
          echo '<li class="page-item ' . $active . '"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['pagina' => $i])) . '">' . $i . '</a></li>';
        }
        if ($end < $totalPaginas) {
          if ($end < $totalPaginas - 1)
            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
          echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['pagina' => $totalPaginas])) . '">' . $totalPaginas . '</a></li>';
        }
        if ($pagina < $totalPaginas): ?>
          <li class="page-item"><a class="page-link"
              href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">Próxima</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>