<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

$pagina_ativa = 'consultar_orcamentos';
require_once 'header.php';
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
  header('Location: index.html');
  exit();
}

// Configurações de Paginação
$por_pagina = 20;
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($pagina_atual - 1) * $por_pagina;

// Filtros
$termo_busca = filter_input(INPUT_GET, 'busca', FILTER_SANITIZE_SPECIAL_CHARS);
$filtro_cliente = filter_input(INPUT_GET, 'cliente', FILTER_SANITIZE_SPECIAL_CHARS);

// Construção da Query
$where_clauses = ["`NUM_ORCAMENTO` IS NOT NULL AND `NUM_ORCAMENTO` != ''"];
$params = [];

if ($termo_busca) {
  $where_clauses[] = "`NUM_ORCAMENTO` LIKE :busca";
  $params[':busca'] = "%$termo_busca%";
}
if ($filtro_cliente) {
  $where_clauses[] = "`RAZÃO SOCIAL` LIKE :cliente";
  $params[':cliente'] = "%$filtro_cliente%";
}

$where_sql = implode(" AND ", $where_clauses);

// Total de orçamentos (com filtro)
$sql_count = "SELECT COUNT(DISTINCT `NUM_ORCAMENTO`) FROM cot_cotacoes_importadas WHERE $where_sql";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total = $stmt_count->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

// Busca os orçamentos
// Busca os orçamentos (Otimizado: Busca IDs primeiro, depois detalhes)
try {
  // 1. Busca apenas os IDs (NUM_ORCAMENTO) da página atual
  // Isso é muito mais leve para o banco ordenar e paginar
  $sqlIds = "SELECT DISTINCT `NUM_ORCAMENTO` 
             FROM cot_cotacoes_importadas 
             WHERE $where_sql 
             ORDER BY `NUM_ORCAMENTO` DESC 
             LIMIT :offset, :por_pagina";

  $stmtIds = $pdo->prepare($sqlIds);
  foreach ($params as $key => $val) {
    if ($key !== ':offset' && $key !== ':por_pagina') { // Bind apenas dos filtros
      $stmtIds->bindValue($key, $val);
    }
  }
  $stmtIds->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmtIds->bindValue(':por_pagina', $por_pagina, PDO::PARAM_INT);
  $stmtIds->execute();
  $ids = $stmtIds->fetchAll(PDO::FETCH_COLUMN);

  $orcamentos = [];

  if (!empty($ids)) {
    // 2. Se encontrou IDs, busca os detalhes APENAS desses orçamentos
    // Usamos IN (...) para fazer uma busca direta e rápida
    $inQuery = implode(',', array_fill(0, count($ids), '?'));

    $sqlDetalhes = "
      SELECT
        c1.`NUM_ORCAMENTO`,
        c1.`RAZÃO SOCIAL`,
        c1.`UF`,
        c1.`DATA`,
        (SELECT c2.`COTADO_POR` 
         FROM cot_cotacoes_importadas c2
         WHERE c2.`NUM_ORCAMENTO` = c1.`NUM_ORCAMENTO`
         LIMIT 1) AS `COTADO_POR`
      FROM cot_cotacoes_importadas c1
      WHERE c1.`NUM_ORCAMENTO` IN ($inQuery)
      GROUP BY c1.`NUM_ORCAMENTO`, c1.`RAZÃO SOCIAL`, c1.`UF`, c1.`DATA`
      ORDER BY c1.`NUM_ORCAMENTO` DESC
      ";

    $stmtDetalhes = $pdo->prepare($sqlDetalhes);
    $stmtDetalhes->execute($ids); // Passa o array de IDs para o IN
    $orcamentos = $stmtDetalhes->fetchAll(PDO::FETCH_ASSOC);
  }

} catch (PDOException $e) {
  echo "Erro na consulta: " . $e->getMessage();
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Consultar Orçamentos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
    }

    .table td {
      vertical-align: middle;
    }
  </style>
</head>

