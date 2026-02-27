<?php
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400);
    session_set_cookie_params(86400);
    session_start();
}

if (!isset($_SESSION['representante_email'])) {
    header('Location: ../index.php');
    exit();
}

require_once 'conexao.php';
$pagina_ativa = 'cenarios';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BI e P&D - Cenários Integrados</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --bg-body: #f3f6f9;
            --card-bg: #ffffff;
            --text-primary: #2c3e50;
        }

        body {
            font-family: 'Open Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-primary);
        }

        h1, h2, h3, h4, h5, h6, .navbar-brand { font-family: 'Montserrat', sans-serif; }

        /* Cards and Filter Theme */
        .filter-section {
            background: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border-left: 5px solid #40883c;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

        .card-kpi {
            border: none;
            border-radius: 12px;
            background-color: var(--card-bg);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
            height: 100%;
        }
        .card-kpi:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); }
        .card-kpi-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
        .card-kpi-value { font-size: 2rem; font-weight: 700; font-family: 'Montserrat', sans-serif; }

        .main-container { max-width: 1400px; margin: 0 auto; }
        
        .sync-loading { display: none; margin-left:10px; }
    </style>
</head>
<body>

    <!-- Include Menu do Sistema Principal -->
    <?php include '../sistema-cotacoes/header.php'; ?>

    <div class="container main-container py-5">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1">Cenários: Inteligência de Consumo</h2>
                <p class="text-muted mb-0">Portal de insights de produtos com sincronização API</p>
            </div>
            <div>
                <button type="button" id="btnSincronizar" class="btn btn-outline-success border-2 fw-bold px-4 rounded-pill">
                    <i class="fas fa-sync-alt me-1 icon-sync"></i> Sincronizar Mainô
                    <span class="spinner-border spinner-border-sm sync-loading" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <!-- Barra de Progresso ETL (oculta por padrao) -->
        <div class="progress mb-4 d-none" id="syncProgressContainer" style="height: 10px;">
          <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%;"></div>
        </div>

        <!-- Bloco de Filtros -->
        <div class="filter-section">
            <h6 class="fw-bold mb-3 text-secondary"><i class="fas fa-filter me-2"></i>Filtros Analíticos</h6>
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold">Buscar Cliente</label>
                    <select id="selectCliente" class="form-select select2-ajax" style="width: 100%"></select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold">Buscar Produto</label>
                    <select id="selectProduto" class="form-select select2-ajax" style="width: 100%"></select>
                </div>
                <div class="col-md-2">
                    <button type="button" id="btnAnalisar" class="btn btn-primary w-100 fw-bold" style="background-color: #40883c; border-color: #40883c;">
                        <i class="fas fa-bolt me-1"></i> Analisar
                    </button>
                </div>
            </div>
        </div>

        <!-- 4 Cards KPI -->
        <div class="row g-4 mb-4">
            <!-- Média 2026 -->
            <div class="col-md-3">
                <div class="card card-kpi p-4 text-center">
                    <div class="text-primary mb-2"><i class="fas fa-tag fa-2x"></i></div>
                    <div class="card-kpi-title text-muted">Média de Preço 2026</div>
                    <div class="card-kpi-value text-dark" id="txtMediaAtual">US$ 0.00</div>
                    <div class="small text-muted mt-2">Valor unitário das movimentações do ano.</div>
                </div>
            </div>

            <!-- Média Histórica -->
            <div class="col-md-3">
                <div class="card card-kpi p-4 text-center">
                    <div class="text-secondary mb-2"><i class="fas fa-history fa-2x"></i></div>
                    <div class="card-kpi-title text-muted">Média Histórica</div>
                    <div class="card-kpi-value text-dark" id="txtMediaHist">US$ 0.00</div>
                    <div class="small text-muted mt-2">Valor unitário global (fora 2026).</div>
                </div>
            </div>

            <!-- Consumo Médio Mensal -->
            <div class="col-md-3">
                <div class="card card-kpi p-4 text-center">
                    <div class="text-info mb-2"><i class="fas fa-chart-bar fa-2x"></i></div>
                    <div class="card-kpi-title text-muted">Consumo Médio</div>
                    <div class="card-kpi-value text-dark" id="txtConsumoMedio">0.0</div>
                    <div class="small text-muted mt-2">Unidades processadas em média ao mês.</div>
                </div>
            </div>

            <!-- Previsão de Estoque -->
            <div class="col-md-3">
                <div class="card card-kpi p-4 text-center" id="cardPrevisaoEstoque">
                    <div class="mb-2" id="iconPrevisao"><i class="fas fa-warehouse fa-2x text-warning"></i></div>
                    <div class="card-kpi-title" id="titlePrevisao">Previsão em Estoque</div>
                    <div class="card-kpi-value" id="txtPrevisaoEstoque">N/A</div>
                    <div class="small mt-2" id="descPrevisao">Duração de abastecimento estimada.</div>
                </div>
            </div>
        </div>

        <!-- Histórico (Tabela) -->
        <div class="card border-0 shadow-sm mt-5" style="border-radius:12px;">
            <div class="card-header bg-white border-0 py-3 d-flex align-items-center" style="border-radius:12px 12px 0 0;">
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-list text-primary me-2"></i>Histórico de Movimentações</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Data</th>
                                <th>Tipo</th>
                                <th class="text-end">Quantidade</th>
                                <th class="text-end">Valor Unitário</th>
                                <th class="text-end pe-4">Valor Total</th>
                            </tr>
                        </thead>
                        <tbody id="gridHistorico">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="fas fa-search fa-2x mb-3 d-block opacity-25"></i>
                                    Selecione um cliente e código para listar o histórico cruzado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // Inicializar Select2 com Bootstrap Theme e Ajax
            $('#selectCliente').select2({
                theme: 'bootstrap-5',
                placeholder: 'Pesquise pelo nome do Cliente...',
                ajax: {
                    url: 'ajax_cenarios.php?action=buscar_clientes',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term }; },
                    processResults: function (data) { return { results: data.results }; }
                }
            });

            $('#selectProduto').select2({
                theme: 'bootstrap-5',
                placeholder: 'Pesquise por código interno ou nome...',
                ajax: {
                    url: 'ajax_cenarios.php?action=buscar_produtos',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term }; },
                    processResults: function (data) { return { results: data.results }; }
                }
            });

            // Botão Sincronizar via ETL
            $('#btnSincronizar').click(function() {
                var btn = $(this);
                btn.attr('disabled', true);
                $('.icon-sync', btn).addClass('fa-spin');
                $('.sync-loading', btn).show();
                $('#syncProgressContainer').removeClass('d-none');

                // Disparo de cron Job / ETL sync
                $.ajax({
                    url: 'sync_maino.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.status === 'success') {
                            Swal.fire({ title: 'Sincronizado!', text: 'Base local atualizada com sucesso.', icon: 'success' });
                        } else {
                            Swal.fire({ title: 'Aviso', text: 'Ops, ' + resp.message, icon: 'warning' });
                        }
                    },
                    error: function(err) {
                        Swal.fire({ title: 'Erro de Requisição', text: 'A API pode ter expirado ou falhado.', icon: 'error' });
                    },
                    complete: function() {
                        btn.attr('disabled', false);
                        $('.icon-sync', btn).removeClass('fa-spin');
                        $('.sync-loading', btn).hide();
                        $('#syncProgressContainer').addClass('d-none');
                    }
                });
            });

            // Botão de Disparo da Análise BI
            $('#btnAnalisar').click(function() {
                var idCliente = $('#selectCliente').val();
                var idProduto = $('#selectProduto').val();

                if(!idCliente || !idProduto) {
                    Swal.fire("Atenção", "Por favor, selecione as duas pontas: Cliente e Produto.", "warning");
                    return;
                }

                // 1. Requisitar Calcular KPIs
                $.getJSON('ajax_cenarios.php?action=calcular_kpis', {cliente: idCliente, produto: idProduto}, function(res) {
                    if(res.status === 'success') {
                        let kpis = res.data;
                        
                        $('#txtMediaAtual').text('US$ ' + parseFloat(kpis.media_2026).toFixed(2));
                        $('#txtMediaHist').text('US$ ' + parseFloat(kpis.media_historica).toFixed(2));
                        $('#txtConsumoMedio').text(parseFloat(kpis.consumo_mensal).toFixed(2));

                        // Tratamento da Previsão
                        let cardEstoque = $('#cardPrevisaoEstoque');
                        let txtPrev = $('#txtPrevisaoEstoque');
                        let titlePrev = $('#titlePrevisao');
                        let descPrev = $('#descPrevisao');
                        let icoPrev = $('#iconPrevisao i');

                        // Clean colors
                        cardEstoque.removeClass('bg-danger bg-warning bg-success text-white');
                        txtPrev.removeClass('text-white text-dark');
                        titlePrev.removeClass('text-white text-dark');
                        descPrev.removeClass('text-white text-muted');
                        icoPrev.removeClass('text-white text-warning');

                        if (kpis.previsao_estoque === -1) {
                            txtPrev.text("Sem Saídas");
                            txtPrev.addClass('text-dark');
                            titlePrev.addClass('text-dark');
                            descPrev.addClass('text-muted').text("Sem histórico recente para base de cálculo.");
                            icoPrev.addClass('text-secondary');
                        } else {
                            let duracao = parseFloat(kpis.previsao_estoque);
                            txtPrev.text(duracao.toFixed(1) + " Meses");

                            if (duracao < 1) { // Menos de 1 Mes
                                cardEstoque.addClass('bg-danger');
                                txtPrev.addClass('text-white');
                                titlePrev.addClass('text-white');
                                descPrev.addClass('text-white').text("Ruptura Iminente (Menos de 30 dias)");
                                icoPrev.addClass('text-white');
                            } else if (duracao < 2) { // Menos de 2 Meses
                                cardEstoque.addClass('bg-warning');
                                txtPrev.addClass('text-dark');
                                titlePrev.addClass('text-dark');
                                descPrev.addClass('text-dark').text("Atenção (Estoque chegando no limite)");
                                icoPrev.addClass('text-dark');
                            } else { // Saudavel
                                cardEstoque.addClass('bg-success');
                                txtPrev.addClass('text-white');
                                titlePrev.addClass('text-white');
                                descPrev.addClass('text-white').text("Abastecimento Saudável");
                                icoPrev.addClass('text-white');
                            }
                        }
                    }
                });

                // 2. Requisitar Grid
                $.getJSON('ajax_cenarios.php?action=historico', {cliente: idCliente, produto: idProduto}, function(res) {
                    var tbody = $('#gridHistorico');
                    tbody.empty();

                    if(res.status === 'success' && res.data.length > 0) {
                        res.data.forEach(function(row) {
                            let valU = parseFloat(row.valor_unitario);
                            let qtd = parseFloat(row.quantidade);
                            let total = valU * qtd;
                            
                            let badgeClass = (row.tipo === 'entrada') ? 'success' : 'danger';
                            let badgeText = (row.tipo === 'entrada') ? 'ENTRADA' : 'SAÍDA';

                            tbody.append(`
                                <tr>
                                    <td class="ps-4 text-muted fw-bold">${row.data_br}</td>
                                    <td><span class="badge bg-${badgeClass} py-1 px-2 border-0 rounded-1">${badgeText}</span></td>
                                    <td class="text-end fw-bold">${qtd.toFixed(1)}</td>
                                    <td class="text-end">US$ ${valU.toFixed(2)}</td>
                                    <td class="text-end pe-4 text-primary fw-bold">US$ ${total.toFixed(2)}</td>
                                </tr>
                            `);
                        });
                    } else {
                        tbody.html('<tr><td colspan="5" class="text-center py-4 text-muted">Ainda não há histórico de movimentos no BD para esse filtro.</td></tr>');
                    }
                });
            });
        });
    </script>
</body>
</html>
