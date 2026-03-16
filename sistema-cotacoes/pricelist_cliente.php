<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
$pagina_ativa = 'pricelist_cliente';
require_once 'header.php';
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html'); exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price List Geral</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; font-family: 'Montserrat', sans-serif; }

        /* ─── Header ───────────────────────────────────────────────── */
        .page-header {
            background: linear-gradient(135deg, #1a6b3c 0%, #2d9e5f 100%);
            color: #fff;
            padding: 1.5rem 0 2rem;
            margin-bottom: 1.5rem;
        }
        .page-header h1 { font-weight: 700; font-size: 1.7rem; }

        /* ─── Card da tabela ───────────────────────────────────────── */
        .table-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
            overflow: hidden;
        }
        .table-card .table-header {
            background: linear-gradient(90deg, #1a6b3c, #2d9e5f);
            color: #fff;
            padding: .85rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .table-card .table-header h5 { margin: 0; font-weight: 600; font-size: 1rem; }

        /* ─── Tabela ────────────────────────────────────────────────── */
        #tabelaProdutos { font-size: .78rem; }

        #tabelaProdutos thead tr.th-labels th {
            background: #f0faf4;
            color: #1a6b3c;
            font-weight: 700;
            white-space: nowrap;
            padding: .55rem .6rem;
            border-bottom: 1px solid #d1eedd;
            vertical-align: middle;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        #tabelaProdutos thead tr.th-filters th {
            background: #fafdfc;
            padding: .3rem .4rem;
            border-bottom: 2px solid #d1eedd;
            position: sticky;
            top: 37px; /* ajuste conforme altura da primeira linha */
            z-index: 2;
        }
        #tabelaProdutos thead tr.th-filters input {
            font-size: .72rem;
            border-radius: 5px;
            border: 1px solid #cde8d8;
            padding: .2rem .4rem;
            width: 100%;
            min-width: 70px;
            background: #fff;
            color: #222;
            outline: none;
            transition: border-color .15s;
        }
        #tabelaProdutos thead tr.th-filters input:focus {
            border-color: #2d9e5f;
            box-shadow: 0 0 0 2px rgba(45,158,95,.15);
        }

        #tabelaProdutos tbody tr:hover { background: #f4fbf6; }
        #tabelaProdutos td { padding: .45rem .6rem; vertical-align: middle; }

        /* Produto + cliente na mesma célula */
        .cell-produto .prod-nome   { font-weight: 600; color: #111; }
        .cell-produto .prod-cliente { font-size: .7rem; color: #888; margin-top: 1px; }

        /* Price List destacado */
        .col-pricelist          { background: #fffbe6; font-weight: 700; color: #856404; }
        .col-pricelist.has-value{ background: #e9f5ef; color: #1a6b3c; }

        /* Prazo Médio — vazio por enquanto */
        .col-prazo { color: #aaa; font-style: italic; font-size: .73rem; }

        /* Loading / vazio */
        #loadingState, #emptyState { display: none; }

        /* Contador de registros */
        .badge-count {
            background: rgba(255,255,255,.25);
            color: #fff;
            padding: .25em .65em;
            border-radius: 20px;
            font-size: .8rem;
            font-weight: 600;
        }

        /* Wrapper da tabela com scroll */
        .table-scroll-wrap {
            max-height: calc(100vh - 270px);
            overflow-y: auto;
            overflow-x: auto;
        }

        /* Spinner */
        .spinner-sm { width: 1.2rem; height: 1.2rem; border-width: 2px; }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1><i class="bi bi-tags me-2"></i>Price List Geral <small class="opacity-75 fs-6">(Budget 2026 v2.0)</small></h1>
                <p class="mb-0 opacity-75 small">Todos os produtos e clientes com histórico de preços e volumes.</p>
            </div>
            <div id="loadingState">
                <div class="spinner-border spinner-sm text-light" role="status"></div>
                <span class="ms-2 small">Carregando dados...</span>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">

    <!-- ─── Tabela geral ──────────────────────────────────────────────────────── -->
    <div class="table-card">
        <div class="table-header">
            <h5><i class="bi bi-table me-2"></i>Produtos
                <span class="badge-count ms-2" id="contadorRegistros">0 registros</span>
            </h5>
            <div class="d-flex gap-2 align-items-center">
                <button class="btn btn-sm btn-light" id="btnLimparFiltros" title="Limpar todos os filtros">
                    <i class="bi bi-x-circle me-1 text-danger"></i>Limpar filtros
                </button>
                <button class="btn btn-sm btn-light" id="btnExportar">
                    <i class="bi bi-file-earmark-excel me-1 text-success"></i>Exportar CSV
                </button>
            </div>
        </div>

        <div class="table-scroll-wrap">
            <table class="table table-hover mb-0" id="tabelaProdutos">
                <thead>
                    <!-- Linha de rótulos -->
                    <tr class="th-labels">
                        <th>Produto / Cliente</th>
                        <th>Cliente Destino</th>
                        <th>Vendedor</th>
                        <th>Fabricante</th>
                        <th class="text-center">Emb. (KG)</th>
                        <th class="text-end">KG 17–24</th>
                        <th class="text-end">KG 2025</th>
                        <th class="text-end">KG Orç. 2026</th>
                        <th class="text-end">Preço Médio NET<br>17-24 (USD)</th>
                        <th class="text-end">Preço Médio NET<br>2025 (USD)</th>
                        <th class="text-end">Preço Médio NET<br>2026 (USD)</th>
                        <th class="text-end">Price List (USD)</th>
                        <th class="text-center">Prazo Médio</th>
                        <th class="text-center"></th>
                    </tr>
                    <!-- Linha de filtros -->
                    <tr class="th-filters">
                        <th><input type="text" id="f-produto"          placeholder="Filtrar..."></th>
                        <th><input type="text" id="f-cliente_destino"  placeholder="Filtrar..."></th>
                        <th><input type="text" id="f-vendedor"         placeholder="Filtrar..."></th>
                        <th><input type="text" id="f-fabricante"       placeholder="Filtrar..."></th>
                        <th></th><!-- embalagem: sem filtro de texto -->
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="produtosBody"></tbody>
            </table>

            <div id="emptyState" class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                Nenhum registro encontrado com os filtros aplicados.
            </div>
        </div><!-- /table-scroll-wrap -->
    </div>

</div><!-- /container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ─── Estado ─────────────────────────────────────────────────────────────────────
let todosOsProdutos = [];

// ─── Elementos ──────────────────────────────────────────────────────────────────
const produtosBody       = document.getElementById('produtosBody');
const emptyState         = document.getElementById('emptyState');
const loadingState       = document.getElementById('loadingState');
const contadorRegistros  = document.getElementById('contadorRegistros');

// Inputs de filtro
const filtros = {
    produto:         document.getElementById('f-produto'),
    cliente_destino: document.getElementById('f-cliente_destino'),
    vendedor:        document.getElementById('f-vendedor'),
    fabricante:      document.getElementById('f-fabricante'),
};

// ─── Helpers de formatação ───────────────────────────────────────────────────────
function esc(s)    { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmt2(v)   { if (!v && v !== 0) return '—'; return Number(v).toLocaleString('pt-BR', {minimumFractionDigits:2,maximumFractionDigits:4}); }
function fmtUSD(v) { if (!v) return '<span class="text-muted">—</span>'; return '$ ' + Number(v).toLocaleString('pt-BR', {minimumFractionDigits:2,maximumFractionDigits:4}); }
function fmtKg(v)  { if (!v && v !== 0) return '<span class="text-muted">—</span>'; return Number(v).toLocaleString('pt-BR', {minimumFractionDigits:3,maximumFractionDigits:3}); }

// ─── Carregar dados ──────────────────────────────────────────────────────────────
async function carregarDados() {
    loadingState.style.display = 'flex';
    try {
        const res  = await fetch('api_budget_cliente.php?action=buscar_todos');
        const data = await res.json();

        if (data?.__erro) {
            alert('Erro ao carregar dados: ' + data.__erro);
            return;
        }
        todosOsProdutos = Array.isArray(data) ? data : [];
        renderTabela(todosOsProdutos);
    } catch(e) {
        alert('Erro de comunicação: ' + e.message);
    } finally {
        loadingState.style.display = 'none';
    }
}

// ─── Renderizar tabela ────────────────────────────────────────────────────────────
function renderTabela(produtos) {
    produtosBody.innerHTML = '';

    // Aplica filtros
    const fProd  = filtros.produto.value.toLowerCase().trim();
    const fDest  = filtros.cliente_destino.value.toLowerCase().trim();
    const fVend  = filtros.vendedor.value.toLowerCase().trim();
    const fFabr  = filtros.fabricante.value.toLowerCase().trim();

    const lista = produtos.filter(p => {
        if (fProd && !(
            (p.produto  || '').toLowerCase().includes(fProd) ||
            (p.cliente  || '').toLowerCase().includes(fProd)
        )) return false;
        if (fDest && !(p.cliente_destino || '').toLowerCase().includes(fDest)) return false;
        if (fVend && !(p.vendedor || '').toLowerCase().includes(fVend)) return false;
        if (fFabr && !(p.fabricante || '').toLowerCase().includes(fFabr)) return false;
        return true;
    });

    // Atualizar contador
    contadorRegistros.textContent = lista.length.toLocaleString('pt-BR') + ' registros';

    if (!lista.length) {
        emptyState.style.display = 'block';
        return;
    }
    emptyState.style.display = 'none';

    const fragment = document.createDocumentFragment();
    lista.forEach(p => {
        const plVal   = p.price_list_usd ? '$ ' + fmt2(p.price_list_usd) : '—';
        const plClass = p.price_list_usd ? 'col-pricelist has-value' : 'col-pricelist';
        const prazo   = p.prazo_medio    ? esc(p.prazo_medio) : '<span class="col-prazo">—</span>';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="cell-produto">
                    <div class="prod-nome">${esc(p.produto || '—')}</div>
                    <div class="prod-cliente"><i class="bi bi-building me-1"></i>${esc(p.cliente || '—')}</div>
                </div>
            </td>
            <td class="text-muted">${esc(p.cliente_destino || '—')}</td>
            <td class="text-muted">${esc(p.vendedor || '—')}</td>
            <td class="text-muted">${esc(p.fabricante || '—')}</td>
            <td class="text-center">${fmtKg(p.embalagem)}</td>
            <td class="text-end">${fmtKg(p.kg_historico)}</td>
            <td class="text-end">${fmtKg(p.kg_realizado_2025)}</td>
            <td class="text-end">${fmtKg(p.kg_orcado_2026)}</td>
            <td class="text-end">${fmtUSD(p.preco_hist_usd)}</td>
            <td class="text-end">${fmtUSD(p.preco_2025_usd)}</td>
            <td class="text-end">${fmtUSD(p.preco_orcado_2026_usd)}</td>
            <td class="text-end ${plClass}">${plVal}</td>
            <td class="text-center">${prazo}</td>
            <td class="text-center">
              <button class="btn btn-xs btn-outline-success py-0 px-1" style="font-size:.68rem;white-space:nowrap"
                      data-produto="${esc(p.produto)}"
                      onclick="abrirModalPricelist(this.dataset.produto)"
                      title="Ver Price List completo do produto">
                <i class="bi bi-tags me-1"></i>PRICELIST
              </button>
            </td>`;
        fragment.appendChild(row);
    });
    produtosBody.appendChild(fragment);
}

// ─── Filtros ─────────────────────────────────────────────────────────────────────
Object.values(filtros).forEach(inp => {
    inp.addEventListener('input', () => renderTabela(todosOsProdutos));
});

// ─── Limpar filtros ───────────────────────────────────────────────────────────────
document.getElementById('btnLimparFiltros').addEventListener('click', () => {
    Object.values(filtros).forEach(inp => inp.value = '');
    renderTabela(todosOsProdutos);
});

// ─── Exportar CSV ─────────────────────────────────────────────────────────────────
document.getElementById('btnExportar').addEventListener('click', () => {
    if (!todosOsProdutos.length) return;
    const headers = [
        'Produto','Cliente','Cliente Destino','Vendedor','Fabricante',
        'Embalagem (KG)','KG 17-24','KG 2025','KG Orç.2026',
        'Preço Médio NET 17-24 USD','Preço Médio NET 2025 USD','Preço Médio NET 2026 USD',
        'Price List USD','Prazo Médio'
    ];
    const rows = todosOsProdutos.map(p => [
        p.produto, p.cliente, p.cliente_destino, p.vendedor, p.fabricante,
        p.embalagem, p.kg_historico, p.kg_realizado_2025, p.kg_orcado_2026,
        p.preco_hist_usd, p.preco_2025_usd, p.preco_orcado_2026_usd,
        p.price_list_usd, p.prazo_medio
    ].map(v => (v === null || v === undefined) ? '' : String(v).replace(/;/g, ',')));

    const bom  = '\xEF\xBB\xBF';
    const csv  = bom + [headers, ...rows].map(r => r.join(';')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `pricelist_geral_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
});

// ─── Iniciar ──────────────────────────────────────────────────────────────────────
carregarDados();
</script>

<!-- ─── Modal Price List completo do produto ────────────────────────────────── -->
<div class="modal fade" id="modalPricelist" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(90deg,#1a6b3c,#2d9e5f);color:#fff">
        <h5 class="modal-title"><i class="bi bi-tags me-2"></i>Price List — <span id="plNomeProduto"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div id="plLoading" class="text-center py-4">
          <div class="spinner-border text-success" role="status"></div>
          <p class="mt-2 text-muted small">Carregando...</p>
        </div>
        <div id="plConteudo" style="display:none">
          <table class="table table-sm table-hover mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Fabricante</th>
                <th>Código</th>
                <th class="text-end">Emb. (KG)</th>
                <th class="text-center">Frac.</th>
                <th>Lead Time</th>
                <th class="text-end fw-bold text-success">Preço Net (USD)</th>
                <th>Classif.</th>
              </tr>
            </thead>
            <tbody id="plBody"></tbody>
          </table>
          <p id="plVazio" class="text-center text-muted py-4 mb-0" style="display:none">
            <i class="bi bi-inbox me-1"></i>Produto não encontrado no Price List atual.
          </p>
        </div>
      </div>
      <div class="modal-footer" style="background:#f8f9fa">
        <small class="text-muted me-auto"><i class="bi bi-info-circle me-1"></i>Todas as embalagens disponíveis na price list geral.</small>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<script>
function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function abrirModalPricelist(nomeProduto) {
    const modalEl = document.getElementById('modalPricelist');
    const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);

    document.getElementById('plNomeProduto').textContent = nomeProduto;
    document.getElementById('plLoading').style.display   = 'block';
    document.getElementById('plConteudo').style.display  = 'none';
    document.getElementById('plBody').innerHTML          = '';
    document.getElementById('plVazio').style.display     = 'none';
    modal.show();

    try {
        const res  = await fetch('api_budget_cliente.php?action=buscar_pricelist_produto&produto=' + encodeURIComponent(nomeProduto));
        const data = await res.json();

        document.getElementById('plLoading').style.display  = 'none';
        document.getElementById('plConteudo').style.display = 'block';

        const rows = Array.isArray(data) ? data : [];
        if (rows.length === 0) {
            document.getElementById('plVazio').style.display = 'block';
            return;
        }

        const tbody = document.getElementById('plBody');
        rows.forEach(r => {
            const tr    = document.createElement('tr');
            const preco = r.preco_net_usd
                ? '<strong class="text-success">$ ' + Number(r.preco_net_usd).toLocaleString('pt-BR', {minimumFractionDigits:2,maximumFractionDigits:4}) + '</strong>'
                : '<span class="text-muted">—</span>';
            const emb = r.embalagem
                ? Number(r.embalagem).toLocaleString('pt-BR', {minimumFractionDigits:3}) + ' KG'
                : '—';
            tr.innerHTML = `
                <td>${esc(r.fabricante || '—')}</td>
                <td><code>${esc(r.codigo || '—')}</code></td>
                <td class="text-end">${emb}</td>
                <td class="text-center">${esc(r.fracionado || '—')}</td>
                <td class="text-muted">${esc(r.lead_time || '—')}</td>
                <td class="text-end">${preco}</td>
                <td class="text-muted small">${esc(r.classificacao || '')}</td>`;
            tbody.appendChild(tr);
        });
    } catch(e) {
        document.getElementById('plLoading').style.display  = 'none';
        document.getElementById('plConteudo').style.display = 'block';
        document.getElementById('plVazio').innerHTML = '<i class="bi bi-exclamation-triangle text-danger me-1"></i>Erro: ' + e.message;
        document.getElementById('plVazio').style.display = 'block';
    }
}
</script>

</body>
</html>
