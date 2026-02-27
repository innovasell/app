<?php
define('PAGE_TITLE', 'Relatórios');
require_once 'header.php';
?>

<div class="container my-5">

    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0"><i class="bi bi-file-earmark-text"></i> Relatórios de Despesas</h2>
            <p class="text-muted">Gere relatórios detalhados em PDF e envie por email</p>
        </div>
    </div>

    <!-- Relatório por Evento/Visita -->
    <div class="row mb-4">
        <div class="col-12 col-lg-10 mx-auto">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Relatório por Evento/Visita</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Liste todas as despesas relacionadas a um evento ou visita específica.</p>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="filterEvento" class="form-label fw-bold">Selecione o Evento/Visita:</label>
                            <select class="form-select form-select-lg" id="filterEvento">
                                <option value="">Carregando...</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-2">
                            <button class="btn btn-primary btn-lg flex-fill"
                                onclick="generateReport('evento', 'download')">
                                <i class="bi bi-file-pdf"></i> Gerar PDF
                            </button>
                            <button class="btn btn-outline-primary btn-lg flex-fill"
                                onclick="generateReport('evento', 'email')">
                                <i class="bi bi-envelope"></i> Enviar Email
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Relatório por Fatura -->
    <div class="row mb-4">
        <div class="col-12 col-lg-10 mx-auto">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-receipt"></i> Relatório por Fatura</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Liste todas as despesas de uma fatura específica.</p>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="filterFatura" class="form-label fw-bold">Selecione a Fatura:</label>
                            <select class="form-select form-select-lg" id="filterFatura">
                                <option value="">Carregando...</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-2">
                            <button class="btn btn-success btn-lg flex-fill"
                                onclick="generateReport('fatura', 'download')">
                                <i class="bi bi-file-pdf"></i> Gerar PDF
                            </button>
                            <button class="btn btn-outline-success btn-lg flex-fill"
                                onclick="generateReport('fatura', 'email')">
                                <i class="bi bi-envelope"></i> Enviar Email
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Relatório por Colaborador -->
    <div class="row mb-4">
        <div class="col-12 col-lg-10 mx-auto">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-person"></i> Relatório por Colaborador</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Liste todas as despesas de um colaborador (passageiro) específico.</p>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="filterColaborador" class="form-label fw-bold">Selecione o Colaborador:</label>
                            <select class="form-select form-select-lg" id="filterColaborador">
                                <option value="">Carregando...</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-2">
                            <button class="btn btn-warning btn-lg flex-fill"
                                onclick="generateReport('colaborador', 'download')">
                                <i class="bi bi-file-pdf"></i> Gerar PDF
                            </button>
                            <button class="btn btn-outline-warning btn-lg flex-fill"
                                onclick="generateReport('colaborador', 'email')">
                                <i class="bi bi-envelope"></i> Enviar Email
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    // Carrega os filtros ao carregar a página
    document.addEventListener('DOMContentLoaded', function () {
        loadReportFilters();
    });

    // Carrega opções de filtros
    async function loadReportFilters() {
        try {
            const response = await fetch('api/get_report_filters.php');
            const result = await response.json();

            if (result.success) {
                // Preenche evento/visita
                const eventosSelect = document.getElementById('filterEvento');
                eventosSelect.innerHTML = '<option value="">Selecione um evento/visita</option>';
                result.data.eventos.forEach(evento => {
                    if (evento && evento.trim()) {
                        const option = document.createElement('option');
                        option.value = evento;
                        option.textContent = evento;
                        eventosSelect.appendChild(option);
                    }
                });

                // Preenche faturas
                const faturasSelect = document.getElementById('filterFatura');
                faturasSelect.innerHTML = '<option value="">Selecione uma fatura</option>';
                result.data.faturas.forEach(fatura => {
                    if (fatura && fatura.trim()) {
                        const option = document.createElement('option');
                        option.value = fatura;
                        option.textContent = fatura;
                        faturasSelect.appendChild(option);
                    }
                });

                // Preenche colaboradores
                const colaboradoresSelect = document.getElementById('filterColaborador');
                colaboradoresSelect.innerHTML = '<option value="">Selecione um colaborador</option>';
                result.data.colaboradores.forEach(colaborador => {
                    if (colaborador && colaborador.trim()) {
                        const option = document.createElement('option');
                        option.value = colaborador;
                        option.textContent = colaborador;
                        colaboradoresSelect.appendChild(option);
                    }
                });
            } else {
                alert('Erro ao carregar filtros: ' + result.message);
            }
        } catch (error) {
            console.error('Erro ao carregar filtros:', error);
            alert('Erro ao carregar opções de filtros.');
        }
    }

    // Gera relatório
    function generateReport(tipo, acao) {
        let valor = '';

        if (tipo === 'evento') {
            valor = document.getElementById('filterEvento').value;
        } else if (tipo === 'fatura') {
            valor = document.getElementById('filterFatura').value;
        } else if (tipo === 'colaborador') {
            valor = document.getElementById('filterColaborador').value;
        }

        if (!valor) {
            alert('Por favor, selecione um filtro antes de gerar o relatório.');
            return;
        }

        const url = `reports/generate_pdf.php?tipo=${encodeURIComponent(tipo)}&valor=${encodeURIComponent(valor)}&acao=${acao}`;

        if (acao === 'download') {
            // Abre o PDF em nova aba
            window.open(url, '_blank');
        } else if (acao === 'email') {
            // Envia por email e mostra confirmação
            fetch(url)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Email enviado com sucesso para: ' + result.email);
                    } else {
                        alert('Erro ao enviar email: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao enviar email.');
                });
        }
    }
</script>

<?php require_once 'footer.php'; ?>