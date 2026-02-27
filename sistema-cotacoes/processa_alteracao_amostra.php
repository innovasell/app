<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once 'conexao.php'; // Conexão PDO

// --- FUNÇÃO DE LOG (Copie ou inclua de outro lugar) ---
function logToFile($message, $logFileName = 'erroslog.txt') {
    $logFilePath = __DIR__ . '/' . $logFileName;
    $timestamp = date("Y-m-d H:i:s");
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    $logEntry = "[{$timestamp}] " . $message . PHP_EOL;
    @file_put_contents($logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
}
// --- FIM FUNÇÃO LOG ---

// 1. Verificar Login
if (!isset($_SESSION['representante_email'])) {
    $_SESSION['message'] = "Erro: Acesso não autorizado.";
    $_SESSION['message_type'] = "danger";
    header('Location: index.html');
    exit();
}

// 2. Verificar Método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = "Erro: Método inválido.";
    $_SESSION['message_type'] = "danger";
    header('Location: consultar_amostras.php');
    exit();
}

// ===========================================================
// === 3. RECEBER DADOS DO FORMULÁRIO DE ALTERAÇÃO ===
// ===========================================================
$pedido_id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);

// Dados gerais (editáveis)
$contato_cliente = trim($_POST['cliente_contato'] ?? '');
$email_contato = filter_input(INPUT_POST, 'cliente_email', FILTER_VALIDATE_EMAIL);
$telefone_contato = trim($_POST['cliente_telefone'] ?? '');
$info_projeto = trim($_POST['info_projeto'] ?? '');
$etapa_projeto = trim($_POST['etapa_projeto'] ?? '');
$data_limite_str = trim($_POST['data_limite'] ?? '');
$autorizado_por_email = filter_input(INPUT_POST, 'autorizado_por', FILTER_VALIDATE_EMAIL);

// --- Itens Existentes ---
// Precisamos dos IDs dos itens para saber quais atualizar ou excluir
$item_ids_existentes = $_POST['item_id_existente'] ?? [];
// Demais dados dos itens existentes (precisam ter a mesma ordem e número de elementos que $item_ids_existentes)
$quantidades_existentes = $_POST['quantidade_existente'] ?? [];
$fabricantes_existentes = $_POST['fabricante_existente'] ?? [];
$estoques_existentes = $_POST['estoque_existente'] ?? [];
$fracionamentos_existentes = $_POST['fracionamento_existente'] ?? [];
$produto_ids_existentes = $_POST['produto_id_existente'] ?? []; // Para referência, se necessário

// --- Itens Novos ---
$produto_ids_novos = $_POST['produto_id_novo'] ?? [];
// Demais dados dos itens novos
$quantidades_novos = $_POST['quantidade_novo'] ?? [];
$fabricantes_novos = $_POST['fabricante_novo'] ?? [];
$estoques_novos = $_POST['estoque_novo'] ?? [];
$fracionamentos_novos = $_POST['fracionamento_novo'] ?? [];

// Log para debug (opcional)
// logToFile("Dados recebidos para alteração do pedido ID {$pedido_id}: " . print_r($_POST, true));
// ===========================================================


// ===========================================================
// === 4. VALIDAÇÕES DOS DADOS RECEBIDOS ===
// ===========================================================
$errors = [];
$data_limite_db = null;

if ($pedido_id === false || $pedido_id <= 0) {
    $errors[] = "ID do pedido inválido ou não fornecido.";
    // Se o ID for inválido, não adianta continuar
     $_SESSION['message'] = implode("<br>", $errors);
     $_SESSION['message_type'] = "danger";
     header('Location: consultar_amostras.php');
     exit();
}
if ($autorizado_por_email === false) { $errors[] = "Aprovador inválido ou não selecionado."; }
if (!empty($_POST['cliente_email']) && !empty($_POST['cliente_email']) && $email_contato === false) { $errors[] = "Formato do E-mail do contato inválido."; }

// Validação da data limite
if (!empty($data_limite_str)) {
    $data_limite_obj = DateTime::createFromFormat('Y-m-d', $data_limite_str);
    if ($data_limite_obj === false) { $errors[] = "Formato da Data Limite inválido."; }
    else { $data_limite_db = $data_limite_obj->format('Y-m-d'); }
}

