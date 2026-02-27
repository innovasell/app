<?php
session_start();
date_default_timezone_set('America/Sao_Paulo'); // Define fuso horário
// !!! DEFINA A PÁGINA ATIVA AQUI !!!
$pagina_ativa = 'incluir_orcamento'; // Exemplo para consultar_amostras.php
// Use: 'incluir_amostra' para incluir_ped_amostras.php
// Use: 'gerenciar_cliente' para gerenciar_cliente.php
// Use: 'incluir_orcamento' para incluir_orcamento.php
// Use: 'filtrar' para filtrar.php
// Use: 'consultar_orcamentos' para consultar_orcamentos.php
// Use: 'previsao' para previsao.php

require_once 'header.php'; // Inclui o header
require_once 'conexao.php'; // Conexão PDO

if (!isset($_SESSION['representante_email'])) {
  header('Location: index.html');
  exit();
}

$select_disponibilidade = '<select name="itens[${index}][disponibilidade]" class="form-select" required>';
$select_disponibilidade .= '<option value="">Selecione</option>';
$disp = $pdo->query("SELECT prazo FROM cot_disponibilidade")->fetchAll(PDO::FETCH_ASSOC);
foreach ($disp as $d) {
  $prazo = htmlspecialchars($d['prazo']);
  $select_disponibilidade .= "<option value=\"$prazo\">$prazo</option>";
}
$select_disponibilidade .= '</select>';

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Incluir Orçamento</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .form-section {
      background-color: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .remove-item {
      cursor: pointer;
      color: red;
      font-weight: bold;
    }

    .custom-remove-btn {
      background-color: #dc3545;
      /* vermelho Bootstrap */
      color: white;
      border: 1px solid #dc3545;
      transition: all 0.2s ease-in-out;
    }

    .custom-remove-btn:hover {
      background-color: white;
      color: #dc3545;
    }

    /* Highlight para navegação via teclado */
    .table-active-custom {
      background-color: #0d6efd !important;
      color: white !important;
    }

    .table-active-custom td {
      color: white !important;
      background-color: #0d6efd !important;
      /* Força sobre o striped */
    }
  </style>
</head>

<body>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0">Novo Orçamento</h2>


    </div>

    <form method="POST" action="salvar_orcamento.php">
      <div class="row mb-4">
        <!-- Card: Informações Gerais -->
        <div class="col-md-12 mb-3">
          <div class="card shadow-sm border-0 border-start border-5 border-primary">
            <div class="card-body">
              <h5 class="card-title text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Informações Gerais</h5>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small fw-bold text-muted">CLIENTE</label>
                  <div class="input-group">
                    <input type="text" name="cliente" id="cliente" class="form-control" readonly required
                      placeholder="Selecione um cliente..." style="background-color: #f8f9fa;">
                    <input type="hidden" name="cnpj" id="cnpj">
                    <button class="btn btn-primary" type="button" onclick="abrirModalCliente()"><i
                        class="fas fa-search"></i></button>
                  </div>
                </div>
                <div class="col-md-2">
                  <label class="form-label small fw-bold text-muted">UF</label>
                  <input type="text" name="uf" id="uf" class="form-control bg-light text-center fw-bold" readonly
                    required>
                </div>
                <div class="col-md-2">
                  <label for="data" class="form-label small fw-bold text-muted">DATA</label>
                  <input type="date" id="data" name="data" class="form-control" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label small fw-bold text-muted">COTADO POR</label>
                  <input type="text" name="cotado_por" class="form-control bg-light"
                    value="<?= strtoupper(trim(($_SESSION['representante_nome'] ?? '') . ' ' . ($_SESSION['representante_sobrenome'] ?? ''))) ?>"
                    readonly required>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Card: Configurações -->
        <div class="col-md-12 mb-3">
          <div class="card shadow-sm border-0 border-start border-5 border-secondary">
            <div class="card-body">
              <h5 class="card-title text-secondary mb-3"><i class="fas fa-cog me-2"></i>Configurações & Valores</h5>
              <div class="row g-3 align-items-end">
                <div class="col-md-3">
                  <label class="form-label small fw-bold text-success">DÓLAR PTAX BDB</label>
                  <div class="input-group">
                    <span class="input-group-text bg-success text-white border-success">R$</span>
                    <input type="text" name="dolar" id="dolar" class="form-control border-success fw-bold"
                      inputmode="decimal" pattern="[0-9]+([,\.][0-9]+)?" required>
                  </div>
                </div>
                <div class="col-md-3">
                  <label class="form-label small fw-bold text-muted">SUFRAMA?</label>
                  <select name="suframa" class="form-select" required>
                    <option value="">Selecione</option>
                    <option value="Sim">Sim</option>
                    <option value="Não" selected>Não</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small fw-bold text-muted">SUSPENSÃO IPI?</label>
                  <select name="suspensao_ipi" class="form-select" required>
                    <option value="">Selecione</option>
                    <option value="Sim">Sim</option>
                    <option value="Não" selected>Não</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small fw-bold text-muted">INCLUIR PREÇO NET?</label>
                  <select name="incluir_net" id="incluir_net" class="form-select" required>
                    <option value="false">Não</option>
                    <option value="true">Sim</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Card: Observações -->
        <div class="col-md-12">
          <div class="form-floating">
            <textarea name="observacoes" class="form-control" placeholder="Deixe uma observação aqui"
              id="floatingTextarea2" style="height: 100px"></textarea>
            <label for="floatingTextarea2">Observações Gerais do Orçamento</label>
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5>Itens do Orçamento</h5>
          <div>
          </div>
        </div>
        <div id="itens-container"></div>

      </div>

      <div class="text-center mt-3">
        <button type="submit" class="btn btn-success" id="btnSalvar">
          Salvar Orçamento
        </button>
        <button type="button" class="btn btn-outline-secondary ms-2" onclick="abrirModalMassa()">
          <i class="fas fa-list"></i> Adicionar em Massa
        </button>
        <button type="button" class="btn btn-outline-primary ms-2" onclick="adicionarItem()">
          + Adicionar Item
        </button>
        <div id="loading" class="spinner-border text-primary" role="status" style="display:none;"></div>
        <div> </div>
      </div>
    </form>
  </div>

  <div class="modal fade" id="modalClientes" tabindex="-1" aria-labelledby="modalClientesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Selecionar Cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <input type="text" id="buscaCliente" class="form-control mb-3" placeholder="Buscar cliente ou UF...">
          <table class="table table-bordered">
            <thead>
              <tr>
                <th>Cliente</th>
                <th>CNPJ</th>
                <th>UF</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody id="listaClientes">
              <tr>
                <td colspan="3">Digite para buscar...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>


  <!-- Modal de Seleção de Produto -->
  <div class="modal fade" id="modalProdutos" tabindex="-1" aria-labelledby="modalProdutosLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalProdutosLabel">Selecionar Produto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <input type="text" id="buscaProduto" class="form-control" placeholder="Buscar por nome, código ou NCM...">
          <button class="btn btn-outline-primary" type="button" onclick="buscarProdutos()">Pesquisar</button>
          <table class="table table-bordered" id="tabelaProdutos">
            <thead>
              <tr>
                <th>Código</th>
                <th>Produto</th>
                <th>Unidade</th>
                <th>Origem</th>
                <th>NCM</th>
                <th>IPI %</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $produtos = $pdo->query("SELECT * FROM cot_estoque WHERE ativo = 1 ORDER BY produto ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
              foreach ($produtos as $p): ?>
                <tr>
                  <td><?= htmlspecialchars($p['codigo']) ?></td>
                  <td><?= htmlspecialchars($p['produto']) ?></td>
                  <td><?= htmlspecialchars($p['unidade']) ?></td>
                  <td><?= htmlspecialchars($p['origem']) ?></td>
                  <td><?= htmlspecialchars($p['ncm']) ?></td>
                  <td><?= htmlspecialchars($p['ipi']) ?></td>
                  <td>
                    <button type="button" class="btn btn-sm btn-primary"
                      onclick='selecionarProduto(<?= json_encode($p) ?>)'>Selecionar</button>
                  </td>
                </tr>
              <?php endforeach; ?>

            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  </div>
  </div>

  <!-- Modal Adicionar em Massa -->
  <div class="modal fade" id="modalMassa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Adicionar Itens em Massa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">Cole os códigos dos produtos abaixo (um por linha). Os itens serão adicionados ao
            orçamento, restando preencher quantidade e preço.</p>
          <textarea id="codesMassa" class="form-control" rows="10"
            placeholder="COD123&#10;COD456&#10;COD789"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" onclick="processarCodesMassa()">
            <i class="fas fa-check"></i> Processar Itens
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast Container -->
  <div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="ploomesToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header" id="ploomesToastHeader">
            <strong class="me-auto" id="ploomesToastTitle">Ploomes</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="ploomesToastBody"></div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Checar parâmetros de URL para feedback do Ploomes
        const urlParams = new URLSearchParams(window.location.search);
        const ploomesStatus = urlParams.get('ploomes_status');
        const ploomesMsg = urlParams.get('ploomes_msg');

        if (ploomesStatus) {
            const toastEl = document.getElementById('ploomesToast');
            const toastTitle = document.getElementById('ploomesToastTitle');
            const toastHeader = document.getElementById('ploomesToastHeader');
            const toastBody = document.getElementById('ploomesToastBody');
            
            if (ploomesStatus === 'success') {
                toastHeader.classList.add('bg-success', 'text-white');
                toastTitle.textContent = 'Integração Ploomes';
                toastBody.textContent = 'Interação registrada com sucesso!';
            } else if (ploomesStatus === 'warning') {
                toastHeader.classList.add('bg-warning', 'text-dark');
                toastTitle.textContent = 'Aviso Ploomes';
                toastBody.textContent = ploomesMsg || 'Atenção: Cliente não encontrado ou status incerto.';
            } else if (ploomesStatus === 'error') {
                toastHeader.classList.add('bg-danger', 'text-white');
                toastTitle.textContent = 'Erro Ploomes';
                toastBody.textContent = ploomesMsg || 'Erro ao conectar com o Ploomes.';
            }

            const toast = new bootstrap.Toast(toastEl, { delay: 6000 });
            toast.show();
            
            // Limpar URL
            const url = new URL(window.location);
            url.searchParams.delete('ploomes_status');
            url.searchParams.delete('ploomes_msg');
            window.history.replaceState({}, document.title, url);
        }
    });
    function adicionarItem() {
      const container = document.getElementById('itens-container');
      const index = container.children.length;

      const html = `
    <div class="card mb-3 shadow-sm border item-row" data-index="${index}" style="border-left: 5px solid #0d6efd !important;">
        <!-- Header -->
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
            <span class="badge bg-primary rounded-pill">Item #${index + 1}</span>
            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('.item-row').remove()">
                <i class="fas fa-trash-alt"></i> Remover
            </button>
        </div>

        <div class="card-body p-3">
             <!-- Section 1: Product Details -->
             <div class="row g-2 mb-3">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">CÓDIGO</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="itens[${index}][codigo]" class="form-control codigo-input item-codigo fw-bold" readonly required>
                        <button class="btn btn-outline-secondary" type="button" title="Buscar Produto" onclick="abrirModalProduto(this)">🔍</button>
                        <button class="btn btn-outline-info btn-historico-preco" type="button" onclick="abrirHistorico(this)" title="Ver Histórico" disabled>🕒</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">PRODUTO</label>
                    <input type="text" name="itens[${index}][produto]" class="form-control form-control-sm item-produto bg-light" readonly required>
                </div>
                <div class="col-md-2">
                     <label class="form-label small fw-bold text-muted mb-1">UNIDADE</label>
                     <input type="text" name="itens[${index}][unidade]" class="form-control form-control-sm bg-light text-center" readonly required>
                </div>
                <div class="col-md-2">
                     <label class="form-label small fw-bold text-muted mb-1">ORIGEM</label>
                     <input id="origem" type="text" name="itens[${index}][origem]" class="form-control form-control-sm bg-light text-center" readonly required>
                </div>
                <div class="col-md-2">
                     <label class="form-label small fw-bold text-muted mb-1">NCM</label>
                     <input type="text" name="itens[${index}][ncm]" class="form-control form-control-sm bg-light text-center" readonly required>
                </div>
             </div>

             <!-- Section 2: Logistics (Blue) -->
             <div class="p-2 mb-3 rounded" style="background-color: #e3f2fd;">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-primary mb-1">QUANTIDADE</label>
                        <input type="text" name="itens[${index}][volume]" class="form-control form-control-sm only-numbers border-primary text-center fw-bold" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-primary mb-1">EMBALAGEM</label>
                        <input type="text" name="itens[${index}][embalagem]" class="form-control form-control-sm only-numbers border-primary text-center" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-primary mb-1">DISPONIBILIDADE</label>
                        <select name="itens[${index}][disponibilidade]" class="form-select form-select-sm border-primary" required>
                            <option value="">Selecione</option>
                            <option value="IMEDIATA">IMEDIATA</option>
                            <option value="LEAD-TIME - 7 DIAS">LEAD-TIME - 7 DIAS</option>
                            <option value="LEAD-TIME - 15 DIAS">LEAD-TIME - 15 DIAS</option>
                            <option value="LEAD-TIME - 20 DIAS">LEAD-TIME - 20 DIAS</option>
                            <option value="LEAD-TIME - 25 DIAS">LEAD-TIME - 25 DIAS</option>
                            <option value="LEAD-TIME - 30 DIAS">LEAD-TIME - 30 DIAS</option>
                            <option value="LEAD-TIME - 35 DIAS">LEAD-TIME - 35 DIAS</option>
                            <option value="LEAD-TIME - 40 DIAS">LEAD-TIME - 40 DIAS</option>
                            <option value="LEAD-TIME - 45 DIAS">LEAD-TIME - 45 DIAS</option>
                            <option value="LEAD-TIME - 50 DIAS">LEAD-TIME - 50 DIAS</option>
                            <option value="LEAD-TIME - 55 DIAS">LEAD-TIME - 55 DIAS</option>
                            <option value="LEAD-TIME - 60 DIAS">LEAD-TIME - 60 DIAS</option>
                            <option value="LEAD-TIME - 65 DIAS">LEAD-TIME - 65 DIAS</option>
                            <option value="LEAD-TIME - 70 DIAS">LEAD-TIME - 70 DIAS</option>
                            <option value="LEAD-TIME - 75 DIAS">LEAD-TIME - 75 DIAS</option>
                            <option value="LEAD-TIME - 80 DIAS">LEAD-TIME - 80 DIAS</option>
                            <option value="LEAD-TIME - 85 DIAS">LEAD-TIME - 85 DIAS</option>
                            <option value="LEAD-TIME - 90 DIAS">LEAD-TIME - 90 DIAS</option>
                            <option value="LEAD-TIME - 120 DIAS">LEAD-TIME - 120 DIAS</option>
                            <option value="PROC IMPORTAÇÃO">PROC IMPORTAÇÃO</option>
                        </select>
                    </div>
                </div>
             </div>

             <!-- Section 3: Pricing (Green) -->
             <div class="p-2 rounded border" style="background-color: #f1f8e9; border-color: #8bc34a !important;">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-success mb-1">IPI %</label>
                        <input type="text" name="itens[${index}][ipi]" class="form-control form-control-sm border-success text-center" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-success mb-1">ICMS %</label>
                        <input type="text" name="itens[${index}][icms]" class="form-control form-control-sm border-success text-center" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-success mb-1">PREÇO NET</label>
                        <div class="input-group input-group-sm">
                             <input type="text" name="itens[${index}][preco_net]" class="form-control border-success fw-bold text-end" oninput="calcularPrecoFull(this.closest('.item-row'))" required>
                             <button class="btn btn-success" type="button" onclick="verPrecoLista(this)" title="Ver Price List" style="font-size: 0.7rem;">Price List</button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-success mb-1">PREÇO FULL</label>
                        <input type="text" name="itens[${index}][preco_full]" class="form-control form-control-sm border-success fw-bold text-end preco_full" required>
                        <small class="aviso_preco_full position-absolute bg-white border border-danger text-danger rounded px-2 mt-1 shadow-sm" style="display: none; z-index: 10;">
                            ⚠️ Edição manual não recomendada
                        </small>
                    </div>
                    <div class="col-md-2 text-end">
                        <small class="text-muted d-block" style="font-size: 0.65rem;">Cálculo automático</small>
                    </div>
                </div>
             </div>

             <!-- Section 4: Price List Validation (Gray) -->
             <div class="p-2 rounded border mt-2 bg-light">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">PREÇO LISTA (USD)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted border-secondary">USD</span>
                            <input type="text" name="itens[${index}][valor_price_list]" class="form-control border-secondary fw-bold text-muted bg-light text-end valor-price-list" readonly title="Valor Oficial da Tabela de Preços">
                            <span class="input-group-text bg-white border-secondary validation-icon" style="display:none;" data-bs-toggle="tooltip" data-bs-placement="top" title="Embalagem não encontrada na Price List"><i class="fas fa-exclamation-triangle text-warning"></i></span>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <small class="text-muted fst-italic validation-msg"></small>
                    </div>
                </div>
             </div>
        </div>
    </div>
    `;


      // Adiciona o evento de cálculo automático
      container.insertAdjacentHTML('beforeend', html);

      const novaLinha = container.lastElementChild;

      // Add event listener for packaging changes to trigger Price List check
      const embalagemInput = novaLinha.querySelector('[name*="[embalagem]"]');
      if (embalagemInput) {
        embalagemInput.addEventListener('change', function () {
          verificarPriceList(novaLinha);
        });
        // Also check on blur just in case
        embalagemInput.addEventListener('blur', function () {
          verificarPriceList(novaLinha);
        });
      }

      // Initialize tooltips
      var tooltipTriggerList = [].slice.call(novaLinha.querySelectorAll('[data-bs-toggle="tooltip"]'))
      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
      })

      // Adiciona o aviso ao focar no campo "preço full"
      const precoInput = novaLinha.querySelector('.preco_full');
      const aviso = novaLinha.querySelector('.aviso_preco_full');

      if (precoInput && aviso) {
        precoInput.addEventListener('focus', () => {
          aviso.style.display = 'inline';
        });

        precoInput.addEventListener('blur', () => {
          aviso.style.display = 'none';
        });
      }

      // Seus outros eventos continuam abaixo
      const netInput = novaLinha.querySelector('[name*="[preco_net]"]');
      const icmsInput = novaLinha.querySelector('[name*="[icms]"]');

      netInput.addEventListener('input', () => calcularPrecoFull(novaLinha));
      icmsInput.addEventListener('input', () => calcularPrecoFull(novaLinha));


    }

    let produtoBtnReferencia = null;
    let priceListRow = null; // Global variable for Price List modal context

    function abrirModalProduto(button) {
      produtoBtnReferencia = button.closest('.item-row');
      const modal = new bootstrap.Modal(document.getElementById('modalProdutos'));
      modal.show();
    }





    function selecionarProduto(produto) {
      if (!produtoBtnReferencia) return;

      produtoBtnReferencia.querySelector('[name*="[codigo]"]').value = produto.codigo;
      produtoBtnReferencia.querySelector('[name*="[produto]"]').value = produto.produto;
      produtoBtnReferencia.querySelector('[name*="[unidade]"]').value = produto.unidade;
      produtoBtnReferencia.querySelector('[name*="[origem]"]').value = produto.origem;
      produtoBtnReferencia.querySelector('[name*="[ncm]"]').value = produto.ncm;

      // Verifica se Suspensão de IPI está marcada como Sim
      const suspensaoIPI = document.querySelector('[name="suspensao_ipi"]').value === 'Sim';
      produtoBtnReferencia.querySelector('[name*="[ipi]"]').value = suspensaoIPI ? '0,00' : String(produto.ipi)
        .replace('.', ',');


      // Buscar alíquota com base na UF
      const uf = document.querySelector('input[name="uf"]').value;
      const ufUpper = uf.trim().toUpperCase();
      const origem = parseInt(produto.origem);
      const icmsInput = produtoBtnReferencia.querySelector('[name*="[icms]"]');

      // Se for SP, fixa 18% para QUALQUER origem (Importado ou Nacional)
      if (ufUpper === 'SP') {
        icmsInput.value = '18%';
      }
      // Se não for SP e for Importado (1, 2, 3, 8) -> Regra 4% (Interestadual Importado)
      // Origem 6 (CAMEX) NÃO entra aqui (exceção à regra 4%), caindo na regra geral (7% ou 12%)
      else if (ufUpper !== '' && (origem === 1 || origem === 2 || origem === 3 || origem === 8)) {
        icmsInput.value = '4%';
      }
      // Se não for SP, e Nacional/CAMEX -> Busca no Banco (7% ou 12%)
      else if (ufUpper !== '') {
        fetch(`buscar_icms.php?uf=${encodeURIComponent(ufUpper)}`)
          .then(res => res.json())
          .then(data => {
            icmsInput.value = data.aliquota ? `${data.aliquota}%` : '';
          })
          .catch(err => console.error("Erro ao buscar ICMS:", err));
      } else {
        icmsInput.value = '';
      }

      // Atualizar estado do botão de histórico com segurança
      try {
        if (typeof atualizarEstadoBotaoHistorico === 'function') {
          atualizarEstadoBotaoHistorico(produtoBtnReferencia);
        }
      } catch (e) {
        console.error("Erro ao atualizar botão histórico:", e);
      }

      bootstrap.Modal.getInstance(document.getElementById('modalProdutos')).hide();
    }




    function filtrarProdutos() {
      const filtro = document.getElementById('buscaProduto').value.toLowerCase();
      const linhas = document.querySelectorAll('#tabelaProdutos tbody tr');
      linhas.forEach(linha => {
        const texto = linha.innerText.toLowerCase();
        linha.style.display = texto.includes(filtro) ? '' : 'none';
      });
    }

    function buscarProdutos() {
      const termo = document.getElementById('buscaProduto').value;
      const tbody = document.querySelector('#tabelaProdutos tbody');
      tbody.innerHTML = '<tr><td colspan="7">Buscando...</td></tr>';

      // Reset keyboard navigation
      selectedProductIndex = -1;

      fetch(`buscar_produtos.php?q=${encodeURIComponent(termo)}`)
        .then(res => res.json())
        .then(produtos => {
          if (!Array.isArray(produtos)) throw new Error("Resposta não é um array");

          if (produtos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7">Nenhum produto encontrado.</td></tr>';
            return;
          }

          tbody.innerHTML = '';
          produtos.forEach(produto => {
            // Escapar aspas simples para não quebrar o HTML do onclick
            const produtoJson = JSON.stringify(produto).replace(/'/g, "&#39;");

            const tr = document.createElement('tr');
            tr.innerHTML = `
          <td>${produto.codigo}</td>
          <td>${produto.produto}</td>
          <td>${produto.unidade}</td>
          <td>${produto.origem}</td>
          <td>${produto.ncm}</td>
          <td>${produto.ipi}</td>
          <td><button type="button" class="btn btn-sm btn-primary" onclick='selecionarProduto(${produtoJson})'>Selecionar</button></td>
        `;
            tbody.appendChild(tr);
          });
        })
        .catch(err => {
          console.error('Erro:', err);
          tbody.innerHTML = '<tr><td colspan="7">Erro ao buscar produtos.</td></tr>';
        });
    }

    document.getElementById('buscaProduto').addEventListener('keypress', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault(); // evita comportamento padrão (como fechar o modal)
        buscarProdutos();
      }
    });

    function buscarICMS(uf, callback) {
      fetch(`buscar_icms.php?uf=${uf}`)
        .then(response => response.json())
        .then(data => {
          if (data.aliquota) {
            callback(data.aliquota);
          } else {
            console.warn(data.erro || 'Erro ao obter ICMS');
            callback(null);
          }
        })
        .catch(() => {
          console.error('Erro na requisição de ICMS');
          callback(null);
        });
    }

    function calcularPrecoFull(row) {
      const precoNetInput = row.querySelector('[name*="[preco_net]"]');
      const icmsInput = row.querySelector('[name*="[icms]"]');
      const precoFullInput = row.querySelector('[name*="[preco_full]"]');
      const suframaSelect = document.querySelector('[name="suframa"]');

      const precoNet = parseFloat(precoNetInput.value.replace(',', '.'));
      const icms = parseFloat(icmsInput.value.replace(',', '.'));
      const isSuframa = suframaSelect.value === 'Sim';

      if (isNaN(precoNet) || (isNaN(icms) && !isSuframa)) {
        precoFullInput.value = '';
        return;
      }

      // Cofatores por alíquota
      const cofatores = {
        4: 0.8712,
        7: 0.8440,
        12: 0.7986,
        18: 0.7442
      };

      let precoFull;
      if (isSuframa) {
        precoFull = precoNet / 0.82;
      } else {
        const cofator = cofatores[icms] || 1;
        precoFull = precoNet / cofator;
      }

      precoFullInput.value = precoFull.toFixed(4).replace('.', ',');
    }


    document.addEventListener('DOMContentLoaded', function () {
      const campoData = document.getElementById('data');
      if (campoData) {
        const hoje = new Date();
        const ano = hoje.getFullYear();
        const mes = String(hoje.getMonth() + 1).padStart(2, '0');
        const dia = String(hoje.getDate()).padStart(2, '0');
        campoData.value = `${ano}-${mes}-${dia}`;
      }
    });


    document.addEventListener('DOMContentLoaded', function () {
      const cotadoInput = document.getElementById('cotado_por');
      if (cotadoInput) {
        // Toda vez que digitar, transforma em maiúscula
        cotadoInput.addEventListener('input', function () {
          this.value = this.value.toUpperCase();
        });

        // Segurança extra: ao enviar o formulário, converte também
        const form = cotadoInput.closest('form');
        if (form) {
          form.addEventListener('submit', function () {
            cotadoInput.value = cotadoInput.value.toUpperCase();
          });
        }
      }
    });



    document.getElementById('buscaCliente').addEventListener('keyup', function (e) {
      // Ignorar teclas de navegação para não re-buscar
      if (['ArrowUp', 'ArrowDown', 'Enter', 'Escape'].includes(e.key)) return;

      const termo = this.value.trim();
      if (termo.length < 3) return;

      fetch(`buscar_clientes.php?q=${encodeURIComponent(termo)}`)
        .then(res => res.json())
        .then(clientes => {
          const lista = document.getElementById('listaClientes');
          lista.innerHTML = '';

          if (clientes.error) {
            lista.innerHTML = `<tr><td colspan="3" class="text-danger">Erro: ${clientes.message}</td></tr>`;
            return;
          }

          if (clientes.length === 0) {
            lista.innerHTML = '<tr><td colspan="3">Nenhum cliente encontrado.</td></tr>';
            return;
          }

          clientes.forEach(cli => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
          <td>${cli.razao_social}</td>
          <td>${cli.cnpj || '-'}</td>
          <td>${cli.uf}</td>
          <td><button type="button" class="btn btn-sm btn-primary" onclick='selecionarCliente(${JSON.stringify(cli)})'>Selecionar</button></td>
        `;
            lista.appendChild(tr);
          });
        })
        .catch(err => {
          console.error(err);
          document.getElementById('listaClientes').innerHTML = '<tr><td colspan="3" class="text-danger">Erro na requisição. Verifique o console.</td></tr>';
        });
    });

    // --- FUNÇÕES DE CLIENTE (MOVIDAS PARA CÁ PARA EVITAR ESCOPO FECHADO) ---
    function abrirModalCliente() {
      const modal = new bootstrap.Modal(document.getElementById('modalClientes'));
      document.getElementById('buscaCliente').value = '';
      document.getElementById('listaClientes').innerHTML = '<tr><td colspan="3">Digite para buscar...</td></tr>';
      selectedClientIndex = -1;
      modal.show();
    }
  </script>
  </script>

  <!-- Modal Aviso Orçamento Recente -->
  <div class="modal fade" id="modalAvisoDuplicidade" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title">⚠️ Cliente com Orçamento Recente</h5>
        </div>
        <div class="modal-body">
          <p>O cliente <strong><span id="duplicidadeCliente"></span></strong> possui um orçamento recente criado em
            <strong><span id="duplicidadeData"></span></strong> (Nº <strong id="duplicidadeNum"></strong>) por <span
              id="duplicidadeAutor"></span>.
          </p>
          <p>Recomendamos editar o orçamento existente para evitar duplicidade.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Criar Novo Assim Mesmo</button>
          <a href="#" id="btnEditarExistente" class="btn btn-primary">Editar Orçamento Existente</a>
        </div>
      </div>
    </div>
  </div>

  <script>
    function selecionarCliente(cliente) {
      document.getElementById('cliente').value = cliente.razao_social;
      document.getElementById('uf').value = cliente.uf;
      
      // Populate hidden CNPJ field if it exists, otherwise create it dynamically or user should have added it
      // Let's assume we added the input in HTML. But since we are replacing JS here, we should ensure the input exists.
      // Better: Add the input in HTML first, then this JS.
      
      const cnpjInput = document.getElementById('cnpj');
      if (cnpjInput) {
          cnpjInput.value = cliente.cnpj || '';
      }
      
      bootstrap.Modal.getInstance(document.getElementById('modalClientes')).hide();

      // Atualizar todos os botões de histórico
      document.querySelectorAll('.item-row').forEach(row => {
        atualizarEstadoBotaoHistorico(row);

        // Validar e Preencher Price List
        verificarPriceList(row);
      });

      // Recalcular ICMS e Preços para todos os itens
      recalcularICMSTodos(cliente.uf);

      // Verificar duplicidade
      fetch(`verificar_orcamento_recente.php?cliente=${encodeURIComponent(cliente.razao_social)}`)
        .then(res => res.json())
        .then(data => {
          if (data.exists) {
            document.getElementById('duplicidadeCliente').textContent = cliente.razao_social;
            document.getElementById('duplicidadeData').textContent = data.data;
            document.getElementById('duplicidadeNum').textContent = data.num_orcamento;
            document.getElementById('duplicidadeAutor').textContent = data.cotado_por;

            document.getElementById('btnEditarExistente').href = `atualizar_orcamento.php?num=${encodeURIComponent(data.num_orcamento)}`;

            const modal = new bootstrap.Modal(document.getElementById('modalAvisoDuplicidade'));
            modal.show();
          }
        })
        .catch(err => console.error("Erro ao verificar duplicidade:", err));
    }


    document.querySelector('[name="suframa"]').addEventListener('change', () => {
      document.querySelectorAll('.item-row').forEach(row => calcularPrecoFull(row));
    });

    // Sempre que mudar a Suspensão de IPI, atualizar os itens
    document.querySelector('[name="suspensao_ipi"]').addEventListener('change', () => {
      const suspensaoIPI = document.querySelector('[name="suspensao_ipi"]').value === 'Sim';

      document.querySelectorAll('.item-row').forEach(row => {
        const ipiInput = row.querySelector('[name*="[ipi]"]');
        if (suspensaoIPI) {
          ipiInput.value = '0,00';
        }
      });
    });


    window.addEventListener('DOMContentLoaded', () => {
      fetch("ptax.php")
        .then(response => response.json())
        .then(data => {
          if (data && data.value && data.value.length > 0) {
            const ptaxVenda = parseFloat(data.value[0].cotacaoVenda).toFixed(4).replace('.', ',');
            const dolarInput = document.getElementById('dolar');
            if (dolarInput) {
               dolarInput.value = ptaxVenda;
               // Remove tooltip if it exists (in case of retry logic, though we don't have it yet)
               const tooltip = bootstrap.Tooltip.getInstance(dolarInput);
               if (tooltip) tooltip.dispose();
            }
          } else {
            console.error("Nenhuma cotação PTAX encontrada.");
            mostrarErroPtax();
          }
        })
        .catch(error => {
          console.error("Erro ao buscar a PTAX via ptax.php:", error);
          mostrarErroPtax();
        });
    });

    function mostrarErroPtax() {
        const dolarInput = document.getElementById('dolar');
        if (dolarInput) {
            dolarInput.setAttribute('data-bs-toggle', 'tooltip');
            dolarInput.setAttribute('data-bs-placement', 'top');
            dolarInput.setAttribute('title', 'Serviço indisponível. Por favor, insira a cotação manualmente.');
            
            const tooltip = new bootstrap.Tooltip(dolarInput);
            tooltip.show();
            
            // Optional: Highlight border
            dolarInput.classList.add('border-warning');
            
            // Hide tooltip after a few seconds or on interaction
            setTimeout(() => tooltip.hide(), 5000);
            dolarInput.addEventListener('focus', () => tooltip.hide(), {once: true});
        }
    }

    document.querySelectorAll('input[name="dolar"], input[name*="[volume]"], input[name*="[embalagem]"]').forEach(
      input => {
        input.addEventListener('input', () => {
          input.value = input.value.replace(/[^0-9,\.]/g, '');
        });
      });

    // --- NOVA FUNÇÃO DE RECALCULO DE ICMS ---


    // --- NOVA FUNÇÃO: Verificar Price List ---
    function verificarPriceList(row) {
      const codigoInput = row.querySelector('[name*="[codigo]"]');
      const embalagemInput = row.querySelector('[name*="[embalagem]"]');
      const priceListInput = row.querySelector('.valor-price-list');
      const validationIcon = row.querySelector('.validation-icon');
      const validationMsg = row.querySelector('.validation-msg');

      if (!codigoInput || !embalagemInput || !priceListInput) return;

      const codigo = codigoInput.value;
      const embalagem = embalagemInput.value.replace(',', '.'); // Normalize for check

      if (!codigo || !embalagem) {
        priceListInput.value = '';
        if (validationIcon) validationIcon.style.display = 'none';
        if (validationMsg) validationMsg.textContent = '';
        return;
      }

      // Buscar dados da Price List
      // Assumindo que buscar_preco_lista.php retorna array de {embalagem, preco_net_usd}
      fetch(`buscar_preco_lista.php?codigo=${codigo}`)
        .then(res => res.json())
        .then(data => {
          let found = false;
          let price = 0;

          // Check exact match on packaging logic
          // DB stores as decimal, inputs might vary. Check float values
          const embalagemFloat = parseFloat(embalagem);

          if (Array.isArray(data)) {
            const item = data.find(d => parseFloat(d.embalagem) === embalagemFloat);
            if (item) {
              found = true;
              price = item.preco_net_usd;
            }
          }

          if (found) {
            priceListInput.value = parseFloat(price).toFixed(2).replace('.', ',');
            if (validationIcon) validationIcon.style.display = 'none';
            if (validationMsg) {
              validationMsg.textContent = "✓ Preço de lista validado.";
              validationMsg.className = "text-success fst-italic validation-msg";
            }
          } else {
            priceListInput.value = ''; // Clear or keep empty
            if (validationIcon) {
              validationIcon.style.display = 'flex';
              // Update tooltip title if possible, or just rely on static title
            }
            if (validationMsg) {
              validationMsg.textContent = "⚠️ Embalagem não encontrada na Price List.";
              validationMsg.className = "text-warning fw-bold fst-italic validation-msg";
            }
          }
        })
        .catch(err => {
          console.error("Erro ao verificar Price List:", err);
          if (validationMsg) validationMsg.textContent = "Erro na validação.";
        });
    }

    function recalcularICMSTodos(uf) {
      if (!uf) return;
      const ufUpper = uf.trim().toUpperCase();

      // 1. Buscar a alíquota da UF
      if (ufUpper === 'SP') {
        // Se for SP, fixa 18% para tudo (evita fetch ou erro de DB)
        document.querySelectorAll('.item-row').forEach(row => {
          const icmsInput = row.querySelector('[name*="[icms]"]');
          if (icmsInput) {
            icmsInput.value = '18%';
            calcularPrecoFull(row);
          }
        });
      } else {
        // Se for outro estado, precisa buscar a alíquota padrão do estado (7% ou 12%)
        // Mas respeitando a regra de Importados (4%)
        fetch(`buscar_icms.php?uf=${encodeURIComponent(ufUpper)}`)
          .then(res => res.json())
          .then(data => {
            document.querySelectorAll('.item-row').forEach(row => {
              const origemInput = row.querySelector('[name*="[origem]"]');
              const icmsInput = row.querySelector('[name*="[icms]"]');

              if (origemInput && icmsInput) {
                const origem = parseInt(origemInput.value);

                // Importados interestadual = 4%
                // Origem 6 (CAMEX) NÃO entra aqui, segue alíquota normal (7% ou 12%)
                if (origem === 1 || origem === 2 || origem === 3 || origem === 8) {
                  icmsInput.value = '4%';
                } else {
                  // Nacionais/CAMEX interestadual = Alíquota do Estado (7% ou 12%)
                  icmsInput.value = data.aliquota ? `${data.aliquota}%` : '';
                }

                calcularPrecoFull(row);
              }
            });
          })
          .catch(err => console.error("Erro ao recalcular ICMS em massa:", err));
      }
    }

    // --- LÓGICA DE ADIÇÃO EM MASSA (TEXTAREA) ---
    const modalMassa = new bootstrap.Modal(document.getElementById('modalMassa'));

    function abrirModalMassa() {
      document.getElementById('codesMassa').value = '';
      modalMassa.show();
    }

    async function processarCodesMassa() {
      const text = document.getElementById('codesMassa').value;
      if (!text.trim()) {
        alert("Cole pelo menos um código.");
        return;
      }

      const codes = text.split(/\r?\n/).map(c => c.trim()).filter(c => c.length > 0);

      let addedCount = 0;
      let notFound = [];

      // Feedback visual
      const btnProcessar = document.querySelector('#modalMassa .btn-primary');
      const originalText = btnProcessar.innerHTML;
      btnProcessar.disabled = true;
      btnProcessar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

      for (const code of codes) {
        try {
          // Fetch product details logic (reusing buscar_produtos logic but exact match)
          const res = await fetch(`buscar_produtos.php?q=${encodeURIComponent(code)}`);
          const produtos = await res.json();

          // Encontrar correspondência exata ou primeiro match forte
          const produto = produtos.find(p => p.codigo.toUpperCase() === code.toUpperCase()) || produtos[0];

          if (produto) {
            // Adicionar item e preencher
            adicionarItem();
            const rows = document.querySelectorAll('.item-row');
            const row = rows[rows.length - 1]; // Última linha adicionada

            // Simular seleção do produto
            selecionarProdutoMassa(row, produto);
            addedCount++;
          } else {
            notFound.push(code);
          }
        } catch (e) {
          console.error(`Erro ao buscar ${code}:`, e);
          notFound.push(code + " (Erro)");
        }
      }

      btnProcessar.disabled = false;
      btnProcessar.innerHTML = originalText;
      modalMassa.hide();

      let msg = `${addedCount} itens adicionados com sucesso.`;
      if (notFound.length > 0) {
        msg += `\n\nNão encontrados/Erros:\n${notFound.join('\n')}`;
      }
      alert(msg);
    }

    function selecionarProdutoMassa(row, produto) {
      row.querySelector('[name*="[codigo]"]').value = produto.codigo;
      row.querySelector('[name*="[produto]"]').value = produto.produto;
      row.querySelector('[name*="[unidade]"]').value = produto.unidade;
      row.querySelector('[name*="[origem]"]').value = produto.origem;
      row.querySelector('[name*="[ncm]"]').value = produto.ncm;

      const suspensaoIPI = document.querySelector('[name="suspensao_ipi"]').value === 'Sim';
      row.querySelector('[name*="[ipi]"]').value = suspensaoIPI ? '0,00' : String(produto.ipi).replace('.', ',');

      // Calcular ICMS
      const uf = document.querySelector('input[name="uf"]').value;
      if (uf) {
        const ufUpper = uf.trim().toUpperCase();
        const origem = parseInt(produto.origem);
        const icmsInput = row.querySelector('[name*="[icms]"]');

        if (ufUpper === 'SP') {
          icmsInput.value = '18%';
          calcularPrecoFull(row);
        }
        // CAMEX (6) não entra no 4%
        else if (origem === 1 || origem === 2 || origem === 3 || origem === 8) {
          icmsInput.value = '4%';
          calcularPrecoFull(row);
        }
        else {
          buscarICMS(ufUpper, (aliquota) => {
            icmsInput.value = aliquota ? `${aliquota}%` : '';
            calcularPrecoFull(row);
          });
        }
      }

      atualizarEstadoBotaoHistorico(row);
    }
  </script>


  <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1): ?>
    <!-- Modal -->
    <div class="modal fade" id="modalSucesso" tabindex="-1" aria-labelledby="modalSucessoLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title" id="modalSucessoLabel">Sucesso!</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            Orçamento salvo com sucesso.
            <div class="alert alert-warning mt-3" role="alert">
              Uma cópia do orçamento foi enviada por e-mail. Caso não localize-a, verifique sua caixa de
              <strong>lixo eletrônico</strong> ou <strong>spam</strong>.
            </div>
            <div class="alert alert-danger mt-3" role="alert">
              Clique em não é <strong>lixo eletrônico</strong> para acessar o PDF.
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            <a href="tmp/orcamento_<?= htmlspecialchars($_GET['num_orcamento'] ?? '') ?>.pdf" class="btn btn-primary"
              target="_blank">
              Visualizar PDF
            </a>

          </div>
        </div>
      </div>
    </div>

    <script>
      const sucessoModal = new bootstrap.Modal(document.getElementById('modalSucesso'));
      window.onload = () => sucessoModal.show();
    </script>
  <?php endif; ?>

  <script>
    function gerarPDF() {
      const cliente = document.getElementById('cliente').value;
      const uf = document.getElementById('uf').value;
      const data = document.getElementById('data').value;
      const cotado_por = document.getElementById('cotado_por').value;

      const url =
        `gerar_pdf.php?cliente=${encodeURIComponent(cliente)}&uf=${encodeURIComponent(uf)}&data=${data}&cotado_por=${encodeURIComponent(cotado_por)}`;
      window.open(url, '_blank');
    }
  </script>




  <script>
    document.getElementById("btnSalvar").addEventListener("click", function () {
      document.getElementById("loading").style.display = "block";
    });
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const params = new URLSearchParams(window.location.search);
      const numOrcamento = params.get("num_orcamento");
      const incluirNet = params.get("incluir_net");

      if (params.get("sucesso") === "1" && numOrcamento && incluirNet !== null) {
        fetch('gerar_pdf_orcamento.php?num=' + numOrcamento + '&incluir_net=' + incluirNet)
          .then(response => {
            if (!response.ok) throw new Error("Erro ao gerar PDF");
            return response.blob(); // retorna o conteúdo como blob
          })
          .then(blob => {
            const blobUrl = URL.createObjectURL(blob);
            window.open(blobUrl, '_blank'); // abre em nova aba o PDF real

            // --- Disparar envio de email (Silent) ---
            // REMOVIDO: O envio já é feito via include no salvar_orcamento.php
            // fetch('enviar_orcamento.php?num=' + numOrcamento + '&incluir_net=' + incluirNet)
            //   .then(r => r.text())
            //   .then(txt => console.log("Email Log:", txt))
            //   .catch(e => console.error("Erro ao disparar email:", e));
          })
          .catch(error => {
            console.error(error);
            alert("Erro ao gerar o PDF.");
          });
      }
    });

  </script>





  <!-- Modal Price List -->
  <div class="modal fade" id="modalPriceList" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title">Preço de Lista (Informativo)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-secondary">
            <strong>Produto:</strong> <span id="plProduto"></span> <br>
            <strong>Código Base:</strong> <span id="plCodigo"></span> <br>
            <strong>PTAX Utilizada:</strong> R$ <span id="plPtax"></span>
          </div>
          <table class="table table-striped table-bordered">
            <thead>
              <tr>
                <th>Fabricante</th>
                <th>Classificação</th>
                <th>Embalagem</th>
                <th>Lead Time</th>
                <th>Preço USD</th>
                <th>Preço BRL (Est.)</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody id="plTableBody">
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function verPrecoLista(btn) {
      priceListRow = btn.closest('.item-row'); // Store row context
      const row = priceListRow;
      const codigoInput = row.querySelector('.item-codigo');
      const produtoInput = row.querySelector('.item-produto');
      const dolarInput = document.querySelector('input[name="dolar"]');

      const codigo = codigoInput.value;
      const produto = produtoInput.value;
      const dolarStr = dolarInput.value;
      const dolar = parseFloat(dolarStr.replace(',', '.')) || 0;

      if (!codigo) {
        alert("Selecione um produto primeiro.");
        return;
      }

      // Preenche cabeçalho do modal
      document.getElementById('plProduto').textContent = produto;
      document.getElementById('plCodigo').textContent = codigo.substring(0, 9);
      document.getElementById('plPtax').textContent = dolarStr;

      // Limpa tabela
      const tbody = document.getElementById('plTableBody');
      tbody.innerHTML = '<tr><td colspan="7" class="text-center">Buscando...</td></tr>';

      // Abre modal
      const modal = new bootstrap.Modal(document.getElementById('modalPriceList'));
      modal.show();

      // Busca dados
      fetch(`buscar_preco_lista.php?codigo=${encodeURIComponent(codigo)}`)
        .then(res => res.json())
        .then(data => {
          tbody.innerHTML = '';

          if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhum preço de lista encontrado para este código (9 primeiros dígitos).</td></tr>';
            return;
          }

          data.forEach(item => {
            const precoUSD = parseFloat(item.preco_net_usd);
            const precoBRL = precoUSD * dolar;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                    <td>${item.fabricante}</td>
                    <td>${item.classificacao}</td>
                    <td>${parseFloat(item.embalagem)}</td>
                    <td>${item.lead_time || '-'}</td>
                    <td>$ ${precoUSD.toFixed(2)}</td>
                    <td class="fw-bold">R$ ${precoBRL.toFixed(2).replace('.', ',')}</td>
                    <td>
                        <button class="btn btn-sm btn-success" onclick="usarPrecoLista(${precoUSD.toFixed(4)})">
                            Selecionar
                        </button>
                    </td>
                `;
            tbody.appendChild(tr);
          });
        })

        .catch(err => {
          console.error(err);
          tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Erro ao buscar preços.</td></tr>';
        });
    }

    function usarPrecoLista(preco) {
      if (!priceListRow) return;

      // Set Preço Net
      const netInput = priceListRow.querySelector('[name*="[preco_net]"]');
      if (netInput) {
        netInput.value = preco.toFixed(4).replace('.', ',');

        // Trigger calculation
        calcularPrecoFull(priceListRow);
      }

      // Close Modal
      bootstrap.Modal.getInstance(document.getElementById('modalPriceList')).hide();
    }

    // --- NOVA FUNÇÃO DE CONTROLE DOS BOTÕES ---
    function atualizarEstadoBotaoHistorico(row) {
      if (!row) return;
      const btn = row.querySelector('.btn-historico-preco');
      if (!btn) return;

      const codigo = row.querySelector('.item-codigo').value;
      const cliente = document.getElementById('cliente').value;

      if (codigo && cliente) {
        btn.removeAttribute('disabled');
      } else {
        btn.setAttribute('disabled', 'disabled');
      }
    }

    // --- FUNÇÃO HISTÓRICO DE PREÇOS ---
    function abrirHistorico(btn) {
      const row = btn.closest('.item-row');
      const codigo = row.querySelector('.item-codigo').value;
      const cliente = document.getElementById('cliente').value;

      if (!codigo) {
        alert('Selecione um produto primeiro.');
        return;
      }
      if (!cliente) {
        alert('Selecione um cliente primeiro.');
        return;
      }

      // Limpar e abrir modal
      document.getElementById('histTitle').textContent = `Histórico: ${codigo}`;
      document.getElementById('histClienteBody').innerHTML = '<tr><td colspan="5" class="text-center">Carregando...</td></tr>';
      document.getElementById('histGeralBody').innerHTML = '<tr><td colspan="6" class="text-center">Carregando...</td></tr>';

      const modal = new bootstrap.Modal(document.getElementById('modalHistorico'));
      modal.show();

      fetch(`buscar_historico_produto.php?codigo=${encodeURIComponent(codigo)}&cliente=${encodeURIComponent(cliente)}`)
        .then(res => res.json())
        .then(data => {
          const bodyCli = document.getElementById('histClienteBody');
          const bodyGeral = document.getElementById('histGeralBody');
          bodyCli.innerHTML = '';
          bodyGeral.innerHTML = '';

          // 1. Cliente
          if (!data.cliente || data.cliente.length === 0) {
            bodyCli.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Nenhum histórico para este cliente.</td></tr>';
          } else {
            data.cliente.forEach(row => {
              const tr = `<tr>
                            <td>${new Date(row.DATA).toLocaleDateString()}</td>
                            <td>${row.VOLUME || '-'}</td>
                            <td>${row.EMBALAGEM_KG || '-'}</td>
                            <td>$ ${parseFloat(row.PRECO_NET).toFixed(4)}</td>
                            <td>$ ${parseFloat(row.PRECO_FULL).toFixed(4)}</td>
                            <td>R$ ${parseFloat(row.DOLAR).toFixed(4)}</td>
                        </tr>`;
              bodyCli.innerHTML += tr;
            });
          }

          // 2. Geral
          if (!data.geral || data.geral.length === 0) {
            bodyGeral.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhum histórico recente.</td></tr>';
          } else {
            data.geral.forEach(row => {
              const tr = `<tr>
                            <td>${row.CLIENTE}</td>
                            <td>${new Date(row.DATA).toLocaleDateString()}</td>
                            <td>${row.VOLUME || '-'}</td>
                            <td>${row.EMBALAGEM_KG || '-'}</td>
                            <td>$ ${parseFloat(row.PRECO_NET).toFixed(4)}</td>
                            <td>$ ${parseFloat(row.PRECO_FULL).toFixed(4)}</td>
                            <td>R$ ${parseFloat(row.DOLAR).toFixed(4)}</td>
                        </tr>`;
              bodyGeral.innerHTML += tr;
            });
          }
        })
        .catch(err => {
          console.error(err);
          document.getElementById('histClienteBody').innerHTML = '<tr><td colspan="5" class="text-danger">Erro ao carregar.</td></tr>';
          document.getElementById('histGeralBody').innerHTML = '<tr><td colspan="6" class="text-danger">Erro ao carregar.</td></tr>';
        });
    }
  </script>

  <!-- Modal Histórico -->
  <div class="modal fade" id="modalHistorico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="histTitle">Histórico de Preços</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">

          <h6 class="fw-bold text-primary mb-2">Histórico deste Cliente</h6>
          <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered table-hover">
              <thead class="table-light">
                <tr>
                  <th>Data</th>
                  <th>Volume</th>
                  <th>Embalagem</th>
                  <th>Preço NET (USD)</th>
                  <th>Preço Full (USD)</th>
                  <th>Dólar Cotado</th>
                </tr>
              </thead>
              <tbody id="histClienteBody"></tbody>
            </table>
          </div>

          <h6 class="fw-bold text-success mb-2">Histórico Geral (Últimos 12 Meses)</h6>
          <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover">
              <thead class="table-light">
                <tr>
                  <th>Cliente</th>
                  <th>Data</th>
                  <th>Volume</th>
                  <th>Embalagem</th>
                  <th>Preço NET (USD)</th>
                  <th>Preço Full (USD)</th>
                  <th>Dólar Cotado</th>
                </tr>
              </thead>
              <tbody id="histGeralBody"></tbody>
            </table>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // --- Keyboard Navigation Logic ---
    let selectedProductIndex = -1;
    let selectedClientIndex = -1;

    function setupKeyboardNavigation(inputId, tableId, type) {
      const input = document.getElementById(inputId);
      const tbody = document.querySelector(`#${tableId} tbody`);

      input.addEventListener('keydown', function (e) {
        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0 || rows[0].innerText.includes('Nenhum') || rows[0].innerText.includes('Buscando')) return;

        if (e.key === 'ArrowDown') {
          e.preventDefault();
          let index = (type === 'product') ? selectedProductIndex : selectedClientIndex;
          if (index < rows.length - 1) {
            index++;
            highlightRow(rows, index);
            if (type === 'product') selectedProductIndex = index;
            else selectedClientIndex = index;
          }
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          let index = (type === 'product') ? selectedProductIndex : selectedClientIndex;
          if (index > 0) {
            index--;
            highlightRow(rows, index);
            if (type === 'product') selectedProductIndex = index;
            else selectedClientIndex = index;
          }
        } else if (e.key === 'Enter') {
          e.preventDefault();
          let index = (type === 'product') ? selectedProductIndex : selectedClientIndex;
          if (index >= 0 && rows[index]) {
            const btn = rows[index].querySelector('button');
            if (btn) btn.click();
          } else if (index === -1 && rows.length > 0) {
            // Se der enter sem selecionar nada, seleciona o primeiro se houver apenas 1 ou se for comportamento desejado
            // Mas vamos deixar explícito. Se quiser o primeiro, aperta baixo.
          }
        }
      });
    }

    function highlightRow(rows, index) {
      // Remove classes de todas as linhas
      rows.forEach(r => r.classList.remove('table-active-custom'));

      // Adiciona à selecionada
      if (rows[index]) {
        rows[index].classList.add('table-active-custom');
        rows[index].scrollIntoView({ block: 'nearest' });
      }
    }

    // Initialize listeners
    document.addEventListener('DOMContentLoaded', () => {
      setupKeyboardNavigation('buscaProduto', 'tabelaProdutos', 'product');
      setupKeyboardNavigation('buscaCliente', 'listaClientes', 'client');
    });

    // Reset index on new search input (listeners already exist, just adding reset logic inline or ensuring fetch clears it)
    // Actually, we added the reset in the fetch calls above.
  </script>
</body>

</html>