<?php
session_start();
// DEFINIR FUSO HORÁRIO LOCAL
date_default_timezone_set('America/Sao_Paulo');

$pagina_ativa = 'pesquisar_amostras';
require_once 'header.php';
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
  header('Location: index.html');
  exit();
}

$isAdmin = (isset($_SESSION['admin']) && $_SESSION['admin'] == 1);
$limite = 25;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $limite;

// --- NOVIDADE: Captura a query string atual para usar nos links de ação ---
$queryString = http_build_query($_GET);

$campos_filtros = [
  'produto' => ['ce.produto', 'ce.codigo'],
  'cliente_razao_social' => 'cc.razao_social',
  'responsavel_pedido' => 'pa.responsavel_pedido',
  'status' => 'pa.status',
  'data_inicial' => 'pa.data_pedido',
  'data_final' => 'pa.data_pedido'
];

$filtros = [];
$parametros = [];

foreach ($campos_filtros as $campo_get => $colunas_db) {
  if (!empty($_GET[$campo_get])) {
    if ($campo_get === 'data_inicial') {
      $filtros[] = "$colunas_db >= :data_inicial";
      $parametros[':data_inicial'] = $_GET[$campo_get] . ' 00:00:00';
    } elseif ($campo_get === 'data_final') {
      $filtros[] = "$colunas_db <= :data_final";
      $parametros[':data_final'] = $_GET[$campo_get] . ' 23:59:59';
    } elseif ($campo_get === 'produto') {
      $filtros[] = "($colunas_db[0] LIKE :produto OR $colunas_db[1] LIKE :produto)";
      $parametros[":produto"] = "%" . $_GET[$campo_get] . "%";
    } else {
      $filtros[] = "$colunas_db LIKE :$campo_get";
      $parametros[":$campo_get"] = "%" . $_GET[$campo_get] . "%";
    }
  }
}

$sql_base = "FROM itens_pedido_amostra AS ipa 
             JOIN pedidos_amostra AS pa ON ipa.id_pedido_amostra = pa.id 
             JOIN cot_clientes AS cc ON pa.id_cliente = cc.id 
             JOIN cot_estoque AS ce ON ipa.id_produto = ce.id";
$where = $filtros ? "WHERE " . implode(" AND ", $filtros) : "";
$orderBy = " ORDER BY pa.data_pedido DESC, ce.produto ASC ";

$totalStmt = $pdo->prepare("SELECT COUNT(ipa.id) $sql_base $where");
$totalStmt->execute($parametros);
$totalResultados = $totalStmt->fetchColumn();

// ADICIONADO: ipa.custo_por_kg, pa.info_projeto, pa.etapa_projeto
$sql = "SELECT pa.id as pedido_id, pa.numero_referencia, pa.responsavel_pedido, pa.data_pedido, pa.info_projeto, pa.etapa_projeto,
               cc.razao_social, cc.cnpj,
               ce.produto AS nome_produto, ce.codigo AS codigo_produto, 
               ipa.quantidade, ce.unidade, ipa.fabricante, ipa.necessita_fracionamento, ipa.disponivel_estoque, ipa.custo_por_kg 
        $sql_base $where $orderBy LIMIT :limite OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($parametros as $key => &$value) {
  $stmt->bindParam($key, $value);
}
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalPaginas = $totalResultados > 0 ? ceil($totalResultados / $limite) : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Pesquisar Amostras</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    body {
      background-color: #f8f9fa;
    }

    .card-header {
      font-weight: bold;
      background-color: #fff;
    }

    .table td {
      vertical-align: middle;
    }

    .badge-plain {
      font-size: 0.85em;
      font-weight: normal;
      border: 1px solid #dee2e6;
      color: #6c757d;
      background-color: #fff;
    }
  </style>
</head>

