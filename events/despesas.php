<?php
define('PAGE_CURRENT', 'evento_despesas');
require_once 'conexao.php';
require_once 'auth.php';
require_login();

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
if (!$event_id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM evt_events WHERE id = ?");
$stmt->execute([$event_id]);
$active_event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$active_event) { header('Location: index.php'); exit; }

define('PAGE_TITLE', 'Despesas — ' . $active_event['nome']);
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
                    <li class="breadcrumb-item active">Despesas</li>
                </ol>
            </nav>
            <h1><i class="bi bi-receipt-cutoff me-2"></i>Despesas do Evento</h1>
        </div>
        <button class="btn btn-success fw-600" data-bs-toggle="modal" data-bs-target="#modalNovaDespesa">
            <i class="bi bi-plus-lg me-1"></i> Registrar Despesa
        </button>
    </div>

    <!-- Cards de resumo -->
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['id'=>'statTotal',    'icon'=>'bi-cash-stack',       'bg'=>'bg-success bg-opacity-10',  'color'=>'text-success',  'label'=>'Total Geral'],
            ['id'=>'statPendente', 'icon'=>'bi-hourglass-split',  'bg'=>'bg-warning bg-opacity-10',  'color'=>'text-warning',  'label'=>'Pendente'],
            ['id'=>'statAprovado', 'icon'=>'bi-check-circle',     'bg'=>'bg-info bg-opacity-10',     'color'=>'text-info',     'label'=>'Aprovado'],
            ['id'=>'statPago',     'icon'=>'bi-check2-all',       'bg'=>'bg-success bg-opacity-10',  'color'=>'text-success',  'label'=>'Pago'],
        ] as $s): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="stat-icon <?= $s['bg'] ?> <?= $s['color'] ?>">
                        <i class="bi <?= $s['icon'] ?>"></i>
                    </div>
                    <div>
                        <div class="stat-label text-muted"><?= $s['label'] ?></div>
                        <div class="stat-value" id="<?= $s['id'] ?>">—</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Categoria</label>
                    <select class="form-select form-select-sm" id="fCategoria">
                        <option value="">Todas</option>
                        <?php foreach ($CATEGORIAS as $c): ?>
                        <option><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select form-select-sm" id="fStatus">
                        <option value="">Todos</option>
                        <option value="pendente">Pendente</option>
                        <option value="aprovado">Aprovado</option>
                        <option value="pago">Pago</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Início</label>
                    <input type="date" class="form-control form-control-sm" id="fDataInicio">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Fim</label>
                    <input type="date" class="form-control form-control-sm" id="fDataFim">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Origem</label>
                    <select class="form-select form-select-sm" id="fOrigem">
                        <option value="">Todas</option>
                        <option value="manual">Manual</option>
                        <option value="viagem_express">Viagem Express</option>
                        <option value="uber">Uber</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary btn-sm w-100" onclick="loadDespesas()">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de despesas -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Fornecedor</th>
                            <th>Origem</th>
                            <th class="text-end">Valor</th>
                            <th>Status</th>
                            <th class="text-center" style="width:110px">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="despesas-body">
                        <tr><td colspan="8" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2"></div>Carregando…
                        </td></tr>
                    </tbody>
                    <tfoot id="despesas-tfoot"></tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ── Modal: Nova / Editar Despesa ──────────────────────────────────────── -->
