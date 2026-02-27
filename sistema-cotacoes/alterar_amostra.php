<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once 'conexao.php';

function logToFile($message, $logFileName = 'erroslog.txt') {
    $logFilePath = __DIR__ . '/' . $logFileName;
    $timestamp = date("Y-m-d H:i:s");
    if (is_array($message) || is_object($message)) { $message = print_r($message, true); }
    $logEntry = "[{$timestamp}] " . $message . PHP_EOL;
    @file_put_contents($logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
}

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}

// --- LÓGICA DE PERMISSÃO ADICIONADA ---
$isAdmin = (isset($_SESSION['admin']) && $_SESSION['admin'] == 1);
$campoAutorizadorDisabled = !$isAdmin ? 'disabled' : '';
// --- FIM DA LÓGICA ---

$pedido_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$pedido_id || $pedido_id <= 0) {
    $_SESSION['message'] = "Erro: ID do pedido inválido ou não fornecido.";
    $_SESSION['message_type'] = "danger";
    header('Location: consultar_amostras.php');
    exit();
}

$pedido = null;
$itens_pedido = [];
try {
    $sql_pedido = "SELECT pa.*, cc.razao_social, cc.cnpj
                   FROM pedidos_amostra pa
                   INNER JOIN cot_clientes cc ON pa.id_cliente = cc.id
                   WHERE pa.id = :pedido_id";
    $stmt_pedido = $pdo->prepare($sql_pedido);
    $stmt_pedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmt_pedido->execute();
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) { throw new Exception("Pedido de amostra com ID {$pedido_id} não encontrado."); }

    $sql_itens = "SELECT ipa.*, ce.produto as produto_nome, ce.unidade, ce.codigo
                  FROM itens_pedido_amostra ipa
                  INNER JOIN cot_estoque ce ON ipa.id_produto = ce.id
                  WHERE ipa.id_pedido_amostra = :pedido_id
                  ORDER BY ipa.id ASC";
    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmt_itens->execute();
    $itens_pedido = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException | Exception $e) {
    logToFile("ERRO ao buscar dados para alterar pedido ID {$pedido_id}: " . $e->getMessage());
    $_SESSION['message'] = "Erro ao carregar dados do pedido: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
    header('Location: consultar_amostras.php');
    exit();
}

