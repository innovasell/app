<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
$pagina_ativa = 'gerenciar_fornecedores';

require_once 'header.php';
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}

// Paginação
$por_pagina = 20;
$pagina_atual = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $por_pagina;

// Busca
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$where = '';
$params = [];

if (!empty($busca)) {
    $where = " WHERE nome LIKE :busca OR pais LIKE :busca OR contato LIKE :busca";
    $params[':busca'] = "%$busca%";
}

// Contar total
$sqlCount = "SELECT COUNT(*) FROM cot_fornecedores" . $where;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total_registros = $stmtCount->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);

// Buscar fornecedores
$sql = "SELECT * FROM cot_fornecedores" . $where . " ORDER BY nome ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Fornecedores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .table-hover tbody tr:hover {
            background-color: rgba(64, 136, 60, 0.05);
        }

        .badge-status {
            padding: 0.35em 0.65em;
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-building me-2"></i>Gerenciar Fornecedores</h2>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalFornecedor"
                onclick="novoFornecedor()">
                <i class="fas fa-plus me-1"></i> Novo Fornecedor
            </button>
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
                            placeholder="Buscar por nome, país ou contato..." value="<?= htmlspecialchars($busca) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>
                            Buscar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabela -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($fornecedores)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?= empty($busca) ? 'Nenhum fornecedor cadastrado.' : 'Nenhum fornecedor encontrado com os critérios de busca.' ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome</th>
                                    <th>País</th>
                                    <th>Contato</th>
                                    <th>Email</th>
                                    <th>Telefone</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fornecedores as $f): ?>
                                    <tr>
                                        <td><strong>
                                                <?= htmlspecialchars($f['nome']) ?>
                                            </strong></td>
                                        <td>
                                            <?= htmlspecialchars($f['pais'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($f['contato'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($f['email'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($f['telefone'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <?php if ($f['ativo']): ?>
                                                <span class="badge bg-success badge-status">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary badge-status">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-primary"
                                                onclick='editarFornecedor(<?= json_encode($f) ?>)'>
                                                <i class="fas fa-edit"></i>
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

    <!-- Modal Fornecedor -->
    <div class="modal fade" id="modalFornecedor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Novo Fornecedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="formFornecedor" method="POST" action="salvar_fornecedor.php">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="fornecedor_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" name="nome" id="fornecedor_nome" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">País</label>
                                <input type="text" name="pais" id="fornecedor_pais" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contato</label>
                                <input type="text" name="contato" id="fornecedor_contato" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" id="fornecedor_email" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="telefone" id="fornecedor_telefone" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="ativo" id="fornecedor_ativo" class="form-select">
                                    <option value="1">Ativo</option>
                                    <option value="0">Inativo</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" id="fornecedor_observacoes" class="form-control"
                                    rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function novoFornecedor() {
            document.getElementById('modalTitle').textContent = 'Novo Fornecedor';
            document.getElementById('formFornecedor').reset();
            document.getElementById('fornecedor_id').value = '';
        }

        function editarFornecedor(fornecedor) {
            document.getElementById('modalTitle').textContent = 'Editar Fornecedor';
            document.getElementById('fornecedor_id').value = fornecedor.id;
            document.getElementById('fornecedor_nome').value = fornecedor.nome;
            document.getElementById('fornecedor_pais').value = fornecedor.pais || '';
            document.getElementById('fornecedor_contato').value = fornecedor.contato || '';
            document.getElementById('fornecedor_email').value = fornecedor.email || '';
            document.getElementById('fornecedor_telefone').value = fornecedor.telefone || '';
            document.getElementById('fornecedor_ativo').value = fornecedor.ativo;
            document.getElementById('fornecedor_observacoes').value = fornecedor.observacoes || '';

            const modal = new bootstrap.Modal(document.getElementById('modalFornecedor'));
            modal.show();
        }
    </script>
</body>

</html>