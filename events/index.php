<?php
define('PAGE_TITLE',   'Eventos');
define('PAGE_CURRENT', 'eventos');
require_once 'conexao.php';
require_once 'header.php';
?>

<div class="container-fluid px-4 py-4">

    <!-- Cabeçalho da página -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1><i class="bi bi-calendar-event me-2"></i>Eventos Corporativos</h1>
            <p class="mb-0 mt-1 opacity-75 small">Gerencie os projetos de eventos e seus gastos em um só lugar.</p>
        </div>
        <button class="btn btn-success fw-600" data-bs-toggle="modal" data-bs-target="#modalNovoEvento">
            <i class="bi bi-plus-lg me-1"></i> Novo Evento
        </button>
    </div>

    <!-- Filtros de status -->
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <span class="text-muted small fw-600">Filtrar:</span>
        <button class="btn btn-sm btn-outline-secondary filter-btn active" data-status="">Todos</button>
        <button class="btn btn-sm btn-outline-warning  filter-btn" data-status="planejamento">Planejamento</button>
        <button class="btn btn-sm btn-outline-success  filter-btn" data-status="em_execucao">Em Execução</button>
        <button class="btn btn-sm btn-outline-secondary filter-btn" data-status="encerrado">Encerrado</button>
    </div>

    <!-- Cards de eventos -->
    <div id="eventGrid" class="row g-3">
        <div class="col-12 text-center py-5 text-muted" id="loadingState">
            <div class="spinner-border spinner-border-sm me-2"></div> Carregando eventos…
        </div>
    </div>

    <!-- Estado vazio -->
    <div id="emptyState" class="text-center py-5" style="display:none">
        <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
        <p class="text-muted">Nenhum evento encontrado.<br>
            <button class="btn btn-success mt-2" data-bs-toggle="modal" data-bs-target="#modalNovoEvento">
                <i class="bi bi-plus-lg"></i> Criar primeiro evento
            </button>
        </p>
    </div>

</div>

<!-- ── Modal: Novo / Editar Evento ────────────────────────────────────────── -->
<div class="modal fade" id="modalNovoEvento" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark-blue">
                <h5 class="modal-title text-white"><i class="bi bi-calendar-plus me-2"></i><span id="modalEventoTitulo">Novo Evento</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="eventoId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nome do Evento *</label>
                        <input type="text" class="form-control" id="eventoNome" placeholder="Ex: Feira Internacional FISPAL 2026" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Data de Início</label>
                        <input type="date" class="form-control" id="eventoDataInicio">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Data de Término</label>
                        <input type="date" class="form-control" id="eventoDataFim">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Local</label>
                        <input type="text" class="form-control" id="eventoLocal" placeholder="Ex: São Paulo, SP — Expo Center Norte">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="eventoStatus">
                            <option value="planejamento">Planejamento</option>
                            <option value="em_execucao">Em Execução</option>
                            <option value="encerrado">Encerrado</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Responsável Principal</label>
                        <input type="text" class="form-control" id="eventoResponsavel" placeholder="Nome do responsável">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Orçamento Total (R$)</label>
                        <input type="number" class="form-control" id="eventoOrcamento" min="0" step="0.01" placeholder="0,00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contingência (%)</label>
                        <input type="number" class="form-control" id="eventoContingencia" min="0" max="100" step="0.5" value="5">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Objetivo</label>
                        <textarea class="form-control" id="eventoObjetivo" rows="2" placeholder="Descreva o objetivo do evento…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnSalvarEvento">
                    <i class="bi bi-check-lg me-1"></i> Salvar Evento
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Confirmar Exclusão ──────────────────────────────────────────── -->
<div class="modal fade" id="modalExcluir" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Excluir Evento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o evento <strong id="excluirNome"></strong>?</p>
                <p class="text-danger small mb-0"><i class="bi bi-exclamation-triangle"></i> Todas as despesas e orçamentos vinculados serão removidos.</p>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="excluirId">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarExcluir">
                    <i class="bi bi-trash me-1"></i> Excluir
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const fmtBRL = v => parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '—';

