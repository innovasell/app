<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
$pagina_ativa = 'incluir_cenario';

require_once 'header.php';
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Cenário de Importação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .item-row {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }

        .calculated-field {
            background-color: #e9ecef !important;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-globe-americas me-2"></i>Novo Cenário de Importação</h2>
        </div>

        <form method="POST" action="salvar_cenario.php" id="formCenario">
            <!-- Seção de Cabeçalho -->
            <div class="form-section">
                <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Informações Gerais</h5>
                <div class="row g-3">
                    <!-- Fornecedor -->
                    <div class="col-md-4">
                        <label class="form-label">Fornecedor <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="hidden" name="id_fornecedor" id="id_fornecedor" required>
                            <input type="text" name="fornecedor" id="fornecedor" class="form-control" readonly required>
                            <button class="btn btn-outline-secondary" type="button" onclick="abrirModalFornecedor()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Data -->
                    <div class="col-md-2">
                        <label class="form-label">Data</label>
                        <input type="date" name="data" id="data" class="form-control" required>
                    </div>

                    <!-- Criado por (oculto) -->
                    <input type="hidden" name="criado_por"
                        value="<?= strtoupper($_SESSION['representante_nome'] ?? '') ?>">

                    <!-- Dólar ocultos -->
                    <input type="hidden" name="dolar_compra" id="dolar_compra" value="">
                    <input type="hidden" name="dolar_venda" id="dolar_venda" value="">

                    <!-- Observações -->
                    <div class="col-12">
                        <label class="form-label">Observações Gerais</label>
                        <textarea name="observacoes" class="form-control" rows="2"
                            placeholder="Observações válidas para todo o documento..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Container de Cenários (Blocos) -->
            <div id="cenarios-container">
                <!-- Os blocos de cenário serão inseridos aqui via JS -->
            </div>

            <div class="text-center mb-4">
                <button type="button" class="btn btn-lg btn-outline-primary" onclick="adicionarBlocoCenario()">
                    <i class="fas fa-plus-circle me-2"></i> Adicionar Novo Cenário
                </button>
            </div>

            <!-- Botão Salvar -->
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-save me-2"></i> Salvar Cenário +
                </button>
            </div>
        </form>
    </div>

    <!-- Modal Fornecedor -->
    <div class="modal fade" id="modalFornecedores" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Selecionar Fornecedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="buscaFornecedor" class="form-control mb-3"
                        placeholder="Buscar fornecedor...">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>País</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody id="listaFornecedores">
                            <tr>
                                <td colspan="3" class="text-center">Carregando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Cliente -->
    <div class="modal fade" id="modalClientes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Selecionar Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="buscaCliente" class="form-control mb-3"
                        placeholder="Buscar cliente ou UF...">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente</th>
                                <th>UF</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody id="listaClientes">
                            <tr>
                                <td colspan="3" class="text-center">Digite para buscar...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Produto -->
    <div class="modal fade" id="modalProdutos" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Selecionar Produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="buscaProduto" class="form-control mb-3"
                        placeholder="Buscar por nome, código ou NCM...">
                    <button class="btn btn-outline-primary btn-sm" type="button"
                        onclick="buscarProdutos()">Pesquisar</button>
                    <table class="table table-hover mt-2">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Produto</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody id="listaProdutos">
                            <tr>
                                <td colspan="3" class="text-center">Digite e clique em Pesquisar...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmação Exclusão -->
    <div class="modal fade" id="modalConfirmarExclusao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja remover este cenário completo? Esta ação não pode ser desfeita.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarExclusao">Excluir</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="item_template.js?v=<?= time() ?>"></script>
    <script src="cenario_script.js?v=<?= time() ?>"></script>


</body>

</html>