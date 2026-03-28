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

<style>
/* ── Parcelas ── */
#parcelasSection { transition: all .2s; }
#tblParcelas td { padding: .35rem .5rem; font-size: .82rem; vertical-align: middle; }
#tblParcelas th { font-size: .75rem; padding: .4rem .5rem; }
.parcelas-badge { font-size: .7rem; font-weight: 700; cursor: pointer; }
.btn-pagar { font-size: .72rem; padding: 2px 7px; }

/* Status parcela */
.sp-pendente  { background:#fff3cd; color:#856404; border:1px solid #ffc107; }
.sp-pago      { background:#d4edda; color:#155724; border:1px solid #28a745; }
.sp-cancelado { background:#f8d7da; color:#721c24; border:1px solid #dc3545; }
</style>

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
            ['id'=>'statAprovado', 'icon'=>'bi-check-circle',     'bg'=>'bg-info bg-opacity-10',     'color'=>'text-info',     'label'=>'Parc. Pago'],
            ['id'=>'statPago',     'icon'=>'bi-check2-all',       'bg'=>'bg-success bg-opacity-10',  'color'=>'text-success',  'label'=>'Pago Integral'],
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
                        <option value="aprovado">Parcialmente Pago</option>
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
                            <th class="text-end">Valor Total</th>
                            <th>Parcelas</th>
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

<!-- ══════════════════════════════════════════════════════════════════════════
     Modal: Nova / Editar Despesa
══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNovaDespesa" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-green">
                <h5 class="modal-title text-white"><i class="bi bi-receipt me-2"></i><span id="despTitulo">Nova Despesa</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="despId">

                <!-- ── Dados gerais ─────────────────────────────────────── -->
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label">Descrição *</label>
                        <input type="text" class="form-control" id="despDescricao"
                               placeholder="Ex: Locação do espaço — Pavilhão Central">
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
                    <div class="col-md-6">
                        <label class="form-label">Fornecedor</label>
                        <input type="text" class="form-control" id="despFornecedor" placeholder="Nome do fornecedor">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data da Despesa</label>
                        <input type="date" class="form-control" id="despData">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Origem</label>
                        <select class="form-select" id="despOrigem">
                            <option value="manual">Manual</option>
                            <option value="viagem_express">Viagem Express</option>
                            <option value="uber">Uber</option>
                        </select>
                    </div>
                </div>

                <hr class="my-3">

                <!-- ── Toggle pagamento ─────────────────────────────────── -->
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="fw-600 small">Forma de Pagamento:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="tipoPag" id="tipoPagUnico" value="unico" checked>
                        <label class="btn btn-outline-primary" for="tipoPagUnico">
                            <i class="bi bi-cash me-1"></i>Pagamento Único
                        </label>
                        <input type="radio" class="btn-check" name="tipoPag" id="tipoPagParcelado" value="parcelado">
                        <label class="btn btn-outline-success" for="tipoPagParcelado">
                            <i class="bi bi-calendar2-week me-1"></i>Parcelado
                        </label>
                    </div>
                </div>

                <!-- ── Pagamento único ──────────────────────────────────── -->
                <div id="secaoUnico">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Valor (R$) *</label>
                            <input type="number" class="form-control" id="despValor" min="0.01" step="0.01" placeholder="0,00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vencimento</label>
                            <input type="date" class="form-control" id="despVencimento">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status de Pagamento</label>
                            <select class="form-select" id="despStatus">
                                <option value="pendente">Pendente</option>
                                <option value="aprovado">Aprovado</option>
                                <option value="pago">Pago</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── Parcelado ────────────────────────────────────────── -->
                <div id="secaoParcelado" style="display:none">

                    <!-- Gerador automático -->
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body py-3">
                            <p class="fw-600 small mb-2"><i class="bi bi-magic me-1 text-success"></i>Gerar parcelas automaticamente:</p>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label">Nº de parcelas</label>
                                    <input type="number" class="form-control form-control-sm" id="genQtd" min="2" max="120" placeholder="Ex: 5">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Valor total (R$)</label>
                                    <input type="number" class="form-control form-control-sm" id="genTotal" min="0.01" step="0.01" placeholder="Ex: 395886,95">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">1º vencimento</label>
                                    <input type="date" class="form-control form-control-sm" id="genPrimeiroVenc">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Intervalo</label>
                                    <select class="form-select form-select-sm" id="genIntervalo">
                                        <option value="monthly">Mensal</option>
                                        <option value="weekly">Semanal</option>
                                        <option value="biweekly">Quinzenal</option>
                                        <option value="custom">Personalizado (dias)</option>
                                    </select>
                                </div>
                                <div class="col-md-2" id="colGenDias" style="display:none">
                                    <label class="form-label">Dias</label>
                                    <input type="number" class="form-control form-control-sm" id="genDias" min="1" placeholder="30">
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-success btn-sm" onclick="gerarParcelas()">
                                        <i class="bi bi-magic me-1"></i>Gerar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de parcelas -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-2" id="tblParcelas">
                            <thead>
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Vencimento *</th>
                                    <th>Valor (R$) *</th>
                                    <th>Observação</th>
                                    <th style="width:40px" class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="parcelasBody">
                                <!-- rows adicionadas via JS -->
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-700">
                                    <td colspan="2" class="text-end small">TOTAL</td>
                                    <td id="parcelasTotal" class="text-success fw-700">R$ 0,00</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="addParcelaRow()">
                        <i class="bi bi-plus-circle me-1"></i>Adicionar linha
                    </button>
                </div>

                <!-- Observação geral -->
                <div class="mt-3">
                    <label class="form-label">Observação</label>
                    <textarea class="form-control" id="despObs" rows="2"></textarea>
                </div>

            </div><!-- /modal-body -->
            <div class="modal-footer justify-content-between">
                <div class="text-muted small" id="despTotalDisplay"></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btnSalvarDesp">
                        <i class="bi bi-check-lg me-1"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     Modal: Parcelas — detalhe e gestão
══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalParcelas" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark-blue">
                <div>
                    <h5 class="modal-title text-white mb-0" id="parcelasModalTitulo">—</h5>
                    <div class="text-white opacity-75 small" id="parcelasModalSub"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="parcelasModalBody">
                <div class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2"></div>Carregando…
                </div>
            </div>
            <div class="modal-footer">
                <div class="text-muted small me-auto" id="parcelasModalResumo"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
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
                <p class="text-muted small mb-0" id="excDespParcelasNote"></p>
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
const fmtBRL  = v => parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '—';
const parseBRL = s => parseFloat(String(s).replace(/\./g,'').replace(',','.')) || 0;

const STATUS_BADGE  = { pendente:'badge-pendente', aprovado:'badge-aprovado', pago:'badge-pago', cancelado:'badge-cancelado' };
const STATUS_LABEL  = { pendente:'Pendente', aprovado:'Parc. Pago', pago:'Pago', cancelado:'Cancelado' };
const ORIGEM_COLOR  = { manual:'bg-light text-dark', viagem_express:'bg-primary bg-opacity-10 text-primary', uber:'bg-dark bg-opacity-10 text-dark' };
const ORIGEM_LABEL  = { manual:'Manual', viagem_express:'Viagem Express', uber:'Uber' };

let allDespesas = [];

// ── Carregar despesas ─────────────────────────────────────────────────────── //
function loadDespesas() {
    const params = new URLSearchParams({
        action: 'list', event_id: EVENT_ID,
        categoria:   $('#fCategoria').val(),
        status:      $('#fStatus').val(),
        data_inicio: $('#fDataInicio').val(),
        data_fim:    $('#fDataFim').val(),
        origem:      $('#fOrigem').val(),
    });
    $('#despesas-body').html('<tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Carregando…</td></tr>');

    $.getJSON('api/despesas.php?' + params, function(res) {
        if (!res.success) { $('#despesas-body').html('<tr><td colspan="8" class="text-center py-3 text-danger">Erro ao carregar.</td></tr>'); return; }
        allDespesas = res.data;
        renderDespesas(res);
    });
}

function renderDespesas(res) {
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
        const isParcelado = parseInt(d.parcelado) === 1;
        const total_parc  = parseInt(d.parcelas_total || 0);
        const pagas       = parseInt(d.parcelas_pagas || 0);
        const pendentes   = parseInt(d.parcelas_pendentes || 0);

        let parcelasCell = '—';
        if (isParcelado && total_parc > 0) {
            const pctPago = total_parc > 0 ? Math.round(pagas / total_parc * 100) : 0;
            const prox    = d.prox_vencimento ? fmtDate(d.prox_vencimento) : null;
            parcelasCell = `
            <div>
              <span class="badge parcelas-badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"
                    onclick="openParcelas(${d.id})" title="Ver parcelas">
                ${pagas}/${total_parc} pagas
              </span>
              ${pendentes > 0 && prox ? `<div class="text-muted" style="font-size:.7rem">próx: ${prox}</div>` : ''}
            </div>`;
        } else if (!isParcelado && d.data_vencimento) {
            parcelasCell = `<span class="text-muted small">Venc: ${fmtDate(d.data_vencimento)}</span>`;
        }

        return `<tr>
          <td class="text-nowrap">${fmtDate(d.data_despesa)}</td>
          <td>
            <div>${d.descricao.length > 45 ? d.descricao.substring(0,45)+'…' : d.descricao}</div>
            ${d.sub_rubrica ? `<div class="text-muted" style="font-size:.72rem">${d.sub_rubrica}</div>` : ''}
          </td>
          <td><span class="badge cat-badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">${d.categoria}</span></td>
          <td class="text-muted small">${d.fornecedor || '—'}</td>
          <td class="text-end fw-600 text-nowrap">
            R$ ${fmtBRL(d.valor)}
            <span class="badge ${ORIGEM_COLOR[d.origem] || 'bg-light text-dark'} border ms-1 small" style="font-size:.65rem">${ORIGEM_LABEL[d.origem] || d.origem}</span>
          </td>
          <td>${parcelasCell}</td>
          <td><span class="badge ${STATUS_BADGE[d.status_pagamento] || ''}">${STATUS_LABEL[d.status_pagamento] || d.status_pagamento}</span></td>
          <td class="text-center">
            ${isParcelado
                ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="openParcelas(${d.id})" title="Parcelas"><i class="bi bi-calendar2-week"></i></button>`
                : `<button class="btn btn-sm btn-outline-secondary me-1" onclick="openEditDesp(${d.id})" title="Editar"><i class="bi bi-pencil"></i></button>`
            }
            <button class="btn btn-sm btn-outline-danger" onclick="openExcluirDesp(${d.id}, '${d.descricao.substring(0,30).replace(/'/g,"\\'")}', ${isParcelado ? total_parc : 0})" title="Excluir"><i class="bi bi-trash"></i></button>
          </td>
        </tr>`;
    }).join('');

    $('#despesas-body').html(rows);
    $('#despesas-tfoot').html(`
      <tr class="table-light fw-700">
        <td colspan="4"><strong>TOTAL (${allDespesas.length} itens)</strong></td>
        <td class="text-end">R$ ${fmtBRL(res.stats.total)}</td>
        <td colspan="3"></td>
      </tr>`);
}

// ══════════════════════════════════════════════════════════════════════════════
// Modal Nova/Editar Despesa
// ══════════════════════════════════════════════════════════════════════════════

// Toggle pagamento único / parcelado
$('input[name="tipoPag"]').on('change', function() {
    if (this.value === 'parcelado') {
        $('#secaoUnico').hide();
        $('#secaoParcelado').show();
    } else {
        $('#secaoUnico').show();
        $('#secaoParcelado').hide();
    }
    updateTotalDisplay();
});

// ── Gerador de parcelas ───────────────────────────────────────────────────── //
$('#genIntervalo').on('change', function() {
    $('#colGenDias').toggle(this.value === 'custom');
});

function gerarParcelas() {
    const qtd     = parseInt($('#genQtd').val());
    const total   = parseFloat($('#genTotal').val());
    const primeir = $('#genPrimeiroVenc').val();
    const interv  = $('#genIntervalo').val();
    const dias    = parseInt($('#genDias').val()) || 30;

    if (!qtd || qtd < 1)   { alert('Informe o número de parcelas.'); return; }
    if (!total || total<=0) { alert('Informe o valor total.'); return; }
    if (!primeir)           { alert('Informe o 1º vencimento.'); return; }

    const valorParcela = total / qtd;
    const rows = [];

    for (let i = 0; i < qtd; i++) {
        const d = new Date(primeir + 'T00:00:00');
        if (i > 0) {
            if      (interv === 'monthly')   d.setMonth(d.getMonth() + i);
            else if (interv === 'weekly')    d.setDate(d.getDate() + 7 * i);
            else if (interv === 'biweekly')  d.setDate(d.getDate() + 15 * i);
            else                             d.setDate(d.getDate() + dias * i);
        }
        const venc = d.toISOString().split('T')[0];
        // Última parcela pega o restante para evitar arredondamento
        const val = (i === qtd - 1) ? (total - valorParcela * (qtd - 1)) : valorParcela;
        rows.push({ venc, val: Math.round(val * 100) / 100 });
    }

    $('#parcelasBody').empty();
    rows.forEach((r, i) => addParcelaRow(i + 1, r.venc, r.val));
    updateParcelasTotal();
}

// ── Adicionar linha de parcela ────────────────────────────────────────────── //
let parcelaRowId = 0;
function addParcelaRow(num, venc, val, obs) {
    parcelaRowId++;
    const rid = 'pr_' + parcelaRowId;
    const idx = $('#parcelasBody tr').length + 1;
    const numDisp = num || idx;
    const row = `<tr id="${rid}">
      <td class="text-center text-muted small fw-600">${numDisp}</td>
      <td><input type="date" class="form-control form-control-sm parcela-venc" value="${venc || ''}" required></td>
      <td><input type="number" class="form-control form-control-sm parcela-val text-end" min="0.01" step="0.01" value="${val ? val.toFixed(2) : ''}" placeholder="0,00" required></td>
      <td><input type="text" class="form-control form-control-sm parcela-obs" value="${obs || ''}" placeholder="Opcional"></td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btn-remover-parcela" data-rid="${rid}">
          <i class="bi bi-x"></i>
        </button>
      </td>
    </tr>`;
    $('#parcelasBody').append(row);
    updateParcelasTotal();
}

$(document).on('click', '.btn-remover-parcela', function() {
    $('#' + $(this).data('rid')).remove();
    renumberRows();
    updateParcelasTotal();
});

$(document).on('input', '.parcela-val', updateParcelasTotal);

function renumberRows() {
    $('#parcelasBody tr').each(function(i) {
        $(this).find('td:first').text(i + 1);
    });
}

function updateParcelasTotal() {
    let total = 0;
    $('.parcela-val').each(function() { total += parseFloat(this.value) || 0; });
    $('#parcelasTotal').text('R$ ' + fmtBRL(total));
    updateTotalDisplay();
}

function updateTotalDisplay() {
    const isParc = $('input[name="tipoPag"]:checked').val() === 'parcelado';
    if (isParc) {
        let t = 0;
        $('.parcela-val').each(function() { t += parseFloat(this.value) || 0; });
        const n = $('#parcelasBody tr').length;
        $('#despTotalDisplay').html(n > 0 ? `<strong>${n}x</strong> parcela${n>1?'s':''} — Total: <strong>R$ ${fmtBRL(t)}</strong>` : '');
    } else {
        const v = parseFloat($('#despValor').val()) || 0;
        $('#despTotalDisplay').html(v > 0 ? `Total: <strong>R$ ${fmtBRL(v)}</strong>` : '');
    }
}
$('#despValor').on('input', updateTotalDisplay);

// ── Limpar modal ao fechar ────────────────────────────────────────────────── //
$('#modalNovaDespesa').on('hidden.bs.modal', function() {
    $('#despTitulo').text('Nova Despesa');
    $('#despId,#despDescricao,#despSubrubrica,#despValor,#despFornecedor,#despObs').val('');
    $('#despCategoria').val('');
    $('#despData').val('<?= date('Y-m-d') ?>');
    $('#despVencimento').val('');
    $('#despStatus').val('pendente');
    $('#despOrigem').val('manual');
    $('#tipoPagUnico').prop('checked', true);
    $('#secaoUnico').show();
    $('#secaoParcelado').hide();
    $('#parcelasBody').empty();
    updateParcelasTotal();
    $('#despTotalDisplay').html('');
    // Reset gerador
    $('#genQtd,#genTotal,#genPrimeiroVenc').val('');
    $('#genIntervalo').val('monthly');
});

// ── Abrir edição ──────────────────────────────────────────────────────────── //
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
    $('#despOrigem').val(d.origem || 'manual');
    // Despesas parceladas → abrem pelo modal de parcelas
    updateTotalDisplay();
    new bootstrap.Modal(document.getElementById('modalNovaDespesa')).show();
}

// ── Salvar despesa ────────────────────────────────────────────────────────── //
$('#btnSalvarDesp').on('click', function() {
    const desc      = $('#despDescricao').val().trim();
    const cat       = $('#despCategoria').val();
    const isParc    = $('input[name="tipoPag"]:checked').val() === 'parcelado';

    if (!desc || !cat) { alert('Preencha descrição e categoria.'); return; }

    let parcelas = [];
    if (isParc) {
        let ok = true;
        $('#parcelasBody tr').each(function() {
            const venc = $(this).find('.parcela-venc').val();
            const val  = parseFloat($(this).find('.parcela-val').val());
            const obs  = $(this).find('.parcela-obs').val().trim();
            if (!venc || isNaN(val) || val <= 0) { ok = false; return false; }
            parcelas.push({ vencimento: venc, valor: val, observacao: obs || null });
        });
        if (!ok) { alert('Verifique vencimentos e valores das parcelas.'); return; }
        if (!parcelas.length) { alert('Adicione pelo menos uma parcela.'); return; }
    } else {
        const val = parseFloat($('#despValor').val());
        if (isNaN(val) || val <= 0) { alert('Informe o valor.'); return; }
    }

    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

    const payload = {
        action:          $('#despId').val() ? 'update' : 'create',
        id:              $('#despId').val() || null,
        event_id:        EVENT_ID,
        descricao:       desc,
        categoria:       cat,
        sub_rubrica:     $('#despSubrubrica').val().trim() || null,
        fornecedor:      $('#despFornecedor').val().trim() || null,
        data_despesa:    $('#despData').val() || null,
        data_vencimento: isParc ? null : ($('#despVencimento').val() || null),
        status_pagamento: isParc ? 'pendente' : ($('#despStatus').val() || 'pendente'),
        observacao:      $('#despObs').val().trim() || null,
        origem:          $('#despOrigem').val() || 'manual',
        valor:           isParc ? 0 : parseFloat($('#despValor').val()),
        parcelas:        isParc ? parcelas : [],
    };

    $.ajax({
        url: 'api/despesas.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        success: function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalNovaDespesa')).hide();
                loadDespesas();
            } else { alert('Erro: ' + res.message); }
        },
        complete: function() {
            $('#btnSalvarDesp').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Salvar');
        }
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// Modal Parcelas — visualizar e pagar
// ══════════════════════════════════════════════════════════════════════════════

function openParcelas(expenseId) {
    $('#parcelasModalBody').html('<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Carregando…</div>');
    new bootstrap.Modal(document.getElementById('modalParcelas')).show();

    $.getJSON('api/parcelas.php?expense_id=' + expenseId, function(res) {
        if (!res.success) {
            $('#parcelasModalBody').html('<div class="text-center py-3 text-danger">Erro ao carregar.</div>');
            return;
        }
        const d = res.despesa;
        const parcelas = res.parcelas;

        $('#parcelasModalTitulo').text(d.descricao);
        $('#parcelasModalSub').text(d.categoria + ' · Total: R$ ' + fmtBRL(d.valor));

        const today = new Date(); today.setHours(0,0,0,0);
        let totalPago = 0, totalPendente = 0;

        const rows = parcelas.map(p => {
            const venc = new Date(p.vencimento + 'T00:00:00');
            const isVencida = venc < today && p.status_pagamento === 'pendente';
            if (p.status_pagamento === 'pago') totalPago += parseFloat(p.valor);
            if (p.status_pagamento === 'pendente') totalPendente += parseFloat(p.valor);

            const statusBadge = `<span class="badge sp-${p.status_pagamento}">${
                p.status_pagamento === 'pago' ? 'Pago' : p.status_pagamento === 'cancelado' ? 'Cancelado' : isVencida ? '⚠ Vencida' : 'Pendente'
            }</span>`;

            const acoes = p.status_pagamento === 'pendente'
                ? `<button class="btn btn-pagar btn-success" onclick="marcarPago(${p.id}, ${expenseId})"><i class="bi bi-check2 me-1"></i>Pagar</button>
                   <button class="btn btn-pagar btn-outline-danger ms-1" onclick="cancelarParcela(${p.id}, ${expenseId})"><i class="bi bi-x"></i></button>`
                : p.status_pagamento === 'pago'
                ? `<button class="btn btn-pagar btn-outline-secondary" onclick="desfazerPagamento(${p.id}, ${expenseId})"><i class="bi bi-arrow-counterclockwise"></i></button>`
                : '';

            return `<tr class="${isVencida ? 'table-warning' : ''}">
              <td class="text-center fw-600 text-muted">${p.numero}</td>
              <td class="text-nowrap ${isVencida ? 'text-danger fw-600' : ''}">${fmtDate(p.vencimento)}</td>
              <td class="text-end fw-600">R$ ${fmtBRL(p.valor)}</td>
              <td>${statusBadge}</td>
              <td class="text-nowrap">${p.data_pagamento ? fmtDate(p.data_pagamento) : '—'}</td>
              <td>${acoes}</td>
            </tr>`;
        }).join('');

        const pctPago = d.valor > 0 ? Math.min(100, totalPago / d.valor * 100) : 0;
        $('#parcelasModalResumo').html(
            `Pago: <strong class="text-success">R$ ${fmtBRL(totalPago)}</strong> · ` +
            `Pendente: <strong class="text-warning">R$ ${fmtBRL(totalPendente)}</strong>`
        );

        $('#parcelasModalBody').html(`
        <div class="px-3 pt-3 pb-2">
          <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Progresso de pagamento</span>
            <span class="fw-600">${pctPago.toFixed(1)}% pago</span>
          </div>
          <div class="progress budget-progress pct-ok" style="height:10px">
            <div class="progress-bar" style="width:${pctPago.toFixed(1)}%"></div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0" style="font-size:.85rem">
            <thead>
              <tr>
                <th style="width:40px">#</th>
                <th>Vencimento</th>
                <th class="text-end">Valor</th>
                <th>Status</th>
                <th>Pago em</th>
                <th style="width:140px">Ações</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>`);
    });
}

function marcarPago(parcelaId, expenseId) {
    if (!confirm('Confirmar pagamento desta parcela?')) return;
    $.ajax({
        url: 'api/parcelas.php', method: 'POST', contentType: 'application/json',
        data: JSON.stringify({ action: 'update_status', id: parcelaId, status: 'pago', data_pagamento: new Date().toISOString().split('T')[0] }),
        success: function(res) {
            if (res.success) { openParcelas(expenseId); loadDespesas(); }
            else alert('Erro: ' + res.message);
        }
    });
}

function desfazerPagamento(parcelaId, expenseId) {
    if (!confirm('Desfazer pagamento?')) return;
    $.ajax({
        url: 'api/parcelas.php', method: 'POST', contentType: 'application/json',
        data: JSON.stringify({ action: 'update_status', id: parcelaId, status: 'pendente' }),
        success: function(res) {
            if (res.success) { openParcelas(expenseId); loadDespesas(); }
            else alert('Erro: ' + res.message);
        }
    });
}

function cancelarParcela(parcelaId, expenseId) {
    if (!confirm('Cancelar esta parcela?')) return;
    $.ajax({
        url: 'api/parcelas.php', method: 'POST', contentType: 'application/json',
        data: JSON.stringify({ action: 'update_status', id: parcelaId, status: 'cancelado' }),
        success: function(res) {
            if (res.success) { openParcelas(expenseId); loadDespesas(); }
            else alert('Erro: ' + res.message);
        }
    });
}

// ══════════════════════════════════════════════════════════════════════════════
// Excluir despesa
// ══════════════════════════════════════════════════════════════════════════════
function openExcluirDesp(id, nome, totalParcelas) {
    $('#excDespId').val(id);
    $('#excDespNome').text(nome + '…');
    $('#excDespParcelasNote').text(totalParcelas > 0
        ? `⚠ Esta despesa possui ${totalParcelas} parcela(s) que também serão excluídas.` : '');
    new bootstrap.Modal(document.getElementById('modalExcluirDesp')).show();
}

$('#btnExcluirDesp').on('click', function() {
    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({
        url: 'api/despesas.php', method: 'POST', contentType: 'application/json',
        data: JSON.stringify({ action: 'delete', id: $('#excDespId').val() }),
        success: function(res) {
            if (res.success) { bootstrap.Modal.getInstance(document.getElementById('modalExcluirDesp')).hide(); loadDespesas(); }
            else alert('Erro: ' + res.message);
        },
        complete: function() { $('#btnExcluirDesp').prop('disabled', false).html('<i class="bi bi-trash me-1"></i>Excluir'); }
    });
});

$(function() { loadDespesas(); });
</script>

<?php require_once 'footer.php'; ?>