<div class="modal fade" id="modalNovaDespesa" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-green">
                <h5 class="modal-title text-white"><i class="bi bi-receipt me-2"></i><span id="despTitulo">Nova Despesa</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="despId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Descrição *</label>
                        <input type="text" class="form-control" id="despDescricao" placeholder="Ex: Locação sala auditório principal">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Categoria *</label>
                        <select class="form-select" id="despCategoria">
                            <option value="">Selecione…</option>
                            <?php foreach ($CATEGORIAS as $c): ?>
                            <option><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sub-rubrica</label>
                        <input type="text" class="form-control" id="despSubrubrica" placeholder="Detalhe opcional">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor (R$) *</label>
                        <input type="number" class="form-control" id="despValor" min="0.01" step="0.01" placeholder="0,00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Data da Despesa</label>
                        <input type="date" class="form-control" id="despData">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vencimento</label>
                        <input type="date" class="form-control" id="despVencimento">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fornecedor</label>
                        <input type="text" class="form-control" id="despFornecedor" placeholder="Nome do fornecedor">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status de Pagamento</label>
                        <select class="form-select" id="despStatus">
                            <option value="pendente">Pendente</option>
                            <option value="aprovado">Aprovado</option>
                            <option value="pago">Pago</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observação</label>
                        <textarea class="form-control" id="despObs" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnSalvarDesp">
                    <i class="bi bi-check-lg me-1"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Confirmar Exclusão ─────────────────────────────────────────── -->
<div class="modal fade" id="modalExcluirDesp" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Excluir Despesa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Excluir a despesa <strong id="excDespNome"></strong>?</p>
                <input type="hidden" id="excDespId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnExcluirDesp">
                    <i class="bi bi-trash me-1"></i>Excluir
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const EVENT_ID = <?= $event_id ?>;
const fmtBRL = v => parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '—';

const STATUS_BADGE = { pendente:'badge-pendente', aprovado:'badge-aprovado', pago:'badge-pago', cancelado:'badge-cancelado' };
const STATUS_LABEL = { pendente:'Pendente', aprovado:'Aprovado', pago:'Pago', cancelado:'Cancelado' };
const ORIGEM_LABEL = { manual:'Manual', viagem_express:'Viagem Express', uber:'Uber' };
const ORIGEM_COLOR = { manual:'bg-light text-dark', viagem_express:'bg-primary bg-opacity-10 text-primary', uber:'bg-dark bg-opacity-10 text-dark' };

let allDespesas = [];

function loadDespesas() {
    const params = new URLSearchParams({
        action: 'list', event_id: EVENT_ID,
        categoria: $('#fCategoria').val(),
        status: $('#fStatus').val(),
        data_inicio: $('#fDataInicio').val(),
        data_fim: $('#fDataFim').val(),
        origem: $('#fOrigem').val(),
    });
    $('#despesas-body').html('<tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Carregando…</td></tr>');

    $.getJSON('api/despesas.php?' + params, function(res) {
        if (!res.success) { $('#despesas-body').html('<tr><td colspan="8" class="text-center py-3 text-danger">Erro ao carregar.</td></tr>'); return; }
        allDespesas = res.data;
        renderDespesas(res);
    });
}

function renderDespesas(res) {
    // Atualizar stats
    $('#statTotal').text('R$ ' + fmtBRL(res.stats.total));
    $('#statPendente').text('R$ ' + fmtBRL(res.stats.pendente));
    $('#statAprovado').text('R$ ' + fmtBRL(res.stats.aprovado));
    $('#statPago').text('R$ ' + fmtBRL(res.stats.pago));

    if (!allDespesas.length) {
        $('#despesas-body').html('<tr><td colspan="8" class="text-center py-4 text-muted">Nenhuma despesa encontrada.</td></tr>');
        $('#despesas-tfoot').html('');
        return;
    }

    const rows = allDespesas.map(d => {
        const origemBadge = `<span class="badge cat-badge ${ORIGEM_COLOR[d.origem] || 'bg-light text-dark'} border">${ORIGEM_LABEL[d.origem] || d.origem}</span>`;
        return `<tr>
          <td class="text-nowrap">${fmtDate(d.data_despesa)}</td>
          <td>${d.descricao.length > 50 ? d.descricao.substring(0,50)+'…' : d.descricao}</td>
          <td><span class="badge cat-badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">${d.categoria}</span></td>
          <td class="text-muted">${d.fornecedor || '—'}</td>
          <td>${origemBadge}</td>
          <td class="text-end fw-600 text-nowrap">R$ ${fmtBRL(d.valor)}</td>
          <td><span class="badge ${STATUS_BADGE[d.status_pagamento] || ''}">${STATUS_LABEL[d.status_pagamento] || d.status_pagamento}</span></td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-secondary me-1" onclick="openEditDesp(${d.id})" title="Editar"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger" onclick="openExcluirDesp(${d.id}, '${d.descricao.substring(0,30).replace(/'/g,"\\'")}…')" title="Excluir"><i class="bi bi-trash"></i></button>
          </td>
        </tr>`;
    }).join('');

    $('#despesas-body').html(rows);
    $('#despesas-tfoot').html(`
      <tr class="table-light fw-700">
        <td colspan="5"><strong>TOTAL (${allDespesas.length} itens)</strong></td>
        <td class="text-end">R$ ${fmtBRL(res.stats.total)}</td>
        <td colspan="2"></td>
      </tr>`);
}

