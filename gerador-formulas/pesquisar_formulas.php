<?php
// CORREÇÃO: A conexão com o banco DEVE vir primeiro.
session_start();
require_once 'config.php';
require_once 'header.php';

// O resto do seu código PHP que usa a conexão
$categorias = [];
$result = $conn->query("SELECT DISTINCT categoria FROM formulacoes ORDER BY categoria");

if (!$result) {
    die("Erro na consulta de categorias: " . $conn->error);
}

while ($row = $result->fetch_assoc()) {
    $categorias[] = $row['categoria'];
}
?>
<div class="container my-5">
    <h1 class="mb-4">Pesquisar Formulações</h1>

    <div id="alert-placeholder"></div>

    <div class="card shadow-sm mb-5">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-filter-right"></i> Filtros de Pesquisa</h5>
        </div>
        <div class="card-body">
            <form id="form-pesquisa" action="api_buscar_formulas.php" method="get">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><label for="nome_formula" class="form-label">Nome da Fórmula</label><input
                            type="text" name="nome_formula" id="nome_formula" class="form-control"
                            placeholder="Digite o nome..."></div>
                    <div class="col-md-4"><label for="ativo" class="form-label">Ativo em Destaque</label><input
                            type="text" name="ativo" id="ativo" class="form-control" placeholder="Digite o ativo...">
                    </div>
                    <div class="col-md-4"><label for="codigo" class="form-label">Código (Novo ou Antigo)</label><input
                            type="text" name="codigo" id="codigo" class="form-control" placeholder="Digite o código...">
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="categoria" class="form-label">Categoria</label>
                        <select name="categoria" id="categoria" class="form-select">
                            <option value="">Todas as Categorias</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= htmlspecialchars($categoria) ?>"><?= htmlspecialchars($categoria) ?>
                                </option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="button" id="btn_pesquisa_avancada" class="btn btn-outline-secondary btn-sm mt-4"><i
                        class="bi bi-sliders"></i> Pesquisa Avançada</button>
                <div id="pesquisa_avancada" class="mt-3 p-3 border rounded bg-light" style="display: none;">
                    <div class="row g-3">
                        <div class="col-md-4"><label for="filtro_avancado" class="form-label">Pesquisar
                                por:</label><select name="filtro_avancado" id="filtro_avancado" class="form-select">
                                <option value="inci_name">INCI Name</option>
                                <option value="materia_prima">Matéria-prima</option>
                            </select></div>
                        <div class="col-md-5"><label for="termo_avancado" class="form-label">Termo
                                Avançado:</label><input type="text" name="termo_avancado" id="termo_avancado"
                                class="form-control" placeholder="Digite o termo..."></div>
                        <div class="col-md-3"><label for="data" class="form-label">Data de Criação:</label><input
                                type="date" name="data" id="data" class="form-control"></div>
                    </div>
                </div>
                <div class="text-end mt-4">
                    <button type="button" id="btn-limpar" class="btn btn-secondary">Limpar Filtros</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Pesquisar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-list-ul"></i> Resultados</h5>
        </div>
        <div class="card-body" id="area-resultados">
            <div class="alert alert-info">Utilize os filtros acima para iniciar uma pesquisa.</div>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">Tem certeza que deseja excluir esta formulação?</div>
            <div class="modal-footer">
                <form id="form-delete" method="post" action="deletar_formula.php">
                    <input type="hidden" id="delete_id" name="id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-confirm-delete" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Gera o timestamp de modificação deste arquivo específico
$timestamp_versao = date("H:i d/m/Y", filemtime(basename(__FILE__)));
// Inclui o rodapé
require_once 'footer.php';
?>