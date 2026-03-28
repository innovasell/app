<?php
define('PAGE_CURRENT', 'evento_orcamento');
require_once 'conexao.php';
require_once 'auth.php';
require_login();

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
if (!$event_id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM evt_events WHERE id = ?");
$stmt->execute([$event_id]);
$active_event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$active_event) { header('Location: index.php'); exit; }

define('PAGE_TITLE', 'Orçamento — ' . $active_event['nome']);
require_once 'header.php';

$CATEGORIAS = [
    'Transporte', 'Hospedagem', 'Alimentação e Bebidas',
    'Contratos e Fornecedores', 'Materiais e Insumos',
    'Infraestrutura e Locação', 'Comunicação Visual',
    'RH e Temporários', 'Imprevistos', 'Outros'
];
?>

<div class="container-fluid px-4 py-4">

    <!-- Cabeçalho -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="index.php">Eventos</a></li>
                    <li class="breadcrumb-item"><a href="evento.php?id=<?= $event_id ?>"><?= htmlspecialchars($active_event['nome']) ?></a></li>
                    <li class="breadcrumb-item active">Orçamento</li>
                </ol>
            </nav>
            <h1><i class="bi bi-wallet2 me-2"></i>Orçamento do Evento</h1>
        </div>
        <button class="btn btn-success fw-600" data-bs-toggle="modal" data-bs-target="#modalNovaRubrica">
            <i class="bi bi-plus-lg me-1"></i> Adicionar Rubrica
        </button>
    </div>

    <!-- Cards de resumo -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div>
                        <div class="stat-label text-muted">Orçamento Total do Evento</div>
                        <div class="stat-value">R$ <?= number_format($active_event['orcamento_total'], 2, ',', '.') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-list-check"></i>
                    </div>
                    <div>
                        <div class="stat-label text-muted">Total Orçado em Rubricas</div>
                        <div class="stat-value" id="totalRubricas">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div>
                        <div class="stat-label text-muted">Total Realizado</div>
                        <div class="stat-value" id="totalRealizado">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <div class="stat-label text-muted">Reserva de Contingência (<?= number_format($active_event['contingencia_pct'],1) ?>%)</div>
                        <div class="stat-value" id="totalContingencia">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de rubricas -->
    <div class="card">
        <div class="card-header"><i class="bi bi-table me-2 text-success"></i>Rubricas Orçamentárias</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tblRubricas">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Sub-rubrica</th>
                            <th class="text-end">Orçado</th>
                            <th class="text-end">Realizado</th>
                            <th class="text-end">Saldo</th>
                            <th style="width:120px">Utilização</th>
                            <th class="text-center" style="width:90px">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="rubricas-body">
                        <tr><td colspan="7" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2"></div>Carregando…
                        </td></tr>
                    </tbody>
                    <tfoot id="rubricas-tfoot"></tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ── Modal: Nova / Editar Rubrica ──────────────────────────────────────── -->
<div class="modal fade" id="modalNovaRubrica" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark-blue">
                <h5 class="modal-title text-white"><i class="bi bi-plus-circle me-2"></i><span id="rubrTitulo">Nova Rubrica</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rubrId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Categoria *</label>
                        <select class="form-select" id="rubrCategoria">
                            <option value="">Selecione…</option>
                            <?php foreach ($CATEGORIAS as $cat): ?>
                            <option><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Sub-rubrica <span class="text-muted">(opcional)</span></label>
                        <input type="text" class="form-control" id="rubrSubrubrica"
                               placeholder="Ex: Traslado aeroporto, Coffee break dia 1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Valor Orçado (R$) *</label>
                        <input type="number" class="form-control" id="rubrValor" min="0" step="0.01" placeholder="0,00">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observação</label>
                        <textarea class="form-control" id="rubrObs" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnSalvarRubrica">
                    <i class="bi bi-check-lg me-1"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Confirmar Exclusão ─────────────────────────────────────────── -->
<div class="modal fade" id="modalExcluirRubrica" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Excluir Rubrica</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Excluir a rubrica <strong id="excRubrNome"></strong>?</p>
                <p class="text-muted small mb-0">As despesas vinculadas não serão excluídas, apenas perderão o vínculo com esta rubrica.</p>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="excRubrId">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnExcluirRubrica">
                    <i class="bi bi-trash me-1"></i>Excluir
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const EVENT_ID = <?= $event_id ?>;
const ORCAMENTO_TOTAL = <?= $active_event['orcamento_total'] ?>;
const CONTINGENCIA_PCT = <?= $active_event['contingencia_pct'] ?> / 100;
const fmtBRL = v => parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});

let allRubricas = [];

function loadRubricas() {
    $.getJSON('api/budget.php?action=list&event_id=' + EVENT_ID, function(res) {
        if (!res.success) return;
        allRubricas = res.data;
        renderRubricas();
    });
}