// ── Salvar despesa ────────────────────────────────────────────────────────── //
$('#modalNovaDespesa').on('hidden.bs.modal', function() {
    $('#despTitulo').text('Nova Despesa');
    $('#despId,#despDescricao,#despSubrubrica,#despValor,#despFornecedor,#despObs').val('');
    $('#despCategoria,#despStatus').val('');
    $('#despData').val('<?= date('Y-m-d') ?>');
    $('#despVencimento').val('');
    $('#despStatus').val('pendente');
});

function openEditDesp(id) {
    const d = allDespesas.find(x => x.id == id);
    if (!d) return;
    $('#despTitulo').text('Editar Despesa');
    $('#despId').val(d.id);
    $('#despDescricao').val(d.descricao);
    $('#despCategoria').val(d.categoria);
    $('#despSubrubrica').val(d.sub_rubrica || '');
    $('#despValor').val(d.valor);
    $('#despData').val(d.data_despesa || '');
    $('#despVencimento').val(d.data_vencimento || '');
    $('#despFornecedor').val(d.fornecedor || '');
    $('#despStatus').val(d.status_pagamento);
    $('#despObs').val(d.observacao || '');
    new bootstrap.Modal(document.getElementById('modalNovaDespesa')).show();
}

$('#btnSalvarDesp').on('click', function() {
    const desc = $('#despDescricao').val().trim();
    const cat  = $('#despCategoria').val();
    const val  = parseFloat($('#despValor').val());
    if (!desc || !cat || isNaN(val) || val <= 0) {
        alert('Preencha descrição, categoria e valor.');
        return;
    }
    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({
        url: 'api/despesas.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: $('#despId').val() ? 'update' : 'create',
            id: $('#despId').val() || null,
            event_id: EVENT_ID,
            descricao: desc,
            categoria: cat,
            sub_rubrica: $('#despSubrubrica').val().trim() || null,
            valor: val,
            data_despesa: $('#despData').val() || null,
            data_vencimento: $('#despVencimento').val() || null,
            fornecedor: $('#despFornecedor').val().trim() || null,
            status_pagamento: $('#despStatus').val() || 'pendente',
            observacao: $('#despObs').val().trim() || null,
        }),
        success: function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalNovaDespesa')).hide();
                loadDespesas();
            } else { alert('Erro: ' + res.message); }
        },
        complete: function() {
            $('#btnSalvarDesp').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Salvar');
        }
    });
});

// ── Excluir despesa ───────────────────────────────────────────────────────── //
function openExcluirDesp(id, nome) {
    $('#excDespId').val(id);
    $('#excDespNome').text(nome);
    new bootstrap.Modal(document.getElementById('modalExcluirDesp')).show();
}

$('#btnExcluirDesp').on('click', function() {
    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({
        url: 'api/despesas.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ action: 'delete', id: $('#excDespId').val() }),
        success: function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalExcluirDesp')).hide();
                loadDespesas();
            } else { alert('Erro: ' + res.message); }
        },
        complete: function() {
            $('#btnExcluirDesp').prop('disabled', false).html('<i class="bi bi-trash me-1"></i>Excluir');
        }
    });
});

$(function() { loadDespesas(); });
</script>

<?php require_once 'footer.php'; ?>
