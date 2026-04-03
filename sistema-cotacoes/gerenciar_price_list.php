<?php
session_start();
$pagina_ativa = 'gerenciar_price_list';
require_once 'conexao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Price List - H Hansen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background-color: #f0f4f8; font-family: 'Montserrat', 'Segoe UI', sans-serif; }
        .thead-dark th { background-color: #0a1e42; color: #fff; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.4px; border: none; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card-header-custom { background: linear-gradient(135deg, #0a1e42 0%, #0047fa 100%); color: #fff; border-radius: 12px 12px 0 0; padding: 16px 20px; }
        .btn-success-custom { background-color: #40883c; border-color: #40883c; color: #fff; }
        .btn-success-custom:hover { background-color: #2c5e29; border-color: #2c5e29; color: #fff; }
        #alertBox { display: none; }
        .stat-badge { font-size: 0.85rem; padding: 6px 14px; border-radius: 8px; }
    </style>
</head>
<body>

    <?php require_once 'header.php'; ?>

    <div class="container-fluid px-4 py-3">

    <!-- Alertas -->
    <div id="alertBox" class="alert mb-3" role="alert"></div>

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>Importação realizada com sucesso! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['sucesso_add'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>Item adicionado com sucesso! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['sucesso_massa'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-layer-group me-2"></i><strong><?= (int)$_GET['sucesso_massa'] ?> itens</strong> adicionados em massa com sucesso! A price list anterior foi mantida. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle me-2"></i><?= htmlspecialchars($_GET['erro']) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Linha de ações e estatísticas -->
    <div class="row mb-3 align-items-center">
        <div class="col-auto">
            <h4 class="fw-bold mb-0 text-dark"><i class="fas fa-tags me-2 text-success"></i>Gerenciar Price List</h4>
        </div>
        <div class="col">
            <?php
            try {
                $total      = $pdo->query("SELECT COUNT(*) FROM cot_price_list")->fetchColumn();
                $fabricantes = $pdo->query("SELECT COUNT(DISTINCT fabricante) FROM cot_price_list")->fetchColumn();
                $codigos    = $pdo->query("SELECT COUNT(DISTINCT codigo) FROM cot_price_list")->fetchColumn();
                $lastUpload = file_exists('last_upload_price_list.txt') ? file_get_contents('last_upload_price_list.txt') : '—';
            } catch (Exception $e) { $total = $fabricantes = $codigos = 0; $lastUpload = '—'; }
            ?>
            <span class="badge bg-primary stat-badge me-1"><i class="fas fa-box me-1"></i><?= number_format($total) ?> itens</span>
            <span class="badge bg-secondary stat-badge me-1"><i class="fas fa-industry me-1"></i><?= $fabricantes ?> fabricantes</span>
            <span class="badge bg-info text-dark stat-badge me-1"><i class="fas fa-barcode me-1"></i><?= $codigos ?> códigos únicos</span>
            <span class="badge bg-light text-muted stat-badge border"><i class="fas fa-clock me-1"></i>Atualizado: <?= htmlspecialchars($lastUpload) ?></span>
        </div>
        <div class="col-auto d-flex gap-2">
            <a href="download_template_price_list.php" class="btn btn-outline-success btn-sm">
                <i class="fas fa-file-csv me-1"></i> Modelo CSV.
            </a>
            <a href="exportar_price_list.php" class="btn btn-outline-info btn-sm text-dark border-info">
                <i class="fas fa-download me-1"></i> Exportar CSV
            </a>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdd">
                <i class="fas fa-plus me-1"></i> Novo Item
            </button>
            <button class="btn btn-sm btn-outline-warning fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddMassa">
                <i class="fas fa-layer-group me-1"></i> Adição em Massa
            </button>
            <button class="btn btn-sm btn-success-custom" data-bs-toggle="modal" data-bs-target="#modalImport">
                <i class="fas fa-file-import me-1"></i> Importar CSV
            </button>
        </div>
    </div>

    <!-- Tabela CRUD -->
    <div class="card">
        <div class="card-body p-0">
            <div class="d-flex flex-wrap gap-3 align-items-center px-3 pt-3 pb-2">
                <div class="flex-grow-1">
                    <input type="text" id="buscaGeral" class="form-control form-control-sm" placeholder="🔍  Buscar por código, produto, fabricante ou embalagem..." style="max-width:440px;">
                </div>
                <select id="filtroFabricante" class="form-select form-select-sm" style="max-width:200px;">
                    <option value="">Todos os Fabricantes</option>
                    <?php
                    $fabs = $pdo->query("SELECT DISTINCT fabricante FROM cot_price_list ORDER BY fabricante")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($fabs as $f) echo '<option value="'.htmlspecialchars($f).'">'.htmlspecialchars($f).'</option>';
                    ?>
                </select>
                <select id="filtroFracionado" class="form-select form-select-sm" style="max-width:140px;">
                    <option value="">Fracionado?</option>
                    <option value="Sim">Sim</option>
                    <option value="Não">Não</option>
                </select>
            </div>
            <div class="table-responsive">
                <table id="tblPriceList" class="table table-hover align-middle mb-0" style="font-size:0.82rem;">
                    <thead>
                        <tr class="thead-dark">
                            <th>#</th>
                            <th>Código</th>
                            <th>Produto</th>
                            <th>Fabricante</th>
                            <th>Classificação</th>
                            <th>Embalagem</th>
                            <th>Fracionado</th>
                            <th class="text-end">Preço Net USD</th>
                            <th>Lead Time</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyPL">
                        <tr><td colspan="10" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL: Adicionar ─────────────────────────────────── -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#0a1e42,#0047fa);color:#fff;">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Novo Item na Price List</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-bold">Fabricante</label><input type="text" class="form-control" id="addFabricante" required></div>
                    <div class="col-md-6"><label class="form-label fw-bold">Classificação</label><input type="text" class="form-control" id="addClassificacao"></div>
                    <div class="col-md-4"><label class="form-label fw-bold">Código</label><input type="text" class="form-control" id="addCodigo"></div>
                    <div class="col-md-8"><label class="form-label fw-bold">Produto</label><input type="text" class="form-control" id="addProduto" required></div>
                    <div class="col-md-4"><label class="form-label fw-bold">Embalagem <small class="text-muted">(ex: 1 KG)</small></label><input type="text" class="form-control" id="addEmbalagem" placeholder="1 KG"></div>
                    <div class="col-md-4"><label class="form-label fw-bold">Preço Net USD</label><input type="number" step="0.0001" class="form-control" id="addPreco"></div>
                    <div class="col-md-4"><label class="form-label fw-bold">Fracionado</label>
                        <select class="form-select" id="addFracionado"><option value="Não">Não</option><option value="Sim">Sim</option></select>
                    </div>
                    <div class="col-12"><label class="form-label fw-bold">Lead Time</label><input type="text" class="form-control" id="addLeadTime"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success-custom" onclick="salvarItem()"><i class="fas fa-save me-1"></i>Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL: Editar ────────────────────────────────────── -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-pencil-alt me-2"></i>Editar Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-bold">Fabricante</label><input type="text" class="form-control" id="editFabricante"></div>
                    <div class="col-md-6"><label class="form-label fw-bold">Classificação</label><input type="text" class="form-control" id="editClassificacao"></div>
                    <div class="col-md-4"><label class="form-label fw-bold">Código</label><input type="text" class="form-control" id="editCodigo"></div>
                    <div class="col-md-8"><label class="form-label fw-bold">Produto</label><input type="text" class="form-control" id="editProduto"></div>
                    <div class="col-md-4"><label class="form-label fw-bold">Embalagem</label><input type="text" class="form-control" id="editEmbalagem"></div>
                    <div class="col-md-4"><label class="form-label fw-bold">Preço Net USD</label><input type="number" step="0.0001" class="form-control" id="editPreco"></div>
                    <div class="col-md-4"><label class="form-label fw-bold">Fracionado</label>
                        <select class="form-select" id="editFracionado"><option value="Não">Não</option><option value="Sim">Sim</option></select>
                    </div>
                    <div class="col-12"><label class="form-label fw-bold">Lead Time</label><input type="text" class="form-control" id="editLeadTime"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger me-auto" onclick="deletarItem()"><i class="fas fa-trash me-1"></i>Excluir</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning" onclick="atualizarItem()"><i class="fas fa-save me-1"></i>Atualizar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL: Adição em Massa ────────────────────────────── -->
<div class="modal fade" id="modalAddMassa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#e67e00,#f59f00);color:#fff;">
                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>Adição em Massa (CSV)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="adicionar_massa_price_list.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Os itens do CSV serão <strong>adicionados</strong> à price list atual — nenhum item existente será removido.
                    </div>
                    <div class="alert alert-light border py-2 small mb-3">
                        <i class="fas fa-table me-1 text-muted"></i>
                        Use o mesmo modelo do CSV padrão, separado por <strong>ponto e vírgula (;)</strong>.
                        <a href="download_template_price_list.php" class="ms-1 text-success fw-bold"><i class="fas fa-download me-1"></i>Baixar modelo</a>
                    </div>
                    <label class="form-label fw-bold">Arquivo CSV</label>
                    <input class="form-control" type="file" name="arquivo_csv_massa" accept=".csv" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn fw-bold text-white" style="background:#e67e00;border-color:#e67e00;"
                            onclick="return confirm('Confirma a adição em massa? Os itens existentes NÃO serão apagados.')">
                        <i class="fas fa-layer-group me-1"></i>Adicionar em Massa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── MODAL: Importar CSV ──────────────────────────────── -->
<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-import me-2"></i>Importar Price List (CSV)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="importar_price_list.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Atenção:</strong> A importação <strong>substitui todos os dados</strong> da lista atual.
                        Use o arquivo separado por <strong>ponto e vírgula (;)</strong>.
                    </div>
                    <label class="form-label fw-bold">Arquivo CSV</label>
                    <input class="form-control" type="file" name="arquivo_csv" accept=".csv" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" onclick="return confirm('Confirma a substituição da price list atual?')">
                        <i class="fas fa-upload me-1"></i>Importar e Substituir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
let allItems = [];
let dtTable  = null;
const modalAdd  = new bootstrap.Modal(document.getElementById('modalAdd'));
const modalEdit = new bootstrap.Modal(document.getElementById('modalEdit'));

// ── Carrega dados via API ──────────────────────────────────
async function carregarDados() {
    try {
        const res  = await fetch('api/price_list_crud.php?action=list');
        const data = await res.json();
        if (!data.success) { showAlert('danger', data.message); return; }
        allItems = data.data;
        aplicarFiltros();
    } catch(e) {
        showAlert('danger', 'Erro ao carregar dados: ' + e.message);
    }
}

// ── Renderiza tabela ───────────────────────────────────────
function renderTabela(data) {
    const fmtUSD = v => parseFloat(v||0).toLocaleString('en-US', {minimumFractionDigits:4});

    if (!dtTable) {
        dtTable = $('#tblPriceList').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
            order: [[1,'asc']],
            pageLength: 50,
            data: data,
            columns: [
                { data: 'id', class: 'text-muted small' },
                { data: 'codigo', render: v => `<b>${v||'—'}</b>` },
                { data: 'produto', render: v => `<span style="max-width:280px;display:inline-block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${v||''}">${(v||'').substring(0,60)}</span>` },
                { data: 'fabricante', render: v => v||'—' },
                { data: 'classificacao', render: v => `<span class="badge bg-light text-dark border">${v||'—'}</span>` },
                { data: 'embalagem', render: v => `<code>${v||'—'}</code>` },
                { data: 'fracionado', render: v => `<span class="badge ${v==='Sim'?'bg-success':'bg-secondary'}">${v||'—'}</span>` },
                { data: 'preco_net_usd', class: 'text-end fw-bold', render: v => `$ ${fmtUSD(v)}` },
                { data: 'lead_time', class: 'text-muted small', render: v => v||'—' },
                { 
                    data: null, 
                    class: 'text-center',
                    orderable: false,
                    render: function(data, type, row) {
                        return `<button class="btn btn-sm btn-outline-warning btn-editar" data-id="${row.id}" title="Editar"><i class="fas fa-pencil-alt"></i></button>`;
                    }
                }
            ]
        });

        // Delegate bind para funcionar independente da paginação
        $('#tblPriceList tbody').on('click', '.btn-editar', function() {
            const id = $(this).attr('data-id');
            abrirEdicao(id);
        });
    } else {
        dtTable.clear();
        dtTable.rows.add(data);
        dtTable.draw(false);
    }
}

// ── Filtros externos (fabricante, fracionado, busca) ───────
function aplicarFiltros() {
    const busca     = document.getElementById('buscaGeral').value.toLowerCase();
    const fab       = document.getElementById('filtroFabricante').value;
    const frac      = document.getElementById('filtroFracionado').value;
    const filtered  = allItems.filter(i => {
        const matchBusca = !busca || [i.codigo,i.produto,i.fabricante,i.embalagem].join(' ').toLowerCase().includes(busca);
        const matchFab   = !fab  || i.fabricante === fab;
        const matchFrac  = !frac || i.fracionado === frac;
        return matchBusca && matchFab && matchFrac;
    });
    renderTabela(filtered);
}

document.getElementById('buscaGeral').addEventListener('input', aplicarFiltros);
document.getElementById('filtroFabricante').addEventListener('change', aplicarFiltros);
document.getElementById('filtroFracionado').addEventListener('change', aplicarFiltros);

// ── Abrir edição ───────────────────────────────────────────
function abrirEdicao(id) {
    const item = allItems.find(i => i.id == id);
    if (!item) return;
    document.getElementById('editId').value           = item.id;
    document.getElementById('editFabricante').value   = item.fabricante   || '';
    document.getElementById('editClassificacao').value= item.classificacao|| '';
    document.getElementById('editCodigo').value       = item.codigo       || '';
    document.getElementById('editProduto').value      = item.produto      || '';
    document.getElementById('editEmbalagem').value    = item.embalagem    || '';
    document.getElementById('editPreco').value        = parseFloat(item.preco_net_usd||0).toFixed(4);
    document.getElementById('editFracionado').value   = item.fracionado   || 'Não';
    document.getElementById('editLeadTime').value     = item.lead_time    || '';
    modalEdit.show();
}

// ── CRUD ───────────────────────────────────────────────────
async function salvarItem() {
    const payload = {
        action:        'create',
        fabricante:    document.getElementById('addFabricante').value,
        classificacao: document.getElementById('addClassificacao').value,
        codigo:        document.getElementById('addCodigo').value,
        produto:       document.getElementById('addProduto').value,
        embalagem:     document.getElementById('addEmbalagem').value,
        preco_net_usd: parseFloat(document.getElementById('addPreco').value) || 0,
        fracionado:    document.getElementById('addFracionado').value,
        lead_time:     document.getElementById('addLeadTime').value,
    };
    if (!payload.fabricante || !payload.produto) { showAlert('warning', 'Fabricante e Produto são obrigatórios.'); return; }
    const res  = await fetch('api/price_list_crud.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (data.success) { modalAdd.hide(); showAlert('success', 'Item criado com sucesso!'); carregarDados(); }
    else showAlert('danger', 'Erro: ' + data.message);
}

async function atualizarItem() {
    const payload = {
        action:        'update',
        id:            parseInt(document.getElementById('editId').value),
        fabricante:    document.getElementById('editFabricante').value,
        classificacao: document.getElementById('editClassificacao').value,
        codigo:        document.getElementById('editCodigo').value,
        produto:       document.getElementById('editProduto').value,
        embalagem:     document.getElementById('editEmbalagem').value,
        preco_net_usd: parseFloat(document.getElementById('editPreco').value) || 0,
        fracionado:    document.getElementById('editFracionado').value,
        lead_time:     document.getElementById('editLeadTime').value,
    };
    const res  = await fetch('api/price_list_crud.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (data.success) { modalEdit.hide(); showAlert('success', 'Item atualizado!'); carregarDados(); }
    else showAlert('danger', 'Erro: ' + data.message);
}

async function deletarItem() {
    if (!confirm('Excluir este item permanentemente da price list?')) return;
    const id   = parseInt(document.getElementById('editId').value);
    const res  = await fetch('api/price_list_crud.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete', id }) });
    const data = await res.json();
    if (data.success) { modalEdit.hide(); showAlert('success', 'Item excluído.'); carregarDados(); }
    else showAlert('danger', 'Erro: ' + data.message);
}

function showAlert(type, msg) {
    const box = document.getElementById('alertBox');
    box.className = `alert alert-${type} alert-dismissible fade show`;
    box.innerHTML = `<i class="fas fa-info-circle me-2"></i>${msg} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    box.style.display = '';
    setTimeout(() => { try { bootstrap.Alert.getOrCreateInstance(box).close(); } catch(e){box.style.display='none';} }, 5000);
}

document.addEventListener('DOMContentLoaded', carregarDados);
</script>
</body>
</html>