<body>
  <div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h4 text-secondary">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
          class="bi bi-folder2-open me-2" viewBox="0 0 16 16">
          <path
            d="M1 3.5A1.5 1.5 0 0 1 2.5 2h2.764c.958 0 1.76.56 2.311 1.184C7.985 3.648 8.48 4 9 4h4.5A1.5 1.5 0 0 1 15 5.5v7a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 1 12.5v-9zM2.5 3a.5.5 0 0 0-.5.5V6h12v-.5a.5.5 0 0 0-.5-.5H9c-.964 0-1.71-.629-2.174-1.154C6.374 3.334 5.82 3 5.264 3H2.5zM14 7H2v5.5a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5V7z" />
        </svg>
        Consultar Orçamentos
      </h2>
    </div>

    <!-- Filtros -->
    <div class="card mb-3 shadow-sm">
      <div class="card-body p-2">
        <form method="GET" class="row g-2 align-items-center">
          <div class="col-auto"><span class="fw-bold text-secondary">Filtrar:</span></div>
          <div class="col-auto">
            <input type="text" name="busca" class="form-control form-control-sm" placeholder="Nº Orçamento"
              value="<?= htmlspecialchars($termo_busca ?? '') ?>">
          </div>
          <div class="col-auto">
            <input type="text" name="cliente" class="form-control form-control-sm" placeholder="Cliente / Razão Social"
              value="<?= htmlspecialchars($filtro_cliente ?? '') ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Buscar</button>
            <a href="consultar_orcamentos.php" class="btn btn-sm btn-outline-secondary">Limpar</a>
          </div>
          <div class="col-auto ms-auto"><small class="text-muted">Total: <strong><?= $total ?></strong>
              registros</small></div>
        </form>
      </div>
    </div>

    <!-- Tabela -->
    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
              <tr>
                <th class="text-center">Nº Orçamento</th>
                <th>Razão Social</th>
                <th class="text-center">UF</th>
                <th class="text-center">Data</th>
                <th>Cotado por</th>
                <th class="text-center">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($orcamentos) > 0): ?>
                <?php foreach ($orcamentos as $orc): ?>
                  <tr>
                    <td class="text-center fw-bold text-primary"><?= htmlspecialchars($orc['NUM_ORCAMENTO']) ?></td>
                    <td><?= htmlspecialchars($orc['RAZÃO SOCIAL']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($orc['UF']) ?></td>
                    <td class="text-center text-muted small"><?= date('d/m/Y', strtotime($orc['DATA'])) ?></td>
                    <td><?= htmlspecialchars($orc['COTADO_POR'] ?? '-') ?></td>
                    <td class="text-center text-nowrap">
                      <a href="gerar_pdf_orcamento.php?num=<?= urlencode($orc['NUM_ORCAMENTO']) ?>&incluir_net=false"
                        target="_blank" class="btn btn-sm btn-outline-secondary me-1" title="Gerar PDF">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                          class="bi bi-file-earmark-pdf" viewBox="0 0 16 16">
                          <path
                            d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z" />
                          <path
                            d="M4.603 14.087a.81.81 0 0 1-.438-.42c-.195-.388-.13-.776.08-1.102.198-.307.526-.568.897-.787a7.68 7.68 0 0 1 1.482-.645 19.697 19.697 0 0 0 1.062-2.227 7.269 7.269 0 0 1-.43-1.295c-.086-.4-.119-.796-.046-1.136.075-.354.274-.672.65-.823.192-.077.4-.12.602-.077a.7.7 0 0 1 .477.365c.088.164.12.356.127.538.007.188-.012.396-.047.614-.084.51-.27 1.134-.52 1.794a10.954 10.954 0 0 0 .98 1.686 5.753 5.753 0 0 1 1.334.05c.364.066.734.195.96.465.12.144.193.32.2.518.007.192-.047.382-.138.563a1.04 1.04 0 0 1-.354.416.856.856 0 0 1-.51.138c-.331-.014-.654-.196-.933-.417a5.712 5.712 0 0 1-.911-.95 11.651 11.651 0 0 0-1.997.406 11.307 11.307 0 0 1-1.02 1.51c-.292.35-.609.656-.927.787a.793.793 0 0 1-.58.029zm1.379-1.901c-.166.076-.32.156-.459.238-.328.194-.541.383-.647.545-.094.145-.096.25-.04.361.01.022.02.036.026.044a.266.266 0 0 0 .035-.012c.137-.056.355-.235.635-.572a8.18 8.18 0 0 0 .45-.604zm1.649-1.825a12.556 12.556 0 0 1 2.337.38c.216.05.45.05.638-.283.045-.08.067-.175.058-.262-.007-.064-.027-.126-.062-.187-.076-.134-.176-.176-.234-.179a.257.257 0 0 0-.087.01c-.244.062-.511.23-.787.493a3.52 3.52 0 0 1-.349.336 6.22 6.22 0 0 1-1.514-1.308zm.725-2.458c.026-.117.022-.224-.002-.303a.35.35 0 0 0-.166-.175.45.45 0 0 0-.256-.006c-.09.034-.168.106-.217.206-.057.117-.05.31-.01.554.066.393.268.995.65 1.724a.55.55 0 0 0 .002.001z" />
                        </svg>
                      </a>
                      <a href="atualizar_orcamento.php?num=<?= urlencode($orc['NUM_ORCAMENTO']) ?>"
                        class="btn btn-sm btn-outline-primary" title="Abrir / Editar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                          class="bi bi-pencil-square" viewBox="0 0 16 16">
                          <path
                            d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z" />
                          <path fill-rule="evenodd"
                            d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z" />
                        </svg>
                      </a>
                      <button type="button" class="btn btn-sm btn-outline-danger" title="Excluir"
                        onclick="confirmarExclusao('<?= $orc['NUM_ORCAMENTO'] ?>')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                          class="bi bi-trash" viewBox="0 0 16 16">
                          <path
                            d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z" />
                          <path fill-rule="evenodd"
                            d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z" />
                        </svg>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="text-center py-4 text-muted">Nenhum orçamento encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <?php
          $queryParams = $_GET;
          unset($queryParams['pagina']);
          $linkBase = '?' . http_build_query($queryParams);
          ?>
          <?php if ($pagina_atual > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= $linkBase ?>&pagina=<?= $pagina_atual - 1 ?>">Anterior</a>
            </li>
          <?php else: ?>
            <li class="page-item disabled"><span class="page-link">Anterior</span></li>
          <?php endif; ?>

          <?php
          $adjacentes = 2; // Quantidade de páginas adjacentes para mostrar
          $inicio = max(1, $pagina_atual - $adjacentes);
          $fim = min($total_paginas, $pagina_atual + $adjacentes);

          // Sempre mostra a página 1
          if ($inicio > 1) {
            echo '<li class="page-item"><a class="page-link" href="' . $linkBase . '&pagina=1">1</a></li>';
            if ($inicio > 2) {
              echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
          }
          ?>
          <?php
          // Loop das páginas centrais
          for ($i = $inicio; $i <= $fim; $i++):
            ?>
            <li class="page-item <?= ($i == $pagina_atual) ? 'active' : '' ?>">
              <a class="page-link" href="<?= $linkBase ?>&pagina=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>

          <?php
          // Sempre mostra a última página
          if ($fim < $total_paginas) {
            if ($fim < $total_paginas - 1) {
              echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="' . $linkBase . '&pagina=' . $total_paginas . '">' . $total_paginas . '</a></li>';
          }
          ?>

          <?php if ($pagina_atual < $total_paginas): ?>
            <li class="page-item"><a class="page-link" href="<?= $linkBase ?>&pagina=<?= $pagina_atual + 1 ?>">Próxima</a>
            </li>
          <?php else: ?>
            <li class="page-item disabled"><span class="page-link">Próxima</span></li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>

  <!-- Modal de Exclusão -->
  <div class="modal fade" id="modalExcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Confirmar Exclusão</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          Tem certeza que deseja excluir o orçamento <strong id="orcamentoParaExcluir"></strong>?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <a href="#" id="btnConfirmarExclusao" class="btn btn-danger">Excluir</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function confirmarExclusao(numOrcamento) {
      document.getElementById('orcamentoParaExcluir').textContent = numOrcamento;
      document.getElementById('btnConfirmarExclusao').href = 'excluir_orcamento.php?num_orcamento=' + encodeURIComponent(numOrcamento);
      var modal = new bootstrap.Modal(document.getElementById('modalExcluir'));
      modal.show();
    }
  </script>
</body>

</html>