<?php
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
                <a href="api/export_commission.php?batch_id=<?= $batch_id ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Exportar CSV</a>
            </div>
        </div>

        <!-- Cards Resumo -->
        <div class="row mb-3" id="cardsResumo"></div>

        <!-- Filtros -->
        <div class="card shadow-sm mb-3 no-print">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-3 align-items-center filter-bar">
                    <strong>Filtrar:</strong>
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
                    <select class="form-select form-select-sm" id="filtroRep" style="width:220px;">
                        <option value="">Todos os Representantes</option>
                        <?php foreach($representantes as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-primary" onclick="aplicarFiltros()"><i class="bi bi-funnel"></i> Aplicar</button>
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
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEdit'));

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
            sem_lista: '<span class="badge badge-sl">S/ Lista</span>',
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
        const rep    = document.getElementById('filtroRep').value;

        filteredItems = allItems.filter(item => {
            const st = calcStatusItem(item);
            const matchStatus = status === 'todos' || st === status;
            const matchRep    = !rep || item.representante === rep;
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
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

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
            (item.cliente||'-').substring(0,22),
            'R$ ' + parseFloat(item.venda_net||0).toLocaleString('pt-BR',{minimumFractionDigits:2}),
            (parseFloat(item.desconto_pct||0)*100).toFixed(1) + '%',
            (parseFloat(item.comissao_final_pct||0)*100).toFixed(2) + '%',
            'R$ ' + parseFloat(item.valor_comissao||0).toLocaleString('pt-BR',{minimumFractionDigits:2}),
            calcStatusItem(item).replace('_', ' ').toUpperCase()
        ]);

        doc.autoTable({
            startY: 25,
            head: [['Representante','Data','NF','Código','Cliente','Venda Net','Desc%','% Final','Comissão','Status']],
            body: dados,
            styles: { fontSize: 7, cellPadding: 1.5 },
            headStyles: { fillColor: [13, 110, 253] },
            alternateRowStyles: { fillColor: [245, 245, 245] },
        });

        const filtroAtivo = document.querySelector('input[name=filtroStatus]:checked').value;
        const repFiltro = document.getElementById('filtroRep').value || 'Todos';
        doc.setFontSize(7);
        doc.text(`Filtro: Status=${filtroAtivo} | Representante=${repFiltro} | Total: ${filteredItems.length} itens`, 14, doc.lastAutoTable.finalY + 5);

        doc.save(`comissao_lote_${BATCH_ID}.pdf`);
    }
    </script>
</body>
</html>
