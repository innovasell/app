<?php
define('PAGE_CURRENT', 'evento_overview');
require_once 'conexao.php';
require_once 'auth.php';
require_login();

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$event_id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM evt_events WHERE id = ?");
$stmt->execute([$event_id]);
$active_event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$active_event) { header('Location: index.php'); exit; }

define('PAGE_TITLE', $active_event['nome']);
require_once 'header.php';

$statusLabels = ['planejamento' => 'Planejamento', 'em_execucao' => 'Em Execução', 'encerrado' => 'Encerrado'];
?>

<div class="container-fluid px-4 py-4">

    <!-- Cards de stats (populados via JS) -->
    <div class="row g-3 mb-4" id="statsCards">
        <?php foreach ([
            ['id'=>'statOrcado',   'icon'=>'bi-wallet2',          'bg'=>'bg-primary bg-opacity-10', 'color'=>'text-primary',  'label'=>'Orçamento Total'],
            ['id'=>'statRealizado','icon'=>'bi-cash-stack',        'bg'=>'bg-success bg-opacity-10', 'color'=>'text-success',  'label'=>'Total Realizado'],
            ['id'=>'statSaldo',    'icon'=>'bi-arrow-down-up',     'bg'=>'bg-info bg-opacity-10',    'color'=>'text-info',     'label'=>'Saldo Disponível'],
            ['id'=>'statPendente', 'icon'=>'bi-hourglass-split',   'bg'=>'bg-warning bg-opacity-10', 'color'=>'text-warning',  'label'=>'Aguardando Pagamento'],
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

    <div class="row g-4">

        <!-- Coluna esquerda: Progresso por categoria + Ações rápidas -->
        <div class="col-lg-5">

            <!-- Progresso por categoria de orçamento -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-bar-chart-steps me-2 text-success"></i>Orçamento por Categoria</span>
                    <a href="orcamento.php?event_id=<?= $event_id ?>" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-gear"></i> Gerenciar
                    </a>
                </div>
                <div class="card-body p-0" id="budgetProgress">
                    <div class="text-center py-4 text-muted">
                        <div class="spinner-border spinner-border-sm"></div>
                    </div>
                </div>
            </div>

            <!-- Ações rápidas -->
            <div class="card">
                <div class="card-header"><i class="bi bi-lightning-charge me-2 text-warning"></i>Ações Rápidas</div>
                <div class="card-body p-3">
                    <div class="d-grid gap-2">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNovaDespesa">
                            <i class="bi bi-plus-circle me-2"></i>Registrar Despesa
                        </button>
                        <a href="orcamento.php?event_id=<?= $event_id ?>" class="btn btn-outline-primary">
                            <i class="bi bi-wallet2 me-2"></i>Gerenciar Orçamento
                        </a>
                        <a href="importar.php?event_id=<?= $event_id ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-cloud-upload me-2"></i>Importar CSV (Viagem Express / Uber)
                        </a>
                        <a href="despesas.php?event_id=<?= $event_id ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-receipt-cutoff me-2"></i>Ver Todas as Despesas
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Coluna direita: Despesas recentes + Info do evento -->
        <div class="col-lg-7">

            <!-- Informações do evento -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-info-circle me-2 text-primary"></i>Informações do Evento</span>
                    <button class="btn btn-sm btn-outline-primary" onclick="openEditEvento()">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-2 small">
                        <?php
                        $fmtDate = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';
                        $info = [
                            ['bi-calendar3',    'Período',      trim($fmtDate($active_event['data_inicio']) . ($active_event['data_fim'] ? ' → ' . $fmtDate($active_event['data_fim']) : ''))],
                            ['bi-geo-alt',       'Local',        $active_event['local_evento'] ?: '—'],
                            ['bi-person',        'Responsável',  $active_event['responsavel'] ?: '—'],
                            ['bi-percent',       'Contingência', number_format($active_event['contingencia_pct'], 1) . '%'],
                        ];
                        foreach ($info as [$icon, $label, $value]): ?>
                        <div class="col-sm-6 d-flex gap-2 align-items-start py-1">
                            <i class="bi <?= $icon ?> text-muted mt-1 flex-shrink-0"></i>
                            <div>
                                <div class="text-muted" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.4px"><?= $label ?></div>
                                <div class="fw-600"><?= htmlspecialchars($value) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($active_event['objetivo']): ?>
                        <div class="col-12 pt-2 border-top mt-1">
                            <div class="text-muted mb-1" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.4px">Objetivo</div>
                            <p class="mb-0 small"><?= nl2br(htmlspecialchars($active_event['objetivo'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Despesas recentes -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history me-2 text-muted"></i>Despesas Recentes</span>
                    <a href="despesas.php?event_id=<?= $event_id ?>" class="btn btn-sm btn-outline-secondary">
                        Ver todas <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div id="recentExpenses">
                        <div class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ── Modal: Editar Evento ───────────────────────────────────────────────── -->
<div class="modal fade" id="modalEditEvento" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark-blue">
                <h5 class="modal-title text-white"><i class="bi bi-pencil me-2"></i>Editar Evento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nome do Evento *</label>
                        <input type="text" class="form-control" id="editNome" value="<?= htmlspecialchars($active_event['nome']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Data de Início</label>
                        <input type="date" class="form-control" id="editDataInicio" value="<?= $active_event['data_inicio'] ?: '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Data de Término</label>
                        <input type="date" class="form-control" id="editDataFim" value="<?= $active_event['data_fim'] ?: '' ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Local</label>
                        <input type="text" class="form-control" id="editLocal" value="<?= htmlspecialchars($active_event['local_evento'] ?: '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="editStatus">
                            <?php foreach (['planejamento', 'em_execucao', 'encerrado'] as $s): ?>
                            <option value="<?= $s ?>" <?= $active_event['status'] === $s ? 'selected' : '' ?>>
                                <?= $statusLabels[$s] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Responsável</label>
                        <input type="text" class="form-control" id="editResponsavel" value="<?= htmlspecialchars($active_event['responsavel'] ?: '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Orçamento Total (R$)</label>
                        <input type="number" class="form-control" id="editOrcamento" min="0" step="0.01" value="<?= $active_event['orcamento_total'] ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contingência (%)</label>
                        <input type="number" class="form-control" id="editContingencia" min="0" max="100" step="0.5" value="<?= $active_event['contingencia_pct'] ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Objetivo</label>
                        <textarea class="form-control" id="editObjetivo" rows="2"><?= htmlspecialchars($active_event['objetivo'] ?: '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnSalvarEdicao">
                    <i class="bi bi-check-lg me-1"></i>Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Nova Despesa Rápida ─────────────────────────────────────────── -->
<div class="modal fade" id="modalNovaDespesa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-green">
                <h5 class="modal-title text-white"><i class="bi bi-plus-circle me-2"></i>Registrar Despesa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Descrição *</label>
                        <input type="text" class="form-control" id="nd_descricao" placeholder="Ex: Buffet coquetel de abertura">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Categoria *</label>
                        <select class="form-select" id="nd_categoria">
                            <option value="">Selecione…</option>
                            <option>Transporte</option>
                            <option>Hospedagem</option>
                            <option>Alimentação e Bebidas</option>
                            <option>Contratos e Fornecedores</option>
                            <option>Materiais e Insumos</option>
                            <option>Infraestrutura e Locação</option>
                            <option>Comunicação Visual</option>
                            <option>RH e Temporários</option>
                            <option>Imprevistos</option>
                            <option>Outros</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Valor (R$) *</label>
                        <input type="number" class="form-control" id="nd_valor" min="0.01" step="0.01" placeholder="0,00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Data da Despesa</label>
                        <input type="date" class="form-control" id="nd_data" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fornecedor</label>
                        <input type="text" class="form-control" id="nd_fornecedor" placeholder="Nome do fornecedor">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observação</label>
                        <textarea class="form-control" id="nd_obs" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnSalvarDespesa">
                    <i class="bi bi-check-lg me-1"></i>Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const EVENT_ID = <?= $event_id ?>;
const fmtBRL = v => parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '—';

const STATUS_PAG_BADGE = {
    pendente:  'badge-pendente',
    aprovado:  'badge-aprovado',
    pago:      'badge-pago',
    cancelado: 'badge-cancelado',
};
const STATUS_PAG_LABEL = { pendente:'Pendente', aprovado:'Aprovado', pago:'Pago', cancelado:'Cancelado' };

// ── Carregar stats e progresso de orçamento ───────────────────────────────── //
function loadSummary() {
    $.getJSON('api/budget.php?action=summary&event_id=' + EVENT_ID, function(res) {
        if (!res.success) return;
        const s = res.summary;

        $('#statOrcado').text('R$ ' + fmtBRL(s.orcado_total));
        $('#statRealizado').text('R$ ' + fmtBRL(s.realizado_total));
        const saldo = s.orcado_total - s.realizado_total;
        $('#statSaldo').html('<span class="' + (saldo < 0 ? 'text-danger' : 'text-success') + '">R$ ' + fmtBRL(saldo) + '</span>');
        $('#statPendente').text('R$ ' + fmtBRL(s.pendente_total));

        // Progresso por categoria
        if (!res.categories || !res.categories.length) {
            $('#budgetProgress').html('<div class="text-center py-4 text-muted small">Nenhuma rubrica cadastrada.<br><a href="orcamento.php?event_id=' + EVENT_ID + '">Cadastrar orçamento →</a></div>');
            return;
        }

        const rows = res.categories.map(c => {
            const pct = c.orcado > 0 ? Math.min(100, (c.realizado / c.orcado) * 100) : 0;
            const pctCls = pct >= 100 ? 'pct-danger' : pct >= 90 ? 'pct-warning' : 'pct-ok';
            return `
            <div class="budget-row px-4 py-3 border-bottom">
              <div class="d-flex justify-content-between small mb-1">
                <span class="fw-600">${c.categoria}</span>
                <span class="text-muted">R$ ${fmtBRL(c.realizado)} / R$ ${fmtBRL(c.orcado)}</span>
              </div>
              <div class="progress budget-progress ${pctCls}">
                <div class="progress-bar" style="width:${pct.toFixed(1)}%"></div>
              </div>
              <div class="text-end small text-muted mt-1">${pct.toFixed(1)}%</div>
            </div>`;
        }).join('');
        $('#budgetProgress').html(rows);
    });
}

// ── Carregar despesas recentes ─────────────────────────────────────────────── //
function loadRecentExpenses() {
    $.getJSON('api/despesas.php?action=list&event_id=' + EVENT_ID + '&limit=8', function(res) {
        if (!res.success || !res.data.length) {
            $('#recentExpenses').html('<div class="text-center py-4 text-muted small">Nenhuma despesa registrada.</div>');
            return;
        }
        const rows = res.data.map(d => `
        <tr>
          <td>${fmtDate(d.data_despesa)}</td>
          <td>${d.descricao.length > 40 ? d.descricao.substring(0,40)+'…' : d.descricao}</td>
          <td><span class="cat-badge bg-light text-dark border">${d.categoria}</span></td>
          <td class="text-end fw-600">R$ ${fmtBRL(d.valor)}</td>
          <td><span class="badge ${STATUS_PAG_BADGE[d.status_pagamento] || ''}">${STATUS_PAG_LABEL[d.status_pagamento] || d.status_pagamento}</span></td>
        </tr>`).join('');

        $('#recentExpenses').html(`
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th class="text-end">Valor</th><th>Status</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>`);
    });
}

// ── Editar evento ─────────────────────────────────────────────────────────── //
function openEditEvento() {
    new bootstrap.Modal(document.getElementById('modalEditEvento')).show();
}

$('#btnSalvarEdicao').on('click', function() {
    const nome = $('#editNome').val().trim();
    if (!nome) { alert('Informe o nome.'); return; }

    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({
        url: 'api/eventos.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'update', id: EVENT_ID,
            nome: nome,
            data_inicio: $('#editDataInicio').val() || null,
            data_fim: $('#editDataFim').val() || null,
            local_evento: $('#editLocal').val().trim() || null,
            status: $('#editStatus').val(),
            responsavel: $('#editResponsavel').val().trim() || null,
            orcamento_total: parseFloat($('#editOrcamento').val()) || 0,
            contingencia_pct: parseFloat($('#editContingencia').val()) || 5,
            objetivo: $('#editObjetivo').val().trim() || null,
        }),
        success: function(res) {
            if (res.success) location.reload();
            else alert('Erro: ' + res.message);
        },
        complete: function() {
            $('#btnSalvarEdicao').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Salvar');
        }
    });
});

// ── Salvar despesa rápida ─────────────────────────────────────────────────── //
$('#btnSalvarDespesa').on('click', function() {
    const desc = $('#nd_descricao').val().trim();
    const cat  = $('#nd_categoria').val();
    const val  = parseFloat($('#nd_valor').val());
    if (!desc || !cat || !val) { alert('Preencha descrição, categoria e valor.'); return; }

    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({
        url: 'api/despesas.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'create',
            event_id: EVENT_ID,
            descricao: desc,
            categoria: cat,
            valor: val,
            data_despesa: $('#nd_data').val() || null,
            fornecedor: $('#nd_fornecedor').val().trim() || null,
            observacao: $('#nd_obs').val().trim() || null,
        }),
        success: function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalNovaDespesa')).hide();
                $('#nd_descricao,#nd_valor,#nd_fornecedor,#nd_obs').val('');
                $('#nd_categoria').val('');
                loadSummary();
                loadRecentExpenses();
            } else { alert('Erro: ' + res.message); }
        },
        complete: function() {
            $('#btnSalvarDespesa').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Salvar');
        }
    });
});

$(function() { loadSummary(); loadRecentExpenses(); });
</script>

<?php require_once 'footer.php'; ?>
