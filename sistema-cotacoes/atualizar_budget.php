<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
$pagina_ativa = 'atualizar_budget';
require_once 'header.php';
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}

// ─── Auto Migration: cria ou atualiza a tabela cot_budget_cliente ───────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cot_budget_cliente` (
        `id`                        INT AUTO_INCREMENT PRIMARY KEY,
        `cnpj`                      VARCHAR(20)     NOT NULL,
        `razao_social`              VARCHAR(255)    DEFAULT NULL,
        `cliente_origem`            VARCHAR(255)    DEFAULT NULL,
        `terceirista`               VARCHAR(255)    DEFAULT NULL,
        `produto`                   VARCHAR(255)    NOT NULL,
        `fabricante`                VARCHAR(255)    DEFAULT NULL,
        `concat`                    VARCHAR(500)    NOT NULL,
        `tipo`                      VARCHAR(100)    DEFAULT NULL,
        `vendedor`                  VARCHAR(255)    DEFAULT NULL,
        `vendedor_ajustado`         VARCHAR(255)    DEFAULT NULL,
        `embalagem`                 DECIMAL(14,4)   DEFAULT NULL,
        `kg_historico`              DECIMAL(14,3)   DEFAULT NULL,
        `kg_realizado_2025`         DECIMAL(14,3)   DEFAULT NULL,
        `kg_orcado_2026`            DECIMAL(14,3)   DEFAULT NULL,
        `kg_realizado_2026`         DECIMAL(14,3)   DEFAULT NULL,
        `preco_hist_brl`            DECIMAL(14,4)   DEFAULT NULL,
        `preco_2025_brl`            DECIMAL(14,4)   DEFAULT NULL,
        `reajuste_sugerido`         DECIMAL(10,4)   DEFAULT NULL,
        `preco_sugerido_brl`        DECIMAL(14,4)   DEFAULT NULL,
        `preco_orcado_2026_brl`     DECIMAL(14,4)   DEFAULT NULL,
        `preco_realizado_2026_brl`  DECIMAL(14,4)   DEFAULT NULL,
        `preco_hist_usd`            DECIMAL(14,4)   DEFAULT NULL,
        `preco_2025_usd`            DECIMAL(14,4)   DEFAULT NULL,
        `preco_sugerido_usd`        DECIMAL(14,4)   DEFAULT NULL,
        `preco_orcado_2026_usd`     DECIMAL(14,4)   DEFAULT NULL,
        `preco_realizado_2026_usd`  DECIMAL(14,4)   DEFAULT NULL,
        `venda_net_2025`            DECIMAL(14,2)   DEFAULT NULL,
        `venda_net_orcado_2026`     DECIMAL(14,2)   DEFAULT NULL,
        `venda_net_realizado_2026`  DECIMAL(14,2)   DEFAULT NULL,
        `custo_unt_realizado_2025`  DECIMAL(14,4)   DEFAULT NULL,
        `custo_unt_orcado_dani`     DECIMAL(14,4)   DEFAULT NULL,
        `comp_custo_dani`           DECIMAL(14,4)   DEFAULT NULL,
        `custo_unt_orcado_2026`     DECIMAL(14,4)   DEFAULT NULL,
        `custo_unt_realizado_2026`  DECIMAL(14,4)   DEFAULT NULL,
        `custo_total_2025`          DECIMAL(14,2)   DEFAULT NULL,
        `custo_total_orcado_2026`   DECIMAL(14,2)   DEFAULT NULL,
        `custo_total_realizado_2026`DECIMAL(14,2)   DEFAULT NULL,
        `lucro_liq_2025`            DECIMAL(14,2)   DEFAULT NULL,
        `lucro_liq_orcado_2026`     DECIMAL(14,2)   DEFAULT NULL,
        `lucro_liq_realizado_2026`  DECIMAL(14,2)   DEFAULT NULL,
        `gm_2025`                   DECIMAL(10,4)   DEFAULT NULL,
        `gm_orcado_2026`            DECIMAL(10,4)   DEFAULT NULL,
        `gm_realizado_2026`         DECIMAL(10,4)   DEFAULT NULL,
        `lote_economico_kg`         DECIMAL(14,3)   DEFAULT NULL,
        `exw_2026_kg_usd`           DECIMAL(14,4)   DEFAULT NULL,
        `exw_2026_total_usd`        DECIMAL(14,2)   DEFAULT NULL,
        `landed_2026_kg_usd`        DECIMAL(14,4)   DEFAULT NULL,
        `landed_2026_total`         DECIMAL(14,2)   DEFAULT NULL,
        `comentarios_supply`        TEXT            DEFAULT NULL,
        `preco_ajustado`            DECIMAL(14,4)   DEFAULT NULL,
        `updated_at`                TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_concat` (`concat`(255)),
        INDEX `idx_cnpj` (`cnpj`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    // Tabela já existe ou erro não crítico — continua
}

// ─── Estatísticas ────────────────────────────────────────────────────────────
$stats = ['total' => 0, 'cnpjs' => 0, 'ultima_atualizacao' => '—'];
try {
    $row = $pdo->query("SELECT COUNT(*) as total, COUNT(DISTINCT cnpj) as cnpjs, MAX(updated_at) as ultima FROM cot_budget_cliente")->fetch(PDO::FETCH_ASSOC);
    $stats['total']  = (int)$row['total'];
    $stats['cnpjs']  = (int)$row['cnpjs'];
    $stats['ultima_atualizacao'] = $row['ultima'] ? date('d/m/Y H:i', strtotime($row['ultima'])) : '—';
} catch (PDOException $e) { /* silencia */ }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar BUDGET — Pricelist Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; font-family: 'Montserrat', sans-serif; }
        .page-header {
            background: linear-gradient(135deg, #1a6b3c 0%, #2d9e5f 100%);
            color: #fff;
            padding: 2rem 0 2.5rem;
            margin-bottom: 2rem;
        }
        .page-header h1 { font-weight: 700; font-size: 1.8rem; }
        .page-header p  { opacity: .85; margin-bottom: 0; }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.4rem 1.6rem;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            display: flex; align-items: center; gap: 1rem;
        }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        .stat-icon.green { background: #e6f4ee; color: #1a6b3c; }
        .stat-icon.blue  { background: #e8f0fe; color: #1a56db; }
        .stat-icon.gray  { background: #f1f3f4; color: #555; }
        .stat-label { font-size: .8rem; color: #888; text-transform: uppercase; letter-spacing: .05em; }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: #1a1a1a; line-height: 1.2; }
        .upload-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
        }
        .upload-card .card-header {
            background: linear-gradient(90deg, #1a6b3c, #2d9e5f);
            color: #fff; border-radius: 14px 14px 0 0;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        .drop-zone {
            border: 2px dashed #a8d5bb;
            border-radius: 10px;
            padding: 2.5rem;
            text-align: center;
            background: #f9fdf9;
            transition: all .2s;
            cursor: pointer;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #1a6b3c;
            background: #e9f5ef;
        }
        .drop-zone i { font-size: 2.5rem; color: #2d9e5f; margin-bottom: .5rem; display: block; }
        .badge-format {
            background: #e6f4ee; color: #1a6b3c;
            font-size: .8rem; padding: .3em .7em; border-radius: 6px; font-weight: 600;
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container">
        <h1><i class="bi bi-cloud-upload me-2"></i>Atualizar BUDGET</h1>
        <p>Importação do arquivo de pricelist por cliente. Campos novos são adicionados, existentes são atualizados (UPSERT por concat).</p>
    </div>
</div>

<div class="container pb-5">

    <?php if (isset($_GET['sucesso'])): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div>
            Base substituída com sucesso! <strong><?= htmlspecialchars((int)$_GET['sucesso']) ?> linha(s)</strong> importada(s).
            <?php if (!empty($_GET['not_found'])): ?>
                <br><small class="text-danger fw-semibold">⚠️ Colunas NÃO detectadas: <code><?= htmlspecialchars($_GET['not_found']) ?></code> — verifique o cabeçalho do CSV.</small>
            <?php endif; ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div><?= htmlspecialchars($_GET['erro']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Estatísticas -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-table"></i></div>
                <div>
                    <div class="stat-label">Total de Linhas</div>
                    <div class="stat-value"><?= number_format($stats['total'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-building"></i></div>
                <div>
                    <div class="stat-label">Clientes (CNPJs)</div>
                    <div class="stat-value"><?= number_format($stats['cnpjs'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon gray"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-label">Última Atualização</div>
                    <div class="stat-value" style="font-size:1.1rem"><?= htmlspecialchars($stats['ultima_atualizacao']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload -->
    <div class="upload-card">
        <div class="card-header">
            <i class="bi bi-file-earmark-arrow-up me-2"></i>Importar CSV de BUDGET
        </div>
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="download_template_budget.php" class="btn btn-outline-success">
                    <i class="bi bi-download me-1"></i> Baixar Template CSV
                </a>
                <span class="badge-format">CSV &nbsp;·&nbsp; separador: <strong>;</strong></span>
                <span class="text-muted small ms-auto">O arquivo deve conter os cabeçalhos na linha 1. Registros existentes (mesmo concat) são atualizados.</span>
            </div>

            <form action="importar_budget_cliente.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="drop-zone mb-3" id="dropZone" onclick="document.getElementById('arquivo_csv').click()">
                    <i class="bi bi-cloud-upload"></i>
                    <div class="fw-semibold text-secondary" id="dropLabel">Clique aqui ou arraste o arquivo CSV</div>
                    <small class="text-muted" id="dropSub">Somente arquivos .csv</small>
                </div>
                <input type="file" id="arquivo_csv" name="arquivo_csv" accept=".csv" class="d-none" required>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success px-4" id="btnImportar" disabled>
                        <i class="bi bi-upload me-1"></i> Importar e Atualizar
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('arquivo_csv').value=''; resetDrop();">
                        <i class="bi bi-x-circle me-1"></i> Limpar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Aviso sobre colunas -->
    <div class="alert alert-info mt-4 small">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Colunas esperadas no CSV</strong> (detecção automática — nomes devem estar na linha 1):
        <br>
        <code>CNPJ · RAZÃO SOCIAL · CLIENTE ORIGEM · TERCEIRISTA · PRODUTO · Fabricante · Concat · Tipo · Vendedor · Vendedor ajustado · Embalagem · TOTAL KG ENTRE 2017 a 2024 · KG Realizado 2025 · KG Orçado 2026 · KG Realizado 2026 · Preço Realizado entre 17 e 23 · Preço Realizado 2025 · Reajuste Sugerido · Preço Sugerido · Preço Orçado 2026 · Preço Realizado 2026 · Preço ... USD · Venda NET · Custo · Lucro · GM% · LOTE ECONÔMICO · EXW 2026 · LANDED 2026 · COMENTARIOS SUPPLY · PREÇO AJUSTADO</code>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const input    = document.getElementById('arquivo_csv');
const dropZone = document.getElementById('dropZone');
const label    = document.getElementById('dropLabel');
const sub      = document.getElementById('dropSub');
const btn      = document.getElementById('btnImportar');

function setFile(file) {
    if (!file) return;
    label.textContent = file.name;
    sub.textContent   = (file.size / 1024).toFixed(1) + ' KB';
    btn.disabled = false;
    dropZone.classList.add('border-success');
}
function resetDrop() {
    label.textContent = 'Clique aqui ou arraste o arquivo CSV';
    sub.textContent   = 'Somente arquivos .csv';
    btn.disabled = true;
    dropZone.classList.remove('border-success');
}

input.addEventListener('change', () => setFile(input.files[0]));

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file && file.name.endsWith('.csv')) {
        // Atribui ao input de forma programática
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        setFile(file);
    } else {
        alert('Por favor, selecione um arquivo .csv');
    }
});
</script>
</body>
</html>