const STATUS_LABELS = { planejamento: 'Planejamento', em_execucao: 'Em Execução', encerrado: 'Encerrado' };
const STATUS_BADGE  = { planejamento: 'bg-warning text-dark', em_execucao: 'bg-success', encerrado: 'bg-secondary' };
const STATUS_CARD   = { planejamento: 'status-planejamento', em_execucao: 'status-em_execucao', encerrado: 'status-encerrado' };

let allEvents = [];
let filterStatus = '';

function loadEvents() {
    $.getJSON('api/eventos.php?action=list', function(res) {
        if (!res.success) { $('#loadingState').html('<span class="text-danger">Erro ao carregar eventos.</span>'); return; }
        allEvents = res.data;
        renderEvents();
    });
}

function renderEvents() {
    const filtered = filterStatus ? allEvents.filter(e => e.status === filterStatus) : allEvents;
    $('#loadingState').hide();

    if (!filtered.length) {
        $('#eventGrid').html('');
        $('#emptyState').show();
        return;
    }
    $('#emptyState').hide();

    const html = filtered.map(ev => {
        const pctUsed = ev.orcamento_total > 0
            ? Math.min(100, (ev.realizado / ev.orcamento_total) * 100).toFixed(1)
            : 0;
        const pctClass = pctUsed >= 100 ? 'bg-danger' : pctUsed >= 90 ? 'bg-warning' : 'bg-success';
        const dataRange = (ev.data_inicio || ev.data_fim)
            ? `${fmtDate(ev.data_inicio)}${ev.data_fim ? ' → ' + fmtDate(ev.data_fim) : ''}`
            : '';

        return `
        <div class="col-md-6 col-xl-4 event-col" data-status="${ev.status}">
          <div class="card event-card h-100 ${STATUS_CARD[ev.status] || ''}">
            <div class="card-body p-4">
              <div class="d-flex align-items-start justify-content-between mb-2">
                <span class="badge ${STATUS_BADGE[ev.status] || 'bg-secondary'}">${STATUS_LABELS[ev.status] || ev.status}</span>
                <div class="dropdown">
                  <button class="btn btn-sm btn-link text-muted p-0" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item" href="evento.php?id=${ev.id}"><i class="bi bi-eye me-2"></i>Abrir</a></li>
                    <li><a class="dropdown-item" href="orcamento.php?event_id=${ev.id}"><i class="bi bi-wallet2 me-2"></i>Orçamento</a></li>
                    <li><a class="dropdown-item" href="despesas.php?event_id=${ev.id}"><i class="bi bi-receipt me-2"></i>Despesas</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="openEditEvento(${ev.id}); return false;"><i class="bi bi-pencil me-2"></i>Editar</a></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="openExcluir(${ev.id}, '${ev.nome.replace(/'/g,"\\'")}'); return false;"><i class="bi bi-trash me-2"></i>Excluir</a></li>
                  </ul>
                </div>
              </div>

              <h5 class="fw-700 mb-1 lh-sm" style="font-size:1rem; cursor:pointer"
                  onclick="location.href='evento.php?id=${ev.id}'">${ev.nome}</h5>

              ${dataRange ? `<p class="text-muted small mb-2"><i class="bi bi-calendar3 me-1"></i>${dataRange}</p>` : ''}
              ${ev.local_evento ? `<p class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i>${ev.local_evento}</p>` : ''}
              ${ev.responsavel ? `<p class="text-muted small mb-3"><i class="bi bi-person me-1"></i>${ev.responsavel}</p>` : '<div class="mb-3"></div>'}

              <!-- Orçamento vs Realizado -->
              <div class="border-top pt-3">
                <div class="d-flex justify-content-between small mb-1">
                  <span class="text-muted">Orçado</span>
                  <span class="fw-600">R$ ${fmtBRL(ev.orcamento_total)}</span>
                </div>
                <div class="d-flex justify-content-between small mb-2">
                  <span class="text-muted">Realizado</span>
                  <span class="fw-600 text-${pctUsed >= 100 ? 'danger' : 'success'}">R$ ${fmtBRL(ev.realizado)}</span>
                </div>
                <div class="progress budget-progress">
                  <div class="progress-bar ${pctClass}" style="width:${pctUsed}%"></div>
                </div>
                <div class="text-end small text-muted mt-1">${pctUsed}% utilizado</div>
              </div>
            </div>
            <div class="card-footer bg-transparent border-top py-2 px-4">
              <a href="evento.php?id=${ev.id}" class="btn btn-sm btn-success w-100">
                <i class="bi bi-arrow-right-circle me-1"></i>Acessar Evento
              </a>
            </div>
          </div>
        </div>`;
    }).join('');

    $('#eventGrid').html(html);
}

