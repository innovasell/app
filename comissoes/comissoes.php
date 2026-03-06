<?php
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cálculo de Comissões</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background: #fff;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #0d6efd;
            background-color: #f1f8ff;
        }
        .upload-icon {
            font-size: 2.5rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        #loadingOverlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            display: none;
            flex-direction: column;
            align-items: center; justify-content: center;
        }
        .badge-warning-custom {
            background-color: #ffc107; color: #000;
        }
        .badge-danger-custom {
            background-color: #dc3545; color: #fff;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="bi bi-percent"></i> Sistema de Comissões</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="upload.php">Upload NFs</a></li>
                    <li class="nav-item"><a class="nav-link active" href="comissoes.php">Cálculo de Comissões</a></li>
                    <li class="nav-item"><a class="nav-link" href="validacao.php">Validação</a></li>
                    <li class="nav-item"><a class="nav-link" href="config_cfop.php">Configurar CFOPs</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <div class="row section-upload mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-primary"><i class="bi bi-cloud-upload"></i> Novo Cálculo de Comissões</h5>
                        <div>
                            <a href="api/download_template_planilhas.php?type=movimentacoes" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Modelo Movimentações</a>
                            <a href="api/download_template_planilhas.php?type=pedidos" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Modelo Pedidos</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-secondary">A) Planilha de Movimentações (CSV)</label>
                                    <input class="form-control" type="file" name="arquivo_movimentacoes" id="movInput" accept=".csv" required>
                                    <div class="form-text">Exerça o modelo de Movimentações (CFOPs Saída).</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-secondary">B) Planilha de Pedidos (CSV)</label>
                                    <input class="form-control" type="file" name="arquivo_pedidos" id="pedInput" accept=".csv" required>
                                    <div class="form-text">Exerça o modelo de Pedidos/Vendas com a coluna `Vencimento(s)` ou `PM (dias)`.</div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label fw-bold text-secondary">Nome / Descrição do Lote <span class="text-danger">*</span></label>
                                    <input class="form-control" type="text" name="nome_lote" id="nomeLote" 
                                           placeholder="Ex: Comissões Janeiro 2026 - Andrea" required>
                                    <div class="form-text">Nome que identificará este lote no histórico.</div>
                                </div>
                            </div>
                            <div class="d-grid mt-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-gear-fill"></i> Processar Planilhas e Calcular
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção de Resultados (Oculta por padrão) -->
        <div class="row section-results d-none mb-5" id="resultsArea">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-success"><i class="bi bi-table"></i> Resultados do Lote <span id="spanBatchId"></span></h5>
                        <a href="#" id="btnExportCsv" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV Completo</a>
                    </div>
                    <div class="card-body">
                        
                        <!-- Resumo Cards -->
                        <div class="row mb-4" id="cardsResumo">
                            <!-- Preenchido via JS -->
                        </div>

                        <div class="table-responsive">
                            <table id="tableComissoes" class="table table-striped table-hover align-middle w-100" style="font-size: 0.85rem;">
                                <thead>
                                    <tr>
                                        <th>Representante</th>
                                        <th>Data/NF</th>
                                        <th>Código/Emb.</th>
                                        <th>Cliente</th>
                                        <th>Venda Net</th>
                                        <th>P.Lista(BRL)</th>
                                        <th>P.Net(BRL)</th>
                                        <th>Desc(%)</th>
                                        <th>% Base</th>
                                        <th>PM</th>
                                        <th>Aj.Prazo</th>
                                        <th>% Final</th>
                                        <th>Comissão(R$)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dados via DataTables -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay">
        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
        <h4 class="text-secondary">Processando arquivos e cruzando dados...</h4>
        <p class="text-muted">Isto pode demorar alguns minutos dependendo do tamanho das planilhas.</p>
    </div>

    <!-- jQuery, Bootstrap, DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        const uploadForm = document.getElementById('uploadForm');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const resultsArea = document.getElementById('resultsArea');
        const uploadSection = document.getElementById('uploadSection');
        const spanBatchId = document.getElementById('spanBatchId');
        const btnExportCsv = document.getElementById('btnExportCsv');
        
        let dataTable = null;

        const urlParams = new URLSearchParams(window.location.search);
        const viewBatch = urlParams.get('view_batch');

        if (viewBatch) {
            uploadSection.style.display = 'none';
            resultsArea.classList.remove('d-none');
            loadBatchResults(viewBatch);
        }

        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(uploadForm);
            
            loadingOverlay.style.display = 'flex';
            resultsArea.classList.add('d-none');

            try {
                const response = await fetch('api/process_commission.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (result.items_processed === 0) {
                        loadingOverlay.style.display = 'none';
                        let reasonMsg = `Nenhum item válido para comissão foi encontrado no lote.\n\nMotivos de ignorados (${result.items_ignored}):`;
                        if (result.ignore_reasons) {
                             reasonMsg += `\n- CFOP Não Permitido (Precisa exp 5xxx/6xxx/7xxx): ${result.ignore_reasons.cfop}`;
                             reasonMsg += `\n- Valor zerado ou inválido: ${result.ignore_reasons.valor_zero}`;
                             reasonMsg += `\n- Sem NFe preenchida: ${result.ignore_reasons.sem_nfe}`;
                        }
                        alert(reasonMsg);
                    } else {
                        loadBatchResults(result.batch_id);
                    }
                } else {
                    loadingOverlay.style.display = 'none';
                    alert("Erro ao processar: " + result.message);
                }
            } catch (error) {
                loadingOverlay.style.display = 'none';
                alert("Falha na comunicação: " + error.message);
            }
        });

        async function loadBatchResults(batchId) {
            try {
                const res = await fetch(`api/get_commission_items.php?batch_id=${batchId}`);
                const json = await res.json();
                
                loadingOverlay.style.display = 'none';

                if (json.success) {
                    spanBatchId.textContent = `#${batchId}`;
                    btnExportCsv.href = `api/export_commission.php?batch_id=${batchId}`;
                    resultsArea.classList.remove('d-none');
                    
                    renderTable(json.data);
                    renderResumoCards(json.data);
                } else {
                    alert('Erro ao carregar itens: ' + json.message);
                }
            } catch (err) {
                loadingOverlay.style.display = 'none';
                alert("Erro ao ler resultados.");
            }
        }

        function renderTable(data) {
            if (dataTable) {
                dataTable.destroy();
            }

            const tbody = document.querySelector('#tableComissoes tbody');
            tbody.innerHTML = '';

            data.forEach(item => {
                let statusBadges = '';
                if (item.flag_aprovacao == 1) statusBadges += '<span class="badge badge-danger-custom" title="Desconto>20% ou PM>42">Aprovação Req.</span> ';
                if (item.flag_teto == 1) statusBadges += '<span class="badge badge-warning-custom" title="Teto > 25k">Teto R$25k</span> ';
                if (item.lista_nao_encontrada == 1) statusBadges += '<span class="badge bg-secondary">S/ Lista</span>';

                if(!statusBadges) statusBadges = '<span class="badge bg-success">OK</span>';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td title="${item.representante ? item.representante : ''}">${item.representante ? item.representante.substring(0,20) + (item.representante.length > 20 ? '...' : '') : '-'}</td>
                    <td>${item.data_nf ? item.data_nf : '-'}<br><small class="text-muted">NF: ${item.nfe}</small></td>
                    <td title="${item.descricao ? item.descricao : ''}">${item.codigo}<br><b>${item.embalagem}</b><br><small class="text-muted" style="font-size: 0.75rem;">${item.descricao ? item.descricao.substring(0,25) + (item.descricao.length > 25 ? '...' : '') : ''}</small></td>
                    <td title="${item.cliente ? item.cliente : ''}">${item.cliente ? item.cliente.substring(0,25) + (item.cliente.length > 25 ? '...' : '') : '-'}</td>
                    <td>R$ ${(item.venda_net).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                    <td>R$ ${(item.preco_lista_brl).toLocaleString('pt-BR', {minimumFractionDigits:2})}</td>
                    <td>R$ ${(item.preco_net_un).toLocaleString('pt-BR', {minimumFractionDigits:2})}</td>
                    <td>${item.desconto_pct_fmt}</td>
                    <td>${item.comissao_base_fmt}</td>
                    <td>${Number(item.pm_dias).toFixed(0)}d</td>
                    <td>${item.ajuste_prazo_fmt}</td>
                    <td><b>${item.comissao_final_fmt}</b></td>
                    <td class="text-success fw-bold">R$ ${(item.valor_comissao).toLocaleString('pt-BR', {minimumFractionDigits:2})}</td>
                    <td>${statusBadges}</td>
                `;
                tbody.appendChild(tr);
            });

            dataTable = $('#tableComissoes').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
                order: [[0, 'asc']],
                pageLength: 25
            });
        }

        function renderResumoCards(data) {
            let resumo = {};
            let totalGeralNet = 0;
            let totalGeralCom = 0;

            data.forEach(i => {
                let rep = i.representante || 'Sem Representante';
                if (!resumo[rep]) {
                    resumo[rep] = { net: 0, comissao: 0 };
                }
                resumo[rep].net += i.venda_net;
                resumo[rep].comissao += i.valor_comissao;
                
                totalGeralNet += i.venda_net;
                totalGeralCom += i.valor_comissao;
            });

            let html = `
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6>Total Venda Net</h6>
                            <h4>R$ ${totalGeralNet.toLocaleString('pt-BR', {minimumFractionDigits:2})}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6>Total de Comissões</h6>
                            <h4>R$ ${totalGeralCom.toLocaleString('pt-BR', {minimumFractionDigits:2})}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body p-2" style="max-height: 100px; overflow-y: auto;">
                            <table class="table table-sm m-0">
                                <thead><tr><th>Representante</th><th class="text-end">V. Net</th><th class="text-end">Comissão</th></tr></thead>
                                <tbody>
            `;

            for (let [rep, vals] of Object.entries(resumo)) {
                html += `<tr>
                    <td>${rep}</td>
                    <td class="text-end">R$ ${vals.net.toLocaleString('pt-BR',{minimumFractionDigits:2})}</td>
                    <td class="text-end fw-bold">R$ ${vals.comissao.toLocaleString('pt-BR',{minimumFractionDigits:2})}</td>
                </tr>`;
            }

            html += `</tbody></table></div></div></div>`;
            document.getElementById('cardsResumo').innerHTML = html;
        }

    </script>
</body>
</html>
