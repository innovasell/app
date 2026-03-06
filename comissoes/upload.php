<?php
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';
$pagina_ativa = 'upload';
require_once __DIR__ . '/header.php';
?>
<style>
    .upload-area {
        border: 2px dashed #40883c;
        border-radius: 12px; padding: 3rem;
        text-align: center; background: #fff;
        transition: all 0.3s ease; cursor: pointer;
    }
    .upload-area:hover { border-color: #2c5e29; background-color: #f1fff1; }
    .upload-icon { font-size: 3rem; color: #40883c; margin-bottom: 1rem; }
    #loadingOverlay {
        position: fixed; top:0; left:0; width:100%; height:100%;
        background: rgba(255,255,255,0.92); z-index:9999;
        display:none; flex-direction:column;
        align-items:center; justify-content:center;
    }
</style>

    <div class="container-fluid px-4 py-3">


    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-primary">Importar Notas Fiscais</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle"></i> Envie um arquivo <strong>.ZIP</strong> contendo os XMLs
                            das notas fiscais.
                        </div>

                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="upload-area" id="dropZone">
                                <i class="bi bi-cloud-arrow-up upload-icon"></i>
                                <h4>Arraste e solte seu arquivo ZIP aqui</h4>
                                <p class="text-muted">ou clique para selecionar</p>
                                <input type="file" name="zip_file" id="zipInput" accept=".zip" class="d-none" required>
                                <div id="fileName" class="mt-3 fw-bold text-success"></div>
                            </div>

                            <div class="mt-4">
                                <label class="form-label fw-bold">Arquivo de Vendedores (CSV) <small
                                        class="text-muted">(Opcional)</small></label>
                                <div class="input-group">
                                    <input type="file" name="sellers_csv" class="form-control" accept=".csv">
                                    <a href="api/download_template_sellers.php" class="btn btn-outline-secondary"
                                        title="Baixar Modelo">
                                        <i class="bi bi-download"></i> Modelo
                                    </a>
                                </div>
                                <div class="form-text">Envie um arquivo CSV com as colunas:
                                    <code>Numero_NF;Nome_Vendedor</code> para vincular automaticamente.
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-gear-fill"></i> Processar Arquivo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay">
        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
        <h4 class="text-secondary">Processando arquivos...</h4>
        <p class="text-muted" id="loadingStatus">Carregando dados e consultando taxas PTAX...</p>
    </div>

    <!-- Modal Error/Success -->
    <div class="modal fade" id="resultModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <a href="validacao.php" class="btn btn-success d-none" id="btnGoValidation">Ir para Validação</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const dropZone = document.getElementById('dropZone');
        const zipInput = document.getElementById('zipInput');
        const fileNameDisplay = document.getElementById('fileName');
        const uploadForm = document.getElementById('uploadForm');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
        const btnGoValidation = document.getElementById('btnGoValidation');

        // Drag and Drop
        dropZone.addEventListener('click', () => zipInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#0d6efd';
            dropZone.style.backgroundColor = '#f1f8ff';
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#dee2e6';
            dropZone.style.backgroundColor = '#fff';
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#dee2e6';
            dropZone.style.backgroundColor = '#fff';

            if (e.dataTransfer.files.length) {
                zipInput.files = e.dataTransfer.files;
                updateFileName();
            }
        });

        zipInput.addEventListener('change', updateFileName);

        function updateFileName() {
            if (zipInput.files.length) {
                fileNameDisplay.textContent = zipInput.files[0].name;
            } else {
                fileNameDisplay.textContent = '';
            }
        }

        // Submit
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!zipInput.files.length) {
                alert("Selecione um arquivo ZIP!");
                return;
            }

            const formData = new FormData(uploadForm);

            loadingOverlay.style.display = 'flex';
            btnGoValidation.classList.add('d-none');

            try {
                const response = await fetch('api/process_upload.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                loadingOverlay.style.display = 'none';

                const modalTitle = document.getElementById('modalTitle');
                const modalBody = document.getElementById('modalBody');

                if (result.success) {
                    modalTitle.className = "modal-title text-success";
                    modalTitle.textContent = "Sucesso!";
                    modalBody.innerHTML = `
                    <p class="fs-5">${result.message}</p>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Arquivos Processados
                            <span class="badge bg-primary rounded-pill">${result.processed_count}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Itens Importados
                            <span class="badge bg-success rounded-pill">${result.imported_count}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Ignorados (CFOP)
                            <span class="badge bg-secondary rounded-pill">${result.ignored_count}</span>
                        </li>
                    </ul>
                `;
                    btnGoValidation.classList.remove('d-none');
                } else {
                    modalTitle.className = "modal-title text-danger";
                    modalTitle.textContent = "Erro";
                    modalBody.innerHTML = `<p class="text-danger">${result.error || 'Erro desconhecido'}</p>`;
                }

                resultModal.show();

            } catch (error) {
                loadingOverlay.style.display = 'none';
                alert("Erro na comunicação com o servidor: " + error.message);
            }
        });
    </script>

</body>

</html>