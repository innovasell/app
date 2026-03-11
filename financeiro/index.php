<?php
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';
$pagina_ativa = 'financeiro_index';

// Recuperar regime atual (default: presumido)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$regime_atual = $_SESSION['regime_tributario'] ?? 'presumido';

require_once __DIR__ . '/header.php';
?>

<div class="container-fluid px-4 py-3">

    <!-- Page Header -->
    <div class="page-header">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="../">Dashboard</a></li>
                <li class="breadcrumb-item active">Financeiro</li>
            </ol>
        </nav>
        <h1><i class="fas fa-file-invoice-dollar me-2"></i> Processamento de NF-e</h1>
    </div>

    <!-- Toolbar: Regime Selecionado -->
    <div class="row mb-4 bg-white p-3 rounded shadow-sm border align-items-center">
        <div class="col-md-6 d-flex align-items-center">
            <h5 class="mb-0 me-3"><i class="fas fa-balance-scale text-success"></i> Regime Tributário:</h5>
            <select id="regimeSelect" class="form-select form-select-sm w-auto fw-bold">
                <option value="presumido" <?= $regime_atual === 'presumido' ? 'selected' : '' ?>>Lucro Presumido</option>
                <option value="real" <?= $regime_atual === 'real' ? 'selected' : '' ?>>Lucro Real</option>
            </select>
        </div>
        <div class="col-md-6 text-md-end mt-2 mt-md-0">
            <!-- Alert Dinâmico dependendo da escolha -->
            <div id="regimeAlert" class="badge rounded-pill px-3 py-2 <?= $regime_atual === 'presumido' ? 'bg-primary' : 'bg-danger' ?> bg-opacity-75 text-white">
                <?= $regime_atual === 'presumido' 
                    ? '<i class="fas fa-info-circle"></i> PIS: 0.65% | COFINS: 3.00% (Cumulativo)' 
                    : '<i class="fas fa-info-circle"></i> PIS: 1.65% | COFINS: 7.60% (Não-Cumulativo)' ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- 1. Coluna Upload -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title fw-bold text-success mb-0"><i class="fas fa-file-upload me-2"></i> Envio de XMLs (ZIP)</h5>
                </div>
                <div class="card-body d-flex flex-column justify-content-center">
                    
                    <form id="uploadForm" enctype="multipart/form-data">
                        <div class="upload-area mb-3" id="dropZone" onclick="document.getElementById('fileZip').click()">
                            <i class="fas fa-file-archive"></i>
                            <h5 class="text-success">Arraste um .zip aqui</h5>
                            <p class="text-muted small mb-0">Ou clique para selecionar</p>
                            <input type="file" name="arquivo_zip" id="fileZip" accept=".zip" required>
                        </div>
                        <div id="fileInfo" class="alert alert-secondary py-2 d-none">
                            <strong>Arquivo:</strong> <span id="fileName">-</span> <br>
                            <small class="text-muted" id="fileSize"></small>
                        </div>
                        
                        <div class="d-grid mt-auto">
                            <button type="button" id="btnProcessar" class="btn btn-success btn-lg" disabled>
                                <i class="fas fa-cogs me-1"></i> Processar NF-e
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>

        <!-- 2. Coluna Logs e Stats Temporário -->
        <div class="col-lg-8 mb-4">
            <div class="row">
                <!-- Stat Cards -->
                <div class="col-md-3 mb-3">
                    <div class="card card-stat bg-success-custom text-white">
                        <div class="card-body p-3">
                            <div class="card-icon"><i class="fas fa-check-double"></i></div>
                            <div class="card-value" id="statProcessadas">0</div>
                            <div class="card-label">NFs Aceitas</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card card-stat bg-warning-custom text-white">
                        <div class="card-body p-3">
                            <div class="card-icon"><i class="fas fa-ban"></i></div>
                            <div class="card-value" id="statCanceladas">0</div>
                            <div class="card-label">Ignoradas (Cancel.)</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card card-stat bg-primary-custom text-white">
                        <div class="card-body p-3">
                            <div class="card-icon"><i class="fas fa-copy"></i></div>
                            <div class="card-value" id="statDuplicadas">0</div>
                            <div class="card-label">Ignoradas (Duplic.)</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card card-stat bg-danger-custom text-white">
                        <div class="card-body p-3">
                            <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="card-value" id="statErros">0</div>
                            <div class="card-label">Com Erro</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm border-0" style="height: 300px;">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold mb-0"><i class="fas fa-terminal me-2"></i> Log de Processamento</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('logOutput').innerHTML = '';"><i class="fas fa-eraser"></i> Limpar</button>
                </div>
                <!-- Log Área -->
                <div class="card-body bg-light overflow-auto p-2" id="logOutput" style="font-family: monospace; font-size: 0.85rem;">
                    <div class="text-muted"><i class="fas fa-info-circle"></i> Aguardando envio...</div>
                </div>
                <!-- Progress -->
                <div class="progress rounded-0" style="height: 5px; display: none;" id="progressBarContainer">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div> <!-- end row 1 -->

    <!-- Tabela de Resultados Oculta -->
    <div id="resultadoWrapper" class="card shadow-sm border-0 d-none">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
            <h5 class="card-title fw-bold mb-0 text-primary">
                <i class="fas fa-table me-2"></i> NFs Extraídas com Sucesso
            </h5>
            
            <a href="#" id="btnExportExcel" class="btn btn-primary fw-bold">
                <i class="fas fa-file-excel me-1"></i> Exportar Excel (.xlsx)
            </a>
        </div>
        <div class="card-body table-responsive">
            <table id="tblNFs" class="table table-hover table-bordered w-100">
                <thead>
                    <tr>
                        <th width="80">Nº NF</th>
                        <th>Emitente</th>
                        <th width="100">Emissão</th>
                        <th width="70">Qtd Item</th>
                        <th width="110">Tot Produtos</th>
                        <th width="110">Tot PIS</th>
                        <th width="110">Tot COFINS</th>
                        <th width="110">Tot ICMS</th>
                        <th width="110">Tot IPI</th>
                        <th width="120">Total NF</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