$data_limite_form = !empty($pedido['data_limite']) ? date('Y-m-d', strtotime($pedido['data_limite'])) : '';
$responsavel_email_sessao = $_SESSION['representante_email'] ?? '';
$primeiro_nome_sessao = $_SESSION['representante_nome'] ?? '';
$sobrenome_sessao = $_SESSION['representante_sobrenome'] ?? '';
$responsavel_nome_completo = trim($primeiro_nome_sessao . ' ' . $sobrenome_sessao);
$responsavel_nome_final = empty($responsavel_nome_completo) ? $responsavel_email_sessao : ucwords(strtolower($responsavel_nome_completo));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Pedido de Amostra Nº <?php echo htmlspecialchars($pedido['numero_referencia'] ?? $pedido_id); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
     <style>
        .form-section { margin-bottom: 2rem; padding: 1.5rem; border: 1px solid #dee2e6; border-radius: 0.375rem; background-color: #f8f9fa; }
        label { font-weight: bold; }
        #displayClienteNome { background-color: #e9ecef; }
        #tabelaItensPedido input, #tabelaItensPedido select { min-width: 80px; }
    </style>
</head>
<body>

<div class="container mt-4">
    <h2>Alterar Pedido de Amostra (Nº: <?php echo htmlspecialchars($pedido['numero_referencia']); ?>)</h2>
    <hr>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo htmlspecialchars($_SESSION['message_type']); ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form id="formPedidoAmostra" action="processa_alteracao_amostra.php" method="POST">
        <input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>">
        <input type="hidden" name="numero_pedido" value="<?php echo htmlspecialchars($pedido['numero_referencia']); ?>">

        <div class="row mb-3 form-section">
             <div class="col-md-6">
                <label class="form-label">Responsável (Sessão Atual):</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($responsavel_nome_final); ?>" readonly>
                <input type="hidden" name="responsavel_original" value="<?php echo htmlspecialchars($pedido['responsavel_pedido']); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Data Criação:</label>
                <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i:s', strtotime($pedido['data_pedido'])); ?>" readonly>
            </div>
        </div>

        <div class="row mb-3 form-section">
             <legend>Dados do Cliente (Contato editável)</legend>
             <input type="hidden" id="idCliente" name="id_cliente" value="<?php echo htmlspecialchars($pedido['id_cliente']); ?>">
             <div class="col-md-12 mb-3">
                <label class="form-label">Cliente:</label>
                <input type="text" id="displayClienteNome" class="form-control" value="<?php echo htmlspecialchars($pedido['razao_social'] . ' (' . ($pedido['cnpj'] ?? 'Sem CNPJ') . ')'); ?>" readonly>
             </div>
             <div class="col-md-4 mb-3">
                <label for="cliente_contato" class="form-label">Contato:</label>
                <input type="text" id="cliente_contato" name="cliente_contato" class="form-control" value="<?php echo htmlspecialchars($pedido['contato_cliente'] ?? ''); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label for="cliente_email" class="form-label">E-mail:</label>
                <input type="email" id="cliente_email" name="cliente_email" class="form-control" value="<?php echo htmlspecialchars($pedido['email_contato'] ?? ''); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label for="cliente_telefone" class="form-label">Telefone:</label>
                <input type="text" id="cliente_telefone" name="cliente_telefone" class="form-control" value="<?php echo htmlspecialchars($pedido['telefone_contato'] ?? ''); ?>">
            </div>
        </div>

        <div class="row mb-3 form-section" id="secaoProdutos">
             <legend>Produtos da Amostra</legend>
             <div class="col-12 mb-3"><button type="button" class="btn btn-success" onclick="abrirModalProdutos()"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/></svg> Adicionar Novo Produto</button></div>
             <div class="col-12 table-responsive">
                 <table class="table table-bordered table-hover" id="tabelaItensPedido">
                     <thead class="table-light"><tr><th>Produto</th><th>Qtd.</th><th>Unid.</th><th>Fabricante</th><th>Estoque?</th><th>Fracionar?</th><th>Ação</th></tr></thead>
                     <tbody>
                         <?php if (empty($itens_pedido)): ?>
                             <tr id="nenhumProdutoRow"><td colspan="7" class="text-center text-muted">Nenhum produto neste pedido.</td></tr>
                         <?php else: ?>
                             <?php foreach ($itens_pedido as $item): ?>
                                 <tr>
                                     <td><?php echo htmlspecialchars($item['produto_nome']); ?><input type="hidden" name="item_id_existente[]" value="<?php echo $item['id']; ?>"><input type="hidden" name="produto_id_existente[]" value="<?php echo $item['id_produto']; ?>"><input type="hidden" name="produto_nome_existente[]" value="<?php echo htmlspecialchars($item['produto_nome']); ?>"><input type="hidden" name="codigo_produto_existente[]" value="<?php echo htmlspecialchars($item['codigo']); ?>"></td>
                                     <td><input type="number" name="quantidade_existente[]" value="<?php echo htmlspecialchars(number_format($item['quantidade'], 3, '.', '')); ?>" class="form-control form-control-sm" step="any" min="0.001" required></td>
                                     <td><?php echo htmlspecialchars($item['unidade']); ?><input type="hidden" name="unidade_existente[]" value="<?php echo htmlspecialchars($item['unidade']); ?>"></td>
                                     <td><input type="text" name="fabricante_existente[]" value="<?php echo htmlspecialchars($item['fabricante']); ?>" class="form-control form-control-sm"></td>
                                     <td><select name="estoque_existente[]" class="form-select form-select-sm" required><option value="SIM" <?php echo ($item['disponivel_estoque'] == 'SIM') ? 'selected' : ''; ?>>SIM</option><option value="NÃO" <?php echo ($item['disponivel_estoque'] == 'NÃO') ? 'selected' : ''; ?>>NÃO</option></select></td>
                                     <td><select name="fracionamento_existente[]" class="form-select form-select-sm" required><option value="SIM" <?php echo ($item['necessita_fracionamento'] == 'SIM') ? 'selected' : ''; ?>>SIM</option><option value="NÃO" <?php echo ($item['necessita_fracionamento'] == 'NÃO') ? 'selected' : ''; ?>>NÃO</option></select></td>
                                     <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removerLinhaProduto(this)" title="Remover este item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg></button></td>
                                 </tr>
                             <?php endforeach; ?>
                         <?php endif; ?>
                     </tbody>
                 </table>
             </div>
        </div>

         <div class="row mb-3 form-section">
            <legend>Informações Adicionais</legend>
             <div class="col-md-12 mb-3"><label for="info_projeto" class="form-label">Informações sobre o Projeto:</label><textarea id="info_projeto" name="info_projeto" class="form-control" rows="3"><?php echo htmlspecialchars($pedido['info_projeto'] ?? ''); ?></textarea></div>
             <div class="col-md-6 mb-3"><label for="etapa_projeto" class="form-label">Etapa do Projeto:</label><input type="text" id="etapa_projeto" name="etapa_projeto" class="form-control" value="<?php echo htmlspecialchars($pedido['etapa_projeto'] ?? ''); ?>"></div>
             <div class="col-md-6 mb-3"><label for="data_limite" class="form-label">Data Limite para Atendimento:</label><input type="date" id="data_limite" name="data_limite" class="form-control" value="<?php echo $data_limite_form; ?>"></div>
             <div class="col-md-12 mb-3">
                <label for="autorizado_por" class="form-label">Autorizado Por:</label>
                <select id="autorizado_por" name="autorizado_por" class="form-select" required <?= $campoAutorizadorDisabled ?>>
                    <option value="" disabled>Selecione...</option>
                    <?php 
                        $aprovadores = ['carina.apassite@innovasell.com.br' => 'Carina Apassite','selmo.araujo@innovasell.com.br' => 'Selmo Araujo','renaldo.rocha@innovasell.com.br' => 'Renaldo Rocha','ti@innovasell.com.br' => 'Hector Hansen']; 
                        $autorizado_atual = $pedido['autorizado_por'] ?? ''; 
                        foreach ($aprovadores as $email => $nome) { 
                            $selected = ($email == $autorizado_atual) ? 'selected' : ''; 
                            echo "<option value=\"" . htmlspecialchars($email) . "\" {$selected}>" . htmlspecialchars($nome) . " <" . htmlspecialchars($email) . "></option>"; 
                        } 
                    ?>
                </select>
                <?php if (!$isAdmin): ?>
                    <small class="form-text text-muted">Apenas administradores podem alterar o aprovador.</small>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4"><a href="consultar_amostras.php" class="btn btn-secondary">Cancelar</a><button type="submit" class="btn btn-primary"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-save" viewBox="0 0 16 16"><path d="M2 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H9.5a1 1 0 0 0-1 1v4.5h2a.5.5 0 0 1 .354.854l-2.5 2.5a.5.5 0 0 1-.708 0l-2.5-2.5A.5.5 0 0 1 5.5 6.5h2V2a2 2 0 0 1 2-2H14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h2.5a.5.5 0 0 1 0 1H2z"/></svg> Salvar Alterações</button></div>
    </form>
</div>

<div class="modal fade" id="modalProdutos" tabindex="-1" aria-labelledby="modalProdutosLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="modalProdutosLabel">Selecionar Produto</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
         <div class="input-group mb-3"><input type="text" id="buscaProduto" class="form-control" placeholder="Buscar por nome, código..."><button class="btn btn-outline-secondary" type="button" onclick="buscarProdutos()">Pesquisar</button></div>
         <div class="table-responsive"><table class="table table-bordered table-hover" id="tabelaProdutosModal"><thead><tr><th>Código</th><th>Produto</th><th>Unidade</th><th>Ação</th></tr></thead><tbody id="listaProdutos"><tr><td colspan="4">Digite para buscar...</td></tr></tbody></table></div>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="modalDetalhesProduto" tabindex="-1" aria-labelledby="modalDetalhesProdutoLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="modalDetalhesProdutoLabel">Adicionar Produto ao Pedido</h5></div>
      <div class="modal-body">
        <input type="hidden" id="produtoSelecionadoJson">
        <div class="mb-3"><label class="form-label">Produto:</label><p class="form-control-plaintext" id="modalProdutoNome"><strong>-- Nome --</strong></p></div>
        <div class="mb-3"><label for="modalProdutoQuantidade" class="form-label">Quantidade:</label><input type="number" class="form-control" id="modalProdutoQuantidade" step="any" min="0.001" required><small class="form-text text-muted">Ex: 1.5</small></div>
        <div class="mb-3"><label class="form-label">Unidade:</label><p class="form-control-plaintext" id="modalProdutoUnidade">-- Unid --</p></div>
        <div class="mb-3"><label class="form-label">Fabricante (Automático):</label><p class="form-control-plaintext" id="modalProdutoFabricante"><strong>-- Fab --</strong></p></div>
        <div class="mb-3"><label for="modalProdutoEstoque" class="form-label">DISPONIVEL EM ESTOQUE:</label><select class="form-select" id="modalProdutoEstoque" required><option value="" selected disabled>Selecione...</option><option value="SIM">SIM</option><option value="NÃO">NÃO</option></select></div>
        <div class="mb-3"><label for="modalProdutoFracionamento" class="form-label">NECESSITA FRACIONAMENTO:</label><select class="form-select" id="modalProdutoFracionamento" required><option value="" selected disabled>Selecione...</option><option value="SIM">SIM</option><option value="NÃO">NÃO</option></select></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" onclick="confirmarAdicaoProduto()">Confirmar Adição</button></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const mapaFabricantes={'001':'CODIF','002':'BIOLAND','003':'CITRÓLEO','004':'HOCK CHEMIE','005':'ECO-SHELL','006':'NATURAL AMAZON','007':'MPR','008':'KPT','009':'COPRA','010':'ARGILE DU VELAY','011':'CHEMLAND','012':'ETHOX','013':'GALACTIC','014':'HERRMAN','015':'RADIFIX','016':'WUHU','017':'SIDDARTH CARBOCHEM','018':'BH BIOTECH','019':'TC-USA','020':'TNJ','021':'ECHEMI','022':'SOHO ANECO','023':'COMPOMAT','024':'ANHUI HERRMAN','025':'SENTINALCO','026':'MICRO POWDER´S','027':'THE INNOVATION COMPANY','028':'JEDWARDS','029':'DISTRIOL','030':'SPEC CHEM','031':'KUMAR','032':'NATURAL HERB','033':'WATEC','034':'WENDA','035':'GRANASUR','036':'IPEL','037':'NANOGEM','038':'JOYVO','039':'MB SUGARS','040':'GREAF','041':'AIGLON','042':'FUMEI','043':'INTERFAT','044':'RESPHARMA','045':'CHOISUN BIOTECH','046':'GREEN BIO','047':'KONCEPTNUTRA','048':'RAWMAP','049':'AF SUTER','050':'VAN WANKUM INGREDIENTS','051':'DERMALAB','052':'SEKISUI','053':'SINOLION','054':'HAITIAN','055':'BAOFENG','056':'CARBONADO','057':'GRAN OILS','058':'CHARKIT CHEMICAL','059':'NATURAL OILS','060':'AROMA HOLLY','061':'FOCUS CHEM','062':'JIANGSU MIAOYI','063':'INTERCARE','064':'GREEN ANDINA','065':'ABC NANOTECH','066':'SALICYLATES','067':'CHEMAXCEL','068':'BISOR','069':'INFINITEC','070':'AROMAAZ','071':'ZHONGLAN','072':'HAIHANG','073':'A AZEVEDO','074':'BIOSYNTHETIC','075':'ONETECH SOLUTION','076':'PUERZAN','077':'SHANGHAI OLI ENTERPRISES','078':'COSROMA','079':'NUTRANOVO','080':'HERBALTEC','081':'Reachin Chemical','082':'Xi\'an DN Biology Co.,Ltd','083':'GUARAMEX / BRASCARBO','084':'KRAEBER & Co GmbH','085':'XI´AN GREEN SPRING TECHNOLOGY','086':'CHANGSHA STAHERB NATURAL','087':'NANOVEX','088':'LIPOMIZE','089':'KANGCARE','090':'WINKEY','091':'ENSINCE','092':'CARBONWAVE','093':'ACTERA','094':'TAUROS (VIPOTECH)','095':'TAEKYUNG','096':'GIGA FINE','097':'BERI PHARMA','098':'HNB BIO','099':'NORMACTIVE','100':'SF SCIENCE','101':'VIABLIFE','102':'COACHCHEM','103':'CITREFINE','104':'UNI POWDER','105':'ZHEJIANG NHU COMPANY LTD'};
    function getFabricantePorCodigo(codigoProduto){if(!codigoProduto||codigoProduto.length<3){return'N/A'}const prefixo=codigoProduto.substring(0,3);return mapaFabricantes[prefixo]||'Desconhecido'}
    function abrirModalProdutos(){document.getElementById('buscaProduto').value='';document.getElementById('listaProdutos').innerHTML='<tr><td colspan="4">Digite para buscar...</td></tr>';var modalProdutos=new bootstrap.Modal(document.getElementById('modalProdutos'));modalProdutos.show()}
    function buscarProdutos(){const termo=document.getElementById('buscaProduto').value.trim();const tbody=document.getElementById('listaProdutos');tbody.innerHTML='<tr><td colspan="4">Buscando...</td></tr>';if(termo.length<2){tbody.innerHTML='<tr><td colspan="4">Digite ao menos 2 caracteres...</td></tr>';return}
    fetch(`buscar_produtos.php?q=${encodeURIComponent(termo)}`).then(response=>{if(!response.ok){throw new Error(`HTTP error! status: ${response.status}`)}return response.json()}).then(produtos=>{tbody.innerHTML='';if(!Array.isArray(produtos)){throw new Error("Resposta inválida")}
    if(produtos.length===0){tbody.innerHTML='<tr><td colspan="4">Nenhum produto encontrado.</td></tr>';return}
    produtos.forEach(produto=>{const tr=document.createElement('tr');tr.innerHTML=`<td>${produto.codigo||produto.id||'N/A'}</td><td>${produto.produto||'Sem Nome'}</td><td>${produto.unidade||'N/A'}</td><td><button type="button" class="btn btn-sm btn-primary" onclick='selecionarProduto(${JSON.stringify(produto)})'>Selecionar</button></td>`;tbody.appendChild(tr)})}).catch(error=>{console.error('Erro ao buscar produtos:',error);tbody.innerHTML=`<tr><td colspan="4" class="text-danger">Erro ao buscar: ${error.message}</td></tr>`})}
    document.getElementById('buscaProduto').addEventListener('keypress',function(event){if(event.key==='Enter'){event.preventDefault();buscarProdutos()}});function selecionarProduto(produto){document.getElementById('produtoSelecionadoJson').value=JSON.stringify(produto);document.getElementById('modalProdutoNome').textContent=produto.produto||'Sem Nome';document.getElementById('modalProdutoUnidade').textContent=produto.unidade||'N/A';document.getElementById('modalProdutoFabricante').textContent=getFabricantePorCodigo(produto.codigo);document.getElementById('modalProdutoQuantidade').value='';document.getElementById('modalProdutoEstoque').value='';document.getElementById('modalProdutoFracionamento').value='';var modalDetalhes=new bootstrap.Modal(document.getElementById('modalDetalhesProduto'));modalDetalhes.show();var modalBuscaProdutos=bootstrap.Modal.getInstance(document.getElementById('modalProdutos'));if(modalBuscaProdutos){modalBuscaProdutos.hide()}}
    function confirmarAdicaoProduto(){const produtoJson=document.getElementById('produtoSelecionadoJson').value;if(!produtoJson){alert("Erro: Dados do produto não encontrados.");return}
    const produtoOriginal=JSON.parse(produtoJson);const quantidade=document.getElementById('modalProdutoQuantidade').value;const estoque=document.getElementById('modalProdutoEstoque').value;const fracionamento=document.getElementById('modalProdutoFracionamento').value;const fabricante=document.getElementById('modalProdutoFabricante').textContent;if(quantidade===""||isNaN(parseFloat(quantidade))||parseFloat(quantidade)<=0){alert("Quantidade inválida.");document.getElementById('modalProdutoQuantidade').focus();return}
    if(!estoque){alert("Selecione a disponibilidade em estoque.");document.getElementById('modalProdutoEstoque').focus();return}
    if(!fracionamento){alert("Selecione a necessidade de fracionamento.");document.getElementById('modalProdutoFracionamento').focus();return}
    adicionarProdutoAoPedido({id:produtoOriginal.id,nome:produtoOriginal.produto,unidade:produtoOriginal.unidade,quantidade:parseFloat(quantidade),fabricante:fabricante,estoque:estoque,fracionamento:fracionamento});var modalDetalhes=bootstrap.Modal.getInstance(document.getElementById('modalDetalhesProduto'));if(modalDetalhes){modalDetalhes.hide()}}
    function adicionarProdutoAoPedido(item){const tbody=document.getElementById('tabelaItensPedido').getElementsByTagName('tbody')[0];const nenhumProdutoRow=document.getElementById('nenhumProdutoRow');if(nenhumProdutoRow){nenhumProdutoRow.remove()}
    const newRow=tbody.insertRow();newRow.innerHTML=`<td>${item.nome}<input type="hidden" name="produto_id_novo[]" value="${item.id}"><input type="hidden" name="produto_nome_novo[]" value="${item.nome}"></td><td><input type="number" name="quantidade_novo[]" value="${item.quantidade.toFixed(3)}" class="form-control form-control-sm" step="any" min="0.001" required></td><td>${item.unidade||'N/A'}<input type="hidden" name="unidade_novo[]" value="${item.unidade||''}"></td><td><input type="text" name="fabricante_novo[]" value="${item.fabricante||''}" class="form-control form-control-sm"></td><td><select name="estoque_novo[]" class="form-select form-select-sm" required><option value="SIM" ${item.estoque==='SIM'?'selected':''}>SIM</option><option value="NÃO" ${item.estoque==='NÃO'?'selected':''}>NÃO</option></select></td><td><select name="fracionamento_novo[]" class="form-select form-select-sm" required><option value="SIM" ${item.fracionamento==='SIM'?'selected':''}>SIM</option><option value="NÃO" ${item.fracionamento==='NÃO'?'selected':''}>NÃO</option></select></td><td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removerLinhaProduto(this)" title="Remover este item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg></button></td>`}
    function removerLinhaProduto(button){const row=button.closest('tr');const tbody=row.parentNode;row.remove();if(tbody.rows.length===0){tbody.innerHTML='<tr id="nenhumProdutoRow"><td colspan="7" class="text-center text-muted">Nenhum produto neste pedido.</td></tr>'}}
</script>

</body>
</html>