<body>
  <div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h4 text-secondary">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-search me-2"
          viewBox="0 0 16 16">
          <path
            d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" />
        </svg>
        Histórico de Amostras
      </h2>
      <div>
        <a href="pesquisar_amostras.php" class="btn btn-outline-secondary me-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
            class="bi bi-eraser-fill me-1" viewBox="0 0 16 16">
            <path
              d="M8.086 2.207a2 2 0 0 1 2.828 0l3.879 3.879a2 2 0 0 1 0 2.828l-5.5 5.5A2 2 0 0 1 7.879 15H5.12a2 2 0 0 1-1.414-.586l-2.5-2.5a2 2 0 0 1 0-2.828l6.879-6.879zm.66 11.34L3.453 8.254 1.914 9.793a1 1 0 0 0 0 1.414l2.5 2.5a1 1 0 0 0 .707.293H7.88a1 1 0 0 0 .707-.293l.16-.16z" />
          </svg>
          Limpar Filtros
        </a>
        <a href="incluir_ped_amostras.php" class="btn btn-primary">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg me-1"
            viewBox="0 0 16 16">
            <path fill-rule="evenodd"
              d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z" />
          </svg>
          Nova Amostra
        </a>
      </div>
    </div>

    <!-- Drill Down Tip -->
    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
        class="bi bi-lightbulb-fill me-2" viewBox="0 0 16 16">
        <path
          d="M2 6a6 6 0 1 1 10.174 4.31c-.203.196-.359.4-.453.619l-.762 1.769A.5.5 0 0 1 10.5 13a.5.5 0 0 1 0 1 .5.5 0 0 1 0 1l-.224.447a1 1 0 0 1-.894.553H6.618a1 1 0 0 1-.894-.553L5.5 15a.5.5 0 0 1 0-1 .5.5 0 0 1 0-1 .5.5 0 0 1-.461-.796l-.761-1.77a1.964 1.964 0 0 0-.453-.618A5.984 5.984 0 0 1 2 6zm3 8.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1l-.224.447a1 1 0 0 1-.894.553H6.618a1 1 0 0 1-.894-.553L5.5 15a.5.5 0 0 1-.5-.5z" />
      </svg>
      <div>
        <strong>Dica:</strong> Clique no nome do <strong>Cliente</strong> ou do <strong>Produto</strong> na tabela para
        filtrar rapidamente os resultados.
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- Filtros Rápidos (Opcional - pode ser expandido depois) -->
    <div class="card mb-3 shadow-sm">
      <div class="card-body p-2">
        <form method="GET" class="row g-2 align-items-center">
          <div class="col-auto"><span class="fw-bold text-secondary">Filtrar:</span></div>
          <div class="col-auto"><input type="text" name="produto" class="form-control form-control-sm"
              placeholder="Produto..." value="<?= htmlspecialchars($_GET['produto'] ?? '') ?>"></div>
          <div class="col-auto"><input type="text" name="cliente_razao_social" class="form-control form-control-sm"
              placeholder="Cliente..." value="<?= htmlspecialchars($_GET['cliente_razao_social'] ?? '') ?>"></div>
          <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-secondary">Buscar</button></div>
          <div class="col-auto ms-auto"><small class="text-muted">Total: <strong><?= $totalResultados ?></strong>
              itens</small></div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
              <tr>
                <th class="text-center">Data</th>
                <th class="text-center">Ref. Pedido</th>
                <th>Cliente</th>
                <th>Produto</th>
                <th>Fabricante</th>
                <th class="text-center">Qtd. Solicitada</th>
                <th class="text-center">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultados): ?>
                <?php foreach ($resultados as $item): ?>
                  <tr>
                    <td class="text-center small text-muted"><?= date('d/m/Y', strtotime($item['data_pedido'])) ?></td>
                    <td class="text-center fw-bold text-primary"><?= htmlspecialchars($item['numero_referencia']) ?></td>
                    <td>
                      <a href="pesquisar_amostras.php?cliente_razao_social=<?= urlencode($item['razao_social']) ?>"
                        class="text-decoration-none text-dark" title="Filtrar por este cliente">
                        <?= htmlspecialchars($item['razao_social']) ?>
                      </a>
                    </td>
                    <td>
                      <a href="pesquisar_amostras.php?produto=<?= urlencode($item['nome_produto']) ?>"
                        class="text-decoration-none text-dark fw-bold" title="Filtrar por este produto">
                        <?= htmlspecialchars($item['nome_produto']) ?>
                      </a>
                    </td>
                    <td><?= htmlspecialchars($item['fabricante']) ?></td>
                    <td class="text-center">
                      <?php
                      // Exibição amigável
                      echo htmlspecialchars(number_format($item['quantidade'], 3, ',', '.'));
                      echo ' <small class="text-muted">' . htmlspecialchars($item['unidade']) . '</small>';
                      ?>
                    </td>
                    <td class="text-center text-nowrap">
                      <!-- PDF -->
                      <a href="gerar_pdf_amostra.php?id=<?= $item['pedido_id']; ?>" class="btn btn-sm btn-outline-secondary"
                        title="Baixar PDF" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                          class="bi bi-file-earmark-pdf" viewBox="0 0 16 16">
                          <path
                            d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z" />
                          <path
                            d="M4.603 14.087a.81.81 0 0 1-.438-.42c-.195-.388-.13-.776.08-1.102.198-.307.526-.568.897-.787a7.68 7.68 0 0 1 1.482-.645 19.697 19.697 0 0 0 1.062-2.227 7.269 7.269 0 0 1-.43-1.295c-.086-.4-.119-.796-.046-1.136.075-.354.274-.672.65-.823.192-.077.4-.12.602-.077a.7.7 0 0 1 .477.365c.088.164.12.356.127.538.007.188-.012.396-.047.614-.084.51-.27 1.134-.52 1.794a10.954 10.954 0 0 0 .98 1.686 5.753 5.753 0 0 1 1.334.05c.364.066.734.195.96.465.12.144.193.32.2.518.007.192-.047.382-.138.563a1.04 1.04 0 0 1-.354.416.856.856 0 0 1-.51.138c-.331-.014-.654-.196-.933-.417a5.712 5.712 0 0 1-.911-.95 11.651 11.651 0 0 0-1.997.406 11.307 11.307 0 0 1-1.02 1.51c-.292.35-.609.656-.927.787a.793.793 0 0 1-.58.029zm1.379-1.901c-.166.076-.32.156-.459.238-.328.194-.541.383-.647.545-.094.148-.096.35.04.535.071.088.163.135.291.145.24.012.56-.12.924-.465a6.002 6.002 0 0 1 .159-.16c.105-.32.239-.556.334-.741a6.6 6.6 0 0 1-.642-.092zm3.336-5.87c.18-.088.58-.327.972-.518-.009-.168-.052-.363-.153-.474-.114-.128-.352-.152-.511-.087-.146.06-.233.245-.262.395a1.2 1.2 0 0 0 .045.31c.029.135.071.268.12.4.037.07.086.134.108.19c.174-.216.354-.504.479-.905.12-.397.168-.78.146-1.109zm-.068 7.377c.307.088.636.166.953.218.42.062.83.058 1.135-.05.186-.067.33-.207.35-.41.006-.062-.008-.124-.038-.178-.063-.112-.206-.188-.415-.195-.536-.017-1.12.28-1.57.54-.15.088-.307.188-.415.275zm-.21-4.273c.277-.665.485-1.284.58-1.748.016-.08.026-.156.028-.225-.002-.02-.007-.038-.014-.048a.185.185 0 0 0-.109-.045.24.24 0 0 0-.11.037.47.47 0 0 0-.17.202c-.066.143-.098.376-.08.675.02.268.082.528.163.77.106.31.22.602.321.847zm-1.895 2.153c-.322.617-.552 1.178-.655 1.543-.024.083-.025.132-.016.148.016.026.082.02.13.003h.001c.205-.08.384-.33.513-.762.06-.197.094-.413.084-.633-.004-.083-.016-.164-.037-.238a8.55 8.55 0 0 1-.02-.262z" />
                        </svg>
                      </a>

                      <!-- Excluir -->
                      <a href="excluir_amostra.php?id=<?= $item['pedido_id']; ?>" class="btn btn-sm btn-outline-danger"
                        title="Excluir" onclick="return confirm('Tem certeza que deseja excluir esta solicitação?');">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                          class="bi bi-trash" viewBox="0 0 16 16">
                          <path
                            d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6Z" />
                          <path
                            d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1ZM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118ZM2.5 3h11V2h-11v1Z" />
                        </svg>
                      </a>
                      <!-- Detalhes -->
                      <button type="button" class="btn btn-sm btn-outline-primary" title="Detalhes"
                        onclick='abrirModalDetalhes(<?= json_encode($item) ?>)'>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye"
                          viewBox="0 0 16 16">
                          <path
                            d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z" />
                          <path
                            d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z" />
                        </svg>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">Nenhum item encontrado para os filtros aplicados.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php if ($totalPaginas > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <?php if ($pagina > 1): ?>
            <li class="page-item"><a class="page-link"
                href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">Anterior</a></li>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <li class="page-item <?= ($pagina == $i) ? 'active' : '' ?>"><a class="page-link"
                href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a></li><?php endfor; ?>
          <?php if ($pagina < $totalPaginas): ?>
            <li class="page-item"><a class="page-link"
                href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">Próxima</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>

  <!-- Modal Detalhes -->
  <div class="modal fade" id="modalDetalhesAmostra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalhes da Amostra</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="fw-bold">Produto:</label>
              <p id="detProduto" class="text-muted mb-0">--</p>
            </div>
            <div class="col-md-6 mb-3">
              <label class="fw-bold">Código:</label>
              <p id="detCodigo" class="text-muted mb-0">--</p>
            </div>
            <div class="col-md-6 mb-3">
              <label class="fw-bold">Fabricante:</label>
              <p id="detFabricante" class="text-muted mb-0">--</p>
            </div>
            <div class="col-md-6 mb-3">
              <label class="fw-bold">Quantidade:</label>
              <p id="detQuantidade" class="text-muted mb-0">--</p>
            </div>
            <div class="col-md-6 mb-3">
              <label class="fw-bold">Custo Unitário (Estimado):</label>
              <p id="detCusto" class="text-muted mb-0">--</p>
            </div>
            <div class="col-md-6 mb-3">
              <label class="fw-bold">Subtotal (USD):</label>
              <p id="detSubtotal" class="fw-bold text-success mb-0">--</p>
            </div>
            <div class="col-md-12">
              <hr>
            </div>
            <div class="col-md-6 mb-3">
              <label class="fw-bold">Disponível em Estoque?</label>
              <p id="detEstoque" class="mb-0">--</p>
            </div>
            <div class="col-md-6 mb-3">
              <label class="fw-bold">Necessita Fracionamento?</label>
              <p id="detFracionamento" class="mb-0">--</p>
            </div>
            <div class="col-md-12">
              <hr>
            </div>
            <div class="col-md-12 mb-3">
              <h6 class="text-secondary">Informações do Projeto</h6>
              <label class="fw-bold small">Projeto:</label>
              <p id="detInfoProjeto" class="text-muted small">--</p>
              <label class="fw-bold small">Etapa:</label>
              <p id="detEtapa" class="text-muted small">--</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function abrirModalDetalhes(item) {
      document.getElementById('detProduto').textContent = item.nome_produto || '-';
      document.getElementById('detCodigo').textContent = item.codigo_produto || '-';
      document.getElementById('detFabricante').textContent = item.fabricante || '-';
      document.getElementById('detQuantidade').textContent = parseFloat(item.quantidade).toFixed(3).replace('.', ',') + ' ' + (item.unidade || '');

      // Custo e Subtotal
      const custo = parseFloat(item.custo_por_kg || 0);
      const qtd = parseFloat(item.quantidade || 0);
      const total = custo * qtd;

      document.getElementById('detCusto').textContent = 'USD ' + custo.toFixed(2).replace('.', ',');
      document.getElementById('detSubtotal').textContent = 'USD ' + total.toFixed(2).replace('.', ',');

      // Badges
      document.getElementById('detEstoque').innerHTML = item.disponivel_estoque === 'SIM'
        ? '<span class="badge bg-success">SIM</span>'
        : '<span class="badge bg-danger">NÃO</span>';

      document.getElementById('detFracionamento').innerHTML = item.necessita_fracionamento === 'SIM'
        ? '<span class="badge bg-warning text-dark">SIM</span>'
        : '<span class="badge bg-secondary">NÃO</span>';

      document.getElementById('detInfoProjeto').textContent = item.info_projeto || 'Não informado';
      document.getElementById('detEtapa').textContent = item.etapa_projeto || 'Não informado';

      var modal = new bootstrap.Modal(document.getElementById('modalDetalhesAmostra'));
      modal.show();
    }
  </script>

</body>

</html>