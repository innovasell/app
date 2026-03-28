<?php
define('PAGE_TITLE',   'Importar Dados');
define('PAGE_CURRENT', 'importar');
require_once 'conexao.php';
require_once 'auth.php';
require_login();

// Se vier de event_id na URL, pré-selecionar o evento
$preselect_event = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Carregar lista de eventos abertos para o select
$events = $pdo->query("SELECT id, nome, status FROM evt_events ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Definir active_event se pré-selecionado (para mostrar a barra de contexto)
$active_event = null;
if ($preselect_event) {
    foreach ($events as $ev) {
        if ($ev['id'] == $preselect_event) { $active_event = $ev; break; }
    }
}

require_once 'header.php';
?>

<div class="container-fluid px-4 py-4">

    <!-- Cabeçalho -->
    <div class="page-header mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php">Eventos</a></li>
                <?php if ($active_event): ?>
                <li class="breadcrumb-item"><a href="evento.php?id=<?= $active_event['id'] ?>"><?= htmlspecialchars($active_event['nome']) ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active">Importar Dados</li>
            </ol>
        </nav>
        <h1><i class="bi bi-cloud-upload-fill me-2"></i>Importar Planilha CSV</h1>
        <p class="mb-0 mt-1 opacity-75 small">Importe despesas do Viagem Express ou Uber e vincule-as a um evento.</p>
    </div>

    <div class="row g-4">
        <!-- Formulário de upload -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Configurar Importação</div>
                <div class="card-body">

                    <!-- Info -->
                    <div class="alert alert-info border-0 mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Selecione um <strong>evento</strong>, o número da fatura e o arquivo CSV exportado do Viagem Express ou Uber.
                        As despesas serão vinculadas ao evento escolhido e categorizadas automaticamente.
                    </div>

                    <form id="uploadForm" enctype="multipart/form-data">

                        <!-- Seleção de Evento -->
                        <div class="mb-4">
                            <label class="form-label">Evento *</label>
                            <select class="form-select form-select-lg" id="eventSelect" name="event_id" required>
                                <option value="">— Selecione o evento —</option>
                                <?php foreach ($events as $ev):
                                    $selected = ($ev['id'] == $preselect_event) ? 'selected' : '';
                                    $statusMap = ['planejamento'=>'Planejamento', 'em_execucao'=>'Em Execução', 'encerrado'=>'Encerrado'];
                                ?>
                                <option value="<?= $ev['id'] ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($ev['nome']) ?>
                                    (<?= $statusMap[$ev['status']] ?? $ev['status'] ?>)
                                </option>
                                <?php endforeach; ?>
                                <option value="__novo__">✚ Criar novo evento agora…</option>
                            </select>
                            <div class="form-text">
                                <a href="index.php" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Gerenciar eventos</a>
                            </div>
                        </div>

                        <!-- Nº da Fatura -->
                        <div class="mb-4">
                            <label class="form-label">Nº da Fatura *</label>
                            <input type="text" class="form-control form-control-lg" id="numFatura" name="num_fatura"
                                   placeholder="Ex: FT00004301" required>
                            <div class="form-text"><i class="bi bi-receipt"></i> Identificador desta importação</div>
                        </div>

                        <!-- Arquivo CSV -->
                        <div class="mb-4">
                            <label class="form-label">Arquivo CSV *</label>
                            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFile').click()">
                                <i class="bi bi-file-earmark-arrow-up fs-2 text-success d-block mb-2"></i>
                                <div class="fw-600">Clique para selecionar ou arraste o arquivo aqui</div>
                                <div class="text-muted small mt-1">Viagem Express (;) ou Uber (,)</div>
                                <div id="fileName" class="mt-2 text-success small fw-600"></div>
                            </div>
                            <input type="file" id="csvFile" name="csv_file" accept=".csv" class="d-none" required>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success btn-lg flex-grow-1" id="uploadBtn">
                                <i class="bi bi-cloud-upload me-2"></i>Importar
                            </button>
                        </div>

                    </form>

                    <!-- Progresso -->
                    <div id="progressArea" class="mt-4" style="display:none">
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <h6><i class="bi bi-hourglass-split me-2"></i>Processando arquivo…</h6>
                                <div class="progress" style="height:24px">
                                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                         style="width:0%">0%</div>
                                </div>
                                <p id="progressText" class="text-muted small mt-2 mb-0">Aguarde…</p>
                            </div>
                        </div>
                    </div>

                    <!-- Resultado -->
                    <div id="resultArea" class="mt-4" style="display:none"></div>

                </div>
            </div>
        </div>

        <!-- Sidebar: Downloads + Histórico recente -->
        <div class="col-lg-5">

            <!-- Modelos -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-download me-2 text-primary"></i>Modelos de Importação</div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Baixe o modelo correspondente ao sistema de origem.</p>
                    <div class="d-grid gap-2">
                        <a href="modelo_importacao.csv" download class="btn btn-outline-success">
                            <i class="bi bi-file-earmark-arrow-down me-2"></i>Modelo Viagem Express
                        </a>
                        <a href="modelo_uber.csv" download class="btn btn-outline-secondary">
                            <i class="bi bi-file-earmark-arrow-down me-2"></i>Modelo Uber
                        </a>
                    </div>
                </div>
            </div>

            <!-- Últimas importações -->
            <div class="card">
                <div class="card-header"><i class="bi bi-clock-history me-2 text-muted"></i>Últimas Importações</div>
                <div class="card-body p-0" id="importHistory">
                    <div class="text-center py-4 text-muted small">
                        <div class="spinner-border spinner-border-sm me-2"></div>Carregando…
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ── Drag & drop na upload zone ────────────────────────────────────────────── //
const csvFile    = document.getElementById('csvFile');
const uploadZone = document.getElementById('uploadZone');
const fileNameEl = document.getElementById('fileName');

csvFile.addEventListener('change', () => {
    fileNameEl.textContent = csvFile.files[0] ? '✓ ' + csvFile.files[0].name : '';
});

['dragover','dragleave','drop'].forEach(evt => uploadZone.addEventListener(evt, e => e.preventDefault()));
uploadZone.addEventListener('dragover', () => uploadZone.classList.add('drag-over'));
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
uploadZone.addEventListener('drop', e => {
    uploadZone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file && file.name.endsWith('.csv')) {
        const dt = new DataTransfer();
        dt.items.add(file);
        csvFile.files = dt.files;
        fileNameEl.textContent = '✓ ' + file.name;
    }
});