// Validações de consistência e dados dos ITENS EXISTENTES
$num_itens_existentes = count($item_ids_existentes);
if ($num_itens_existentes > 0 && (
    count($quantidades_existentes) !== $num_itens_existentes || count($fabricantes_existentes) !== $num_itens_existentes ||
    count($estoques_existentes) !== $num_itens_existentes || count($fracionamentos_existentes) !== $num_itens_existentes
)) {
    $errors[] = "Inconsistência nos dados dos itens existentes.";
} else {
    for ($i = 0; $i < $num_itens_existentes; $i++) {
        if (!isset($quantidades_existentes[$i]) || !is_numeric($quantidades_existentes[$i]) || floatval($quantidades_existentes[$i]) <= 0) { $errors[] = "Quantidade inválida para o item existente ID " . htmlspecialchars($item_ids_existentes[$i]); }
        if (!isset($estoques_existentes[$i]) || !in_array($estoques_existentes[$i], ['SIM', 'NÃO'])) { $errors[] = "Valor inválido para 'Estoque' no item existente ID " . htmlspecialchars($item_ids_existentes[$i]); }
        if (!isset($fracionamentos_existentes[$i]) || !in_array($fracionamentos_existentes[$i], ['SIM', 'NÃO'])) { $errors[] = "Valor inválido para 'Fracionamento' no item existente ID " . htmlspecialchars($item_ids_existentes[$i]); }
    }
}

// Validações de consistência e dados dos ITENS NOVOS
$num_itens_novos = count($produto_ids_novos);
if ($num_itens_novos > 0 && (
    count($quantidades_novos) !== $num_itens_novos || count($fabricantes_novos) !== $num_itens_novos ||
    count($estoques_novos) !== $num_itens_novos || count($fracionamentos_novos) !== $num_itens_novos
)) {
    $errors[] = "Inconsistência nos dados dos itens novos.";
} else {
    for ($i = 0; $i < $num_itens_novos; $i++) {
        if (!isset($produto_ids_novos[$i]) || !filter_var($produto_ids_novos[$i], FILTER_VALIDATE_INT) || $produto_ids_novos[$i] <= 0) { $errors[] = "ID de produto inválido para um novo item."; }
        if (!isset($quantidades_novos[$i]) || !is_numeric($quantidades_novos[$i]) || floatval($quantidades_novos[$i]) <= 0) { $errors[] = "Quantidade inválida para um novo item (Produto ID " . htmlspecialchars($produto_ids_novos[$i]) . ")."; }
        if (!isset($estoques_novos[$i]) || !in_array($estoques_novos[$i], ['SIM', 'NÃO'])) { $errors[] = "Valor inválido para 'Estoque' em um novo item."; }
        if (!isset($fracionamentos_novos[$i]) || !in_array($fracionamentos_novos[$i], ['SIM', 'NÃO'])) { $errors[] = "Valor inválido para 'Fracionamento' em um novo item."; }
    }
}
// ===========================================================


// 5. Se houver erros de validação, redireciona de volta
if (!empty($errors)) {
    $_SESSION['message'] = "Erro ao validar os dados:<br>" . implode("<br>", $errors);
    $_SESSION['message_type'] = "danger";
    logToFile("Erro de validação na alteração do pedido ID {$pedido_id}: " . implode("; ", $errors));
    // Redireciona de volta para a página de alteração
    header('Location: alterar_amostra.php?id=' . $pedido_id);
    exit();
}

