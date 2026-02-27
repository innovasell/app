<?php
require_once 'conexao.php';
session_start();

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}

$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] == 1;

// Filtros
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final = $_GET['data_final'] ?? date('Y-m-t');
$representante = $_GET['representante'] ?? '';
$uf_filtro = $_GET['uf'] ?? '';

// Filtros SQL
$filtros = [];
$params = [];

if ($data_inicial) {
    $filtros[] = "`DATA` >= :data_inicial";
    $params[':data_inicial'] = $data_inicial;
}
if ($data_final) {
    $filtros[] = "`DATA` <= :data_final";
    $params[':data_final'] = $data_final;
}
if ($representante) {
    $filtros[] = "`COTADO_POR` = :representante";
    $params[':representante'] = $representante;
}
if ($uf_filtro) {
    $filtros[] = "`UF` = :uf";
    $params[':uf'] = $uf_filtro;
}

$where = $filtros ? "WHERE " . implode(" AND ", $filtros) : "";

// Função auxiliar para consulta
function fetchData($pdo, $sql, $params)
{
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 1. KPI Total Valor Orçado
// Assumindo que VOLUME e PREÇO FULL são salvos com ponto decimal. Se não, precisaríamos de REPLACE.
// Vamos assumir que estão corretos baseados na análise anterior.
$sqlKPI = "SELECT SUM(VOLUME * `PREÇO FULL USD/KG`) as total 
           FROM cot_cotacoes_importadas $where";
$resKPI = fetchData($pdo, $sqlKPI, $params);
$totalValorOrcado = $resKPI[0]['total'] ?? 0;

// 2. Tops e Rankings
// Top 10 Produtos (Qtd de vezes orçado)
$sqlProdQtd = "SELECT PRODUTO, COUNT(*) as qtd FROM cot_cotacoes_importadas $where GROUP BY PRODUTO ORDER BY qtd DESC LIMIT 10";
$dataProdQtd = fetchData($pdo, $sqlProdQtd, $params);

// Top 10 Clientes (Propostas - Distinct Num Orcamento)
$sqlCliQtd = "SELECT `RAZÃO SOCIAL` as cliente, COUNT(DISTINCT NUM_ORCAMENTO) as qtd FROM cot_cotacoes_importadas $where GROUP BY `RAZÃO SOCIAL` ORDER BY qtd DESC LIMIT 10";
$dataCliQtd = fetchData($pdo, $sqlCliQtd, $params);

// Top 10 Vendedores (Itens Orçados)
$sqlRepQtd = "SELECT COTADO_POR, COUNT(*) as qtd FROM cot_cotacoes_importadas $where GROUP BY COTADO_POR ORDER BY qtd DESC LIMIT 10";
$dataRepQtd = fetchData($pdo, $sqlRepQtd, $params);

// Top 10 UF (Propostas)
$sqlUFQtd = "SELECT UF, COUNT(DISTINCT NUM_ORCAMENTO) as qtd FROM cot_cotacoes_importadas $where GROUP BY UF ORDER BY qtd DESC LIMIT 10";
$dataUFQtd = fetchData($pdo, $sqlUFQtd, $params);

// Ranking Vendedor (Valor)
$sqlRepVal = "SELECT COTADO_POR, SUM(VOLUME * `PREÇO FULL USD/KG`) as total FROM cot_cotacoes_importadas $where GROUP BY COTADO_POR ORDER BY total DESC LIMIT 10";
$dataRepVal = fetchData($pdo, $sqlRepVal, $params);

// Ranking Clientes (Valor)
$sqlCliVal = "SELECT `RAZÃO SOCIAL` as cliente, SUM(VOLUME * `PREÇO FULL USD/KG`) as total FROM cot_cotacoes_importadas $where GROUP BY `RAZÃO SOCIAL` ORDER BY total DESC LIMIT 10";
$dataCliVal = fetchData($pdo, $sqlCliVal, $params);

// Ranking Produtos (Valor)
$sqlProdVal = "SELECT PRODUTO, SUM(VOLUME * `PREÇO FULL USD/KG`) as total FROM cot_cotacoes_importadas $where GROUP BY PRODUTO ORDER BY total DESC LIMIT 10";
$dataProdVal = fetchData($pdo, $sqlProdVal, $params);


// Buscar listas para filtro
$listaReps = $pdo->query("SELECT DISTINCT COTADO_POR FROM cot_cotacoes_importadas ORDER BY COTADO_POR")->fetchAll(PDO::FETCH_COLUMN);
$listaUFs = $pdo->query("SELECT DISTINCT UF FROM cot_cotacoes_importadas ORDER BY UF")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard BI - H Hansen</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap"
        rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .navbar-brand {
            font-family: 'Montserrat', sans-serif;
        }

        /* Navbar Refinada */
        .navbar-custom {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 1rem 0;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            background-color: var(--card-bg);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
        }

        .card-header-custom {
            background-color: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header-title {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        /* KPI Card Especial */
        .kpi-card {
            background: linear-gradient(135deg, #40883c 0%, #2e662a 100%);
            color: white;
        }

        .kpi-card .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .kpi-value {
            font-size: 2.8rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Filter Section */
        .filter-section {
            background: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border-left: 5px solid var(--primary-color);
        }

        /* Charts */
        .chart-container {
            position: relative;
            height: 320px;
            width: 100%;
        }

        /* Utilities */
        .badge-soft {
            background-color: rgba(13, 110, 253, 0.1);
            color: var(--primary-color);
            padding: 0.5em 0.8em;
            font-weight: 600;
            border-radius: 6px;
        }

        /* Container limit */
        .main-container {
            max-width: 1400px;
            /* Limita largura em telas muito grandes */
            margin: 0 auto;
        }
    </style>
</head>

<body>

    <!-- Header / Navbar -->
    <?php require_once 'header.php'; ?>

    <div class="container main-container py-5">

        <!-- Header Page -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1">Business Intelligence</h2>
                <p class="text-muted mb-0">Análise detalhada de performance e vendas</p>
            </div>
            <div>
                <span class="badge bg-light text-dark border p-2">
                    <i class="far fa-calendar-alt me-1"></i>
                    <?= date('d/m/Y') ?>
                </span>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-section shadow-sm">
            <h6 class="fw-bold mb-3 text-secondary"><i class="fas fa-filter me-2"></i>Filtros de Análise</h6>
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Data Inicial</label>
                    <input type="date" class="form-control form-control-sm border-0 bg-light" name="data_inicial"
                        value="<?= $data_inicial ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Data Final</label>
                    <input type="date" class="form-control form-control-sm border-0 bg-light" name="data_final"
                        value="<?= $data_final ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Representante</label>
                    <select class="form-select form-select-sm border-0 bg-light" name="representante">
                        <option value="">Todos</option>
                        <?php foreach ($listaReps as $rep): ?>
                            <option value="<?= htmlspecialchars($rep) ?>" <?= $representante == $rep ? 'selected' : '' ?>>
                                <?= htmlspecialchars($rep) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">UF</label>
                    <select class="form-select form-select-sm border-0 bg-light" name="uf">
                        <option value="">Todas</option>
                        <?php foreach ($listaUFs as $uf): ?>
                            <option value="<?= htmlspecialchars($uf) ?>" <?= $uf_filtro == $uf ? 'selected' : '' ?>>
                                <?= htmlspecialchars($uf) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold"><i
                            class="fas fa-search me-1"></i> Aplicar Filtros</button>
                </div>
            </form>
        </div>

        <!-- KPI Hero -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card kpi-card border-0">
                    <div class="card-body text-center p-5">
                        <h6 class="text-uppercase text-muted mb-2 letter-spacing-1">Total Valor Orçado (Periodo)</h6>
                        <h1 class="kpi-value">USD$ <?= number_format($totalValorOrcado, 2, ',', '.') ?></h1>
                        <p class="mb-0 mt-2 text-white-50 small"><i class="fas fa-chart-line me-1"></i> Performance
                            calculada com base nos filtros aplicados</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Rankings (Volume & Quantidade) -->
        <h5 class="fw-bold mb-3 ps-2 border-start border-4 border-warning">Top Performers (Quantidade)</h5>
        <div class="row g-4 mb-5">
            <!-- Produto Mais Orçado -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-header-custom">
                        <div>
                            <span class="card-header-title d-block">Top 10 Produtos (Qtd)</span>
                            <small class="text-muted fw-normal" style="font-size: 0.75rem;">Itens mais frequentes em orçamentos</small>
                        </div>
                        <?php if ($isAdmin): ?>
                            <a href="exportar_bi.php?tipo=prod_qtd&<?= http_build_query($_GET) ?>" class="text-muted"
                                title="Exportar CSV"><i class="fas fa-download"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartProdQtd"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cliente Mais Propostas -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-header-custom">
                        <div>
                            <span class="card-header-title d-block">Top 10 Clientes (Propostas)</span>
                            <small class="text-muted fw-normal" style="font-size: 0.75rem;">Clientes com maior número de orçamentos gerados</small>
                        </div>
                        <?php if ($isAdmin): ?>
                            <a href="exportar_bi.php?tipo=cli_qtd&<?= http_build_query($_GET) ?>" class="text-muted"><i
                                    class="fas fa-download"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartCliQtd"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- UF Mais Propostas -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-header-custom">
                        <div>
                            <span class="card-header-title d-block">Top 10 Estados</span>
                            <small class="text-muted fw-normal" style="font-size: 0.75rem;">Distribuição geográfica das cotações</small>
                        </div>
                        <?php if ($isAdmin): ?>
                            <a href="exportar_bi.php?tipo=uf_qtd&<?= http_build_query($_GET) ?>" class="text-muted"><i
                                    class="fas fa-download"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartUFQtd"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vendedor Mais Itens -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-header-custom">
                        <div>
                            <span class="card-header-title d-block">Top 10 Representantes (Itens)</span>
                            <small class="text-muted fw-normal" style="font-size: 0.75rem;">Vendedores com maior volume de itens cotados</small>
                        </div>
                        <?php if ($isAdmin): ?>
                            <a href="exportar_bi.php?tipo=rep_qtd&<?= http_build_query($_GET) ?>" class="text-muted"><i
                                    class="fas fa-download"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartRepQtd"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Section: Financeiro (Valores) -->
        <h5 class="fw-bold mb-3 ps-2 border-start border-4 border-success">Análise Financeira (USD)</h5>
        <div class="row g-4 mb-4">
            <!-- Ranking Vendedor Valor -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header-custom">
                        <div>
                            <span class="card-header-title text-success d-block">Ranking Representantes (Valor)</span>
                            <small class="text-muted fw-normal" style="font-size: 0.75rem;">Total em USD orçado por vendedor</small>
                        </div>
                        <?php if ($isAdmin): ?>
                            <a href="exportar_bi.php?tipo=rep_val&<?= http_build_query($_GET) ?>" class="text-success"><i
                                    class="fas fa-download"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartRepVal"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ranking Cliente Valor -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header-custom">
                        <div>
                            <span class="card-header-title text-primary d-block">Ranking Clientes (Valor)</span>
                            <small class="text-muted fw-normal" style="font-size: 0.75rem;">Total em USD orçado por cliente</small>
                        </div>
                        <?php if ($isAdmin): ?>
                            <a href="exportar_bi.php?tipo=cli_val&<?= http_build_query($_GET) ?>" class="text-primary"><i
                                    class="fas fa-download"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartCliVal"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ranking Produto Valor -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header-custom">
                        <div>
                            <span class="card-header-title text-warning d-block">Ranking Produtos (Valor)</span>
                            <small class="text-muted fw-normal" style="font-size: 0.75rem;">Total em USD orçado por produto</small>
                        </div>
                        <?php if ($isAdmin): ?>
                            <a href="exportar_bi.php?tipo=prod_val&<?= http_build_query($_GET) ?>" class="text-warning"><i
                                    class="fas fa-download"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartProdVal"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script>
        // Configuração Comum Clean
        Chart.defaults.font.family = "'Open Sans', sans-serif";
        Chart.defaults.color = "#6c757d";

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: { family: "'Montserrat', sans-serif", size: 13 },
                    bodyFont: { family: "'Open Sans', sans-serif", size: 12 }
                }
            },
            scales: {
                x: { grid: { display: false } }, // Cleaner look
                y: { grid: { color: 'rgba(0,0,0,0.05)' }, beginAtZero: true }
            }
        };

        const commonOptionsH = {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { grid: { color: 'rgba(0,0,0,0.05)' }, beginAtZero: true },
                y: { grid: { display: false } }
            }
        };

        // Dados PHP
        const dataProdQtd = <?= json_encode($dataProdQtd) ?>;
        const dataCliQtd = <?= json_encode($dataCliQtd) ?>;
        const dataRepQtd = <?= json_encode($dataRepQtd) ?>;
        const dataUFQtd = <?= json_encode($dataUFQtd) ?>;
        const dataRepVal = <?= json_encode($dataRepVal) ?>;
        const dataCliVal = <?= json_encode($dataCliVal) ?>;
        const dataProdVal = <?= json_encode($dataProdVal) ?>;

        function createChart(ctxId, labelCol, dataCol, datasetLabel, dataSrc, horizontal = false, color = '#0d6efd') {
            const ctx = document.getElementById(ctxId).getContext('2d');

            // Gradient fill
            let bg = color;
            if (!horizontal) {
                const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                gradient.addColorStop(0, color);
                gradient.addColorStop(1, changeAlpha(color, 0.2));
                bg = gradient;
            }

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dataSrc.map(item => item[labelCol]),
                    datasets: [{
                        label: datasetLabel,
                        data: dataSrc.map(item => item[dataCol]),
                        backgroundColor: bg,
                        borderColor: color,
                        borderWidth: 1,
                        borderRadius: 4,
                        maxBarThickness: 50
                    }]
                },
                options: horizontal ? commonOptionsH : commonOptions
            });
        }

        // Helper para transparência no gradiente
        function changeAlpha(color, opacity) {
            // Se for hex, converter. Simplificação: assumindo cores hex 6 digitos
            // Se já passar string rgba ok.
            // Para demo simples, vamos retornar o próprio cor se falhar.
            if (color.startsWith('#')) {
                let r = parseInt(color.slice(1, 3), 16);
                let g = parseInt(color.slice(3, 5), 16);
                let b = parseInt(color.slice(5, 7), 16);
                return `rgba(${r}, ${g}, ${b}, ${opacity})`;
            }
            return color;
        }

        // Renderização
        createChart('chartProdQtd', 'PRODUTO', 'qtd', 'Qtd', dataProdQtd, true, '#fd7e14');
        createChart('chartCliQtd', 'cliente', 'qtd', 'Propostas', dataCliQtd, true, '#0dcaf0');
        createChart('chartRepQtd', 'COTADO_POR', 'qtd', 'Itens', dataRepQtd, false, '#6610f2');
        createChart('chartUFQtd', 'UF', 'qtd', 'Propostas', dataUFQtd, false, '#d63384');

        createChart('chartRepVal', 'COTADO_POR', 'total', 'Valor USD', dataRepVal, false, '#198754');
        createChart('chartCliVal', 'cliente', 'total', 'Valor USD', dataCliVal, true, '#0d6efd');
        createChart('chartProdVal', 'PRODUTO', 'total', 'Valor USD', dataProdVal, true, '#ffc107');

    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>