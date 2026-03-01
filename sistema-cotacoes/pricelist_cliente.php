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
    <title>Price List por Cliente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; font-family: 'Montserrat', sans-serif; }

        /* ─── Header da página ─────────────────────────────────── */
        .page-header {
            background: linear-gradient(135deg, #1a6b3c 0%, #2d9e5f 100%);
            color: #fff;
            padding: 2rem 0 2.5rem;
            margin-bottom: 2rem;
        }
        .page-header h1 { font-weight: 700; font-size: 1.8rem; }

        /* ─── Autocomplete ─────────────────────────────────────── */
        .autocomplete-wrapper { position: relative; }
        .autocomplete-list {
            position: absolute; top: 100%; left: 0; right: 0; z-index: 9999;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
            max-height: 300px; overflow-y: auto;
        }
        .autocomplete-list .ac-item {
            padding: .65rem 1rem; cursor: pointer; font-size: .9rem;
            border-bottom: 1px solid #f1f3f4;
            transition: background .15s;
        }
        .autocomplete-list .ac-item:hover { background: #e9f5ef; }
        .autocomplete-list .ac-item .ac-razao { font-weight: 600; color: #1a1a1a; }
        .autocomplete-list .ac-item .ac-cnpj  { color: #888; font-size: .8rem; }

        /* ─── Card do cliente ─────────────────────────────────── */
        .client-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
            padding: 1.4rem 1.8rem;
            margin-bottom: 1.5rem;
            display: none;
        }
        .client-card .client-name { font-size: 1.25rem; font-weight: 700; color: #1a6b3c; }
        .client-card .client-meta { font-size: .85rem; color: #666; }
        .badge-vendedor {
            background: #e6f4ee; color: #1a6b3c;
            padding: .3em .75em; border-radius: 20px;
            font-size: .8rem; font-weight: 600;
        }

        /* ─── Tabela ──────────────────────────────────────────── */
        .table-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
            overflow: hidden;
            display: none;
        }
        .table-card .table-header {
            background: linear-gradient(90deg, #1a6b3c, #2d9e5f);
            color: #fff;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem;
        }
        .table-card .table-header h5 { margin: 0; font-weight: 600; }
        #tabelaProdutos {
            font-size: .82rem;
        }
        #tabelaProdutos thead th {
            background: #f0faf4;
            color: #1a6b3c;
            font-weight: 700;
            white-space: nowrap;
            padding: .6rem .75rem;
            border-bottom: 2px solid #d1eedd;
        }
        #tabelaProdutos tbody tr:hover { background: #f9fdf9; }
        #tabelaProdutos td { padding: .5rem .75rem; vertical-align: middle; }

        /* Coluna price list destacada */
        .col-pricelist {
            background: #fffbe6;
            font-weight: 700;
            color: #856404;
        }
        .col-pricelist.has-value {
            background: #e9f5ef;
            color: #1a6b3c;
        }

        /* Sem dados */
        #emptyState { display: none; }
        #loadingState { display: none; }

        /* ─── Busca inicial ───────────────────────────────────── */
        .search-hero {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
            padding: 2.5rem;
            text-align: center;
        }
        .search-hero .search-icon {
            font-size: 3rem; color: #2d9e5f; margin-bottom: 1rem;
        }

        /* Spinner */
        .spinner-sm { width: 1.2rem; height: 1.2rem; border-width: 2px; }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container">
        <h1><i class="bi bi-tags me-2"></i>Price List por Cliente</h1>
        <p class="mb-0 opacity-75">Consulte os produtos, históricos de compra e preços negociados por cliente.</p>
    </div>
</div>

<div class="container pb-5">

    <!-- ─── Busca de cliente ─────────────────────────────────────────────────── -->
    <div class="search-hero mb-4">
        <div class="search-icon"><i class="bi bi-search"></i></div>
        <h5 class="mb-1">Selecione o Cliente</h5>
        <p class="text-muted mb-3 small">Digite o nome fantasia, razão social ou CNPJ</p>

        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="autocomplete-wrapper">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white"><i class="bi bi-building text-success"></i></span>
                        <input type="text" id="buscaCliente" class="form-control"
                               placeholder="Ex: Empresa XYZ ou 08816379000114"
                               autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="btnLimpar" title="Limpar" style="display:none">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="autocomplete-list" id="autocompleteList" style="display:none"></div>
                </div>
            </div>
        </div>

        <div id="loadingState" class="mt-3">
            <div class="spinner-border spinner-sm text-success" role="status"></div>
            <span class="ms-2 text-muted small">Carregando produtos...</span>
        </div>
    </div>

    <!-- ─── Card do cliente selecionado ─────────────────────────────────────── -->
    <div class="client-card" id="clientCard">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
            <div>
                <div class="client-name" id="clientNome">—</div>
                <div class="client-meta mt-1">
                    CNPJ: <strong id="clientCnpj">—</strong>
                    &nbsp;|&nbsp;
                    Origem: <span id="clientOrigem">—</span>
                </div>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <span class="badge-vendedor" id="clientVendedor"><i class="bi bi-person me-1"></i>—</span>
                <span class="badge bg-secondary" id="clientTotal">0 produtos</span>
            </div>
        </div>
    </div>

    <!-- ─── Tabela de produtos ───────────────────────────────────────────────── -->
    <div class="table-card" id="tableCard">
        <div class="table-header">
            <h5><i class="bi bi-table me-2"></i>Produtos</h5>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <div class="input-group input-group-sm" style="width:220px">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchProduto" class="form-control" placeholder="Filtrar produto...">
                </div>
                <!-- Exportar -->
                <button class="btn btn-sm btn-light" id="btnExportar">
                    <i class="bi bi-file-earmark-excel me-1 text-success"></i> Exportar CSV
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0" id="tabelaProdutos">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Fabricante</th>
                        <th class="text-center">Emb. (KG)</th>
                        <th class="text-end">KG 17–24</th>
                        <th class="text-end">KG 2025</th>
                        <th class="text-end">KG Orç. 2026</th>
                        <th class="text-end">Preço Ant. (USD)</th>
                        <th class="text-end">Preço 2025 (USD)</th>
                        <th class="text-end">Preço Orç. 2026 (USD)</th>
                        <th class="text-end">Price List (USD)</th>
                        <th class="text-center"></th>
                    </tr>
                </thead>
                <tbody id="produtosBody">
                </tbody>
            </table>
        </div>

        <div id="emptyState" class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
            Nenhum produto encontrado para este cliente.
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ─── Estado ────────────────────────────────────────────────────────────────────
let todosOsProdutos = [];
let debounceTimer   = null;
let clienteSelecionado = null;

// ─── Elementos ─────────────────────────────────────────────────────────────────
const inpBusca       = document.getElementById('buscaCliente');
const acList         = document.getElementById('autocompleteList');
const clientCard     = document.getElementById('clientCard');
const tableCard      = document.getElementById('tableCard');
const loadingState   = document.getElementById('loadingState');
const produtosBody   = document.getElementById('produtosBody');
const emptyState     = document.getElementById('emptyState');
const searchProduto  = document.getElementById('searchProduto');
const btnLimpar      = document.getElementById('btnLimpar');

// ─── Autocomplete ──────────────────────────────────────────────────────────────
inpBusca.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    const q = inpBusca.value.trim();
    if (q.length < 2) { acList.style.display = 'none'; return; }
    debounceTimer = setTimeout(() => fetchClientes(q), 300);
});

async function fetchClientes(q) {
    try {
        const res  = await fetch(`api_budget_cliente.php?action=buscar_clientes&q=${encodeURIComponent(q)}`);
        const data = await res.json();
        renderAcList(data);
    } catch(e) { acList.style.display = 'none'; }
}

function renderAcList(clientes) {
    if (!clientes.length) { acList.style.display = 'none'; return; }
    acList.innerHTML = clientes.map(c => `
        <div class="ac-item" data-cnpj="${esc(c.cnpj)}"
             data-nome="${esc(c.nome)}" data-razao="${esc(c.razao_social)}">
            <div class="ac-razao">${esc(c.razao_social || c.nome)}</div>
            <div class="ac-cnpj">${esc(c.cnpj)}</div>
        </div>`).join('');
    acList.style.display = 'block';

    acList.querySelectorAll('.ac-item').forEach(el => {
        el.addEventListener('click', () => selecionarCliente(el.dataset));
    });
}

// Fechar autocomplete ao clicar fora
document.addEventListener('click', e => {
    if (!e.target.closest('.autocomplete-wrapper')) acList.style.display = 'none';
});

// ─── Selecionar cliente ────────────────────────────────────────────────────────
async function selecionarCliente(data) {
    acList.style.display = 'none';
    inpBusca.value = data.razao || data.nome;
    btnLimpar.style.display = '';
    clienteSelecionado = data.cnpj;

    // Mostrar loading
    loadingState.style.display = 'block';
    clientCard.style.display   = 'none';
    tableCard.style.display    = 'none';

    try {
        // Resumo do cliente
        const resResumo = await fetch(`api_budget_cliente.php?action=resumo_cliente&cnpj=${encodeURIComponent(data.cnpj)}`);
        const resumo    = await resResumo.json();

        // Produtos
        const resProd = await fetch(`api_budget_cliente.php?action=buscar_produtos&cnpj=${encodeURIComponent(data.cnpj)}`);
        const rawProd = await resProd.json();

        loadingState.style.display = 'none';

        // Nova estrutura: {produtos: [...], __sem_orcado: bool} ou {__erro: "..."}
        if (rawProd?.__erro) {
            const msg = rawProd.__erro;
            tableCard.style.display = 'block';
            emptyState.style.display = 'block';
            emptyState.innerHTML = `<i class="bi bi-exclamation-triangle fs-2 d-block mb-2 text-danger"></i>
                <span class="text-danger">Erro ao carregar produtos: <code>${esc(msg)}</code></span>`;
            renderClientCard(resumo);
            return;
        }

        // Extrai produtos e flag de sem orçado
        const semOrcado = rawProd?.__sem_orcado ?? false;
        todosOsProdutos = Array.isArray(rawProd?.produtos) ? rawProd.produtos
                        : Array.isArray(rawProd) ? rawProd : [];

        renderClientCard(resumo);

        // Aviso quando KG Orçado 2026 não foi mapeado na importação
        if (semOrcado && todosOsProdutos.length > 0) {
            const warn = document.createElement('div');
            warn.className = 'alert alert-warning alert-dismissible fade show mb-3';
            warn.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>
                <strong>Atenção:</strong> Nenhum produto com KG Orçado 2026 preenchido. Mostrando <strong>todos os produtos</strong> do cliente.
                Reimporte o CSV após verificar a coluna <code>KG Orçado 2026</code>.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            document.querySelector('.container.pb-5').insertBefore(warn, tableCard);
        }

        renderTabela(todosOsProdutos);
    } catch(e) {
        loadingState.style.display = 'none';
        alert('Erro ao carregar dados: ' + e.message);
    }
}

// ─── Renderizar card do cliente ────────────────────────────────────────────────
function renderClientCard(r) {
    document.getElementById('clientNome').textContent   = r.razao_social || r.nome || '—';
    document.getElementById('clientCnpj').textContent   = r.cnpj || '—';
    document.getElementById('clientOrigem').textContent = r.cliente_origem || '—';
    document.getElementById('clientVendedor').innerHTML = `<i class="bi bi-person me-1"></i>${r.vendedor_ajustado || 'Não informado'}`;
    document.getElementById('clientTotal').textContent  = (r.total_produtos || 0) + ' produtos adquiridos';
    clientCard.style.display = 'block';
}

// ─── Renderizar tabela ─────────────────────────────────────────────────────────
function renderTabela(produtos) {
    produtosBody.innerHTML = '';
    tableCard.style.display = 'block';

    const filtro = searchProduto.value.toLowerCase().trim();
    const lista  = filtro
        ? produtos.filter(p => (p.produto || '').toLowerCase().includes(filtro) || (p.fabricante || '').toLowerCase().includes(filtro))
        : produtos;

    if (!lista.length) {
        emptyState.style.display = 'block';
        return;
    }
    emptyState.style.display = 'none';

    lista.forEach(p => {
        const plVal    = p.price_list_usd ? '$ ' + fmt2(p.price_list_usd) : '—';
        const plClass  = p.price_list_usd ? 'col-pricelist has-value' : 'col-pricelist';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="fw-semibold">${esc(p.produto || '—')}</td>
            <td class="text-muted">${esc(p.fabricante || '—')}</td>
            <td class="text-center">${fmtKg(p.embalagem)}</td>
            <td class="text-end">${fmtKg(p.kg_historico)}</td>
            <td class="text-end">${fmtKg(p.kg_realizado_2025)}</td>
            <td class="text-end">${fmtKg(p.kg_orcado_2026)}</td>
            <td class="text-end">${fmtUSD(p.preco_hist_usd)}</td>
            <td class="text-end">${fmtUSD(p.preco_2025_usd)}</td>
            <td class="text-end">${fmtUSD(p.preco_orcado_2026_usd)}</td>
            <td class="text-end ${plClass}">${plVal}</td>
            <td class="text-center">
              <button class="btn btn-xs btn-outline-success py-0 px-1" style="font-size:.72rem;white-space:nowrap"
                      onclick="abrirModalPricelist(${JSON.stringify(esc(p.produto))})"
                      title="Ver Price List completo do produto">
                <i class="bi bi-tags me-1"></i>PRICELIST
              </button>
            </td>`;
        produtosBody.appendChild(row);
    });
}

// ─── Filtro de produto ─────────────────────────────────────────────────────────
searchProduto.addEventListener('input', () => renderTabela(todosOsProdutos));

// ─── Limpar seleção ────────────────────────────────────────────────────────────
btnLimpar.addEventListener('click', () => {
    inpBusca.value = '';
    btnLimpar.style.display = 'none';
    clientCard.style.display = 'none';
    tableCard.style.display  = 'none';
    searchProduto.value = '';
    todosOsProdutos = [];
    clienteSelecionado = null;
});

// ─── Exportar CSV ─────────────────────────────────────────────────────────────
document.getElementById('btnExportar').addEventListener('click', () => {
    if (!todosOsProdutos.length) return;
    const headers = ['Produto','Fabricante','Embalagem','KG 17-24','KG 2025','KG Orç.2026',
                     'Preço Ant.BRL','Preço 2025 BRL','Preço Orç.2026','Price List USD'];
    const rows = todosOsProdutos.map(p => [
        p.produto, p.fabricante, p.embalagem, p.kg_historico, p.kg_realizado_2025,
        p.kg_orcado_2026, p.preco_hist_brl, p.preco_2025_brl,
        p.preco_orcado_2026_brl, p.price_list_usd
    ].map(v => (v === null || v === undefined) ? '' : String(v).replace(/;/g, ',')));

    const bom  = '\xEF\xBB\xBF';
    const csv  = bom + [headers, ...rows].map(r => r.join(';')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `pricelist_${clienteSelecionado || 'cliente'}.csv`;
    a.click();
    URL.revokeObjectURL(url);
});

// ─── Helpers de formatação ─────────────────────────────────────────────────────
function esc(s)    { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmt2(v)   { if (v === null || v === undefined || v === '') return '—'; return Number(v).toLocaleString('pt-BR', {minimumFractionDigits:2,maximumFractionDigits:4}); }
function fmtUSD(v) { if (!v) return '<span class="text-muted">—</span>'; return '$ ' + Number(v).toLocaleString('pt-BR', {minimumFractionDigits:2,maximumFractionDigits:4}); }
function fmtBRL(v) { if (!v) return '<span class="text-muted">—</span>'; return 'R$ ' + Number(v).toLocaleString('pt-BR', {minimumFractionDigits:2,maximumFractionDigits:4}); }
function fmtKg(v)  { if (!v) return '<span class="text-muted">—</span>'; return Number(v).toLocaleString('pt-BR', {minimumFractionDigits:3,maximumFractionDigits:3}); }
</script>
</body>

<!-- ─── Modal Price List completo do produto ─────────────────────────────── -->
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
const _modalPL = new bootstrap.Modal(document.getElementById('modalPricelist'));

async function abrirModalPricelist(nomeProduto) {
    document.getElementById('plNomeProduto').textContent     = nomeProduto;
    document.getElementById('plLoading').style.display       = 'block';
    document.getElementById('plConteudo').style.display      = 'none';
    document.getElementById('plBody').innerHTML              = '';
    document.getElementById('plVazio').style.display         = 'none';
    _modalPL.show();

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
            const emb   = r.embalagem
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

</html>
