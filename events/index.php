<?php
define('PAGE_TITLE', 'Dashboard');
require_once 'header.php';
?>

<div class="container my-5">

    <!-- Cards de Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card total-geral h-100 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light p-3 rounded-circle me-3 text-success">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                    <div>
                        <div class="text-uppercase small text-muted fw-bold">Total Geral</div>
                        <div class="fs-4 fw-bold text-dark" id="totalGeral">R$ 0,00</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card total-passagens h-100 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light p-3 rounded-circle me-3 text-primary">
                        <i class="bi bi-airplane-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="text-uppercase small text-muted fw-bold">Passagens Aéreas</div>
                        <div class="fs-5 fw-bold text-dark" id="totalPassagens">R$ 0,00</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card total-hoteis h-100 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light p-3 rounded-circle me-3" style="color: #8b5cf6;">
                        <i class="bi bi-building-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="text-uppercase small text-muted fw-bold">Hotéis</div>
                        <div class="fs-5 fw-bold text-dark" id="totalHoteis">R$ 0,00</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card total-seguros h-100 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light p-3 rounded-circle me-3 text-info">
                        <i class="bi bi-car-front-fill fs-4" style="color: #0dcaf0;"></i>
                    </div>
                    <div>
                        <div class="text-uppercase small text-muted fw-bold">Transporte</div>
                        <div class="fs-5 fw-bold text-dark" id="totalTransporte">R$ 0,00</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 text-secondary"><i class="bi bi-pie-chart"></i> Distribuição por Categoria</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 text-secondary"><i class="bi bi-people"></i> Top 5 Passageiros</h5>
                </div>
                <div class="card-body">
                    <canvas id="clientsChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Despesas Detalhadas -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 text-secondary"><i class="bi bi-list-ul"></i> Despesas Detalhadas</h5>
                </div>
                <div class="card-body">

                    <!-- Filtros -->
                    <div class="card mb-3 bg-light border-0">
                        <div class="card-body p-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <span class="fw-bold text-secondary small">Filtrar:</span>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Categoria</label>
                                    <select class="form-select form-select-sm" id="filterCategoria">
                                        <option value="">Todas as Categorias</option>
                                        <option value="Passagem Aérea">Passagem Aérea</option>
                                        <option value="Hotel">Hotel</option>
                                        <option value="Seguro">Seguro</option>
                                        <option value="Transporte">Transporte</option>
                                        <option value="Outros">Outros</option>
                                        <option value="Não Categorizado">Não Categorizado</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Data Início</label>
                                    <input type="date" class="form-control form-control-sm" id="filterDataInicio">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Data Fim</label>
                                    <input type="date" class="form-control form-control-sm" id="filterDataFim">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Passageiro</label>
                                    <input type="text" class="form-control form-control-sm" id="filterPassageiro"
                                        placeholder="Nome">
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-sm btn-primary" onclick="loadExpenses()">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0" style="font-size: 0.875rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Passageiro</th>
                                    <th>Evento/Visita</th>
                                    <th>Fatura</th>
                                    <th>Produto/Rota</th>
                                    <th>Categoria</th>
                                    <th>Total</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="expensesTableBody">
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="bi bi-hourglass-split"></i> Carregando despesas...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal de Edição de Categoria -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Categoria</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editExpenseId">
                <div class="mb-3">
                    <label class="form-label fw-bold">Produto:</label>
                    <p id="editProductName" class="form-control-plaintext bg-light p-2 rounded"></p>
                </div>
                <div class="mb-3">
                    <label for="editCategory" class="form-label fw-bold">Nova Categoria:</label>
                    <select class="form-select" id="editCategory">
                        <option value="Passagem Aérea">Passagem Aérea</option>
                        <option value="Hotel">Hotel</option>
                        <option value="Seguro">Seguro</option>
                        <option value="Transporte">Transporte</option>
                        <option value="Outros">Outros</option>
                        <option value="Não Categorizado">Não Categorizado</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveCategory()">
                    <i class="bi bi-check"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Edição de Evento/Visita -->
<div class="modal fade" id="editEventoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Detalhes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editEventoExpenseId">
                <div class="mb-3">
                    <label class="form-label fw-bold">Produto/Rota:</label>
                    <textarea class="form-control" id="editEventoProduto" rows="2"
                        placeholder="Descrição do produto ou rota"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="editEventoData" class="form-label fw-bold">Data:</label>
                        <input type="date" class="form-control" id="editEventoData">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="editEventoValor" class="form-label fw-bold">Valor Total (R$):</label>
                        <input type="number" class="form-control" id="editEventoValor" step="0.01">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="editEventoPassageiro" class="form-label fw-bold">Passageiro:</label>
                    <input type="text" class="form-control" id="editEventoPassageiro" placeholder="Nome do passageiro">
                </div>
                <div class="mb-3">
                    <label for="editEventoVisita" class="form-label fw-bold">Evento ou Visita:</label>
                    <input type="text" class="form-control" id="editEventoVisita"
                        placeholder="Ex: Feira Internacional 2026, Visita Cliente XYZ">
                    <div class="form-text">
                        <i class="bi bi-info-circle"></i> Descreva o evento ou visita relacionado a esta despesa
                    </div>
                </div>
                <div class="mb-3">
                    <label for="editFatura" class="form-label fw-bold">Número da Fatura:</label>
                    <input type="text" class="form-control" id="editFatura" placeholder="Ex: FAT-1234, INV-9876">
                    <div class="form-text">
                        <i class="bi bi-receipt"></i> Associe manualmente a uma fatura se necessário
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-info" onclick="saveEvento()">
                    <i class="bi bi-check"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="js/dashboard.js?v=<?= $version ?>"></script>

<?php require_once 'footer.php'; ?>