function renderRubricas() {
    if (!allRubricas.length) {
        $('#rubricas-body').html('<tr><td colspan="7" class="text-center py-4 text-muted">Nenhuma rubrica cadastrada. Clique em "Adicionar Rubrica".</td></tr>');
        $('#rubricas-tfoot').html('');
        $('#totalRubricas').text('R$ 0,00');
        $('#totalRealizado').text('R$ 0,00');
        $('#totalContingencia').text('R$ ' + fmtBRL(ORCAMENTO_TOTAL * CONTINGENCIA_PCT));
        return;
    }

    let sumOrcado = 0, sumRealizado = 0;

    const rows = allRubricas.map(r => {
        const orcado    = parseFloat(r.orcado || 0);
        const realizado = parseFloat(r.realizado || 0);
        const saldo     = orcado - realizado;
        const pct       = orcado > 0 ? Math.min(100, realizado / orcado * 100) : 0;
        const pctCls    = pct >= 100 ? 'pct-danger' : pct >= 90 ? 'pct-warning' : 'pct-ok';
        sumOrcado    += orcado;
        sumRealizado += realizado;

        return `<tr>
          <td class="fw-600">${r.categoria}</td>
          <td class="text-muted">${r.sub_rubrica || '—'}</td>
          <td class="text-end">R$ ${fmtBRL(orcado)}</td>
          <td class="text-end">R$ ${fmtBRL(realizado)}</td>
          <td class="text-end ${saldo < 0 ? 'text-danger fw-600' : ''}">R$ ${fmtBRL(saldo)}</td>
          <td>
            <div class="progress budget-progress ${pctCls}">
              <div class="progress-bar" style="width:${pct.toFixed(1)}%"></div>
            </div>
            <div class="text-end small text-muted">${pct.toFixed(1)}%</div>
          </td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-secondary me-1" onclick="openEditRubrica(${r.id})" title="Editar">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="openExcluirRubrica(${r.id}, '${r.categoria.replace(/'/g,"\\'")}${r.sub_rubrica ? ' / '+r.sub_rubrica.replace(/'/g,"\\'") : ''}')" title="Excluir">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>`;
    }).join('');

    const saldoTotal = sumOrcado - sumRealizado;
    const contingencia = ORCAMENTO_TOTAL * CONTINGENCIA_PCT;
    const pctTotal = sumOrcado > 0 ? Math.min(100, sumRealizado/sumOrcado*100) : 0;

    $('#rubricas-body').html(rows);
    $('#rubricas-tfoot').html(`
      <tr class="table-light fw-700">
        <td colspan="2"><strong>TOTAL</strong></td>
        <td class="text-end">R$ ${fmtBRL(sumOrcado)}</td>
        <td class="text-end">R$ ${fmtBRL(sumRealizado)}</td>
        <td class="text-end ${saldoTotal < 0 ? 'text-danger' : ''}">R$ ${fmtBRL(saldoTotal)}</td>
        <td>
          <div class="progress budget-progress ${pctTotal >= 100 ? 'pct-danger' : pctTotal >= 90 ? 'pct-warning' : 'pct-ok'}">
            <div class="progress-bar" style="width:${pctTotal.toFixed(1)}%"></div>
          </div>
          <div class="text-end small">${pctTotal.toFixed(1)}%</div>
        </td>
        <td></td>
      </tr>`);

    $('#totalRubricas').text('R$ ' + fmtBRL(sumOrcado));
    $('#totalRealizado').text('R$ ' + fmtBRL(sumRealizado));
    $('#totalContingencia').text('R$ ' + fmtBRL(contingencia));
}

// ── Salvar rubrica ─────────────────────────────────────────────────────────── //
$('#modalNovaRubrica').on('hidden.bs.modal', function() {
    $('#rubrTitulo').text('Nova Rubrica');
    $('#rubrId,#rubrSubrubrica,#rubrValor,#rubrObs').val('');
    $('#rubrCategoria').val('');
});

function openEditRubrica(id) {
    const r = allRubricas.find(x => x.id == id);
    if (!r) return;
    $('#rubrTitulo').text('Editar Rubrica');
    $('#rubrId').val(r.id);
    $('#rubrCategoria').val(r.categoria);
    $('#rubrSubrubrica').val(r.sub_rubrica || '');
    $('#rubrValor').val(r.orcado);
    $('#rubrObs').val(r.obs || '');
    new bootstrap.Modal(document.getElementById('modalNovaRubrica')).show();
}

$('#btnSalvarRubrica').on('click', function() {
    const cat = $('#rubrCategoria').val();
    const val = parseFloat($('#rubrValor').val());
    if (!cat) { alert('Selecione a categoria.'); return; }
    if (isNaN(val) || val < 0) { alert('Informe um valor válido.'); return; }

    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({
        url: 'api/budget.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: $('#rubrId').val() ? 'update' : 'create',
            id: $('#rubrId').val() || null,
            event_id: EVENT_ID,
            categoria: cat,
            sub_rubrica: $('#rubrSubrubrica').val().trim() || null,
            valor_orcado: val,
            obs: $('#rubrObs').val().trim() || null,
        }),
        success: function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalNovaRubrica')).hide();
                loadRubricas();
            } else { alert('Erro: ' + res.message); }
        },
        complete: function() {
            $('#btnSalvarRubrica').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Salvar');
        }
    });
});

// ── Excluir rubrica ────────────────────────────────────────────────────────── //
function openExcluirRubrica(id, nome) {
    $('#excRubrId').val(id);
    $('#excRubrNome').text(nome);
    new bootstrap.Modal(document.getElementById('modalExcluirRubrica')).show();
}

$('#btnExcluirRubrica').on('click', function() {
    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({
        url: 'api/budget.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ action: 'delete', id: $('#excRubrId').val() }),
        success: function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalExcluirRubrica')).hide();
                loadRubricas();
            } else { alert('Erro: ' + res.message); }
        },
        complete: function() {
            $('#btnExcluirRubrica').prop('disabled', false).html('<i class="bi bi-trash me-1"></i>Excluir');
        }
    });
});

$(loadRubricas);
</script>

<?php require_once 'footer.php'; ?>