</div> <!-- /.container-fluid -->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- SweetAlert (Optional but nice) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    
    // Tabela Init DataTables
    const table = $('#tblNFs').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
        order: [[2, 'desc']], // Emissao
        pageLength: 25,
        columnDefs: [
            { className: "text-end", targets: [4,5,6,7,8,9] },
            { className: "text-center", targets: [0, 2, 3] }
        ]
    });

    let currentFile = null;
    let lastUniqid = null;

    // Handlers Regime Update UI
    $('#regimeSelect').on('change', function() {
        const value = $(this).val();
        const alertBox = $('#regimeAlert');
        
        if (value === 'presumido') {
            alertBox.removeClass('bg-danger').addClass('bg-primary');
            alertBox.html('<i class="fas fa-info-circle"></i> PIS: 0.65% | COFINS: 3.00% (Cumulativo)');
        } else {
            alertBox.removeClass('bg-primary').addClass('bg-danger');
            alertBox.html('<i class="fas fa-info-circle"></i> PIS: 1.65% | COFINS: 7.60% (Não-Cumulativo)');
        }
    });

    // Drag and Drop Logic
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileZip');
    const btnProcessar = document.getElementById('btnProcessar');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    function preventDefaults (e) { e.preventDefault(); e.stopPropagation(); }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
    });
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
    });

    dropZone.addEventListener('drop', handleDrop, false);
    fileInput.addEventListener('change', function() { handleFiles(this.files); });

    function handleDrop(e) { handleFiles(e.dataTransfer.files); }

    function handleFiles(files) {
        if (files.length) {
            currentFile = files[0];
            if (!currentFile.name.toLowerCase().endsWith('.zip')) {
                Swal.fire('Formato Inválido', 'Por favor, envie apenas arquivos .zip contendo os XMLs.', 'error');
                resetUpload();
                return;
            }
            
            $('#fileInfo').removeClass('d-none');
            $('#fileName').text(currentFile.name);
            $('#fileSize').text((currentFile.size / 1024 / 1024).toFixed(2) + ' MB');
            $('#btnProcessar').prop('disabled', false);
            
            // Reattach to input if it came from drag drop
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(currentFile);
            fileInput.files = dataTransfer.files;
        }
    }

    function resetUpload() {
        currentFile = null;
        fileInput.value = '';
        $('#fileInfo').addClass('d-none');
        $('#btnProcessar').prop('disabled', true);
    }

    // Submit do Arquivo
    $('#btnProcessar').click(function() {
        if (!currentFile) return;

        const formData = new FormData($('#uploadForm')[0]);
        formData.append('regime', $('#regimeSelect').val());

        const $btn = $(this);
        const logBox = $('#logOutput');
        
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Processando...').prop('disabled', true);
        $('#progressBarContainer').show();
        $('#progressBar').css('width', '50%'); // fake progress initially
        logBox.html('<div class="text-primary"><i class="fas fa-sync fa-spin"></i> Enviando e descompactando arquivo...</div>');
        
        // Esconder tabela
        $('#resultadoWrapper').addClass('d-none');
        table.clear().draw();

        $.ajax({
            url: 'api/process_nfe.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                $('#progressBar').css('width', '100%');
                setTimeout(() => $('#progressBarContainer').hide(), 1000);
                $btn.html('<i class="fas fa-cogs me-1"></i> Processar NF-e').prop('disabled', false);

                if (res.success) {
                    // Update Stats
                    $('#statProcessadas').text(res.processadas);
                    $('#statCanceladas').text(res.ignoradas_canceladas);
                    $('#statDuplicadas').text(res.ignoradas_duplicadas);
                    $('#statErros').text(res.erros);
                    
                    // Populate Log
                    let htmlLog = '';
                    if (res.log && res.log.length > 0) {
                        res.log.forEach(item => {
                            let color = 'text-dark';
                            let icon = 'fa-info-circle';
                            if(item.tipo === 'ok') { color = 'text-success'; icon = 'fa-check'; }
                            if(item.tipo === 'ignorada') { color = 'text-warning'; icon = 'fa-exclamation-triangle'; }
                            if(item.tipo === 'erro') { color = 'text-danger'; icon = 'fa-times'; }
                            htmlLog += `<div class="${color} mb-1"><i class="fas ${icon} me-1"></i> ${item.msg}</div>`;
                        });
                        logBox.html(htmlLog);
                    } else {
                        logBox.html('<div class="text-success"><i class="fas fa-check"></i> Processamento concluído sem logs detalhados.</div>');
                    }
                    
                    // Render NFs no datatable
                    if (res.nfs && res.nfs.length > 0) {
                        res.nfs.forEach(nf => {
                            table.row.add([
                                `<b>${nf.nNF}</b>`,
                                `<span title="${nf.cnpj_emit}">${nf.nome_emit}</span>`,
                                nf.dhEmi.split('-').reverse().join('/'),
                                nf.qtd_itens,
                                formatMoney(nf.v_prod),
                                formatMoney(nf.v_pis),
                                formatMoney(nf.v_cofins),
                                formatMoney(nf.v_icms),
                                formatMoney(nf.v_ipi),
                                `<b>${formatMoney(nf.v_nf)}</b>`
                            ]);
                        });
                        table.draw();
                        $('#resultadoWrapper').removeClass('d-none');
                        
                        // Atualiza uniqid para download
                        lastUniqid = res.session_id;
                        $('#btnExportExcel').attr('href', `api/export_excel.php?session_id=${lastUniqid}`);
                    }

                    if(res.processadas > 0) {
                        Swal.fire('Sucesso!', `${res.processadas} notas fiscais processadas corretamente.`, 'success');
                    } else {
                        Swal.fire('Atenção', 'O arquivo foi lido mas nenhuma nota nova foi validadada.', 'info');
                    }
                    
                } else {
                    logBox.html(`<div class="text-danger fw-bold"><i class="fas fa-times"></i> Erro: ${res.error}</div>`);
                    Swal.fire('Erro', res.error || 'Ocorreu um erro no processamento do arquivo', 'error');
                }
            },
            error: function(xhr, status, error) {
                $('#progressBarContainer').hide();
                $btn.html('<i class="fas fa-cogs me-1"></i> Processar NF-e').prop('disabled', false);
                logBox.html(`<div class="text-danger fw-bold"><i class="fas fa-server"></i> Erro de Comunicação (HTTP ${xhr.status})<br>${xhr.responseText || ''}</div>`);
            }
        });
    });

    const formatMoney = (val) => {
        val = parseFloat(val) || 0;
        return 'R$ ' + val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

});
</script>
</body>
</html>
