<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
$pagina_ativa = 'consultar_cenarios';

require_once 'header.php';
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}

if (!isset($_GET['num'])) {
    $_SESSION['mensagem'] = 'Cenário não informado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: consultar_cenarios.php');
    exit();
}

$num_cenario = $_GET['num'];

try {
    // Buscar cabeçalho
    $stmt = $pdo->prepare("SELECT * FROM cot_cenarios_importacao WHERE num_cenario = :num");
    $stmt->execute([':num' => $num_cenario]);
    $cenario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cenario) {
        throw new Exception("Cenário não encontrado.");
    }

    // Buscar itens
    $stmtItens = $pdo->prepare("SELECT * FROM cot_cenarios_itens WHERE num_cenario = :num");
    $stmtItens->execute([':num' => $num_cenario]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    // Tentar recuperar ID de cliente se estiver faltando (correção de legado)
    foreach ($itens as &$item) {
        if (empty($item['id_cliente']) && !empty($item['cliente'])) {
            // Tenta buscar pelo nome exato
            $stmtCli = $pdo->prepare("SELECT id FROM cot_clientes WHERE razao_social = :nome LIMIT 1");
            $stmtCli->execute([':nome' => $item['cliente']]);
            $cliId = $stmtCli->fetchColumn();
            if ($cliId) {
                $item['id_cliente'] = $cliId;
            }
        }
    }
    unset($item); // quebrar referência

} catch (Exception $e) {
    $_SESSION['mensagem'] = 'Erro: ' . $e->getMessage();
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: consultar_cenarios.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cenário #<?= htmlspecialchars($num_cenario) ?></title>
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
            <h2 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Cenário #<?= htmlspecialchars($num_cenario) ?></h2>
            <a href="consultar_cenarios.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
        </div>

        <form method="POST" action="atualizar_cenario.php" id="formCenario">
            <input type="hidden" name="num_cenario" value="<?= htmlspecialchars($num_cenario) ?>">

            <!-- Cabeçalho -->
            <div class="form-section">
                <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Informações Gerais</h5>
                <div class="row g-3">
                    <!-- Fornecedor -->
                    <div class="col-md-5">
                        <label class="form-label">Fornecedor <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="hidden" name="id_fornecedor" id="id_fornecedor"
                                value="<?= htmlspecialchars($cenario['id_fornecedor']) ?>" required>
                            <input type="text" name="fornecedor" id="fornecedor" class="form-control"
                                value="<?= htmlspecialchars($cenario['fornecedor']) ?>" readonly required>
                            <button class="btn btn-outline-secondary" type="button" onclick="abrirModalFornecedor()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Data -->
                    <div class="col-md-2">
                        <label class="form-label">Data</label>
                        <input type="date" name="data" id="data" class="form-control"
                            value="<?= htmlspecialchars($cenario['data_criacao']) ?>" required>
                    </div>

                    <!-- Criado por -->
                    <div class="col-md-2">
                        <label class="form-label">Criado por</label>
                        <input type="text" name="criado_por" class="form-control"
                            value="<?= htmlspecialchars($cenario['criado_por']) ?>" readonly>
                    </div>

                    <!-- Campos Ocultos (Dolar) -->
                    <input type="hidden" name="dolar_compra" id="dolar_compra"
                        value="<?= htmlspecialchars($cenario['dolar_compra']) ?>">
                    <input type="hidden" name="dolar_venda" id="dolar_venda"
                        value="<?= htmlspecialchars($cenario['dolar_venda']) ?>">

                    <!-- Observações -->
                    <div class="col-12 mt-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control"
                            rows="2"><?= htmlspecialchars($cenario['observacoes']) ?></textarea>
                    </div>

                </div>
            </div>

            <!-- Container de Cenários (Blocos) -->
            <div id="cenarios-container">
            </div>

            <div class="text-center mb-4">
                <button type="button" class="btn btn-lg btn-outline-primary" onclick="adicionarBlocoCenario()">
                    <i class="fas fa-plus-circle me-2"></i> Adicionar Novo Cenário
                </button>
            </div>

            <div class="text-center mt-4 mb-5">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i> Atualizar Cenário
                </button>
            </div>
        </form>
    </div>

    <!-- Modais (Mantidos) -->
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
                        <tbody id="listaFornecedores"></tbody>
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
                        <tbody id="listaClientes"></tbody>
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
                        <tbody id="listaProdutos"></tbody>
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
                    <button type="button" class="btn btn-danger" id="btnConfirmarExclusao">Excluir_</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- item_template.js não é mais necessário pois o cenario_script.js gera o HTML -->
    <!-- <script src="item_template.js?v=<?= time() ?>"></script> -->
    <script src="cenario_script.js?v=<?= time() ?>"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Inicializar itens do PHP
            const itensDB = <?= json_encode($itens) ?>;

            // Agrupar itens por sub-cenário (nome)
            // Se nome_sub_cenario for vazio, agrupa em "Cenário Principal" ou similar
            const grupos = {};

            // Ordem de inserção importa
            const nomesOrdenados = [];

            if (itensDB.length === 0) {
                // Se não tiver itens, cria um bloco vazio padrão
                adicionarBlocoCenario();
                return;
            }

            itensDB.forEach(item => {
                let nome = item.nome_sub_cenario || 'Cenário 1';
                if (!grupos[nome]) {
                    grupos[nome] = [];
                    nomesOrdenados.push(nome);
                }
                grupos[nome].push(item);
            });

            // Para cada grupo, criar um bloco e adicionar os itens
            nomesOrdenados.forEach((nomeGrupo, indexGrupo) => {
                // Criar o bloco
                adicionarBlocoCenario();

                // O último bloco adicionado é o atual. 
                // blockIdCounter é global no script.js e incrementa toda vez
                // Entao o ID é `cenario-block-${blockIdCounter}`
                const blockId = `cenario-block-${blockIdCounter}`;
                const blockEl = document.getElementById(blockId);

                // Configurar cabeçalho do bloco com dados do primeiro item do grupo
                const primeiroItem = grupos[nomeGrupo][0];

                if (blockEl) {
                    blockEl.querySelector('.block-nome').value = nomeGrupo;
                    blockEl.querySelector('.block-modal').value = primeiroItem.modal || 'Aéreo';
                    blockEl.querySelector('.block-taxa').value = primeiroItem.taxa_juros_mensal || 3.00;
                }

                // Limpar o item "default" que o adicionarBlocoCenario cria automaticamente
                // (Opcional: podemos usar ele para preencher o primeiro, mas acho mais limpo limpar e adicionar os do banco)
                const containerItens = blockEl.querySelector('.itens-lista-container');
                containerItens.innerHTML = '';

                // Adicionar itens do grupo
                grupos[nomeGrupo].forEach(item => {
                    adicionarItem(blockId, false);

                    // Pegar a linha recém criada (última do container)
                    const row = containerItens.lastElementChild;

                    // Preencher campos
                    // Precisamos saber o índice correto. Como adicionamos com adicionarItem, o itemIndex global incrementou.
                    // Podemos buscar inputs pelo name ou classe.

                    row.querySelector('.item-id-cliente').value = item.id_cliente;
                    row.querySelector('.item-cliente').value = item.cliente;
                    row.querySelector('.item-uf').value = item.uf;

                    row.querySelector('.item-codigo').value = item.codigo_produto;
                    row.querySelector('.item-produto').value = item.produto;
                    row.querySelector('.item-qtd').value = item.qtd;
                    row.querySelector('.item-unidade').value = item.unidade;

                    row.querySelector('.item-landed').value = item.landed_usd_kg;
                    row.querySelector('.item-preco-venda').value = item.preco_unit_venda_usd_kg;

                    // Embalagem
                    const embalagemInput = row.querySelector('.item-embalagem');
                    if (embalagemInput) {
                        embalagemInput.value = item.embalagem || '';
                    }

                    // Data Necessidade
                    const deadlineInput = row.querySelector('.item-deadline');
                    if (deadlineInput && item.data_necessidade) {
                        deadlineInput.value = item.data_necessidade;
                    }

                    // Necessidade Cliente
                    const necessidadeInput = row.querySelector('.item-necessidade');
                    if (necessidadeInput) {
                        necessidadeInput.value = item.necessidade_cliente || '';
                    }

                    // Tempo/Previsão
                    const tempoInput = row.querySelector('input[title="Meses para venda"]'); // O input visível
                    if (tempoInput) {
                        tempoInput.value = item.tempo_venda_meses;
                    }
                    row.querySelector('.item-tempo').value = item.tempo_venda_meses;

                    // Tipo Demanda
                    const tipoInput = row.querySelector('.item-tipo-demanda');
                    if (tipoInput && item.tipo_demanda) {
                        tipoInput.value = item.tipo_demanda;
                    }

                    // Spec Exclusiva
                    if (parseInt(item.spec_exclusiva) === 1) {
                        const specCheck = row.querySelector('input[type="checkbox"]');
                        if (specCheck) specCheck.checked = true;
                    }

                    // Recalcular
                    calcularItem(row.querySelector('.item-qtd'));
                });

                // Forçar atualização dos ocultos do bloco
                atualizarItensOcultos(blockId);
                recalcularBloco(blockId);
            });
        });
    </script>
</body>

</html>