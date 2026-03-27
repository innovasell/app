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

    // Remove diacríticos para compatibilidade com fontes padrão do jsPDF (Helvetica não suporta UTF-8)
    function fixText(str) {
        return (str || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    // ══════════════════════════════════════════════════════════════════════
    // PDF AUDITORIA — 10 etapas detalhadas por item (POP 10/2025 Rev.01)
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
        const fmtPct = (v, d) => (parseFloat(v||0)*100).toFixed(d||4).replace('.',',') + '%';
        const fmtN   = (v,d=2) => parseFloat(v||0).toFixed(d).replace('.',',');

        const batchNome   = fixText(document.querySelector('h4.text-primary')?.textContent || 'Lote');
        const selectedRep = fixText(Array.from(document.getElementById('filtroRep').selectedOptions)
            .map(o => o.value).filter(v => v).join(', ') || 'Todos os representantes');
        const geradoEm = new Date().toLocaleString('pt-BR');

        let y = 14, pageNum = 1;

        function cabecalho(first) {
            doc.setFillColor(10,30,66);
            doc.rect(0, 0, PW, 10, 'F');
            doc.setTextColor(255,255,255); doc.setFontSize(7.5); doc.setFont('helvetica','normal');
            doc.text('RELATORIO DE AUDITORIA DE COMISSOES - INNOVA  |  POP 10/2025 Rev.01', ML, 6.5);
            doc.text('Pag. ' + pageNum, PW-MR, 6.5, {align:'right'});
            doc.setTextColor(0,0,0);
            if (first) {
                y = 16;
                doc.setFontSize(13); doc.setFont('helvetica','bold');
                doc.text(batchNome, ML, y); y += 6;
                doc.setFontSize(8); doc.setFont('helvetica','normal'); doc.setTextColor(80,80,80);
                doc.text('Representante: ' + selectedRep + '   |   Gerado em: ' + geradoEm + '   |   ' + filteredItems.length + ' item(ns)', ML, y); y += 5;
                doc.setTextColor(0,0,0);
                doc.setDrawColor(10,30,66); doc.setLineWidth(0.5); doc.line(ML, y, PW-MR, y); y += 5;
            } else { y = 14; }
        }

        function checkY(needed) {
            if (y + needed > PH - 12) { doc.addPage(); pageNum++; cabecalho(false); }
        }

        // Cabecalho colorido de cada etapa
        function etapaHeader(num, titulo, r, g, b) {
            checkY(7);
            doc.setFillColor(r, g, b);
            doc.rect(ML+1, y, usableW-2, 5.5, 'F');
            doc.setFont('helvetica','bold'); doc.setFontSize(8); doc.setTextColor(255,255,255);
            doc.text('ETAPA ' + num + ' - ' + titulo, ML+3, y+3.8);
            doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40);
            y += 6.5;
        }

        // Texto explicativo com quebra automatica de linha
        function txt(texto, indent, negrito, tr, tg, tb) {
            indent = indent || 0;
            doc.setFont('helvetica', negrito ? 'bold' : 'normal');
            doc.setFontSize(7.2);
            doc.setTextColor(tr||50, tg||50, tb||50);
            const lines = doc.splitTextToSize(texto, usableW - 4 - indent);
            lines.forEach(function(l) {
                checkY(4.5);
                doc.text(l, ML + 3 + indent, y + 3.2);
                y += 4.2;
            });
            doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40);
        }

        // Par label: valor com valor em negrito azul
        function kv(label, valor, indent) {
            indent = indent || 0;
            checkY(4.8);
            doc.setFont('helvetica','normal'); doc.setFontSize(7.2); doc.setTextColor(80,80,80);
            doc.text(label, ML+3+indent, y+3.2);
            doc.setFont('helvetica','bold'); doc.setTextColor(10,30,100);
            doc.text(valor, ML+90+indent, y+3.2);
            doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40);
            y += 4.4;
        }

        // Linha de formula com fundo azul claro
        function formula(texto, indent) {
            indent = indent || 0;
            doc.setFont('helvetica','bold'); doc.setFontSize(7.2); doc.setTextColor(10,30,66);
            const lines = doc.splitTextToSize(texto, usableW - 8 - indent);
            const totalH = lines.length * 4.4 + 1.5;
            checkY(totalH + 1);
            doc.setFillColor(240,245,255);
            doc.rect(ML+2+indent, y, usableW-4-indent, totalH, 'F');
            lines.forEach(function(l, li) {
                doc.text(l, ML+4+indent, y + 3.4 + li * 4.4);
            });
            y += totalH + 1.5;
            doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40);
        }

        // Caixa de resultado destacada
        function resultado(label, valor, fr, fg, fb, tr, tg, tb) {
            checkY(8);
            doc.setFillColor(fr, fg, fb);
            doc.rect(ML+2, y, usableW-4, 6.5, 'F');
            doc.setFont('helvetica','bold'); doc.setFontSize(8); doc.setTextColor(tr, tg, tb);
            doc.text(label, ML+5, y+4.3);
            doc.text(valor, PW-MR-4, y+4.3, {align:'right'});
            doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40);
            y += 8;
        }

        function spacer(h) { y += (h || 3); }

        cabecalho(true);

        // ══════════════════════════════════════════════════════════════════════
        // ITERACAO POR ITEM
        // ══════════════════════════════════════════════════════════════════════
        filteredItems.forEach(function(item, idx) {
            const semLista  = parseInt(item.lista_nao_encontrada||0) === 1;
            const flagAprov = parseInt(item.flag_aprovacao||0) === 1;
            const flagTeto  = parseInt(item.flag_teto||0) === 1;

            const valorBruto      = parseFloat(item.valor_bruto||0);
            const icms            = parseFloat(item.icms||0);
            const pis             = parseFloat(item.pis||0);
            const cofins          = parseFloat(item.cofins||0);
            const venda_net       = parseFloat(item.venda_net||0);
            const outrasDeducoes  = Math.max(0, valorBruto - icms - pis - cofins - venda_net);
            const qtde            = parseFloat(item.qtde||1);
            const pl_brl          = parseFloat(item.preco_lista_brl||0);
            const pl_usd          = parseFloat(item.preco_lista_usd||0);
            const pnu             = parseFloat(item.preco_net_un||0);
            const dsc_pct         = parseFloat(item.desconto_pct||0);
            const base_pct        = parseFloat(item.comissao_base_pct||0);
            const pm_dias         = parseFloat(item.pm_dias||0);
            const pm_semanas      = parseFloat(item.pm_semanas||0);
            const ajuste          = parseFloat(item.ajuste_prazo_pct||0);
            const final_pct       = parseFloat(item.comissao_final_pct||0);
            const comissao        = parseFloat(item.valor_comissao||0);
            const comissaoPreTeto = semLista ? 0 : venda_net * final_pct;
            const pmDif           = pm_dias - 28;

            // ── Cabecalho do item ──────────────────────────────────────────────────
            checkY(24);
            doc.setFillColor(10,30,66);
            doc.rect(ML, y, usableW, 7.5, 'F');
            doc.setFont('helvetica','bold'); doc.setFontSize(9.5); doc.setTextColor(255,255,255);
            doc.text('ITEM #' + (idx+1) + '  |  NF ' + (item.nfe||'--') + '   ' + fixText(item.codigo||'') + ' ' + fixText(item.embalagem||''), ML+3, y+5.2);
            let badge = semLista ? 'S/Lista' : flagTeto ? 'Teto Atingido' : flagAprov ? 'Requer Aprovacao' : 'Comissao OK';
            let bclr  = semLista ? [108,117,125] : flagTeto ? [200,100,0] : flagAprov ? [180,40,40] : [25,135,84];
            const bw  = doc.getTextWidth(badge) + 5;
            doc.setFillColor(...bclr);
            doc.roundedRect(PW-MR-bw-2, y+1.5, bw, 4.5, 0.8, 0.8, 'F');
            doc.setFontSize(7); doc.text(badge, PW-MR-bw+0.5, y+4.8);
            doc.setTextColor(40,40,40); doc.setFont('helvetica','normal');
            y += 9;

            doc.setFontSize(7.5); doc.setTextColor(50,50,50);
            doc.text(fixText('Produto: ' + (item.descricao||'--').slice(0,65)), ML+2, y+3.2); y += 4.2;
            doc.text(fixText('Cliente: ' + (item.cliente||'--').slice(0,42)), ML+2, y+3.2);
            doc.text('Data NF: ' + (item.data_nf||'--') + '  |  CFOP: ' + (item.cfop||'--'), PW*0.56, y+3.2); y += 4.2;
            doc.text(fixText('Representante: ' + (item.representante||'--').slice(0,38)), ML+2, y+3.2);
            doc.text('Qtde: ' + fmtN(qtde,4) + ' UN', PW*0.56, y+3.2); y += 5;
            doc.setTextColor(40,40,40);
            doc.setDrawColor(10,30,66); doc.setLineWidth(0.4); doc.line(ML, y, PW-MR, y); y += 4;

            // ══════════════════════════════════════════════════
            // ETAPA 1 — Identificacao
            // ══════════════════════════════════════════════════
            etapaHeader(1, 'Identificacao do Documento Fiscal', 41, 128, 185);
            txt('Antes de qualquer calculo, identificamos a nota fiscal que sera analisada. O numero da NF e a data de emissao sao fundamentais: a data sera usada na Etapa 5 para buscar a cotacao do dolar (PTAX) do Banco Central vigente naquele dia, pois os precos do catalogo de produtos sao em USD.');
            spacer(1);
            kv('Numero da Nota Fiscal:', 'NF ' + (item.nfe||'--'));
            kv('Data de Emissao:', item.data_nf||'--');
            kv('CFOP (Natureza da Operacao):', item.cfop||'--');
            kv('Produto (Codigo / Embalagem):', fixText((item.codigo||'--') + ' / ' + (item.embalagem||'--')));
            kv('Quantidade faturada:', fmtN(qtde,4) + ' unidades');
            spacer(3);

            // ══════════════════════════════════════════════════
            // ETAPA 2 — Elegibilidade
            // ══════════════════════════════════════════════════
            etapaHeader(2, 'Elegibilidade do Representante', 41, 128, 185);
            txt('Conforme o POP 10/2025, somente representantes cadastrados como "Gerente de Contas" tem direito a comissao sobre vendas de produtos da linha de cotacoes. Representantes em outros cargos (ex.: estagiario, suporte) nao sao elegiveis. A verificacao e feita cruzando o codigo do vendedor na NF-e com o cadastro interno de representantes comerciais no momento da importacao do lote.');
            spacer(1);
            kv('Representante identificado:', fixText(item.representante||'--'));
            kv('Cliente atendido:', fixText((item.cliente||'--').slice(0,45)));
            checkY(5.5);
            doc.setFillColor(232,246,239);
            doc.rect(ML+2, y, usableW-4, 5, 'F');
            doc.setFont('helvetica','italic'); doc.setFontSize(7.2); doc.setTextColor(25,135,84);
            doc.text('Elegibilidade confirmada durante a importacao do lote.', ML+5, y+3.4);
            doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40);
            y += 6.5;
            spacer(3);

            // ══════════════════════════════════════════════════
            // ETAPA 3 — Dados Fiscais
            // ══════════════════════════════════════════════════
            etapaHeader(3, 'Extracao dos Dados Fiscais da NF-e', 41, 128, 185);
            txt('A Nota Fiscal Eletronica (NF-e) contem varios componentes de valor. Para calcular a base de comissao corretamente, precisamos separar cada componente. Pense assim: o valor bruto e o total que o cliente pagou, mas uma parte ja esta comprometida com impostos e custos que a empresa repassa — esse dinheiro nao pertence a empresa e nao pode ser base de comissao.');
            spacer(1);

            const comps = [
                ['(+) Valor Bruto da Mercadoria', 'R$ ' + fmtBRL(valorBruto), 'Total faturado antes de qualquer deducao (campo vProd da NF-e).'],
                ['(-) ICMS', 'R$ ' + fmtBRL(icms), 'Imposto Estadual sobre Circulacao de Mercadorias: vai para o governo estadual.'],
                ['(-) PIS', 'R$ ' + fmtBRL(pis), 'Programa de Integracao Social: contribuicao federal obrigatoria.'],
                ['(-) COFINS', 'R$ ' + fmtBRL(cofins), 'Contribuicao para o Financiamento da Seguridade Social: outra contribuicao federal.'],
                ['(-) Outras Deducoes (frete, etc.)', 'R$ ' + fmtBRL(outrasDeducoes), 'Frete destacado na NF e outras deducoes eventuais.'],
            ];
            comps.forEach(function(c, ci) {
                checkY(10);
                doc.setFillColor(ci%2===0 ? 248:242, ci%2===0 ? 250:246, ci%2===0 ? 255:252);
                doc.rect(ML+2, y, usableW-4, 9, 'F');
                doc.setFont('helvetica','bold'); doc.setFontSize(7.2);
                doc.setTextColor(ci===0 ? 10 : 150, ci===0 ? 80 : 0, ci===0 ? 100 : 0);
                doc.text(c[0], ML+4, y+3.4);
                doc.setTextColor(10,30,66);
                doc.text(c[1], PW-MR-4, y+3.4, {align:'right'});
                doc.setFont('helvetica','italic'); doc.setFontSize(6.5); doc.setTextColor(110,110,110);
                doc.text(c[2], ML+5, y+7.4);
                doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40);
                y += 9.5;
            });
            spacer(3);

            // ══════════════════════════════════════════════════
            // ETAPA 4 — Venda Net
            // ══════════════════════════════════════════════════
            etapaHeader(4, 'Calculo da Venda Net (Base de Comissao)', 39, 174, 96);
            txt('A Venda Net e a receita verdadeiramente liquida: o que sobra para a empresa depois de pagar todos os impostos e deducoes da nota. E SOMENTE sobre esse valor que a comissao sera calculada — nunca sobre o valor bruto. Isso e justo porque a empresa so pode remunerar o representante pelo que realmente fica no caixa.');
            spacer(1);
            formula('Venda Net = Valor Bruto - ICMS - PIS - COFINS - Outras Deducoes');
            formula('Venda Net = R$ ' + fmtBRL(valorBruto) + ' - R$ ' + fmtBRL(icms) + ' - R$ ' + fmtBRL(pis) + ' - R$ ' + fmtBRL(cofins) + ' - R$ ' + fmtBRL(outrasDeducoes));
            spacer(1);
            resultado('Venda Net (Base de Comissao):', 'R$ ' + fmtBRL(venda_net), 232,246,239, 10,100,50);
            if (valorBruto > 0) {
                const pctNet = (venda_net / valorBruto * 100).toFixed(1).replace('.',',');
                txt('Interpretacao: De cada R$ 100,00 de valor bruto nesta NF, R$ ' + pctNet + ' e receita liquida. O restante (R$ ' + fmtBRL(valorBruto - venda_net) + ') sao impostos e deducoes repassados a terceiros.', 0, false, 90,90,90);
            }
            spacer(3);

            // ══════════════════════════════════════════════════
            // ETAPA 5 — Price List, Preco Net Un e Desconto
            // ══════════════════════════════════════════════════
            etapaHeader(5, 'Price List, Preco Net Unitario e Desconto', 142, 68, 173);
            txt('Quanto maior o desconto que o representante concedeu ao cliente, menor sera sua comissao. Esse mecanismo incentiva vendas pelo preco cheio de tabela. A logica e simples: quem vende mais barato ganha menos comissao; quem vende pelo preco de catalogo ganha mais.');
            spacer(2);

            // 5a — Preco Net Unitario
            txt('5a)  Preco Net Unitario — quanto cada unidade foi vendida (liquida de impostos):', 0, true, 80,40,120);
            txt('Dividimos a Venda Net pela quantidade para encontrar o preco real de cada unidade, descontados os impostos. Esse numero sera comparado com o preco de tabela (Price List) para calcular o desconto.');
            spacer(1);
            formula('Preco Net Unitario = Venda Net / Quantidade');
            formula('Preco Net Unitario = R$ ' + fmtBRL(venda_net) + ' / ' + fmtN(qtde,4) + ' UN = R$ ' + fmtN(pnu,4));
            spacer(2);

            // 5b — Price List
            txt('5b)  Preco de Tabela (Price List) — preco oficial do catalogo sem nenhum desconto:', 0, true, 80,40,120);
            if (semLista) {
                checkY(7);
                doc.setFillColor(255,243,205);
                doc.rect(ML+2, y, usableW-4, 6, 'F');
                doc.setFont('helvetica','bold'); doc.setFontSize(7.5); doc.setTextColor(133,77,14);
                doc.text('ATENCAO: Produto nao localizado na Price List. Comissao = R$ 0,00.', ML+5, y+4);
                doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40); y += 7.5;
                txt('Este produto nao foi encontrado no catalogo de precos vigente durante a importacao do lote. Sem um preco de referencia para comparar, nao e possivel calcular o desconto nem a comissao. Para corrigir, acesse a pagina de Diagnostico S/Lista, associe o produto manualmente a uma embalagem e reprocesse o lote.', 0, false, 133,77,14);
            } else if (pl_usd > 0) {
                const ptaxCalc = pl_brl / pl_usd;
                txt('O preco de catalogo e em dolares americanos (USD). Para converter para reais, usamos a PTAX do Banco Central do Brasil (BCB) do dia da emissao da NF. A PTAX e a taxa de cambio oficial do dolar divulgada pelo BCB ao final de cada dia util — ela e a referencia oficial para conversoes comerciais no Brasil.');
                spacer(1);
                formula('Preco Lista BRL = Preco Lista USD x PTAX do dia da NF');
                formula('Preco Lista BRL = USD ' + fmtN(pl_usd,4) + ' x R$ ' + fmtN(ptaxCalc,4) + ' (PTAX de ' + (item.data_nf||'--') + ') = R$ ' + fmtBRL(pl_brl));
            } else {
                txt('O preco de tabela foi informado manualmente em reais (BRL), sem conversao de dolar. Isso ocorre quando o produto nao possui preco em USD no catalogo ou quando o preco foi ajustado diretamente via edicao manual do item.');
                formula('Preco Lista BRL (manual) = R$ ' + fmtBRL(pl_brl));
            }
            spacer(2);

            if (!semLista) {
                // 5c — Desconto
                txt('5c)  Calculo do Desconto:', 0, true, 80,40,120);
                txt('Pense assim: se o preco de tabela e R$ 100 e o representante vendeu por R$ 80 (liquido de impostos), ele concedeu 20% de desconto ao cliente. Calculamos esse percentual para saber em qual faixa da tabela de comissao o item se enquadra.');
                spacer(1);
                formula('Desconto % = (Preco Lista - Preco Net Un) / Preco Lista x 100');
                formula('Desconto % = (R$ ' + fmtBRL(pl_brl) + ' - R$ ' + fmtN(pnu,4) + ') / R$ ' + fmtBRL(pl_brl) + ' x 100 = ' + fmtPct(dsc_pct,2));
                spacer(2);

                // 5d — Tabela/Matriz
                txt('5d)  Tabela de Comissao (Matriz) — a faixa de desconto determina o % base:', 0, true, 80,40,120);
                txt('A empresa estabelece seis faixas de desconto. Cada faixa tem um percentual base de comissao correspondente. Quanto menor o desconto dado, maior a comissao — isso recompensa quem vende pelo preco cheio. A linha destacada em azul e a faixa que se aplica a este item:');
                spacer(1);

                const faixas = [
                    ['Sem desconto (0,00%)', '1,00% de comissao base', dsc_pct <= 0],
                    ['Desconto de 0,01% ate 5,00%', '0,90% de comissao base', dsc_pct > 0 && dsc_pct <= 0.05],
                    ['Desconto de 5,01% ate 10,00%', '0,70% de comissao base', dsc_pct > 0.05 && dsc_pct <= 0.10],
                    ['Desconto de 10,01% ate 15,00%', '0,50% de comissao base', dsc_pct > 0.10 && dsc_pct <= 0.15],
                    ['Desconto de 15,01% ate 20,00%', '0,40% de comissao base', dsc_pct > 0.15 && dsc_pct <= 0.20],
                    ['Desconto acima de 20,00%  [REQUER APROVACAO]', '0,25% de comissao base', dsc_pct > 0.20],
                ];
                faixas.forEach(function(f, fi) {
                    checkY(5.5);
                    if (f[2]) {
                        doc.setFillColor(10,30,66);
                        doc.rect(ML+2, y, usableW-4, 5, 'F');
                        doc.setFont('helvetica','bold'); doc.setFontSize(7.2); doc.setTextColor(255,255,255);
                        doc.text(f[0], ML+5, y+3.3);
                        doc.text(f[1] + '  <- FAIXA APLICADA', PW-MR-4, y+3.3, {align:'right'});
                    } else {
                        doc.setFillColor(fi%2===0 ? 250:244, fi%2===0 ? 252:248, fi%2===0 ? 255:252);
                        doc.rect(ML+2, y, usableW-4, 5, 'F');
                        doc.setFont('helvetica','normal'); doc.setFontSize(7.2); doc.setTextColor(90,90,90);
                        doc.text(f[0], ML+5, y+3.3);
                        doc.text(f[1], PW-MR-4, y+3.3, {align:'right'});
                    }
                    doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40);
                    y += 5;
                });
                spacer(1);
                resultado('% Base de Comissao (saida da Etapa 5):', fmtPct(base_pct,2), 232,246,239, 10,100,50);
            }
            spacer(3);

            // ══════════════════════════════════════════════════
            // ETAPA 6 — Prazo Medio
            // ══════════════════════════════════════════════════
            etapaHeader(6, 'Prazo Medio de Pagamento (PM)', 230,126,34);
            txt('O Prazo Medio (PM) representa, em media, quantos dias o cliente leva para pagar as duplicatas desta venda. Por que isso importa para a comissao? Porque quanto mais tempo o cliente demora para pagar, maior o custo financeiro para a empresa (o dinheiro fica parado mais tempo, podendo render juros em outras aplicacoes). Para compensar isso, a comissao e ajustada em funcao do prazo.');
            spacer(1);
            txt('O PM e calculado como media ponderada: cada duplicata (parcela) tem um peso proporcional ao seu valor. Uma parcela maior "puxa" mais o prazo medio do que uma parcela menor. Veja a formula:');
            spacer(1);
            formula('PM = Soma(Dias_parcela_i x Valor_parcela_i) / Soma(Valor_parcela_i)');
            txt('Onde "i" representa cada duplicata diferente da NF. O resultado registrado para este item foi:', 0, false, 80,80,80);
            spacer(1);
            kv('Prazo Medio calculado:', fmtN(pm_dias,0) + ' dias  =  ' + fmtN(pm_semanas,2) + ' semanas');
            kv('Prazo padrao de referencia (baseline):', '28 dias  =  4,00 semanas');
            kv('Diferenca em relacao ao baseline:', fmtN(pmDif,1) + ' dias  (' + fmtN(pmDif/7,2) + ' semanas)');
            spacer(3);

            // ══════════════════════════════════════════════════
            // ETAPA 7 — Ajuste de Prazo
            // ══════════════════════════════════════════════════
            etapaHeader(7, 'Ajuste da Comissao pelo Prazo Medio', 230,126,34);
            txt('A comissao base foi calculada assumindo um prazo padrao de 28 dias (4 semanas). Cada semana de diferenca em relacao a esse padrao ajusta a comissao em 0,05%:');
            checkY(12);
            doc.setFillColor(255,247,235);
            doc.rect(ML+2, y, usableW-4, 11, 'F');
            doc.setFont('helvetica','normal'); doc.setFontSize(7.2); doc.setTextColor(80,60,0);
            doc.text('Prazo maior que 28 dias  ->  ajuste NEGATIVO (comissao reduz):  cada semana a mais vale -0,05%', ML+5, y+4);
            doc.text('Prazo menor que 28 dias  ->  ajuste POSITIVO (comissao aumenta): cada semana a menos vale +0,05%', ML+5, y+8.5);
            doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40);
            y += 12.5;
            spacer(1);
            formula('Ajuste = -((PM - 28) / 7) x 0,05%');
            formula('Ajuste = -((' + fmtN(pm_dias,0) + ' - 28) / 7) x 0,05%');
            formula('Ajuste = -(' + fmtN(pmDif,1) + ' dias / 7) x 0,05%  =  -(' + fmtN(pmDif/7,4) + ' semanas) x 0,05%  =  ' + fmtPct(ajuste,4));
            spacer(1);
            if (Math.abs(pmDif) < 0.1) {
                txt('Interpretacao: O PM e exatamente igual ao padrao de 28 dias. Nenhum ajuste necessario — o ajuste e zero.', 0, false, 90,90,90);
            } else {
                txt('Interpretacao: Como o prazo e ' + Math.abs(pmDif).toFixed(0) + ' dias ' + (pmDif > 0 ? 'a mais' : 'a menos') + ' que o padrao de 28 dias (' + Math.abs(pmDif/7).toFixed(2).replace('.',',') + ' semanas), a comissao e ' + (pmDif > 0 ? 'REDUZIDA' : 'AUMENTADA') + ' em ' + (Math.abs(ajuste)*100).toFixed(4).replace('.',',') + '%.', 0, false, 90,90,90);
            }
            spacer(3);

            // ══════════════════════════════════════════════════
            // ETAPA 8 — Validacoes de Aprovacao
            // ══════════════════════════════════════════════════
            etapaHeader(8, 'Validacoes de Aprovacao', 231,76,60);
            txt('Algumas situacoes excedem os limites normais de operacao e exigem aprovacao da gestao antes do pagamento da comissao. Isso e uma protecao para a empresa em casos atipicos. Duas regras sao verificadas:');
            spacer(2);

            const regras = [
                {
                    num: 1,
                    label: 'Desconto acima de 20,00%',
                    check: !semLista && dsc_pct > 0.20,
                    valor: fmtPct(dsc_pct,2) + ' (limite: 20,00%)',
                    exp_ok:  'Desconto dentro do limite operacional. Nenhuma aprovacao necessaria por este criterio.',
                    exp_nok: 'DESCONTO ACIMA DO LIMITE! Descontos acima de 20% prejudicam significativamente a margem da empresa e requerem autorizacao gerencial antes do pagamento da comissao.'
                },
                {
                    num: 2,
                    label: 'Prazo Medio acima de 42 dias (6 semanas)',
                    check: pm_dias > 42,
                    valor: fmtN(pm_dias,0) + ' dias (limite: 42 dias)',
                    exp_ok:  'Prazo dentro do limite operacional. Nenhuma aprovacao necessaria por este criterio.',
                    exp_nok: 'PRAZO ACIMA DO LIMITE! Prazos superiores a 42 dias representam custo financeiro significativo e requerem autorizacao gerencial antes do pagamento da comissao.'
                },
            ];

            regras.forEach(function(r) {
                checkY(18);
                doc.setFillColor(r.check ? 254:240, r.check ? 226:253, r.check ? 226:244);
                doc.rect(ML+2, y, usableW-4, 16, 'F');
                doc.setFont('helvetica','bold'); doc.setFontSize(7.5);
                doc.setTextColor(...(r.check ? [180,0,0] : [10,100,50]));
                doc.text((r.check ? '[!] ' : '[OK] ') + 'Regra ' + r.num + ': ' + r.label, ML+5, y+4.5);
                doc.setFont('helvetica','normal'); doc.setFontSize(7); doc.setTextColor(70,70,70);
                doc.text('Valor encontrado: ' + r.valor, ML+7, y+9);
                const msgLines = doc.splitTextToSize(r.check ? r.exp_nok : r.exp_ok, usableW-14);
                msgLines.forEach(function(l, li) { doc.text(l, ML+7, y+13 + li*4); });
                doc.setTextColor(40,40,40);
                y += 18;
            });
            spacer(1);
            if (flagAprov) {
                resultado('STATUS DA ETAPA 8:', 'REQUER APROVACAO GERENCIAL', 254,226,226, 180,0,0);
            } else {
                resultado('STATUS DA ETAPA 8:', 'Sem pendencias de aprovacao', 232,246,239, 10,100,50);
            }
            spacer(3);

            // ══════════════════════════════════════════════════
            // ETAPA 9 — Calculo da Comissao
            // ══════════════════════════════════════════════════
            etapaHeader(9, 'Calculo da Comissao sobre a Venda', 39,174,96);
            if (semLista) {
                checkY(7);
                doc.setFillColor(255,243,205);
                doc.rect(ML+2, y, usableW-4, 6, 'F');
                doc.setFont('helvetica','bold'); doc.setFontSize(7.5); doc.setTextColor(133,77,14);
                doc.text('Comissao = R$ 0,00: produto sem Price List. Consulte a Etapa 5 acima.', ML+5, y+4);
                doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40); y += 7.5;
            } else {
                txt('Agora reunimos todas as pecas calculadas nas etapas anteriores. O percentual final de comissao e a soma do percentual base (Etapa 5) com o ajuste de prazo (Etapa 7). Depois multiplicamos esse percentual pela Venda Net (Etapa 4) para chegar no valor monetario da comissao.');
                spacer(1);
                txt('Passo 1 — Percentual Final:', 0, true, 50,80,50);
                formula('% Final = % Base + Ajuste de Prazo');
                formula('% Final = ' + fmtPct(base_pct,4) + ' + (' + fmtPct(ajuste,4) + ')  =  ' + fmtPct(final_pct,4));
                spacer(1);
                if ((base_pct + ajuste) < 0.0005 && !semLista) {
                    checkY(7);
                    doc.setFillColor(255,243,205);
                    doc.rect(ML+2, y, usableW-4, 6, 'F');
                    doc.setFont('helvetica','bold'); doc.setFontSize(7.2); doc.setTextColor(133,77,14);
                    doc.text('PISO MINIMO APLICADO: O % calculado ficaria abaixo de 0,05%. A regra garante no minimo 0,05%.', ML+5, y+4);
                    doc.setFont('helvetica','normal'); doc.setTextColor(40,40,40); y += 7.5;
                }
                spacer(1);
                txt('Passo 2 — Valor da Comissao:', 0, true, 50,80,50);
                formula('Comissao = Venda Net x % Final');
                formula('Comissao = R$ ' + fmtBRL(venda_net) + ' x ' + fmtPct(final_pct,4) + '  =  R$ ' + fmtBRL(comissaoPreTeto));
                spacer(1);
                resultado('Comissao calculada (antes de aplicar teto):', 'R$ ' + fmtBRL(comissaoPreTeto), 232,246,239, 10,100,50);
            }
            spacer(3);

            // ══════════════════════════════════════════════════
            // ETAPA 10 — Piso e Teto
            // ══════════════════════════════════════════════════
            etapaHeader(10, 'Aplicacao do Piso Minimo e Teto Maximo', 52,73,94);
            txt('Para garantir equidade, existem dois limites que protegem tanto o representante (piso) quanto a empresa (teto):');
            spacer(2);

            // Piso
            checkY(14);
            doc.setFillColor(240,253,244);
            doc.rect(ML+2, y, usableW-4, 12.5, 'F');
            doc.setFont('helvetica','bold'); doc.setFontSize(7.5); doc.setTextColor(10,100,50);
            doc.text('PISO (minimo): 0,05% da Venda Net', ML+5, y+4.5);
            doc.setFont('helvetica','normal'); doc.setFontSize(7); doc.setTextColor(60,60,60);
            doc.text('Nenhuma comissao pode ser menor que 0,05% da Venda Net. Isso garante remuneracao minima ao representante.', ML+7, y+8.5);
            const pisoAtivado = !semLista && final_pct < 0.0005;
            doc.text('Piso minimo = R$ ' + fmtBRL(venda_net * 0.0005) + '  |  % Final = ' + fmtPct(final_pct,4) + '  ->  ' + (pisoAtivado ? 'PISO ATIVADO (0,05% aplicado).' : 'Piso NAO ativado (% Final ja esta acima do minimo).'), ML+7, y+12);
            doc.setTextColor(40,40,40);
            y += 14;
            spacer(2);

            // Teto
            checkY(16);
            doc.setFillColor(flagTeto ? 255:240, flagTeto ? 243:253, flagTeto ? 205:244);
            doc.rect(ML+2, y, usableW-4, flagTeto ? 18 : 12.5, 'F');
            doc.setFont('helvetica','bold'); doc.setFontSize(7.5);
            doc.setTextColor(...(flagTeto ? [180,100,0] : [10,100,50]));
            doc.text('TETO (maximo): R$ 25.000,00 por item', ML+5, y+4.5);
            doc.setFont('helvetica','normal'); doc.setFontSize(7); doc.setTextColor(60,60,60);
            doc.text('Nenhuma comissao pode ultrapassar R$ 25.000,00 por item. A parcela acima do teto vira bonificacao (10% do excedente).', ML+7, y+8.5);
            if (flagTeto) {
                const excedente = comissaoPreTeto - 25000;
                doc.text('TETO ATIVADO! Calculo: R$ 25.000,00 + (' + 'R$ ' + fmtBRL(comissaoPreTeto) + ' - R$ 25.000,00) x 10%', ML+7, y+12.5);
                doc.text('= R$ 25.000,00 + R$ ' + fmtBRL(excedente) + ' x 10%  =  R$ 25.000,00 + R$ ' + fmtBRL(excedente*0.1) + '  =  R$ ' + fmtBRL(comissao), ML+7, y+16.5);
                y += 19.5;
            } else {
                doc.text('Comissao calculada (R$ ' + fmtBRL(comissaoPreTeto) + ') esta abaixo de R$ 25.000,00. Teto NAO ativado.', ML+7, y+12);
                y += 14;
            }
            doc.setTextColor(40,40,40);
            spacer(3);

            // ══════════════════════════════════════════════════
            // RESULTADO FINAL
            // ══════════════════════════════════════════════════
            checkY(20);
            const corFR = semLista ? [220,220,220] : flagTeto ? [255,243,200] : flagAprov ? [255,235,235] : [210,237,218];
            const corTR = semLista ? [80,80,80]    : flagTeto ? [150,80,0]    : flagAprov ? [150,0,0]     : [10,80,40];
            doc.setFillColor(...corFR);
            doc.rect(ML, y, usableW, 15, 'F');
            doc.setDrawColor(...corTR); doc.setLineWidth(0.6);
            doc.rect(ML, y, usableW, 15, 'S');
            doc.setFont('helvetica','bold'); doc.setFontSize(9.5); doc.setTextColor(...corTR);
            doc.text('RESULTADO FINAL', ML+4, y+5.5);
            doc.setFontSize(12);
            doc.text('R$ ' + fmtBRL(comissao), PW-MR-4, y+5.5, {align:'right'});
            doc.setFontSize(7.5); doc.setFont('helvetica','normal');
            const pctEfetiva = (venda_net > 0 && !semLista) ? fmtPct(comissao/venda_net,4) : '--';
            doc.text('Venda Net: R$ ' + fmtBRL(venda_net) + '  |  % Efetiva: ' + pctEfetiva + '  |  ' + (semLista ? 'S/ Price List — comissao zerada' : flagTeto ? 'Teto de R$ 25.000 atingido' : flagAprov ? 'Aguardando aprovacao gerencial' : 'Comissao aprovada automaticamente'), ML+4, y+11);
            doc.setTextColor(40,40,40); doc.setDrawColor(0,0,0); doc.setLineWidth(0.2);
            y += 17;

            if (item.obs && item.obs.trim()) {
                checkY(6);
                doc.setFontSize(7); doc.setTextColor(100,100,100); doc.setFont('helvetica','italic');
                doc.text(fixText('Obs: ' + item.obs.slice(0,120)), ML+2, y+3.5);
                doc.setTextColor(40,40,40); doc.setFont('helvetica','normal'); y += 5;
            }

            // Separador entre itens
            checkY(12);
            doc.setDrawColor(10,30,66); doc.setLineWidth(0.6);
            doc.line(ML, y+4, PW-MR, y+4); y += 12;
        });

        // ── Rodape totalizador ─────────────────────────────────────────────────
        checkY(14);
        const totalNet = filteredItems.reduce(function(a,i){ return a+parseFloat(i.venda_net||0); }, 0);
        const totalCom = filteredItems.reduce(function(a,i){ return a+parseFloat(i.valor_comissao||0); }, 0);
        doc.setFillColor(10,30,66); doc.rect(ML, y, usableW, 12, 'F');
        doc.setTextColor(255,255,255); doc.setFontSize(9); doc.setFont('helvetica','bold');
        doc.text('RESUMO GERAL: ' + filteredItems.length + ' item(ns)', ML+4, y+5.5);
        doc.setFontSize(8);
        doc.text('Venda Net Total: R$ ' + fmtBRL(totalNet) + '  |  Comissao Total: R$ ' + fmtBRL(totalCom) + '  |  % Media Efetiva: ' + (totalNet>0 ? ((totalCom/totalNet)*100).toFixed(4).replace('.',',')+'%' : '--'), ML+4, y+10.5);
        doc.setTextColor(0,0,0);

        const slug = selectedRep.replace(/[^\w]/g,'_').slice(0,20);
        doc.save('Auditoria_Comissao_' + slug + '_' + new Date().toISOString().slice(0,10) + '.pdf');
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
            preco_lista_usd: 0, // Zera USD ao editar BRL manualmente — preserva valor no reprocessamento
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

        const batchNome = fixText(document.querySelector('h4.text-primary').textContent);
        doc.setFontSize(13);
        doc.text(batchNome, 14, 14);
        doc.setFontSize(9);
        doc.text('Gerado em: ' + new Date().toLocaleString('pt-BR'), 14, 20);

        const dados = filteredItems.map(item => {
            const semLista = parseInt(item.lista_nao_encontrada||0) === 1;
            return [
                fixText((item.representante||'-').substring(0,20)),
                item.data_nf || '-',
                item.nfe,
                item.codigo,
                fixText((item.descricao||'-').substring(0,80)),
                fixText((item.cliente||'-').substring(0,22)),
                'R$ ' + parseFloat(item.venda_net||0).toLocaleString('pt-BR',{minimumFractionDigits:2}),
                semLista ? 'S/ Lista' : 'R$ ' + parseFloat(item.preco_lista_brl||0).toLocaleString('pt-BR',{minimumFractionDigits:2}),
                semLista ? '-' : (parseFloat(item.desconto_pct||0)*100).toFixed(1) + '%',
                semLista ? '-' : (parseFloat(item.comissao_final_pct||0)*100).toFixed(2) + '%',
                semLista ? 'S/ Lista' : 'R$ ' + parseFloat(item.valor_comissao||0).toLocaleString('pt-BR',{minimumFractionDigits:2})
            ];
        });

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
            },
            didParseCell: (data) => {
                // Destaca linhas S/Lista em cinza claro
                if (data.row.raw && data.row.raw[7] === 'S/ Lista') {
                    data.cell.styles.textColor = [120, 120, 120];
                    data.cell.styles.fontStyle = 'italic';
                }
            }
        });

        const filtroAtivo = document.querySelector('input[name=filtroStatus]:checked').value;
        const repSelect = document.getElementById('filtroRep');
        const selectedReps = Array.from(repSelect.selectedOptions).map(opt => opt.value).filter(val => val !== "");
        const repFiltro = fixText(selectedReps.length ? selectedReps.join(', ') : 'Todos');

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

        const batchNome = fixText(document.querySelector('h4.text-primary').textContent);
        doc.setFontSize(13);
        doc.text("Relatorio de Divergencia de Embalagem - S/Lista", 14, 14);
        doc.setFontSize(10);
        doc.text(batchNome, 14, 20);
        doc.setFontSize(8);
        doc.text('Gerado em: ' + new Date().toLocaleString('pt-BR'), 14, 25);

        const dados = itensDivergentesGlobais.map(item => [
            item.codigo,
            fixText((item.nome_produto || '-').substring(0, 40)),
            item.nfe || '-',
            item.embalagem_db || '-',
            item.opcoes_price_list.map(o => o.embalagem).join(', ') || '-',
            fixText((item.representante || '-').substring(0, 20))
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

        const batchNome = fixText(document.querySelector('h4.text-primary').textContent);
        doc.setFontSize(14);
        doc.text('Resumo de Comissoes por Representante', 14, 14);
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
                fixText(rep),
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
