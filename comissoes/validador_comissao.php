<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../sistema-cotacoes/conexao.php';
$pagina_ativa = 'validador';
require_once __DIR__ . '/header.php';
?>
<style>
    .step-card {
        border-left: 4px solid #0a1e42;
        border-radius: 0 8px 8px 0;
        margin-bottom: 0.6rem;
    }
    .step-card.border-primary  { border-left-color: #0d6efd; }
    .step-card.border-success  { border-left-color: #198754; }
    .step-card.border-info     { border-left-color: #0dcaf0; }
    .step-card.border-warning  { border-left-color: #ffc107; }
    .step-card.border-danger   { border-left-color: #dc3545; }
    .step-card.border-secondary{ border-left-color: #6c757d; }
    .step-card.border-dark     { border-left-color: #212529; }
    .step-card.border-success-final { border-left-color: #198754; background: #f0fff4; }

    .resultado-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 16px rgba(0,0,0,0.10);
        margin-bottom: 1.5rem;
    }
    .resultado-card .card-header {
        border-radius: 12px 12px 0 0;
        font-weight: 700;
        font-size: 0.95rem;
    }
    .linha-calculo {
        font-size: 0.87rem;
        padding: 3px 0;
        border-bottom: 1px dashed #e0e0e0;
    }
    .linha-calculo:last-child { border-bottom: none; }
    .upload-area {
        border: 2px dashed #40883c;
        border-radius: 12px;
        padding: 2.5rem;
        text-align: center;
        background: #fff;
        transition: all 0.3s;
        cursor: pointer;
    }
    .upload-area:hover { border-color: #2c5e29; background: #f1fff1; }
    #loadingValidador {
        display: none;
        text-align: center;
        padding: 2rem;
    }
    .badge-step {
        font-size: 0.75rem;
        vertical-align: middle;
    }
    .subtitulo-step {
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 4px;
        color: #444;
    }
    .resumo-item-card {
        background: linear-gradient(135deg, #0a1e42 0%, #0047fa 100%);
        color: #fff;
        border-radius: 10px;
        padding: 16px 20px;
        margin-bottom: 1rem;
    }
    .resumo-valor { font-size: 1.5rem; font-weight: 700; }
    .resumo-label { font-size: 0.75rem; opacity: 0.85; }
</style>

<div class="container-fluid px-4 py-3">

    <div class="page-header mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="comissoes.php">Comissões</a></li>
                <li class="breadcrumb-item active">Validador</li>
            </ol>
        </nav>
        <h1><i class="fas fa-search-dollar me-2"></i> Validador de Comissão por NF-e</h1>
        <small style="opacity:0.8">Carregue o XML de uma NF-e e veja o passo a passo completo do cálculo de comissão.</small>
    </div>

    <!-- Upload -->
    <div class="card shadow-sm mb-4" id="cardUpload">
        <div class="card-body">
            <form id="formValidar" enctype="multipart/form-data">
                <label class="fw-bold mb-2"><i class="bi bi-file-earmark-code me-1"></i> Arquivo XML da NF-e</label>
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('xmlInput').click()">
                    <div style="font-size:2.5rem; color:#40883c;"><i class="bi bi-file-earmark-code"></i></div>
                    <div class="fw-bold text-secondary mt-1">Clique para selecionar o XML da NF-e</div>
                    <div class="text-muted small">Formatos aceitos: .xml (NF-e padrão SEFAZ versão 4.00)</div>
                    <div id="nomeArquivo" class="mt-2 text-success fw-bold" style="display:none"></div>
                </div>
                <input type="file" id="xmlInput" name="xml_nfe" accept=".xml" class="d-none">
                <div class="d-grid mt-3">
                    <button type="submit" class="btn btn-primary btn-lg" id="btnValidar" disabled>
                        <i class="fas fa-search-dollar me-2"></i> Analisar e Calcular Comissão
                    </button>
                </div>
            </form>

            <div id="loadingValidador">
                <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;"></div>
                <h5 class="text-secondary">Analisando NF-e, consultando Price List e PTAX...</h5>
            </div>
        </div>
    </div>

    <!-- Resultado -->
    <div id="areaResultado" style="display:none;">

        <!-- Cabeçalho NF -->
        <div class="card shadow-sm mb-3">
            <div class="card-body py-2">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="badge bg-primary fs-6" id="resBadgeNF"></span>
                    </div>
                    <div class="col">
                        <span id="resCliente" class="fw-bold"></span>
                        <span class="text-muted small ms-2" id="resData"></span>
                    </div>
                    <div class="col-auto">
                        <span class="text-muted small">Representante: <strong id="resRepresentante">—</strong></span>
                        &nbsp;|&nbsp;
                        <span class="text-muted small">PM: <strong id="resPM">—</strong> dias</span>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-outline-secondary" onclick="resetValidador()">
                            <i class="bi bi-arrow-repeat"></i> Nova Análise
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Passos globais (dados NF e PM) -->
        <div id="stepsGlobais"></div>

        <!-- Itens -->
        <div id="containerItens"></div>

        <!-- Resumo Total -->
        <div class="card shadow-sm mt-3" id="cardResumoTotal" style="display:none;">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-calculator me-1"></i> Resumo Geral da NF-e
            </div>
            <div class="card-body" id="bodyResumoTotal"></div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const xmlInput  = document.getElementById('xmlInput');
const formValidar = document.getElementById('formValidar');
const btnValidar  = document.getElementById('btnValidar');

xmlInput.addEventListener('change', () => {
    const f = xmlInput.files[0];
    if (f) {
        document.getElementById('nomeArquivo').style.display = '';
        document.getElementById('nomeArquivo').textContent = '📎 ' + f.name;
        btnValidar.disabled = false;
    }
});

formValidar.addEventListener('submit', async (e) => {
    e.preventDefault();
    document.getElementById('cardUpload').querySelector('form').style.display = 'none';
    document.getElementById('loadingValidador').style.display = 'block';
    document.getElementById('areaResultado').style.display = 'none';

    const fd = new FormData(formValidar);
    try {
        const res  = await fetch('api/validar_comissao_xml.php', { method: 'POST', body: fd });
        const data = await res.json();
        document.getElementById('loadingValidador').style.display = 'none';

        if (!data.success) {
            alert('Erro: ' + data.message);
            document.getElementById('cardUpload').querySelector('form').style.display = '';
            return;
        }
        renderResultado(data);
    } catch (err) {
        document.getElementById('loadingValidador').style.display = 'none';
        alert('Erro de comunicação: ' + err.message);
        document.getElementById('cardUpload').querySelector('form').style.display = '';
    }
});

function renderResultado(data) {
    document.getElementById('resBadgeNF').textContent = 'NF ' + data.nf;
    document.getElementById('resCliente').textContent = data.cliente;
    document.getElementById('resData').textContent = data.data;
    document.getElementById('resRepresentante').textContent = data.representante || '—';
    document.getElementById('resPM').textContent = data.pm_dias;

    // Passos globais
    const sgEl = document.getElementById('stepsGlobais');
    sgEl.innerHTML = '';
    (data.steps_globais || []).forEach(s => { sgEl.innerHTML += buildStepCard(s); });

    // Itens
    const contEl = document.getElementById('containerItens');
    contEl.innerHTML = '';

    let totalNet = 0, totalComissao = 0, itensValidos = 0;

    data.itens.forEach((item, idx) => {
        const cor = item.cfop_invalido ? 'secondary' : item.sem_lista ? 'warning' : item.flag_aprovacao ? 'danger' : 'success';
        const iconeTit = item.cfop_invalido ? '🚫' : item.sem_lista ? '⚠️' : item.flag_aprovacao ? '⚠️' : '✔';

        let html = `<div class="resultado-card card">
            <div class="card-header bg-${cor} ${cor === 'warning' || cor === 'secondary' ? 'text-dark' : 'text-white'}">
                ${iconeTit} Item ${idx + 1}: ${item.produto}
                ${item.cfop_invalido ? `<span class="badge bg-light text-dark ms-2">CFOP ${item.cfop} — Não comissionável</span>` : ''}
                ${!item.cfop_invalido && !item.sem_lista && !item.flag_aprovacao && !item.flag_teto
                    ? `<span class="badge bg-light text-success ms-2">✔ OK</span>` : ''}
                ${item.flag_teto ? `<span class="badge bg-warning text-dark ms-2">★ Teto</span>` : ''}
                ${item.flag_aprovacao ? `<span class="badge bg-danger ms-2">⚠ Aprovação</span>` : ''}
                ${item.sem_lista ? `<span class="badge bg-secondary ms-2">S/ Lista</span>` : ''}
            </div>
            <div class="card-body py-2">`;

        item.steps.forEach(sub => {
            html += `<div class="step-card card card-body py-2 px-3 mb-2 border-${sub.cor}">
                        <div class="subtitulo-step">${sub.subtitulo}</div>`;
            sub.linhas.forEach(l => { html += `<div class="linha-calculo">${l}</div>`; });
            html += `</div>`;
        });

        if (!item.cfop_invalido) {
            const fmtBRL = v => 'R$ ' + parseFloat(v).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
            html += `<div class="d-flex gap-3 mt-2 flex-wrap">
                <div class="resumo-item-card flex-grow-1">
                    <div class="resumo-label">VENDA NET</div>
                    <div class="resumo-valor">${fmtBRL(item.venda_net)}</div>
                </div>
                <div class="resumo-item-card flex-grow-1" style="background:linear-gradient(135deg,#198754,#0dcaf0)">
                    <div class="resumo-label">COMISSÃO FINAL</div>
                    <div class="resumo-valor">${fmtBRL(item.valor_comissao)}</div>
                </div>
            </div>`;
            totalNet       += parseFloat(item.venda_net || 0);
            totalComissao  += parseFloat(item.valor_comissao || 0);
            itensValidos++;
        }

        html += `</div></div>`;
        contEl.innerHTML += html;
    });

    // Resumo total
    if (itensValidos > 0) {
        const fmtBRL = v => 'R$ ' + parseFloat(v).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
        document.getElementById('bodyResumoTotal').innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <div class="card bg-success text-white text-center p-3">
                        <div class="small">Total Venda Net</div>
                        <div class="fw-bold fs-4">${fmtBRL(totalNet)}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-primary text-white text-center p-3">
                        <div class="small">Total Comissões</div>
                        <div class="fw-bold fs-4">${fmtBRL(totalComissao)}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-dark text-white text-center p-3">
                        <div class="small">% Comissão Geral</div>
                        <div class="fw-bold fs-4">${totalNet > 0 ? ((totalComissao/totalNet)*100).toFixed(4).replace('.',',') + '%' : '—'}</div>
                    </div>
                </div>
            </div>`;
        document.getElementById('cardResumoTotal').style.display = '';
    }

    document.getElementById('areaResultado').style.display = '';
    document.getElementById('areaResultado').scrollIntoView({behavior:'smooth'});
}

function buildStepCard(s) {
    let html = `<div class="card shadow-sm mb-3">
        <div class="card-header bg-${s.cor} ${s.cor === 'warning' || s.cor === 'light' ? 'text-dark' : 'text-white'} py-2">
            <strong>${s.titulo}</strong>
        </div>
        <div class="card-body py-2">`;
    s.linhas.forEach(l => { html += `<div class="linha-calculo">${l}</div>`; });
    html += `</div></div>`;
    return html;
}

function resetValidador() {
    document.getElementById('areaResultado').style.display = 'none';
    document.getElementById('cardUpload').querySelector('form').style.display = '';
    document.getElementById('stepsGlobais').innerHTML = '';
    document.getElementById('containerItens').innerHTML = '';
    document.getElementById('cardResumoTotal').style.display = 'none';
    document.getElementById('nomeArquivo').style.display = 'none';
    xmlInput.value = '';
    btnValidar.disabled = true;
}
</script>
</body>
</html>
