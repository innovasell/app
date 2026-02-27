<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
$pagina_ativa = 'consultar_cenarios';

require_once 'header.php';
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}

// Paginação
$por_pagina = 15;
$pagina_atual = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $por_pagina;

// Filtros
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$where_conditions = [];
$params = [];

if (!empty($busca)) {
    $where_conditions[] = "(cliente LIKE :busca OR fornecedor LIKE :busca OR num_cenario LIKE :busca)";
    $params[':busca'] = "%$busca%";
}

$where = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';

// Contar total
$sqlCount = "SELECT COUNT(*) FROM cot_cenarios_importacao" . $where;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total_registros = $stmtCount->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);

// Buscar cenários
// Buscar cenários
// Alterado para buscar a lista concatenada de clientes via JOIN
$sql = "SELECT c.*, 
        GROUP_CONCAT(DISTINCT cli.nome SEPARATOR ', ') as clientes_nomes
        FROM cot_cenarios_importacao c
        LEFT JOIN cot_cenarios_itens i ON c.num_cenario = i.num_cenario
        LEFT JOIN cot_clientes cli ON i.id_cliente = cli.id
        " . $where . " 
        GROUP BY c.num_cenario
        ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$cenarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Cenários de Importação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .table-hover tbody tr:hover {
            background-color: rgba(64, 136, 60, 0.05);
        }

        .badge-custom {
            padding: 0.35em 0.65em;
            font-size: 0.85em;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-list me-2"></i>Cenários de Importação</h2>
            <a href="incluir_cenario_importacao.php" class="btn btn-success">
                <i class="fas fa-plus me-1"></i> Novo Cenário
            </a>
        </div>

        <?php if (isset($_SESSION['mensagem'])): ?>
            <div class="alert alert-<?= $_SESSION['tipo_mensagem'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['mensagem']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
            <?php
            unset($_SESSION['mensagem']);
            unset($_SESSION['tipo_mensagem']);
            ?>
        <?php endif; ?>

        <!-- Busca -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" name="busca" class="form-control"
                            placeholder="Buscar por cliente, fornecedor ou número do cenário..."
                            value="<?= htmlspecialchars($busca) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Buscar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabela -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($cenarios)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?= empty($busca) ? 'Nenhum cenário cadastrado.' : 'Nenhum cenário encontrado com os critérios de busca.' ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Nº Cenário</th>
                                    <th>Fornecedor</th>
                                    <th>Cliente(s)</th>
                                    <th>Data</th>
                                    <th>Criado por</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cenarios as $c): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($c['num_cenario']) ?></strong>
                                            <?php if (!empty($c['oc_gerada'])): ?>
                                                <span class="badge bg-success badge-custom ms-2">OC GERADA</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($c['fornecedor']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($c['clientes_nomes'] ?? $c['cliente']) ?>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y', strtotime($c['data_criacao'])) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($c['criado_por']) ?>
                                        </td>
                                        <!-- Removido Spec Ex -->

                                        <td class="text-center">
                                            <a href="editar_cenario.php?num=<?= urlencode($c['num_cenario']) ?>"
                                                class="btn btn-sm btn-warning" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="gerar_pdf_cenario.php?num=<?= urlencode($c['num_cenario']) ?>"
                                                class="btn btn-sm btn-danger" target="_blank" title="Gerar PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                            <button class="btn btn-sm btn-info"
                                                onclick="verDetalhes('<?= htmlspecialchars($c['num_cenario']) ?>')"
                                                title="Ver Detalhes">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <?php if (empty($c['oc_gerada'])): ?>
                                                <button class="btn btn-sm btn-success"
                                                    onclick="aprovarCenario('<?= htmlspecialchars($c['num_cenario']) ?>')"
                                                    title="Aprovar Cenário (Gerar OC)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php else: ?>
                                                <a href="gerar_pdf_oc.php?num_cenario=<?= urlencode($c['num_cenario']) ?>"
                                                    class="btn btn-sm btn-primary" target="_blank" title="Ver OC">
                                                    <i class="fas fa-file-invoice-dollar"></i>
                                                </a>
                                            <?php endif; ?>

                                            <button class="btn btn-sm btn-outline-danger"
                                                onclick="excluirCenario('<?= htmlspecialchars($c['num_cenario']) ?>')"
                                                title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <?php if ($total_paginas > 1): ?>
                        <nav aria-label="Navegação de página">
                            <ul class="pagination justify-content-center mt-3">
                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <li class="page-item <?= $i == $pagina_atual ? 'active' : '' ?>">
                                        <a class="page-link"
                                            href="?pagina=<?= $i ?><?= !empty($busca) ? '&busca=' . urlencode($busca) : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Detalhes -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Cenário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="modalDetalhesBody">
                    <div class="text-center">
                        <div class="spinner-border" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verDetalhes(numCenario) {
            const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
            const body = document.getElementById('modalDetalhesBody');
            body.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
            modal.show();

            fetch(`detalhes_cenario.php?num=${encodeURIComponent(numCenario)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.erro) {
                        body.innerHTML = `<div class="alert alert-danger">${data.erro}</div>`;
                        return;
                    }

                    let html = `
            <h6 class="border-bottom pb-2 mb-3">Informações Gerais</h6>
            <div class="row mb-3">
              <div class="col-md-6"><strong>Fornecedor:</strong> ${data.cabecalho.fornecedor}</div>
              <div class="col-md-6"><strong>Cliente:</strong> ${data.cabecalho.cliente} - ${data.cabecalho.uf}</div>
              <div class="col-md-4"><strong>Dólar Compra:</strong> ${data.cabecalho.dolar_compra}</div>
              <div class="col-md-4"><strong>Dólar Venda:</strong> ${data.cabecalho.dolar_venda}</div>
              <div class="col-md-4"><strong>Taxa Juros:</strong> ${data.cabecalho.taxa_juros_mensal}%</div>
              <div class="col-md-4"><strong>Tempo Venda:</strong> ${data.cabecalho.tempo_venda_meses == 0 ? 'IMEDIATO' : data.cabecalho.tempo_venda_meses + ' meses'}</div>
            </div>

            <h6 class="border-bottom pb-2 mb-3 mt-4">Produtos</h6>
            <div class="table-responsive">
              <table class="table table-sm table-bordered">
                <thead class="table-light">
                  <tr>
                    <th>Produto</th>
                    <th>QTD</th>
                    <th>Unidade</th>
                    <th>Landed</th>
                    <th>Total Landed</th>
                    <th>VF</th>
                    <th>Preço Venda</th>
                    <th>Total Venda</th>
                    <th>GM%</th>
                  </tr>
                </thead>
                <tbody>`;

                    data.itens.forEach(item => {
                        html += `
              <tr>
                <td>${item.produto} <small class="text-muted">(${item.embalagem || ''} ${item.unidade})</small></td>
                <td>${item.qtd}</td>
                <td>${item.unidade}</td>
                <td>$${item.landed_usd_kg}</td>
                <td>$${item.total_landed_usd}</td>
                <td>$${item.valor_futuro}</td>
                <td>$${item.preco_unit_venda_usd_kg}</td>
                <td>$${item.total_venda_usd}</td>
                <td>${item.gm_percentual}%</td>
              </tr>`;
                    });

                    html += `</tbody></table></div>`;
                    body.innerHTML = html;
                })
                .catch(err => {
                    console.error('Erro:', err);
                    body.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes.</div>';
                });
        }

        function excluirCenario(numCenario) {
            if (!confirm('Tem certeza que deseja excluir este cenário? Esta ação não pode ser desfeita.')) {
                return;
            }

            fetch(`excluir_cenario.php?num=${encodeURIComponent(numCenario)}`, {
                method: 'POST'
            })
                .then(res => res.json())
                .then(data => {
                    if (data.sucesso) {
                        alert('Cenário excluído com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao excluir cenário: ' + data.erro);
                    }
                })
                .catch(err => {
                    console.error('Erro:', err);
                    alert('Erro ao excluir cenário.');
                });
        }

        function aprovarCenario(numCenario) {
            if (!confirm('Deseja Aprovar este cenário? Isso irá gerar uma Ordem de Compra para o fornecedor.')) {
                return;
            }

            fetch('gerar_oc.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ num_scenario: numCenario })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.sucesso) {
                        alert(data.mensagem);
                        location.reload();
                    } else {
                        alert('Erro: ' + data.erro);
                    }
                })
                .catch(err => {
                    console.error('Erro:', err);
                    alert('Erro ao gerar Ordem de Compra.');
                });
        }
    </script>
</body>

</html>