// --- BLOCO PRINCIPAL: Alterações no Banco de Dados ---
$pdo->beginTransaction();
try {

    // 1. Atualizar Dados Gerais do Pedido (`pedidos_amostra`)
    $sql_update_pedido = "UPDATE pedidos_amostra SET
                            contato_cliente = :contato_cliente,
                            email_contato = :email_contato,
                            telefone_contato = :telefone_contato,
                            info_projeto = :info_projeto,
                            etapa_projeto = :etapa_projeto,
                            data_limite = :data_limite,
                            autorizado_por = :autorizado_por
                          WHERE id = :pedido_id";
    $stmt_update_pedido = $pdo->prepare($sql_update_pedido);
    $stmt_update_pedido->bindParam(':contato_cliente', $contato_cliente);
    $stmt_update_pedido->bindParam(':email_contato', $email_contato);
    $stmt_update_pedido->bindParam(':telefone_contato', $telefone_contato);
    $stmt_update_pedido->bindParam(':info_projeto', $info_projeto);
    $stmt_update_pedido->bindParam(':etapa_projeto', $etapa_projeto);
    $stmt_update_pedido->bindParam(':data_limite', $data_limite_db); // Usa valor formatado ou null
    $stmt_update_pedido->bindParam(':autorizado_por', $autorizado_por_email);
    $stmt_update_pedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmt_update_pedido->execute();
    logToFile("Pedido principal ID {$pedido_id} atualizado.");

    // 2. Deletar Itens Removidos
    // Busca os IDs dos itens que *estão* no banco para este pedido
    $stmt_db_items = $pdo->prepare("SELECT id FROM itens_pedido_amostra WHERE id_pedido_amostra = :pedido_id");
    $stmt_db_items->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmt_db_items->execute();
    $db_item_ids = $stmt_db_items->fetchAll(PDO::FETCH_COLUMN, 0); // Pega só a coluna de IDs

    // Garante que $item_ids_existentes é um array (mesmo que vazio)
    $form_item_ids = array_map('intval', $item_ids_existentes); // Converte para inteiros para comparação

    // Encontra os IDs que estão no banco mas NÃO vieram do formulário
    $ids_to_delete = array_diff($db_item_ids, $form_item_ids);

    if (!empty($ids_to_delete)) {
        // Cria placeholders para a cláusula IN (?,?,?)
        $delete_placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
        $sql_delete_items = "DELETE FROM itens_pedido_amostra WHERE id_pedido_amostra = ? AND id IN ($delete_placeholders)";
        $stmt_delete_items = $pdo->prepare($sql_delete_items);
        // Monta o array de parâmetros: [pedido_id, id_para_deletar_1, id_para_deletar_2, ...]
        $delete_params = array_merge([$pedido_id], array_values($ids_to_delete));
        $stmt_delete_items->execute($delete_params);
        logToFile("Itens deletados para pedido ID {$pedido_id}: " . implode(', ', $ids_to_delete));
    }

    // 3. Atualizar Itens Existentes
    if ($num_itens_existentes > 0) {
        $sql_update_item = "UPDATE itens_pedido_amostra SET
                                quantidade = :quantidade,
                                fabricante = :fabricante,
                                disponivel_estoque = :disponivel_estoque,
                                necessita_fracionamento = :necessita_fracionamento
                            WHERE id = :item_id AND id_pedido_amostra = :pedido_id"; // Segurança extra
        $stmt_update_item = $pdo->prepare($sql_update_item);

        for ($i = 0; $i < $num_itens_existentes; $i++) {
            $stmt_update_item->bindParam(':quantidade', $quantidades_existentes[$i]);
            $stmt_update_item->bindParam(':fabricante', $fabricantes_existentes[$i]);
            $stmt_update_item->bindParam(':disponivel_estoque', $estoques_existentes[$i]);
            $stmt_update_item->bindParam(':necessita_fracionamento', $fracionamentos_existentes[$i]);
            $stmt_update_item->bindParam(':item_id', $item_ids_existentes[$i], PDO::PARAM_INT);
            $stmt_update_item->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT); // Verifica se o item pertence ao pedido
            $stmt_update_item->execute();
        }
        logToFile("Itens existentes atualizados para pedido ID {$pedido_id}.");
    }

    // 4. Inserir Itens Novos
    if ($num_itens_novos > 0) {
        $sql_insert_item = "INSERT INTO itens_pedido_amostra
                               (id_pedido_amostra, id_produto, quantidade, fabricante, disponivel_estoque, necessita_fracionamento)
                             VALUES
                               (:id_pedido_amostra, :id_produto, :quantidade, :fabricante, :disponivel_estoque, :necessita_fracionamento)";
        $stmt_insert_item = $pdo->prepare($sql_insert_item);

        for ($i = 0; $i < $num_itens_novos; $i++) {
            $stmt_insert_item->bindParam(':id_pedido_amostra', $pedido_id, PDO::PARAM_INT);
            $stmt_insert_item->bindParam(':id_produto', $produto_ids_novos[$i], PDO::PARAM_INT);
            $stmt_insert_item->bindParam(':quantidade', $quantidades_novos[$i]);
            $stmt_insert_item->bindParam(':fabricante', $fabricantes_novos[$i]);
            $stmt_insert_item->bindParam(':disponivel_estoque', $estoques_novos[$i]);
            $stmt_insert_item->bindParam(':necessita_fracionamento', $fracionamentos_novos[$i]);
            $stmt_insert_item->execute();
        }
        logToFile("Itens novos inseridos para pedido ID {$pedido_id}.");
    }

    // Se tudo deu certo, comita a transação
    $pdo->commit();
    logToFile("Alterações no pedido ID {$pedido_id} commitadas com sucesso.");
    $_SESSION['message'] = "Pedido de amostra Nº " . htmlspecialchars($_POST['numero_pedido'] ?? $pedido_id) . " alterado com sucesso!";
    $_SESSION['message_type'] = "success";
    header('Location: consultar_amostras.php'); // Volta para a lista
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    logToFile("ERRO PDOException ao ALTERAR Pedido ID {$pedido_id}: " . $e->getMessage());
    $_SESSION['message'] = "Erro CRÍTICO ao salvar as alterações no banco: <br><pre>" . htmlspecialchars($e->getMessage()) . "</pre>"; // Mantém erro detalhado para debug
    $_SESSION['message_type'] = "danger";
    header('Location: alterar_amostra.php?id=' . $pedido_id); // Volta para a edição
    exit();

} catch (Exception $e) {
     if ($pdo->inTransaction()) { $pdo->rollBack(); }
     logToFile("ERRO Exception GERAL ao ALTERAR Pedido ID {$pedido_id}: " . $e->getMessage());
     $_SESSION['message'] = "Ocorreu um erro inesperado durante a alteração: " . htmlspecialchars($e->getMessage());
     $_SESSION['message_type'] = "danger";
     header('Location: alterar_amostra.php?id=' . $pedido_id); // Volta para a edição
     exit();
}

?>