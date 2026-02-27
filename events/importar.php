<?php
define('PAGE_TITLE', 'Importar Dados');
require_once 'header.php';
?>

<div class="container my-5">

    <!-- Card de Upload -->
    <div class="row">
        <div class="col-12 col-lg-8 mx-auto">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0 text-secondary">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Upload de Planilha CSV
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info border-0">
                        <i class="bi bi-info-circle"></i>
                        <strong>Instruções:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Selecione o arquivo CSV exportado do VIAGEM EXPRESS ou UBER</li>
                            <li>O arquivo deve estar no formato padrão com delimitador <code>;</code> (ou <code>,</code>
                                para Uber)</li>
                            <li>As despesas serão categorizadas automaticamente</li>
                            <li>Você poderá editar as categorias manualmente após a importação</li>
                        </ul>
                    </div>

                    <!-- Botão de Download do Modelo -->
                    <div class="mb-4">
                        <div class="alert alert-success border-0 d-flex align-items-center justify-content-between">
                            <div>
                                <i class="bi bi-download"></i>
                                <strong>Não tem um arquivo no formato correto?</strong>
                                <p class="mb-0 mt-1 small">Baixe o arquivo modelo para garantir que sua planilha esteja
                                    no padrão esperado.</p>
                            </div>
                            <a href="modelo_importacao.csv" download="modelo_importacao_viagem_express.csv"
                                class="btn btn-success btn-sm ms-3 flex-shrink-0">
                                <i class="bi bi-file-earmark-arrow-down"></i> Baixar Modelo Viagem Express
                            </a>
                            <a href="modelo_uber.csv" download="modelo_uber.csv"
                                class="btn btn-outline-success btn-sm ms-2 flex-shrink-0">
                                <i class="bi bi-file-earmark-arrow-down"></i> Baixar Modelo Uber
                            </a>
                        </div>
                    </div>

                    <form id="uploadForm" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label for="numFatura" class="form-label fw-bold">Nº da Fatura:</label>
                            <input type="text" class="form-control form-control-lg" id="numFatura" name="num_fatura"
                                placeholder="Ex: FT00004301" required>
                            <div class="form-text">
                                <i class="bi bi-receipt"></i> Informe o número da fatura para identificar esta
                                importação
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="csvFile" class="form-label fw-bold">Selecione o arquivo CSV:</label>
                            <input type="file" class="form-control form-control-lg" id="csvFile" name="csv_file"
                                accept=".csv" required>
                            <div class="form-text">
                                <i class="bi bi-file-earmark-text"></i> Apenas arquivos .csv são permitidos
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="uploadBtn">
                                <i class="bi bi-cloud-upload"></i> Iniciar Importação
                            </button>
                        </div>
                    </form>


                    <!-- Área de Progresso -->
                    <div id="progressArea" class="mt-4" style="display: none;">
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <h6 class="mb-3">
                                    <i class="bi bi-hourglass-split"></i> Processando arquivo...
                                </h6>
                                <div class="progress" style="height: 25px;">
                                    <div id="progressBar"
                                        class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                        role="progressbar" style="width: 0%">0%</div>
                                </div>
                                <p id="progressText" class="text-muted small mt-2 mb-0">Aguarde...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Área de Resultado -->
                    <div id="resultArea" class="mt-4" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card de Histórico (opcional) -->
    <div class="row mt-4">
        <div class="col-12 col-lg-8 mx-auto">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 text-secondary">
                        <i class="bi bi-clock-history"></i> Últimas Importações
                    </h5>
                </div>
                <div class="card-body">
                    <div id="importHistory">
                        <p class="text-muted text-center py-3">
                            <i class="bi bi-inbox"></i> Nenhuma importação realizada ainda
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    const uploadForm = document.getElementById('uploadForm');
    const uploadBtn = document.getElementById('uploadBtn');
    const progressArea = document.getElementById('progressArea');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const resultArea = document.getElementById('resultArea');

    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const fileInput = document.getElementById('csvFile');
        const file = fileInput.files[0];
        const numFatura = document.getElementById('numFatura').value.trim();

        if (!file) {
            alert('Por favor, selecione um arquivo CSV.');
            return;
        }

        if (!numFatura) {
            alert('Por favor, informe o número da fatura.');
            return;
        }

        // Mostra progresso
        progressArea.style.display = 'block';
        resultArea.style.display = 'none';
        uploadBtn.disabled = true;
        progressBar.style.width = '30%';
        progressBar.textContent = '30%';
        progressText.textContent = 'Enviando arquivo...';

        const formData = new FormData();
        formData.append('csv_file', file);
        formData.append('num_fatura', numFatura);

        try {
            progressBar.style.width = '60%';
            progressBar.textContent = '60%';
            progressText.textContent = 'Processando dados...';

            const response = await fetch('api/process_upload.php', {
                method: 'POST',
                body: formData
            });


            const data = await response.json();

            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            progressText.textContent = 'Concluído!';

            // Mostra resultado
            setTimeout(() => {
                progressArea.style.display = 'none';
                resultArea.style.display = 'block';

                if (data.success) {
                    resultArea.innerHTML = `
                        <div class="alert alert-success border-0 shadow-sm">
                            <h5 class="alert-heading">
                                <i class="bi bi-check-circle"></i> Sucesso!
                            </h5>
                            <p class="mb-2">${data.message}</p>
                            <hr>
                            <div class="row g-3 mt-2">
                                <div class="col-6 col-md-3">
                                    <div class="text-center">
                                        <div class="fs-4 fw-bold text-success">${data.data.imported}</div>
                                        <div class="small text-muted">Importados</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-center">
                                        <div class="fs-4 fw-bold text-warning">${data.data.skipped}</div>
                                        <div class="small text-muted">Ignorados</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-center">
                                        <div class="fs-4 fw-bold text-primary">${data.data.total_lines}</div>
                                        <div class="small text-muted">Total Linhas</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-center">
                                        <div class="fs-4 fw-bold text-info">${Object.keys(data.data.categories || {}).length}</div>
                                        <div class="small text-muted">Categorias</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="index.php" class="btn btn-success">
                                    <i class="bi bi-speedometer2"></i> Ver Dashboard
                                </a>
                                <button class="btn btn-outline-secondary" onclick="location.reload()">
                                    <i class="bi bi-arrow-clockwise"></i> Nova Importação
                                </button>
                            </div>
                        </div>
                    `;
                } else {
                    resultArea.innerHTML = `
                        <div class="alert alert-danger border-0 shadow-sm">
                            <h5 class="alert-heading">
                                <i class="bi bi-exclamation-triangle"></i> Erro na Importação
                            </h5>
                            <p class="mb-0">${data.message}</p>
                            <div class="mt-3">
                                <button class="btn btn-outline-danger" onclick="location.reload()">
                                    <i class="bi bi-arrow-clockwise"></i> Tentar Novamente
                                </button>
                            </div>
                        </div>
                    `;
                }

                uploadBtn.disabled = false;
            }, 500);

        } catch (error) {
            progressArea.style.display = 'none';
            resultArea.style.display = 'block';
            resultArea.innerHTML = `
                <div class="alert alert-danger border-0 shadow-sm">
                    <h5 class="alert-heading">
                        <i class="bi bi-exclamation-triangle"></i> Erro ao processar
                    </h5>
                    <p class="mb-0">Erro ao processar o arquivo: ${error.message}</p>
                    <div class="mt-3">
                        <button class="btn btn-outline-danger" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Tentar Novamente
                        </button>
                    </div>
                </div>
            `;
            uploadBtn.disabled = false;
        }
    });
</script>

<?php require_once 'footer.php'; ?>