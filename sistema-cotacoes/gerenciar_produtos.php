<?php
session_start();
$pagina_ativa = 'incluir_produto'; // Mantém ativo o menu 'Produtos'
require_once 'header.php';
require_once 'conexao.php';

// Busca produtos (com filtro opcional)
$termo = $_GET['q'] ?? '';
$where = "WHERE ativo = 1"; // Filtro padrão: apenas ativos
$params = [];

if (!empty($termo)) {
    $where .= " AND (codigo LIKE :termo OR produto LIKE :termo OR ncm LIKE :termo)";
    $params[':termo'] = "%$termo%";
}

try {
    $sql = "SELECT * FROM cot_estoque $where ORDER BY produto ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erro = "Erro ao buscar produtos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-boxes me-2"></i>Gerenciar Produtos</h2>
            <div>
                <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal"
                    data-bs-target="#modalImportar">
                    <i class="fas fa-file-import me-1"></i> Importar Estoque
                </button>
                <a href="incluir_produto.php" class="btn btn-success"><i class="fas fa-plus me-1"></i> Novo Produto</a>
            </div>
        </div>

        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Operação realizada com sucesso!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['erro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['erro']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-10">
                        <input type="text" name="q" class="form-control"
                            placeholder="Pesquisar por Código, Nome ou NCM..." value="<?= htmlspecialchars($termo) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>
                            Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive bg-white rounded shadow-sm">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Produto</th>
                        <th>UN</th>
                        <th>NCM</th>
                        <th>Origem</th>
                        <th>IPI (%)</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($produtos)): ?>
                        <?php foreach ($produtos as $p): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($p['codigo']) ?>
                                </td>
                                <td class="fw-bold text-success">
                                    <?= htmlspecialchars($p['produto']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($p['unidade']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($p['ncm']) ?>
                                </td>
                                <td>
                                    <?php
                                    $origem_map = [0 => 'Nacional', 1 => 'Importado', 6 => 'Importado (CAMEX)'];
                                    echo htmlspecialchars($origem_map[$p['origem']] ?? $p['origem']);
                                    ?>
                                </td>
                                <td>
                                    <?= number_format($p['ipi'], 2, ',', '.') ?>%
                                </td>
                                <td class="text-end">
                                    <a href="editar_produto.php?codigo=<?= urlencode($p['codigo']) ?>"
                                        class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Excluir"
                                        onclick="confirmarExclusao('<?= htmlspecialchars($p['codigo']) ?>', '<?= htmlspecialchars(addslashes($p['produto'])) ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Nenhum produto encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Importar -->
    <div class="modal fade" id="modalImportar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Importar Estoque (CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form action="processar_upload_produtos.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Atenção:</strong> Esta ação irá <strong>INATIVAR</strong> todos os produtos atuais e
                            ativar
                            apenas os listados no arquivo. Os códigos existentes serão atualizados.
                        </div>
                        <div class="mb-3">
                            <label for="arquivo_csv" class="form-label">Selecione o arquivo CSV</label>
                            <input type="file" class="form-control" id="arquivo_csv" name="arquivo_csv" accept=".csv"
                                required>
                        </div>
                        <div class="mb-3">
                            <a href="template_produtos_estoque.csv" class="small text-decoration-none" download>
                                <i class="fas fa-download me-1"></i> Baixar modelo (template_produtos_estoque.csv)
                            </a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Processar Importação</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmarExclusao(codigo, nome) {
            if (confirm(`Tem certeza que deseja excluir o produto "${nome}"?\nCÓDIGO: ${codigo}\n\nEsta ação não pode ser desfeita.`)) {
                window.location.href = `excluir_produto.php?codigo=${encodeURIComponent(codigo)}`;
            }
        }
    </script>

    <!-- Scripts Bootstap (caso header não inclua footer com scripts) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>