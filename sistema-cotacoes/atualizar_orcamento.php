<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once 'header.php';
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
  header('Location: index.html');
  exit();
}

$num_orcamento = $_GET['num'] ?? null;
if (!$num_orcamento) {
  echo "<div class='container mt-5'><div class='alert alert-danger'>Número do orçamento não informado.</div></div>";
  exit;
}

// Busca cabeçalho do orçamento (pegando do primeiro item)
$stmt = $pdo->prepare("SELECT * FROM cot_cotacoes_importadas WHERE NUM_ORCAMENTO = ? LIMIT 1");
$stmt->execute([$num_orcamento]);
$headerOrcamento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$headerOrcamento) {
  echo "<div class='container mt-5'><div class='alert alert-warning'>Orçamento não encontrado.</div></div>";
  exit;
}

// Busca todos os itens
$stmtItens = $pdo->prepare("SELECT * FROM cot_cotacoes_importadas WHERE NUM_ORCAMENTO = ?");
$stmtItens->execute([$num_orcamento]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
// DEBUG TEMPORARIO
// echo '<pre>'; print_r($itens[0] ?? 'Nenhum item'); echo '</pre>';

// Carrega produtos para modal
$produtos = $pdo->query("SELECT codigo, produto, origem, ncm, ipi FROM cot_estoque WHERE ativo = 1 ORDER BY produto ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Editar Orçamento <?= htmlspecialchars($num_orcamento) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .form-section {
      background-color: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
  </style>
</head>

<body>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0">Editar Orçamento: <span class="text-primary"><?= htmlspecialchars($num_orcamento) ?></span></h2>
      <a href="consultar_orcamentos.php" class="btn btn-secondary">Voltar</a>
    </div>

    <!-- Informações do Cliente (Somente Leitura neste contexto, pois é flat table) -->
    <div class="form-section">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-bold">Cliente</label>
          <div class="input-group">
            <input type="text" class="form-control" id="cliente"
              value="<?= htmlspecialchars($headerOrcamento['RAZÃO SOCIAL']) ?>" readonly>
            <button class="btn btn-outline-secondary" type="button" onclick="abrirModalCliente()">🔍</button>
          </div>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-bold">UF</label>
          <input type="text" class="form-control" id="uf" value="<?= htmlspecialchars($headerOrcamento['UF']) ?>"
            readonly>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-bold">Data Original</label>
          <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($headerOrcamento['DATA'])) ?>"
            readonly>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-bold">Cotado Por</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($headerOrcamento['COTADO_POR']) ?>"
            readonly>
        </div>
      </div>
    </div>

    <div class="form-section">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>Itens do Orçamento</h5>
        <button type="button" class="btn btn-primary" onclick="abrirModalAdicionarItem()">+ Adicionar Novo Item</button>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-bordered">
          <thead class="table-light">
            <tr>
              <th>Produto</th>
              <th>Origem</th>
              <th>Volume</th>
              <th>Preço Net</th>
              <th>ICMS</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($itens as $item): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($item['PRODUTO']) ?></strong><br>
                  <small class="text-muted">Cod: <?= htmlspecialchars($item['COD DO PRODUTO']) ?></small>
                </td>
                <td><?= htmlspecialchars($item['ORIGEM']) ?></td>
                <td><?= htmlspecialchars($item['VOLUME']) ?></td>
                <td>USD <?= htmlspecialchars($item['PREÇO NET USD/KG']) ?></td>
                <td><?= htmlspecialchars($item['ICMS']) ?></td>
                <td>
                  <!-- Botões de Ação -->
                  <button class="btn btn-sm btn-warning"
                    onclick='abrirModalEditar(<?= json_encode($item) ?>)'>Alterar</button>
                  <?php
                  // Fallback para ID
                  $itemId = $item['id'] ?? $item['ID'] ?? $item['Id'] ?? null;
                  ?>
                  <button class="btn btn-sm btn-danger"
                    onclick="confirmarExclusao('<?= $itemId ?>', '<?= htmlspecialchars($item['PRODUTO']) ?>')">Excluir</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Modal Adicionar/Editar Item -->
  <div class="modal fade" id="modalItem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <form method="post" action="salvar_item.php">
        <input type="hidden" name="num_orcamento" value="<?= $num_orcamento ?>">
        <input type="hidden" name="id_linha" id="itemId"> <!-- Vazio para novo, preenchido para editar -->

        <!-- Campos ocultos para replicar dados do cabeçalho -->
        <input type="hidden" name="razao_social" value="<?= htmlspecialchars($headerOrcamento['RAZÃO SOCIAL']) ?>">
        <input type="hidden" name="uf" value="<?= htmlspecialchars($headerOrcamento['UF']) ?>">
        <input type="hidden" name="data" value="<?= htmlspecialchars($headerOrcamento['DATA']) ?>">
        <input type="hidden" name="cotado_por" value="<?= htmlspecialchars($headerOrcamento['COTADO_POR']) ?>">
        <!-- Campo Oculto para Suframa (usado no cálculo JS) -->
        <input type="hidden" id="headerSuframa"
          value="<?= htmlspecialchars($headerOrcamento['SUFRAMA'] ?? $headerOrcamento['suframa'] ?? 'Não') ?>">
        <input type="hidden" id="current_dolar" value=""> <!-- Added for Price List logic -->

        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalItemTitle">Adicionar Item</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-3">
                <label>Código</label>
                <div class="input-group">
                  <input type="text" name="codigo" id="itemCodigo" class="form-control" readonly required>
                  <button class="btn btn-outline-secondary" type="button" onclick="abrirModalBuscaProduto()">🔍</button>
                </div>
              </div>
              <div class="col-md-5">
                <label>Produto</label>
                <input type="text" name="produto" id="itemProduto" class="form-control" readonly required>
              </div>
              <div class="col-md-2">
                <label>Unidade</label>
                <input type="text" name="unidade" id="itemUnidade" class="form-control" readonly>
              </div>
              <div class="col-md-2">
                <label>Origem</label>
                <input type="text" name="origem" id="itemOrigem" class="form-control" readonly>
              </div>
              <div class="col-md-2">
                <label>NCM</label>
                <input type="text" name="ncm" id="itemNcm" class="form-control" readonly>
              </div>
              <!-- Campos editáveis -->
              <div class="col-md-2">
                <label>Volume</label>
                <input type="text" name="volume" id="itemVolume" class="form-control" required>
              </div>
              <div class="col-md-2">
                <label>Embalagem</label>
                <input type="text" name="embalagem" id="itemEmbalagem" class="form-control">
              </div>
              <div class="col-md-2">
                <label>IPI %</label>
                <input type="text" name="ipi" id="itemIpi" class="form-control">
              </div>
              <div class="col-md-2">
                <label>ICMS</label>
                <input type="text" name="icms" id="itemIcms" class="form-control">
              </div>
              <div class="col-md-4">
                <label>Disponibilidade</label>
                <select name="disponibilidade" id="itemDisponibilidade" class="form-select">
                  <option value="IMEDIATA">IMEDIATA</option>
                  <option value="LEAD-TIME">LEAD-TIME (verificar)</option>
                </select>
              </div>
              <div class="col-md-3">
                <label>Preço Net (USD)</label>
                <div class="input-group">
                  <input type="text" name="preco_net" id="itemPrecoNet" class="form-control" required>
                  <button class="btn btn-success" type="button" onclick="verPrecoLista()" title="Ver Price List"
                    style="font-size: 0.7rem;">Price List</button>
                </div>
              </div>
              <div class="col-md-3">
                <label>Preço Full (USD)</label>
                <input type="text" name="preco_full" id="itemPrecoFull" class="form-control">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success">Salvar Item</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Clientes (Adicionado) -->
  <div class="modal fade" id="modalClientes" tabindex="-1" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Selecionar Cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

  <!-- Modal Busca Produto -->
  <div class="modal fade" id="modalBuscaProduto" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Buscar Produto</h5>
          <button type="button" class="btn-close" onclick="fecharModalBusca()"></button>
        </div>
        <div class="modal-body">
          <div class="input-group mb-3">
            <input type="text" id="buscaProduto" class="form-control" placeholder="Buscar por nome, código ou NCM...">
            <button class="btn btn-primary" type="button" onclick="buscarProdutos()">Pesquisar</button>
          </div>
          <div style="max-height: 300px; overflow-y: auto;">
            <table class="table table-sm table-bordered" id="tabelaBuscaProdutos">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Produto</th>
                  <th>Ação</th>
                </tr>
              </thead>
              <tbody id="tbodyBuscaProdutos">
                <?php foreach ($produtos as $p): ?>
                  <tr>
                    <td><?= $p['codigo'] ?></td>
                    <td><?= $p['produto'] ?></td>
                    <td><button type="button" class="btn btn-sm btn-primary"
                        onclick='selecionarProduto(<?= json_encode($p) ?>)'>Selecionar</button></td>
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

  <!-- Modal Price List -->
  <div class="modal fade" id="modalPriceList" tabindex="-1" aria-hidden="true" style="z-index: 1090;">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title">Preço de Lista (Informativo)</h5>
          <button type="button" class="btn-close" onclick="fecharModalPriceList()"></button>
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
          <button type="button" class="btn btn-secondary" onclick="fecharModalPriceList()">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Deletion Safety -->
  <div class="modal fade" id="modalConfirmarExclusao" tabindex="-1" aria-hidden="true" style="z-index: 1080;">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Confirmar Exclusão</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Tem certeza que deseja remover o item abaixo?</p>
          <p class="fw-bold" id="nomeItemExclusao"></p>
          <p class="text-muted small">Essa ação não pode ser desfeita.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <a href="#" id="btnConfirmarExclusao" class="btn btn-danger">Sim, Excluir</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const modalItem = new bootstrap.Modal(document.getElementById('modalItem'));
    const modalBusca = new bootstrap.Modal(document.getElementById('modalBuscaProduto'));

    function abrirModalAdicionarItem() {
      document.getElementById('modalItemTitle').textContent = "Adicionar Novo Item";
      document.getElementById('itemId').value = ""; // Limpa ID -> Insert
      // Limpar campos
      document.getElementById('itemCodigo').value = "";
      document.getElementById('itemProduto').value = "";
      document.getElementById('itemVolume').value = "";
      document.getElementById('itemPrecoNet').value = "";
      modalItem.show();
    }

    function abrirModalEditar(item) {
      document.getElementById('modalItemTitle').textContent = "Editar Item";
      document.getElementById('itemId').value = item.id;

      document.getElementById('itemCodigo').value = item['COD DO PRODUTO'];
      document.getElementById('itemProduto').value = item['PRODUTO'];
      document.getElementById('itemUnidade').value = item['UNIDADE'];
      document.getElementById('itemOrigem').value = item['ORIGEM'];
      document.getElementById('itemNcm').value = item['NCM'];
      document.getElementById('itemVolume').value = item['VOLUME'];
      document.getElementById('itemEmbalagem').value = item['EMBALAGEM_KG'];
      document.getElementById('itemIpi').value = item['IPI %'];
      document.getElementById('itemIcms').value = item['ICMS'];
      document.getElementById('itemDisponibilidade').value = item['DISPONIBILIDADE'];
      document.getElementById('itemPrecoNet').value = item['PREÇO NET USD/KG'];
      document.getElementById('itemPrecoFull').value = item['PREÇO FULL USD/KG'];

      modalItem.show();
    }

    function abrirModalBuscaProduto() {
      modalBusca.show();
    }
    function fecharModalBusca() {
      modalBusca.hide();
    }

    function buscarProdutos() {
      const termo = document.getElementById('buscaProduto').value;
      const tbody = document.getElementById('tbodyBuscaProdutos');
      tbody.innerHTML = '<tr><td colspan="3">Buscando...</td></tr>';

      fetch(`buscar_produtos.php?q=${encodeURIComponent(termo)}`)
        .then(res => res.json())
        .then(produtos => {
          if (!Array.isArray(produtos)) throw new Error("Resposta não é um array");

          if (produtos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3">Nenhum produto encontrado.</td></tr>';
            return;
          }

          tbody.innerHTML = '';
          produtos.forEach(p => {
            const produtoJson = JSON.stringify(p).replace(/'/g, "&#39;");
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${p.codigo}</td>
              <td>${p.produto}</td>
              <td><button type="button" class="btn btn-sm btn-primary" onclick='selecionarProduto(${produtoJson})'>Selecionar</button></td>
            `;
            tbody.appendChild(tr);
          });
        })
        .catch(err => {
          console.error('Erro:', err);
          tbody.innerHTML = '<tr><td colspan="3">Erro ao buscar produtos.</td></tr>';
        });
    }

    // Bind Enter key
    document.addEventListener('DOMContentLoaded', function () {
      const inputBusca = document.getElementById('buscaProduto');
      if (inputBusca) {
        inputBusca.addEventListener('keypress', function (e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            buscarProdutos();
          }
        });
      }
    });

    function selecionarProduto(p) {
      document.getElementById('itemCodigo').value = p.codigo;
      document.getElementById('itemProduto').value = p.produto;
      document.getElementById('itemUnidade').value = 'KG';
      document.getElementById('itemOrigem').value = p.origem;
      document.getElementById('itemNcm').value = p.ncm;
      document.getElementById('itemIpi').value = p.ipi;

      // Buscar ICMS baseado na UF atual (que pode ter mudado)
      const uf = document.getElementById('uf').value;
      const modalProdRef = document.getElementById('modalItem'); // Ref ao modal de item
      const icmsInput = document.getElementById('itemIcms');

      fetch(`buscar_icms.php?uf=${uf}`)
        .then(res => res.json())
        .then(data => {
          const origem = parseInt(p.origem);
          const ufUpper = uf.trim().toUpperCase();

          if (origem === 1 || origem === 2) {
            icmsInput.value = (ufUpper === 'SP') ? '18%' : '4%';
          } else {
            icmsInput.value = data.aliquota ? `${data.aliquota}%` : '';
          }
          // Disparar calculo se tiver preco net
          calcularPrecoItem();
        })
        .catch(err => console.error("Erro ao buscar ICMS:", err));

      modalBusca.hide();
    }

    const modalExclusao = new bootstrap.Modal(document.getElementById('modalConfirmarExclusao'));

    function confirmarExclusao(id, produto) {
      document.getElementById('nomeItemExclusao').textContent = produto;
      const link = `excluir_orcamento_item.php?id=${id}&num=${encodeURIComponent('<?= $num_orcamento ?>')}`;
      document.getElementById('btnConfirmarExclusao').href = link;
      modalExclusao.show();
    }

    // Lógica de Cálculo Automático do Preço Full
    function calcularPrecoItem() {
      const netInput = document.getElementById('itemPrecoNet');
      const icmsInput = document.getElementById('itemIcms');
      const fullInput = document.getElementById('itemPrecoFull');
      const suframaValue = document.getElementById('headerSuframa').value; // Vem do PHP

      // Tratar valores (virgula para ponto)
      let net = parseFloat(netInput.value.replace(',', '.'));

      // Tentar extrair número do ICMS (pode vir como "18%" ou "18.00")
      let icmsStr = icmsInput.value.replace('%', '').replace(',', '.').trim();
      let icms = parseFloat(icmsStr);

      const isSuframa = (suframaValue === 'Sim');

      if (isNaN(net)) {
        fullInput.value = '';
        return;
      }

      // Se ICMS não for número e não for Suframa, não calcula (ou assume algo?)
      // Na lógica original: if (isNaN(precoNet) || (isNaN(icms) && !isSuframa)) return;
      if (!isSuframa && (isNaN(icms))) {
        // Talvez o ICMS esteja vazio
        return;
      }

      // Cofatores por alíquota (Lógica do incluir_orcamento.php)
      const cofatores = {
        4: 0.8712,
        7: 0.8440,
        12: 0.7986,
        18: 0.7442
      };

      let precoFull;
      if (isSuframa) {
        precoFull = net / 0.82;
      } else {
        // Se a alíquota não estiver mapeada, usa cofator 1? Ou tenta aproximar?
        // O código original usava: cofatores[icms] || 1;
        // Vamos arredondar o ICMS para bater com a key (ex: 18.00 -> 18)
        let icmsInt = Math.round(icms);
        const cofator = cofatores[icmsInt] || 1;

        // Se cofator for 1 e ICMS for > 0, talvez devesse avisar, mas vamos manter a lógica original
        precoFull = net / cofator;
      }

      fullInput.value = precoFull.toFixed(4).replace('.', ',');
    }

    // Add listeners
    document.getElementById('itemPrecoNet').addEventListener('input', calcularPrecoItem);
    document.getElementById('itemIcms').addEventListener('input', calcularPrecoItem);
    document.getElementById('itemIpi').addEventListener('input', calcularPrecoItem); // IPI afeta? Na lógica original não parecia usar IPI no calculo do FULL direta, mas vamos manter o padrão. 
    // OBS: O código original NÃO usava IPI para calcular o Full, apenas Net e ICMS (divisão pelo cofator).
    // O IPI entra no custo, mas Preço Full normalmente é (Preço Net / Fator de Markup do ICMS/Impostos).
    // Vou manter igual ao incluir_orcamento.php

    // --- Lógica de Cliente (Copiada e Adaptada) ---
    const modalCliente = new bootstrap.Modal(document.getElementById('modalClientes'));

    function abrirModalCliente() {
      document.getElementById('buscaCliente').value = '';
      document.getElementById('listaClientes').innerHTML = '<tr><td colspan="3">Digite para buscar...</td></tr>';
      modalCliente.show();
    }

    document.getElementById('buscaCliente').addEventListener('keyup', function () {
      const termo = this.value.trim();
      if (termo.length < 3) return;

      fetch(`buscar_clientes.php?q=${encodeURIComponent(termo)}`)
        .then(res => res.json())
        .then(clientes => {
          const lista = document.getElementById('listaClientes');
          lista.innerHTML = '';
          if (clientes.error) {
            lista.innerHTML = `<tr><td colspan="3" class="text-danger">Erro: ${clientes.message}</td></tr>`; return;
          }
          if (clientes.length === 0) {
            lista.innerHTML = '<tr><td colspan="3">Nenhum cliente encontrado.</td></tr>'; return;
          }
          clientes.forEach(cli => {
            // Strings seguras para JS
            const jsonCli = JSON.stringify(cli).replace(/'/g, "&#39;");
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${cli.razao_social}</td>
              <td>${cli.cnpj || '-'}</td>
              <td>${cli.uf}</td>
              <td><button type="button" class="btn btn-sm btn-primary" onclick='confirmarAlteracaoCliente(${jsonCli})'>Selecionar</button></td>
            `;
            lista.appendChild(tr);
          });
        });
    });

    function confirmarAlteracaoCliente(cli) {
      if (confirm(`Deseja alterar o cliente deste orçamento para ${cli.razao_social} (${cli.uf})?\nIsso atualizará todos os itens existentes.`)) {
        // AJAX Update
        const formData = new FormData();
        formData.append('num_orcamento', '<?= $num_orcamento ?>');
        formData.append('razao_social', cli.razao_social);
        formData.append('uf', cli.uf);

        fetch('atualizar_cliente_orcamento.php', {
          method: 'POST',
          body: formData
        })
          .then(r => r.json())
          .then(data => {
            if (data.success) {
              alert('Cliente atualizado com sucesso!');
              location.reload();
            } else {
              alert('Erro ao atualizar cliente: ' + (data.message || 'Erro desconhecido'));
            }
          })
          .catch(e => alert('Erro na requisição: ' + e));
      }
    }

    // --- Price List Logic ---
    const modalPriceList = new bootstrap.Modal(document.getElementById('modalPriceList'));

    function fecharModalPriceList() {
      modalPriceList.hide();
    }

    function verPrecoLista() {
      const codigo = document.getElementById('itemCodigo').value;
      const produto = document.getElementById('itemProduto').value;
      const dolarStr = document.getElementById('current_dolar').value;
      const dolar = parseFloat(dolarStr.replace(',', '.')) || 0;

      if (!codigo) {
        alert("Selecione um produto primeiro.");
        return;
      }

      // Preenche cabeçalho do modal
      document.getElementById('plProduto').textContent = produto;
      document.getElementById('plCodigo').textContent = codigo.substring(0, 9);
      document.getElementById('plPtax').textContent = dolarStr || 'Não definida';

      // Limpa tabela
      const tbody = document.getElementById('plTableBody');
      tbody.innerHTML = '<tr><td colspan="7" class="text-center">Buscando...</td></tr>';

      // Abre modal
      modalPriceList.show();

      // Busca dados
      fetch(`buscar_preco_lista.php?codigo=${encodeURIComponent(codigo)}`)
        .then(res => res.json())
        .then(data => {
          tbody.innerHTML = '';

          if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">Nenhum preço de lista encontrado para este código (9 primeiros dígitos).</td></tr>';
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

    function usarPrecoLista(precoUSD) {
      // Atualiza campo Preço Net (USD)
      document.getElementById('itemPrecoNet').value = precoUSD.toFixed(4).replace('.', ',');
      // Recalcula Preço Full
      calcularPrecoItem();
      // Fecha modal
      modalPriceList.hide();
    }

    // Fetch PTAX on load
    document.addEventListener('DOMContentLoaded', () => {
      fetch("ptax.php")
        .then(response => response.json())
        .then(data => {
          if (data && data.value && data.value.length > 0) {
            const ptaxVenda = parseFloat(data.value[0].cotacaoVenda).toFixed(4).replace('.', ',');
            document.getElementById('current_dolar').value = ptaxVenda;
          }
        })
        .catch(console.error);
    });

  </script>
</body>

</html>