// ── Redirecionar para criar novo evento ───────────────────────────────────── //
document.getElementById('eventSelect').addEventListener('change', function() {
    if (this.value === '__novo__') {
        window.open('index.php', '_blank');
        this.value = '';
    }
});

// ── Upload ────────────────────────────────────────────────────────────────── //
document.getElementById('uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const eventId  = document.getElementById('eventSelect').value;
    const numFat   = document.getElementById('numFatura').value.trim();
    const file     = csvFile.files[0];

    if (!eventId || eventId === '__novo__') { alert('Selecione o evento.'); return; }
    if (!numFat) { alert('Informe o número da fatura.'); return; }
    if (!file)  { alert('Selecione o arquivo CSV.'); return; }

    const progressArea = document.getElementById('progressArea');
    const progressBar  = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const resultArea   = document.getElementById('resultArea');
    const uploadBtn    = document.getElementById('uploadBtn');

    progressArea.style.display = 'block';
    resultArea.style.display = 'none';
    uploadBtn.disabled = true;
    progressBar.style.width = '30%'; progressBar.textContent = '30%';
    progressText.textContent = 'Enviando arquivo…';

    const formData = new FormData();
    formData.append('csv_file', file);
    formData.append('num_fatura', numFat);
    formData.append('event_id', eventId);

    try {
        progressBar.style.width = '60%'; progressBar.textContent = '60%';
        progressText.textContent = 'Processando…';

        const resp = await fetch('api/process_upload.php', { method: 'POST', body: formData });
        const data = await resp.json();

        progressBar.style.width = '100%'; progressBar.textContent = '100%';
        progressText.textContent = 'Concluído!';

        setTimeout(() => {
            progressArea.style.display = 'none';
            resultArea.style.display = 'block';

            if (data.success) {
                resultArea.innerHTML = `
                <div class="alert alert-success border-0 shadow-sm">
                    <h5 class="alert-heading"><i class="bi bi-check-circle me-2"></i>Importação concluída!</h5>
                    <p class="mb-3">${data.message}</p>
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3 text-center">
                            <div class="fs-4 fw-bold text-success">${data.data.imported}</div>
                            <div class="small text-muted">Importados</div>
                        </div>
                        <div class="col-6 col-md-3 text-center">
                            <div class="fs-4 fw-bold text-warning">${data.data.skipped}</div>
                            <div class="small text-muted">Ignorados</div>
                        </div>
                        <div class="col-6 col-md-3 text-center">
                            <div class="fs-4 fw-bold text-primary">${data.data.total_lines}</div>
                            <div class="small text-muted">Total Linhas</div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="despesas.php?event_id=${eventId}" class="btn btn-success">
                            <i class="bi bi-receipt-cutoff me-1"></i>Ver Despesas do Evento
                        </a>
                        <button class="btn btn-outline-secondary" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Nova Importação
                        </button>
                    </div>
                </div>`;
                loadHistory();
            } else {
                resultArea.innerHTML = `
                <div class="alert alert-danger border-0 shadow-sm">
                    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Erro na Importação</h5>
                    <p class="mb-2">${data.message}</p>
                    <button class="btn btn-outline-danger btn-sm" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Tentar Novamente
                    </button>
                </div>`;
            }
            uploadBtn.disabled = false;
        }, 500);

    } catch (err) {
        progressArea.style.display = 'none';
        resultArea.style.display = 'block';
        resultArea.innerHTML = `<div class="alert alert-danger border-0 shadow-sm">Erro: ${err.message}</div>`;
        uploadBtn.disabled = false;
    }
});

// ── Histórico de importações ──────────────────────────────────────────────── //
function loadHistory() {
    $.getJSON('api/get_expenses.php?limit=5', function(res) {
        if (!res.expenses || !res.expenses.length) {
            $('#importHistory').html('<div class="text-center py-4 text-muted small"><i class="bi bi-inbox me-2"></i>Nenhuma importação ainda.</div>');
            return;
        }
        const rows = res.expenses.slice(0,5).map(e => `
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <div>
                <div class="small fw-600">${e.num_fatura || '—'}</div>
                <div class="text-muted" style="font-size:.75rem">${e.passageiro || e.produto || '—'}</div>
            </div>
            <div class="text-end small text-muted">${e.dt_emissao || ''}</div>
        </div>`).join('');
        $('#importHistory').html(rows);
    }).fail(function() {
        $('#importHistory').html('<div class="text-center py-4 text-muted small">—</div>');
    });
}

$(loadHistory);
</script>

<?php require_once 'footer.php'; ?>
