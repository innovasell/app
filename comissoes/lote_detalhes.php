<?php
// Previne cache agressivo do navegador e LiteSpeed (útil para que atualizações no JS inline tenham efeito imediato e dados do DB sejam sempre frescos)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/../sistema-cotacoes/conexao.php';

$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
if (!$batch_id) {
    header('Location: validacao.php');
    exit;
}

$stmtBatch = $pdo->prepare("SELECT * FROM com_commission_batches WHERE id = ?");
$stmtBatch->execute([$batch_id]);
$batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);
if (!$batch) {
    die('<div class="alert alert-danger m-5">Lote não encontrado.</div>');
}

$stmtReps = $pdo->prepare("SELECT DISTINCT representante FROM com_commission_items WHERE batch_id = ? AND representante != '' ORDER BY representante");
$stmtReps->execute([$batch_id]);
$representantes = $stmtReps->fetchAll(PDO::FETCH_COLUMN);

$pagina_ativa = 'validacao'; // Ativo em Validação pois é sub-página
require_once __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<style>
    @media print { .no-print { display:none!important; } body { font-size:0.75rem; } }
</style>

    <div class="container-fluid px-4 py-3">

        <!-- Cabeçalho do Lote -->
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <div>
                <a href="validacao.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Voltar</a>
                <h4 class="d-inline text-primary">Lote #<?= $batch_id ?> — <?= htmlspecialchars($batch['nome'] ?? 'Sem nome') ?></h4>
                <small class="text-muted ms-2"><?= date('d/m/Y H:i', strtotime($batch['created_at'])) ?></small>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-success" onclick="gerarPDF()"><i class="bi bi-file-earmark-pdf"></i> Gerar PDF</button>
                <button class="btn btn-outline-primary" onclick="gerarPDFResumo()" title="Resumo agrupado por representante"><i class="bi bi-bar-chart-line"></i> Resumo PDF</button>
                <button class="btn btn-outline-info" onclick="gerarPDFAuditoria()" title="Etapas de cálculo para auditoria"><i class="fas fa-file-contract"></i> Auditoria PDF</button>
                <button class="btn btn-outline-warning" onclick="reprocessarLote()" title="Recalcula comissões com PTAX e PM corretos"><i class="fas fa-sync-alt"></i> Reprocessar Lote</button>
                <a href="api/export_commission.php?batch_id=<?= $batch_id ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Exportar CSV</a>
            </div>
        </div>

        <!-- Cards Resumo -->
        <div class="row mb-3" id="cardsResumo"></div>

        <!-- Filtros -->
        <div class="card shadow-sm mb-3 no-print">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-3 align-items-center filter-bar">
                    <strong>Filtrar :</strong>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="filtroStatus" id="fTodos" value="todos" checked>
                        <label class="btn btn-outline-secondary" for="fTodos">Todos</label>
                        <input type="radio" class="btn-check" name="filtroStatus" id="fOk" value="ok">
                        <label class="btn btn-outline-success" for="fOk">✔ OK</label>
                        <input type="radio" class="btn-check" name="filtroStatus" id="fAprov" value="aprovacao">
                        <label class="btn btn-outline-danger" for="fAprov">⚠ Aprovação</label>
                        <input type="radio" class="btn-check" name="filtroStatus" id="fTeto" value="teto">
                        <label class="btn btn-outline-warning" for="fTeto">★ Teto</label>
                        <input type="radio" class="btn-check" name="filtroStatus" id="fSL" value="sem_lista">
                        <label class="btn btn-outline-secondary" for="fSL">S/ Lista</label>
                    </div>
                    <div class="d-flex flex-column align-items-start gap-1">
                        <select class="form-select form-select-sm" id="filtroRep" multiple size="3" style="width:250px; font-size: 0.8rem;">
                            <option value="">(Todos os Representantes)</option>
                            <?php foreach($representantes as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted" style="font-size: 0.7rem;">Segure CTRL para múltipla seleção</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick="aplicarFiltros()"><i class="bi bi-funnel"></i> Aplicar</button>
                    <button class="btn btn-sm btn-outline-warning ms-2" onclick="diagnoseSemLista()" title="Analisar por que produtos S/Lista não foram encontrados na price list">
                        <i class="fas fa-microscope"></i> Diagnóstico S/Lista
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabela -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tblLote" class="table table-striped table-hover align-middle mb-0" style="font-size:0.82rem;">
                        <thead class="table-dark">
                            <tr>
                                <th>Representante</th>
                                <th>Data / NF</th>
                                <th>Código / Emb.</th>
                                <th>Cliente</th>
                                <th>Venda Net</th>
                                <th>P.Lista(BRL)</th>
                                <th>Desc%</th>
                                <th>% Base</th>
                                <th>PM(d)</th>
                                <th>% Final</th>
                                <th class="text-end">Comissão</th>
                                <th>Status</th>
                                <th class="no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyLote">
                            <tr><td colspan="13" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ▶ PAINEL DE DIAGNÓSTICO S/LISTA ◀ -->
        <div class="card shadow-sm mt-3 no-print border-warning" id="painelDiagnostico" style="display:none;">
            <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold text-warning"><i class="fas fa-microscope me-1"></i> Diagnóstico — Produtos S/ Lista</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('painelDiagnostico').style.display='none'">✕ Fechar</button>
            </div>
            <div class="card-body p-3" id="diagBody">
                <div class="text-center py-3"><div class="spinner-border text-warning"></div></div>
            </div>
        </div>

    </div>

    <!-- Modal de Edição -->
    <div class="modal fade" id="modalEdit" tabindex="-1">

    <!-- Modal Reprocessar Lote -->
    <div class="modal fade" id="modalReprocessar" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-sync-alt me-2"></i> Reprocessamento do Lote</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bodyReprocessar">
                    <div class="text-center py-4"><div class="spinner-border text-warning"></div><p class="mt-2 text-muted">Recalculando comissões...</p></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary d-none" id="btnRecarregarAposReproc" onclick="modalReprocessar.hide(); carregarDados(); aplicarFiltros();">Atualizar Tabela</button>
                </div>
            </div>
        </div>
    </div>

        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Representante</label>
                            <input type="text" class="form-control" id="editRep">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cliente</label>
                            <input type="text" class="form-control" id="editCliente">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Código</label>
                            <input type="text" class="form-control" id="editCodigo">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Embalagem</label>
                            <input type="text" class="form-control" id="editEmb">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Qtde</label>
                            <input type="number" step="0.001" class="form-control" id="editQtde">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Venda Net (R$)</label>
                            <input type="number" step="0.01" class="form-control" id="editVendaNet" oninput="previewRecalc()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">P.Lista (R$)</label>
                            <input type="number" step="0.01" class="form-control" id="editPrecoLista" oninput="previewRecalc()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">P.Net Unitário (R$)</label>
                            <input type="number" step="0.01" class="form-control" id="editPrecoNet" oninput="previewRecalc()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">PM (dias)</label>
                            <input type="number" step="1" class="form-control" id="editPm" oninput="previewRecalc()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Obs</label>
                            <input type="text" class="form-control" id="editObs">
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0" id="previewCalc" style="font-size:0.83rem;">
                                ← Altere valores para ver o recálculo de comissão
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-danger me-auto" onclick="deletarItem()"><i class="bi bi-trash"></i> Excluir</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarEdicao()"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Seleção de Embalagem -->
    <div class="modal fade" id="modalSelectEmb" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-box-open"></i> Selecionar Embalagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="selDivergId">
                    <input type="hidden" id="selDivergPtax">
                    <p class="mb-2"><strong>Produto:</strong> <span id="selDivergProdNfe"></span></p>
                    <p class="mb-3 text-muted small">Escolha a embalagem correspondente na Price List para este item.</p>
                    
                    <label class="form-label fw-bold">Embalagem Disponível</label>
                    <select class="form-select" id="selDivergOpcoes"></select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="salvarSelecaoEmb()"><i class="bi bi-check-lg"></i> Associar Embalagem</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

    <script>
    const BATCH_ID = <?= $batch_id ?>;
    let allItems = [];
    let filteredItems = [];
    let dtTable = null;
    let itensDivergentesGlobais = []; // Armazena os itens divergentes para o jsPDF
    let itensParaReprocessarGlobais = []; // Armazena os itens para reprocessamento
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEdit'));
    const modalSelectEmb = new bootstrap.Modal(document.getElementById('modalSelectEmb'));
    const modalReprocessar = new bootstrap.Modal(document.getElementById('modalReprocessar'));

    // === REPROCESSAR LOTE ===
    async function reprocessarLote() {
        if (!confirm('Reprocessar TODOS os itens do lote com PTAX corrigida?\n\nEditações manuais (embalagem, preço lista) serão PRESERVADAS.\nItens sem Price List continuarão sem comissão.')) return;

        const body = document.getElementById('bodyReprocessar');
        const btnAtualizar = document.getElementById('btnRecarregarAposReproc');
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-warning"></div><p class="mt-2 text-muted">Recalculando comissões e buscando PTAX...</p></div>';
        btnAtualizar.classList.add('d-none');
        modalReprocessar.show();

        try {
            const res  = await fetch('api/reprocessar_lote.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ batch_id: BATCH_ID })
            });
            const json = await res.json();

            if (!json.success) {
                body.innerHTML = `<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>${json.message}</div>`;
                return;
            }

            // ─── Resultado ────────────────────────────────────────────────────
            const iconTipos = {
                sem_lista:  { icon: 'bi-question-circle-fill', cor: 'secondary', label: 'Sem Price List' },
                sem_ptax:   { icon: 'bi-currency-dollar',      cor: 'warning',   label: 'PTAX não encontrada' },
                sem_pm:     { icon: 'bi-clock-history',        cor: 'info',      label: 'PM = 0 (baseline 28d usado)' },
                sem_preco:  { icon: 'bi-tag-fill',             cor: 'danger',    label: 'Preço lista zerado' },
            };

            let html = `
                <div class="row text-center mb-3">
                    <div class="col">
                        <div class="card bg-success text-white py-2">
                            <div class="fw-bold fs-4">${json.recalculados}</div>
                            <small>Recalculados</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card bg-secondary text-white py-2">
                            <div class="fw-bold fs-4">${json.ignorados_sem_lista}</div>
                            <small>Ignorados (S/Lista)</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card bg-warning text-dark py-2">
                            <div class="fw-bold fs-4">${json.warnings.length}</div>
                            <small>Warnings</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card bg-dark text-white py-2">
                            <div class="fw-bold fs-4">${json.total}</div>
                            <small>Total</small>
                        </div>
                    </div>
                </div>`;

            if (json.warnings.length > 0) {
                html += `<hr><h6 class="text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i> Atenção — Itens que precisam de revisão manual:</h6>`;

                // Agrupa por tipo
                const grupos = {};
                json.warnings.forEach(w => {
                    if (!grupos[w.tipo]) grupos[w.tipo] = [];
                    grupos[w.tipo].push(w);
                });

                for (const [tipo, lista] of Object.entries(grupos)) {
                    const t = iconTipos[tipo] || { icon: 'bi-info-circle', cor: 'secondary', label: tipo };
                    html += `<div class="alert alert-${t.cor === 'secondary' ? 'secondary' : t.cor} py-2 mb-2">
                        <strong><i class="bi ${t.icon} me-1"></i> ${t.label} (${lista.length})</strong>
                        <ul class="mb-0 mt-1" style="font-size:0.82rem">`;
                    lista.forEach(w => {
                        html += `<li><code>${w.nfe}</code> — ${w.msg}</li>`;
                    });
                    html += `</ul></div>`;
                }
            } else {
                html += `<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i> Todos os itens foram recalculados sem pendências!</div>`;
            }

            body.innerHTML = html;
            btnAtualizar.classList.remove('d-none');

        } catch (err) {
            body.innerHTML = `<div class="alert alert-danger">Erro de comunicação: ${err.message}</div>`;
        }
    }



    // ══════════════════════════════════════════════════════════════════════
    // PDF AUDITORIA — passo a passo por item para revisão de vendedor
    // ══════════════════════════════════════════════════════════════════════
    function gerarPDFAuditoria() {
        if (!filteredItems || filteredItems.length === 0) {
            alert('Nenhum item no filtro atual para gerar auditoria.'); return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
        const PW = doc.internal.pageSize.getWidth();
        const PH = doc.internal.pageSize.getHeight();
        const ML = 12, MR = 12;
        const usableW = PW - ML - MR;

        const fmtBRL = v => parseFloat(v||0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
        const fmtPct = v => (parseFloat(v||0)*100).toFixed(4).replace('.',',') + '%';
        const fmtN   = (v,d=2) => parseFloat(v||0).toFixed(d).replace('.',',');

        const batchNome   = document.querySelector('h4.text-primary')?.textContent || 'Lote';
        const selectedRep = Array.from(document.getElementById('filtroRep').selectedOptions)
            .map(o => o.value).filter(v => v).join(', ') || 'Todos os representantes';
        const geradoEm = new Date().toLocaleString('pt-BR');

        let y = 14, pageNum = 1;

        function cabecalho(first) {
            doc.setFillColor(10, 30, 66);
            doc.rect(0, 0, PW, 10, 'F');
            doc.setTextColor(255,255,255); doc.setFontSize(7.5);
            doc.text('RELATÓRIO DE AUDITORIA DE COMISSÕES — INNOVA', ML, 6.5);
            doc.text(`Pág. ${pageNum}`, PW-MR, 6.5, {align:'right'});
            doc.setTextColor(0,0,0);
            if (first) {
                y = 16;
                doc.setFontSize(12); doc.setFont('helvetica','bold');
                doc.text(batchNome, ML, y); y += 5.5;
                doc.setFontSize(8); doc.setFont('helvetica','normal'); doc.setTextColor(80,80,80);
                doc.text(`Representante: ${selectedRep}   |   Gerado: ${geradoEm}   |   ${filteredItems.length} item(ns)`, ML, y); y += 4.5;
                doc.setTextColor(0,0,0);
                doc.setDrawColor(10,30,66); doc.setLineWidth(0.5); doc.line(ML, y, PW-MR, y); y += 4;
            } else { y = 14; }
        }

        function checkY(needed) {
            if (y + needed > PH - 12) { doc.addPage(); pageNum++; cabecalho(false); }
        }

        cabecalho(true);

        filteredItems.forEach((item, idx) => {
            const semLista  = parseInt(item.lista_nao_encontrada||0) === 1;
            const flagAprov = parseInt(item.flag_aprovacao||0) === 1;
            const flagTeto  = parseInt(item.flag_teto||0) === 1;
            const venda_net = parseFloat(item.venda_net||0);
            const pl_brl    = parseFloat(item.preco_lista_brl||0);
            const pl_usd    = parseFloat(item.preco_lista_usd||0);
            const pnu       = parseFloat(item.preco_net_un||0);
            const dsc_pct   = parseFloat(item.desconto_pct||0);
            const base_pct  = parseFloat(item.comissao_base_pct||0);
            const pm_dias   = parseFloat(item.pm_dias||0);
            const ajuste    = parseFloat(item.ajuste_prazo_pct||0);
            const final_pct = parseFloat(item.comissao_final_pct||0);
            const comissao  = parseFloat(item.valor_comissao||0);

            checkY(60);

            // Cabeçalho do item
            doc.setFillColor(semLista ? 220:flagTeto?255:flagAprov?255:220, semLista?220:flagTeto?193:flagAprov?235:235, semLista?220:flagTeto?7:flagAprov?245:250);
            doc.rect(ML, y, usableW, 6.5, 'F');
            doc.setFont('helvetica','bold'); doc.setFontSize(8.5); doc.setTextColor(10,30,66);
            doc.text(`#${idx+1}  NF ${item.nfe}   ${item.codigo} ${item.embalagem}`, ML+2, y+4.5);
            // badge status
            let badge = semLista ? 'S/Lista' : flagTeto ? '★ Teto' : flagAprov ? '⚠ Aprovação' : '✔ OK';
            let bclr  = semLista ? [108,117,125] : flagTeto ? [133,77,14] : flagAprov ? [220,53,69] : [25,135,84];
            const bw  = doc.getTextWidth(badge) + 4;
            doc.setFillColor(...bclr);
            doc.roundedRect(PW-MR-bw-1, y+1, bw, 4, 0.8, 0.8, 'F');
            doc.setFontSize(7); doc.setTextColor(255,255,255);
            doc.text(badge, PW-MR-bw+1, y+4.3);
            doc.setTextColor(0,0,0); doc.setFont('helvetica','normal'); y += 7.5;

            // Info linha 1
            doc.setFontSize(7.5);
            doc.text(`Produto: ${(item.descricao||'').slice(0,58)}`, ML+1, y); y += 4;
            doc.text(`Cliente: ${(item.cliente||'—').slice(0,42)}`, ML+1, y);
            doc.text(`Data: ${item.data_nf||'—'}  |  CFOP: ${item.cfop||'—'}  |  Qtde: ${fmtN(item.qtde,4)} UN`, PW/2, y); y += 3.5;
            doc.text(`Representante: ${item.representante||'—'}  |  V.Bruto: R$ ${fmtBRL(item.valor_bruto)}  |  ICMS: R$ ${fmtBRL(item.icms)}  |  PIS: R$ ${fmtBRL(item.pis)}  |  COFINS: R$ ${fmtBRL(item.cofins)}`, ML+1, y); y += 4;

            doc.setDrawColor(220,220,220); doc.setLineWidth(0.2); doc.line(ML+1,y,PW-MR-1,y); y += 2.5;

            // Tabela de etapas
            const etapas = [
                ['A — Venda Net',        `Bruto R$ ${fmtBRL(item.valor_bruto)} − ICMS − PIS − COFINS`,                                `R$ ${fmtBRL(venda_net)}`],
                ['B — Preço Net Unit.',   `R$ ${fmtBRL(venda_net)} ÷ ${fmtN(item.qtde,4)} UN`,                                        `R$ ${fmtN(pnu,4)}`],
                ['C — Price List',        semLista ? 'Não encontrado'
                                          : pl_usd>0 ? `USD ${fmtN(pl_usd,4)} × PTAX = R$ ${fmtBRL(pl_brl)}`
                                          : `R$ ${fmtBRL(pl_brl)} (manual)`,                                                          semLista ? '—' : `R$ ${fmtBRL(pl_brl)}`],
                ['D — Desconto %',        semLista ? '—' : `(R$ ${fmtBRL(pl_brl)} − R$ ${fmtN(pnu,4)}) ÷ R$ ${fmtBRL(pl_brl)}`,     semLista ? '—' : fmtPct(dsc_pct)],
                ['E — % Base (Matriz)',   matrizLabel(dsc_pct, semLista),                                                              fmtPct(base_pct)],
                ['F — PM / Ajuste',      `${fmtN(pm_dias,1)} dias (base 28d). Dif ${fmtN(pm_dias-28,1)}d = ${fmtN((pm_dias-28)/7,2)} sem × 0,05%`, fmtPct(ajuste)],
                ['G — % Final',           `${fmtPct(base_pct)} + (${fmtPct(ajuste)})`,                                                fmtPct(final_pct)],
                ['H — Comissão Final',    `R$ ${fmtBRL(venda_net)} × ${fmtPct(final_pct)}${flagTeto?' (+teto R$25k)':''}`,            `R$ ${fmtBRL(comissao)}`],
            ];

            checkY(etapas.length * 4.8 + 6);

            // Cabeçalho colunas
            doc.setFillColor(230,235,245);
            doc.rect(ML+1, y, usableW-2, 4.8, 'F');
            doc.setFont('helvetica','bold'); doc.setFontSize(7); doc.setTextColor(10,30,66);
            doc.text('Etapa', ML+3, y+3.4);
            doc.text('Fórmula / Explicação', ML+35, y+3.4);
            doc.text('Resultado', PW-MR-2, y+3.4, {align:'right'});
            doc.setFont('helvetica','normal'); doc.setTextColor(0,0,0);
            y += 4.8;

            etapas.forEach((et, ei) => {
                checkY(5);
                if (ei % 2 === 0) { doc.setFillColor(250,251,253); doc.rect(ML+1,y,usableW-2,4.8,'F'); }
                doc.setFontSize(7.2);
                doc.setFont('helvetica','bold');   doc.text(et[0], ML+3, y+3.4);
                doc.setFont('helvetica','normal'); doc.text(String(et[1]).slice(0,73), ML+35, y+3.4);
                const isTotal = ei === etapas.length-1;
                if (isTotal) { doc.setFont('helvetica','bold'); doc.setTextColor(25,135,84); }
                doc.text(et[2], PW-MR-2, y+3.4, {align:'right'});
                doc.setFont('helvetica','normal'); doc.setTextColor(0,0,0);
                y += 4.8;
            });

            // Aviso
            if (flagAprov || semLista) {
                checkY(6);
                doc.setFillColor(255,243,205); doc.rect(ML+1,y,usableW-2,5.5,'F');
                doc.setFontSize(7); doc.setTextColor(133,77,14);
                let av = semLista?'Produto não localizado na Price List. ':''
                       + (dsc_pct>0.20?'Desconto > 20%. ':'')
                       + (pm_dias>42?'PM > 42 dias. ':'');
                doc.text('⚠ ' + av.trim(), ML+3, y+3.8);
                doc.setTextColor(0,0,0); y += 6.5;
            }
            if (item.obs?.trim()) {
                checkY(5); doc.setFontSize(7); doc.setTextColor(100,100,100);
                doc.text('Obs: ' + item.obs.slice(0,110), ML+1, y+3.5);
                doc.setTextColor(0,0,0); y += 5;
            }
            // Separador
            doc.setDrawColor(180,180,180); doc.setLineWidth(0.3);
            doc.line(ML, y+2, PW-MR, y+2); y += 6;
        });

        // Rodapé total
        checkY(12);
        const totalNet = filteredItems.reduce((a,i)=>a+parseFloat(i.venda_net||0),0);
        const totalCom = filteredItems.reduce((a,i)=>a+parseFloat(i.valor_comissao||0),0);
        doc.setFillColor(10,30,66); doc.rect(ML, y, usableW, 9, 'F');
        doc.setTextColor(255,255,255); doc.setFontSize(8.5); doc.setFont('helvetica','bold');
        doc.text(
            `TOTAL: ${filteredItems.length} itens  |  Venda Net: R$ ${fmtBRL(totalNet)}  |  Comissão: R$ ${fmtBRL(totalCom)}  |  Média: ${totalNet>0?((totalCom/totalNet)*100).toFixed(4).replace('.',',')+'%':'—'}`,
            ML+3, y+6
        );
        doc.setTextColor(0,0,0);

        const slug = selectedRep.replace(/[^\w]/g,'_').slice(0,20);
        doc.save(`Auditoria_Comissao_${slug}_${new Date().toISOString().slice(0,10)}.pdf`);
    }

    function matrizLabel(dsc, semLista) {
        if (semLista)        return 'S/ Price List → 0%';
        if (dsc <= 0)        return 'Desc ≤ 0% → 1,00%';
        if (dsc <= 0.05)     return 'Desc ≤ 5% → 0,90%';
        if (dsc <= 0.10)     return 'Desc ≤ 10% → 0,70%';
        if (dsc <= 0.15)     return 'Desc ≤ 15% → 0,50%';
        if (dsc <= 0.20)     return 'Desc ≤ 20% → 0,40%';
        return 'Desc > 20% → 0,25% (⚠ Aprovação)';
    }

    // === REPROCESSAR LOTE ===
    async function reprocessarLote() {
        if (!confirm('Reprocessar TODOS os itens do lote com PTAX corrigida?\n\nEdições manuais (embalagem, preço lista) serão PRESERVADAS.\nItens sem Price List continuarão sem comissão.')) return;

        const body = document.getElementById('bodyReprocessar');
        const btnAtualizar = document.getElementById('btnRecarregarAposReproc');
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-warning"></div><p class="mt-2 text-muted">Recalculando comissões e buscando PTAX...</p></div>';
        btnAtualizar.classList.add('d-none');
        modalReprocessar.show();

        try {
            const res  = await fetch('api/reprocessar_lote.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ batch_id: BATCH_ID })
            });
            const json = await res.json();

            if (!json.success) {
                body.innerHTML = `<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>${json.message}</div>`;
                return;
            }

            const iconTipos = {
                sem_lista:  { icon: 'bi-question-circle-fill', cor: 'secondary', label: 'Sem Price List' },
                sem_ptax:   { icon: 'bi-currency-dollar',      cor: 'warning',   label: 'PTAX não encontrada' },
                sem_pm:     { icon: 'bi-clock-history',        cor: 'info',      label: 'PM = 0 (baseline 28d usado)' },
                sem_preco:  { icon: 'bi-tag-fill',             cor: 'danger',    label: 'Preço lista zerado' },
            };

            let html = `<div class="row text-center mb-3">
                <div class="col"><div class="card bg-success text-white py-2"><div class="fw-bold fs-4">${json.recalculados}</div><small>Recalculados</small></div></div>
                <div class="col"><div class="card bg-secondary text-white py-2"><div class="fw-bold fs-4">${json.ignorados_sem_lista}</div><small>Ignorados (S/Lista)</small></div></div>
                <div class="col"><div class="card bg-warning text-dark py-2"><div class="fw-bold fs-4">${json.warnings.length}</div><small>Warnings</small></div></div>
                <div class="col"><div class="card bg-dark text-white py-2"><div class="fw-bold fs-4">${json.total}</div><small>Total</small></div></div>
            </div>`;

            if (json.warnings.length > 0) {
                html += `<hr><h6 class="text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i> Itens que precisam de revisão manual:</h6>`;
                const grupos = {};
                json.warnings.forEach(w => { if (!grupos[w.tipo]) grupos[w.tipo]=[]; grupos[w.tipo].push(w); });
                for (const [tipo, lista] of Object.entries(grupos)) {
                    const t = iconTipos[tipo] || { icon: 'bi-info-circle', cor: 'secondary', label: tipo };
                    html += `<div class="alert alert-${t.cor} py-2 mb-2"><strong><i class="bi ${t.icon} me-1"></i>${t.label} (${lista.length})</strong><ul class="mb-0 mt-1" style="font-size:0.82rem">`;
                    lista.forEach(w => { html += `<li><code>${w.nfe}</code> — ${w.msg}</li>`; });
                    html += `</ul></div>`;
                }
            } else {
                html += `<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i> Todos os itens foram recalculados sem pendências!</div>`;
            }

            body.innerHTML = html;
            btnAtualizar.classList.remove('d-none');
        } catch (err) {
            body.innerHTML = `<div class="alert alert-danger">Erro de comunicação: ${err.message}</div>`;
        }
    }

    // Carrega dados ao abrir

    document.addEventListener('DOMContentLoaded', () => carregarDados());

    async function carregarDados() {
        const res = await fetch(`api/get_commission_items.php?batch_id=${BATCH_ID}`);
        const json = await res.json();
        if (!json.success) {
            document.getElementById('tbodyLote').innerHTML = `<tr><td colspan="13" class="text-danger text-center">Erro: ${json.message}</td></tr>`;
            return;
        }
        allItems = json.data;
        filteredItems = allItems;
        renderCards(allItems);
        renderTabela(allItems);
    }

    function calcStatusItem(item) {
        if (item.lista_nao_encontrada == 1) return 'sem_lista';
        if (item.flag_aprovacao == 1) return 'aprovacao';
        if (item.flag_teto == 1) return 'teto';
        return 'ok';
    }

    function renderCards(data) {
        const totalNet = data.reduce((s,i) => s + parseFloat(i.venda_net||0), 0);
        const totalCom = data.reduce((s,i) => s + parseFloat(i.valor_comissao||0), 0);
        const cntAprov = data.filter(i => i.flag_aprovacao == 1).length;
        const cntTeto  = data.filter(i => i.flag_teto == 1).length;
        const cntSL    = data.filter(i => i.lista_nao_encontrada == 1).length;

        const fmtBRL = v => v.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});

        document.getElementById('cardsResumo').innerHTML = `
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-success text-white h-100">
                    <div class="card-body py-2"><small>Total Venda Net</small><h5 class="mb-0">${fmtBRL(totalNet)}</h5></div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body py-2"><small>Total Comissões</small><h5 class="mb-0">${fmtBRL(totalCom)}</h5></div>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card h-100 border-danger">
                    <div class="card-body py-2 text-center"><small class="text-muted">Aprov. Req.</small><h5 class="text-danger mb-0">${cntAprov}</h5></div>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card h-100 border-warning">
                    <div class="card-body py-2 text-center"><small class="text-muted">C/ Prêmio</small><h5 class="text-warning mb-0">${cntTeto}</h5></div>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card h-100 border-secondary">
                    <div class="card-body py-2 text-center"><small class="text-muted">S/ Lista</small><h5 class="text-secondary mb-0">${cntSL}</h5></div>
                </div>
            </div>
        `;
    }

    function statusBadge(item) {
        const st = calcStatusItem(item);
        const map = {
            sem_lista: '<span class="badge badge-sem-lista">S/ Lista</span>',
            aprovacao: '<span class="badge badge-aprovacao">⚠ Aprovação</span>',
            teto:      '<span class="badge badge-teto">★ Teto</span>',
            ok:        '<span class="badge badge-ok">✔ OK</span>'
        };
        return map[st] || '';
    }

    function renderTabela(data) {
        if (dtTable) { dtTable.destroy(); dtTable = null; }

        const fmtBRL = v => parseFloat(v||0).toLocaleString('pt-BR', {minimumFractionDigits:2});
        const fmtPct = v => (parseFloat(v||0)*100).toFixed(2) + '%';

        const tbody = document.getElementById('tbodyLote');
        tbody.innerHTML = '';
        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.dataset.id = item.id;
            tr.innerHTML = `
                <td>${item.representante||'-'}</td>
                <td>${item.data_nf||'-'}<br><small class="text-muted">${item.nfe}</small></td>
                <td><b>${item.codigo}</b><br><small>${item.embalagem}</small></td>
                <td title="${item.cliente||''}">${(item.cliente||'-').substring(0,25)}</td>
                <td>R$ ${fmtBRL(item.venda_net)}</td>
                <td>R$ ${fmtBRL(item.preco_lista_brl)}</td>
                <td>${fmtPct(item.desconto_pct)}</td>
                <td>${fmtPct(item.comissao_base_pct)}</td>
                <td>${Math.round(item.pm_dias||0)}d</td>
                <td><b>${fmtPct(item.comissao_final_pct)}</b></td>
                <td class="text-end fw-bold text-success">R$ ${fmtBRL(item.valor_comissao)}</td>
                <td>${statusBadge(item)}</td>
                <td class="no-print">
                    <button class="btn btn-sm btn-outline-primary" onclick="abrirEdicao(${item.id})"><i class="bi bi-pencil"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        dtTable = $('#tblLote').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
            order: [[0, 'asc']],
            pageLength: 50,
            columnDefs: [{ targets: [-1], orderable: false }]
        });
    }

    function aplicarFiltros() {
        const status = document.querySelector('input[name=filtroStatus]:checked').value;
        const repSelect = document.getElementById('filtroRep');
        const selectedReps = Array.from(repSelect.selectedOptions).map(opt => opt.value).filter(val => val !== "");

        filteredItems = allItems.filter(item => {
            const st = calcStatusItem(item);
            const matchStatus = status === 'todos' || st === status;
            const matchRep    = selectedReps.length === 0 || selectedReps.includes(item.representante);
            return matchStatus && matchRep;
        });
        renderCards(filteredItems);
        renderTabela(filteredItems);
    }

    // Evento: botões de rádio aplicam filtro automaticamente
    document.querySelectorAll('input[name=filtroStatus]').forEach(el => {
        el.addEventListener('change', aplicarFiltros);
    });
    document.getElementById('filtroRep').addEventListener('change', aplicarFiltros);

    // === CRUD ===
    function abrirEdicao(id) {
        const item = allItems.find(i => i.id == id);
        if (!item) return;
        document.getElementById('editId').value      = item.id;
        document.getElementById('editRep').value     = item.representante || '';
        document.getElementById('editCliente').value = item.cliente || '';
        document.getElementById('editCodigo').value  = item.codigo || '';
        document.getElementById('editEmb').value     = item.embalagem || '';
        document.getElementById('editQtde').value    = item.qtde || '';
        document.getElementById('editVendaNet').value    = parseFloat(item.venda_net||0).toFixed(2);
        document.getElementById('editPrecoLista').value  = parseFloat(item.preco_lista_brl||0).toFixed(2);
        document.getElementById('editPrecoNet').value    = parseFloat(item.preco_net_un||0).toFixed(2);
        document.getElementById('editPm').value          = Math.round(item.pm_dias||0);
        document.getElementById('editObs').value         = item.obs || '';
        document.getElementById('previewCalc').textContent = '← Altere valores para ver o recálculo de comissão';
        modalEdit.show();
    }

    function previewRecalc() {
        const net   = parseFloat(document.getElementById('editVendaNet').value) || 0;
        const lista = parseFloat(document.getElementById('editPrecoLista').value) || 0;
        const pnu   = parseFloat(document.getElementById('editPrecoNet').value) || 0;
        const pm    = parseFloat(document.getElementById('editPm').value) || 0;

        if (!net || !lista) { document.getElementById('previewCalc').textContent = 'Preencha Venda Net e P.Lista para ver o recálculo.'; return; }

        const dscBrl = Math.max(0, lista - pnu);
        const dscPct = lista > 0 ? dscBrl / lista : 0;
        let basePct = 0.0025;
        if (dscPct <= 0)        basePct = 0.0100;
        else if (dscPct <= 0.05) basePct = 0.0090;
        else if (dscPct <= 0.10) basePct = 0.0070;
        else if (dscPct <= 0.15) basePct = 0.0050;
        else if (dscPct <= 0.20) basePct = 0.0040;

        const ajuste = -((pm - 28) / 7 * 0.0005);
        const final  = Math.max(0.0005, basePct + ajuste);
        const comissao = net * (lista > 0 ? final : 0);
        const teto = comissao > 25000;
        const comissaoFinal = teto ? 25000 + (comissao - 25000) * 0.10 : comissao;

        const fmtPct = v => (v*100).toFixed(2) + '%';
        const fmtBRL = v => v.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});

        document.getElementById('previewCalc').innerHTML = `
            <b>Preview:</b> Desc: ${fmtPct(dscPct)} | Base: ${fmtPct(basePct)} | Aj.Prazo: ${fmtPct(ajuste)} | 
            <b>Final: ${fmtPct(lista>0?final:0)}</b> | Comissão: <b class="text-success">${fmtBRL(comissaoFinal)}</b>
            ${teto ? ' <span class="badge badge-teto">★ Teto aplicado</span>' : ''}
            ${dscPct > 0.20 || pm > 42 ? ' <span class="badge badge-aprovacao">⚠ Requer Aprovação</span>' : ''}
        `;
    }

    async function salvarEdicao() {
        const id = parseInt(document.getElementById('editId').value);
        const payload = {
            id,
            action: 'update',
            representante: document.getElementById('editRep').value,
            cliente:       document.getElementById('editCliente').value,
            codigo:        document.getElementById('editCodigo').value,
            embalagem:     document.getElementById('editEmb').value,
            qtde:          parseFloat(document.getElementById('editQtde').value) || 0,
            venda_net:     parseFloat(document.getElementById('editVendaNet').value) || 0,
            preco_lista_brl: parseFloat(document.getElementById('editPrecoLista').value) || 0,
            preco_net_un:  parseFloat(document.getElementById('editPrecoNet').value) || 0,
            pm_dias:       parseFloat(document.getElementById('editPm').value) || 0,
            obs:           document.getElementById('editObs').value
        };

        const res = await fetch('api/update_commission_item.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.success) {
            modalEdit.hide();
            await carregarDados();
            aplicarFiltros();
        } else {
            alert('Erro ao salvar: ' + json.message);
        }
    }

    async function deletarItem() {
        const id = parseInt(document.getElementById('editId').value);
        if (!confirm('Excluir este item permanentemente?')) return;
        const res = await fetch('api/update_commission_item.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id, action: 'delete'})
        });
        const json = await res.json();
        if (json.success) {
            modalEdit.hide();
            await carregarDados();
            aplicarFiltros();
        } else {
            alert('Erro ao excluir: ' + json.message);
        }
    }

    // === PDF via jsPDF ===
    function gerarPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a3' });

        const batchNome = document.querySelector('h4.text-primary').textContent;
        doc.setFontSize(13);
        doc.text(batchNome, 14, 14);
        doc.setFontSize(9);
        doc.text('Gerado em: ' + new Date().toLocaleString('pt-BR'), 14, 20);

        const dados = filteredItems.map(item => [
            (item.representante||'-').substring(0,20),
            item.data_nf || '-',
            item.nfe,
            item.codigo,
            (item.descricao||'-').substring(0,80), // Produto
            (item.cliente||'-').substring(0,22),
            'R$ ' + parseFloat(item.venda_net||0).toLocaleString('pt-BR',{minimumFractionDigits:2}),
            'R$ ' + parseFloat(item.preco_lista_brl||0).toLocaleString('pt-BR',{minimumFractionDigits:2}),
            (parseFloat(item.desconto_pct||0)*100).toFixed(1) + '%',
            (parseFloat(item.comissao_final_pct||0)*100).toFixed(2) + '%',
            'R$ ' + parseFloat(item.valor_comissao||0).toLocaleString('pt-BR',{minimumFractionDigits:2})
        ]);

        doc.autoTable({
            startY: 25,
            head: [['Representante','Data','NF','Código','Produto','Cliente','Venda Net','Pricelist','Desc%','% Final','Comissão']],
            body: dados,
            styles: { fontSize: 7.5, cellPadding: 1.5, valign: 'middle' },
            headStyles: { fillColor: [13, 110, 253] },
            alternateRowStyles: { fillColor: [245, 245, 245] },
            columnStyles: {
                4: { cellWidth: 'auto' }, // Produto
                5: { cellWidth: 'auto' }, // Cliente
                9: { halign: 'right' },   // % Final
                10: { halign: 'right' }   // Comissão
            }
        });

        const filtroAtivo = document.querySelector('input[name=filtroStatus]:checked').value;
        const repSelect = document.getElementById('filtroRep');
        const selectedReps = Array.from(repSelect.selectedOptions).map(opt => opt.value).filter(val => val !== "");
        const repFiltro = selectedReps.length ? selectedReps.join(', ') : 'Todos';
        
        doc.setFontSize(7);
        doc.text(`Filtro: Status=${filtroAtivo} | Representante=${repFiltro} | Total: ${filteredItems.length} itens`, 14, doc.lastAutoTable.finalY + 5);

        doc.save(`comissao_lote_${BATCH_ID}.pdf`);
    }
    // === DIAGNÓSTICO S/LISTA ===
    async function diagnoseSemLista() {
        const painel = document.getElementById('painelDiagnostico');
        const body   = document.getElementById('diagBody');
        painel.style.display = '';
        body.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-warning"></div><p class="mt-2 text-muted">Analisando produtos S/ Lista...</p></div>';
        painel.scrollIntoView({behavior:'smooth', block:'start'});

        try {
            const res  = await fetch(`api/debug_sem_lista.php?batch_id=${BATCH_ID}&limit=200`);
            const data = await res.json();
            if (!data.success) { body.innerHTML = `<div class="alert alert-danger">${data.message}</div>`; return; }

            const t = data.totais;
            // Separa por grupo de problema
            const semCodigo  = data.itens.filter(i => i.opcoes_price_list.length === 0);
            const codExiste  = data.itens.filter(i => i.opcoes_price_list.length > 0 && i.match.length === 0);
            const jaAcharia  = data.itens.filter(i => i.encontraria_agora);

            const fmtGrupo = (titulo, cor, icone, itens, extra='') => {
                if (!itens.length) return '';
                const rows = itens.map(i => {
                    const disponiveis = i.opcoes_price_list.map(o => `<code>${o.embalagem}</code>`).join(', ') || '—';
                    const tentados   = i.candidatos_tentados.slice(0,5).map(c => `<code>${c}</code>`).join(', ');
                    return `<tr>
                        <td><b>${i.codigo}</b></td>
                        <td><code>${i.emb_limpa}</code></td>
                        <td>${tentados}</td>
                        <td>${disponiveis}</td>
                        <td class="text-muted small">${(i.nfe||'')}</td>
                        ${cor === 'warning' ? `<td class="text-center no-print"><button class="btn btn-sm btn-outline-warning" onclick='abrirModalSelecaoEmb(${JSON.stringify(i).replace(/'/g, "&apos;")})'>Selecionar EMB</button></td>` : ''}
                    </tr>`;
                }).join('');
                return `<div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-${cor} mb-0"><i class="${icone} me-1"></i>${titulo} (${itens.length})</h6>
                        ${cor === 'warning' ? `<button class="btn btn-sm btn-outline-danger" onclick="gerarPdfDivergencias()"><i class="bi bi-file-earmark-pdf"></i> Gerar PDF S/Lista</button>` : ''}
                        ${cor === 'success' ? `<button class="btn btn-sm btn-outline-success border-2 fw-bold" onclick="reprocessarAcharia(this)"><i class="fas fa-sync-alt"></i> Reprocessar Encontrados</button>` : ''}
                    </div>
                    ${extra}
                    <div class="table-responsive"><table class="table table-sm table-bordered" style="font-size:0.8rem">
                        <thead class="table-secondary"><tr>
                            <th>Código</th><th>Emb. extraída</th><th>Candidatos tentados</th><th>Disponível na Price List</th><th>NF</th>${cor === 'warning' ? '<th class="no-print">Ação</th>' : ''}
                        </tr></thead>
                        <tbody>${rows}</tbody>
                    </table></div></div>`;
            };

            let html = `<div class="row mb-3">
                <div class="col"><div class="alert alert-secondary py-2 mb-0">
                    <b>Total S/ Lista:</b> ${t.total_sem_lista} &nbsp;|
                    <span class="text-danger"><b>Código inexistente na PL:</b> ${t.sem_codigo_na_lista}</span> &nbsp;|
                    <span class="text-warning"><b>Cód. existe, Emb. diferente:</b> ${t.cod_existe_emb_diff}</span> &nbsp;|
                    <span class="text-success"><b>Seria encontrado agora:</b> ${t.encontraria_agora}</span>
                </div></div></div>`;

            if (jaAcharia.length) {
                itensParaReprocessarGlobais = jaAcharia;
                html += fmtGrupo('✅ Seriam encontrados com o algoritmo atual (reprocessar)', 'success', 'fas fa-check-circle', jaAcharia,
                    '<div class="alert alert-success py-1 small">Estes itens seriam resolvidos ao reprocessar a planilha com o algoritmo atual.</div>');
            }
            itensDivergentesGlobais = codExiste.filter(i => !i.encontraria_agora);
            html += fmtGrupo('⚠️ Código existe na Price List mas embalagem diverge', 'warning', 'fas fa-exclamation-triangle',
                itensDivergentesGlobais,
                '<div class="alert alert-warning py-1 small">O código existe na price list mas a embalagem extraída do CSV não bate com nenhuma cadastrada. Verifique os formatos e atualize o cadastro se necessário.</div>');
            html += fmtGrupo('❌ Código não encontrado na Price List', 'danger', 'fas fa-times-circle',
                semCodigo,
                '<div class="alert alert-danger py-1 small">O código não existe na price list. Importe/cadastre o produto antes de reprocessar.</div>');

            body.innerHTML = html || '<div class="alert alert-success">Nenhum problema de embalagem identificado!</div>';
        } catch(e) {
            body.innerHTML = `<div class="alert alert-danger">Erro ao carregar diagnóstico: ${e.message}</div>`;
        }
    }

    // Modal e Salvamento da Embalagem Divergente
    function abrirModalSelecaoEmb(item) {
        document.getElementById('selDivergId').value = item.id;
        document.getElementById('selDivergPtax').value = item.ptax_usado;
        document.getElementById('selDivergProdNfe').innerHTML = `<b>${item.codigo}</b> - ${item.nome_produto} <br> <small class="text-muted">NF: ${item.nfe} | Emb. Faturada: ${item.embalagem_db}</small>`;
        
        const sel = document.getElementById('selDivergOpcoes');
        sel.innerHTML = '<option value="">-- Selecione a embalagem correta --</option>';
        item.opcoes_price_list.forEach(op => {
            const opt = document.createElement('option');
            opt.value = JSON.stringify(op);
            opt.textContent = `Emb: ${op.embalagem} — USD ${parseFloat(op.preco_net_usd).toFixed(4)}`;
            sel.appendChild(opt);
        });
        
        modalSelectEmb.show();
    }

    async function salvarSelecaoEmb() {
        const id = document.getElementById('selDivergId').value;
        const ptax = parseFloat(document.getElementById('selDivergPtax').value) || 0;
        const selVal = document.getElementById('selDivergOpcoes').value;
        
        if (!selVal) { alert('Selecione uma embalagem!'); return; }
        
        const priceListOption = JSON.parse(selVal);
        const precoUsd = parseFloat(priceListOption.preco_net_usd) || 0;
        const precoBrl = precoUsd * ptax;

        const payload = {
            id: parseInt(id),
            action: 'update',
            embalagem: priceListOption.embalagem,
            preco_lista_usd: precoUsd,
            preco_lista_brl: precoBrl
        };

        const btn = document.querySelector('#modalSelectEmb .btn-warning');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';

        try {
            const res = await fetch('api/update_commission_item.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (json.success) {
                modalSelectEmb.hide();
                await diagnoseSemLista(); // Recarrega o diagnóstico
                carregarDados(); // Recarrega a tabela de trás em background
            } else {
                alert('Erro ao salvar: ' + json.message);
            }
        } catch(e) {
            alert('Erro de comunicação: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Associar Embalagem';
        }
    }

    async function reprocessarAcharia(btn) {
        if (!itensParaReprocessarGlobais || itensParaReprocessarGlobais.length === 0) return;
        if (!confirm(`Deseja reprocessar automaticamente ${itensParaReprocessarGlobais.length} itens encontrados?`)) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processando...';

        try {
            const res = await fetch('api/reprocess_sem_lista.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ batch_id: BATCH_ID, itens: itensParaReprocessarGlobais })
            });
            const json = await res.json();
            if (json.success) {
                alert(`Concluído! ${json.sucessos} itens reprocessados (${json.falhas} falharam).`);
                await diagnoseSemLista();
                carregarDados();
            } else {
                alert('Erro ao reprocessar: ' + json.message);
            }
        } catch(e) {
            alert('Erro de comunicação: ' + e.message);
        } finally {
            if(btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Reprocessar Encontrados';
            }
        }
    }


    // Geração do PDF específico do grupo "Código existe na Price List mas embalagem diverge"
    function gerarPdfDivergencias() {
        if (!itensDivergentesGlobais || itensDivergentesGlobais.length === 0) {
            alert('Não há itens divergentes para gerar relatório.');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

        const batchNome = document.querySelector('h4.text-primary').textContent;
        doc.setFontSize(13);
        doc.text("Relatório de Divergência de Embalagem — S/Lista", 14, 14);
        doc.setFontSize(10);
        doc.text(batchNome, 14, 20);
        doc.setFontSize(8);
        doc.text('Gerado em: ' + new Date().toLocaleString('pt-BR'), 14, 25);

        const dados = itensDivergentesGlobais.map(item => [
            item.codigo,
            (item.nome_produto || '-').substring(0, 40),
            item.nfe || '-',
            item.embalagem_db || '-',
            // Pega as embalagens disponiveis e junta com virgula
            item.opcoes_price_list.map(o => o.embalagem).join(', ') || '-',
            (item.representante || '-').substring(0, 20)
        ]);

        doc.autoTable({
            startY: 30,
            head: [['CÓDIGO', 'NOME DO PRODUTO', 'NF', 'EMB VENDIDA', 'EMB DISPONÍVEL NA PRICE LIST', 'VENDEDOR']],
            body: dados,
            styles: { fontSize: 8, cellPadding: 2, valign: 'middle' },
            headStyles: { fillColor: [240, 173, 78], textColor: [255, 255, 255] }, // Warning color (Orange)
            alternateRowStyles: { fillColor: [252, 248, 227] }, // Light yellow/orange
            columnStyles: {
                1: { cellWidth: 70 }, // Produto maior
                4: { cellWidth: 50 } // Emb disp
            }
        });

        doc.setFontSize(8);
        doc.text(`Total de itens: ${itensDivergentesGlobais.length}`, 14, doc.lastAutoTable.finalY + 5);

        doc.save(`divergencias_lote_${BATCH_ID}.pdf`);
    }

    // === RESUMO POR REPRESENTANTE ===
    function gerarPDFResumo() {
        if (!filteredItems || filteredItems.length === 0) {
            alert('Nenhum item para resumir. Aplique os filtros antes de gerar.');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

        const batchNome = document.querySelector('h4.text-primary').textContent;
        doc.setFontSize(14);
        doc.text('Resumo de Comissões por Representante', 14, 14);
        doc.setFontSize(10);
        doc.text(batchNome, 14, 21);
        doc.setFontSize(8);
        doc.text('Gerado em: ' + new Date().toLocaleString('pt-BR'), 14, 27);

        // Agrupa por representante
        const grupos = {};
        filteredItems.forEach(item => {
            const rep = item.representante || '(sem nome)';
            if (!grupos[rep]) {
                grupos[rep] = { nfs: new Set(), vendas: 0, comissoes: 0, pm_soma: 0, pm_cnt: 0, pct_soma: 0, pct_cnt: 0 };
            }
            const g = grupos[rep];
            if (item.nfe) g.nfs.add(item.nfe);
            g.vendas     += parseFloat(item.venda_net      || 0);
            g.comissoes  += parseFloat(item.valor_comissao || 0);
            const pm = parseFloat(item.pm_dias || 0);
            if (pm > 0) { g.pm_soma += pm; g.pm_cnt++; }
            const pct = parseFloat(item.comissao_final_pct || 0);
            if (pct > 0) { g.pct_soma += pct; g.pct_cnt++; }
        });

        const fmtBRL = v => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        const fmtPct = v => (v * 100).toFixed(2) + '%';

        const linhas = Object.entries(grupos)
            .sort((a, b) => a[0].localeCompare(b[0]))
            .map(([rep, g]) => [
                rep,
                g.nfs.size,
                fmtBRL(g.vendas),
                fmtBRL(g.comissoes),
                g.pm_cnt  > 0 ? (g.pm_soma  / g.pm_cnt).toFixed(1)  + 'd' : '-',
                g.pct_cnt > 0 ? fmtPct(g.pct_soma / g.pct_cnt)            : '-'
            ]);

        // Linha de totais
        const totVendas = filteredItems.reduce((s, i) => s + parseFloat(i.venda_net      || 0), 0);
        const totCom    = filteredItems.reduce((s, i) => s + parseFloat(i.valor_comissao || 0), 0);
        const allNfs    = new Set(filteredItems.map(i => i.nfe).filter(Boolean));
        const pmVals    = filteredItems.map(i => parseFloat(i.pm_dias || 0)).filter(v => v > 0);
        const pctVals   = filteredItems.map(i => parseFloat(i.comissao_final_pct || 0)).filter(v => v > 0);
        const pmMedio   = pmVals.length  ? (pmVals.reduce((a,b)=>a+b,0)  / pmVals.length).toFixed(1)  + 'd' : '-';
        const pctMedio  = pctVals.length ? fmtPct(pctVals.reduce((a,b)=>a+b,0) / pctVals.length)          : '-';

        doc.autoTable({
            startY: 32,
            head: [['Representante', 'Total NFs', 'Total Vendas', 'Total Comissões', 'PM Médio', '% Comissão Média']],
            body: linhas,
            foot: [['TOTAL GERAL', allNfs.size, fmtBRL(totVendas), fmtBRL(totCom), pmMedio, pctMedio]],
            styles:     { fontSize: 9, cellPadding: 2.5, valign: 'middle' },
            headStyles: { fillColor: [13, 110, 253], fontStyle: 'bold' },
            footStyles: { fillColor: [30, 30, 30], textColor: [255, 255, 255], fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [240, 245, 255] },
            columnStyles: {
                0: { cellWidth: 'auto' },
                1: { halign: 'center' },
                2: { halign: 'right' },
                3: { halign: 'right' },
                4: { halign: 'center' },
                5: { halign: 'center' }
            }
        });

        const filtroAtivo = document.querySelector('input[name=filtroStatus]:checked').value;
        doc.setFontSize(7);
        doc.text(`Filtro: ${filtroAtivo} | ${filteredItems.length} itens | ${Object.keys(grupos).length} representantes`, 14, doc.lastAutoTable.finalY + 5);

        doc.save(`resumo_comissoes_lote_${BATCH_ID}.pdf`);
    }

    </script>
</body>
</html>