// ── Filtro ────────────────────────────────────────────────────────────────── //
$(document).on('click', '.filter-btn', function() {
    $('.filter-btn').removeClass('active');
    $(this).addClass('active');
    filterStatus = $(this).data('status');
    renderEvents();
});

// ── Criar / Editar ────────────────────────────────────────────────────────── //
function openEditEvento(id) {
    const ev = allEvents.find(e => e.id == id);
    if (!ev) return;
    $('#modalEventoTitulo').text('Editar Evento');
    $('#eventoId').val(ev.id);
    $('#eventoNome').val(ev.nome);
    $('#eventoDataInicio').val(ev.data_inicio || '');
    $('#eventoDataFim').val(ev.data_fim || '');
    $('#eventoLocal').val(ev.local_evento || '');
    $('#eventoStatus').val(ev.status);
    $('#eventoResponsavel').val(ev.responsavel || '');
    $('#eventoOrcamento').val(ev.orcamento_total || '');
    $('#eventoContingencia').val(ev.contingencia_pct || 5);
    $('#eventoObjetivo').val(ev.objetivo || '');
    new bootstrap.Modal(document.getElementById('modalNovoEvento')).show();
}

$('#modalNovoEvento').on('hidden.bs.modal', function() {
    $('#modalEventoTitulo').text('Novo Evento');
    $('#eventoId, #eventoNome, #eventoDataInicio, #eventoDataFim, #eventoLocal, #eventoResponsavel, #eventoOrcamento, #eventoObjetivo').val('');
    $('#eventoStatus').val('planejamento');
    $('#eventoContingencia').val(5);
});

$('#btnSalvarEvento').on('click', function() {
    const nome = $('#eventoNome').val().trim();
    if (!nome) { alert('Informe o nome do evento.'); $('#eventoNome').focus(); return; }

    const payload = {
        action:          $('#eventoId').val() ? 'update' : 'create',
        id:              $('#eventoId').val() || null,
        nome:            nome,
        data_inicio:     $('#eventoDataInicio').val() || null,
        data_fim:        $('#eventoDataFim').val() || null,
        local_evento:    $('#eventoLocal').val().trim() || null,
        status:          $('#eventoStatus').val(),
        responsavel:     $('#eventoResponsavel').val().trim() || null,
        orcamento_total: parseFloat($('#eventoOrcamento').val()) || 0,
        contingencia_pct: parseFloat($('#eventoContingencia').val()) || 5,
        objetivo:        $('#eventoObjetivo').val().trim() || null,
    };

    $('#btnSalvarEvento').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Salvando…');

    $.ajax({
        url: 'api/eventos.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        success: function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalNovoEvento')).hide();
                loadEvents();
            } else {
                alert('Erro: ' + res.message);
            }
        },
        error: function() { alert('Erro de comunicação com o servidor.'); },
        complete: function() {
            $('#btnSalvarEvento').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Salvar Evento');
        }
    });
});

// ── Excluir ───────────────────────────────────────────────────────────────── //
function openExcluir(id, nome) {
    $('#excluirId').val(id);
    $('#excluirNome').text(nome);
    new bootstrap.Modal(document.getElementById('modalExcluir')).show();
}

$('#btnConfirmarExcluir').on('click', function() {
    const id = $('#excluirId').val();
    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

    $.ajax({
        url: 'api/eventos.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ action: 'delete', id: id }),
        success: function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalExcluir')).hide();
                loadEvents();
            } else {
                alert('Erro: ' + res.message);
            }
        },
        complete: function() {
            $('#btnConfirmarExcluir').prop('disabled', false).html('<i class="bi bi-trash me-1"></i> Excluir');
        }
    });
});

// ── Init ──────────────────────────────────────────────────────────────────── //
$(loadEvents);
</script>

<?php require_once 'footer.php'; ?>
