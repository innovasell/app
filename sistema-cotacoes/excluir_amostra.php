<?php
session_start();
date_default_timezone_set('America/Sao_Paulo'); // Define fuso horário (boa prática)
require_once 'conexao.php'; // Conexão PDO

// --- FUNÇÃO DE LOG (Copie ou inclua de outro lugar) ---
function logToFile($message, $logFileName = 'erroslog.txt')
{
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
    // Não logamos aqui, pois o acesso não é necessariamente malicioso, só não logado
    header('Location: index.html');
    exit();
}

// 2. Obter e Validar ID do Pedido da URL (via GET)
$pedido_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($pedido_id === false || $pedido_id <= 0) {
    $_SESSION['message'] = "Erro: ID do pedido inválido ou não fornecido para exclusão.";
    $_SESSION['message_type'] = "danger";
    logToFile("Tentativa de exclusão com ID inválido: " . print_r($_GET['id'] ?? 'N/A', true) . " por usuário: " . ($_SESSION['representante_email'] ?? 'N/A'));
    header('Location: consultar_amostras.php');
    exit();
}

// 3. (OPCIONAL MAS RECOMENDADO) Verificação de Autorização
//    Aqui você verificaria se o usuário logado tem permissão para excluir ESTE pedido.
//    Exemplo: O usuário é admin OU o email da sessão é igual ao 'responsavel_pedido' do pedido?
/*
try {
    $stmt_check = $pdo->prepare("SELECT responsavel_pedido FROM pedidos_amostra WHERE id = :id");
    $stmt_check->bindParam(':id', $pedido_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $owner_email = $stmt_check->fetchColumn();

    $is_admin = isset($_SESSION['admin']) && $_SESSION['admin'] == 1; // Assume que 'admin' = 1 na sessão para admins
    $is_owner = ($owner_email === $_SESSION['representante_email']);

    if (!$is_admin && !$is_owner) {
        $_SESSION['message'] = "Erro: Você não tem permissão para excluir este pedido.";
        $_SESSION['message_type'] = "danger";
        logToFile("Tentativa de exclusão NÃO AUTORIZADA do pedido ID {$pedido_id} por usuário: " . $_SESSION['representante_email']);
        header('Location: consultar_amostras.php');
        exit();
    }
    // Se chegou aqui, está autorizado

} catch (PDOException $e) {
     logToFile("ERRO PDOException ao verificar autorização para excluir pedido ID {$pedido_id}: " . $e->getMessage());
     $_SESSION['message'] = "Erro ao verificar permissões. Tente novamente.";
     $_SESSION['message_type'] = "danger";
     header('Location: consultar_amostras.php');
     exit();
}
*/
// --- Fim da verificação de autorização (descomente e ajuste se necessário) ---


// 4. Executar a Exclusão
try {
    // Prepara o comando DELETE para a tabela principal
    // O ON DELETE CASCADE cuidará da tabela itens_pedido_amostra
    $sql = "DELETE FROM pedidos_amostra WHERE id = :pedido_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);

    // Executa a exclusão
    $success = $stmt->execute();

    // Verifica se alguma linha foi realmente afetada (excluída)
    if ($success && $stmt->rowCount() > 0) {
        $_SESSION['message'] = "Pedido de amostra ID {$pedido_id} excluído com sucesso.";
        $_SESSION['message_type'] = "success";
        logToFile("Pedido ID {$pedido_id} excluído com sucesso por usuário: " . ($_SESSION['representante_email'] ?? 'N/A'));
    } else if ($success && $stmt->rowCount() === 0) {
        // O comando executou, mas nenhuma linha foi deletada (ID não encontrado?)
        $_SESSION['message'] = "Aviso: Nenhum pedido de amostra encontrado com o ID {$pedido_id} para excluir.";
        $_SESSION['message_type'] = "warning";
        logToFile("Tentativa de exclusão do pedido ID {$pedido_id}, mas ID não encontrado. Usuário: " . ($_SESSION['representante_email'] ?? 'N/A'));
    } else {
        // A execução falhou por algum motivo não capturado pelo PDOException (raro)
        throw new Exception("Comando DELETE executado, mas retornou falha sem PDOException.");
    }

} catch (PDOException $e) {
    // Erro durante a execução do DELETE
    logToFile("ERRO PDOException ao EXCLUIR pedido ID {$pedido_id}: " . $e->getMessage());
    $_SESSION['message'] = "Erro ao excluir o pedido do banco de dados: <br><pre>" . htmlspecialchars($e->getMessage()) . "</pre>"; // Mostra erro detalhado para debug
    // $_SESSION['message'] = "Erro ao excluir o pedido do banco de dados. Verifique os logs."; // Mensagem genérica
    $_SESSION['message_type'] = "danger";

} catch (Exception $e) {
    // Outros erros inesperados
    logToFile("ERRO Exception GERAL ao EXCLUIR pedido ID {$pedido_id}: " . $e->getMessage());
    $_SESSION['message'] = "Ocorreu um erro inesperado durante a exclusão: " . htmlspecialchars($e->getMessage());
    $_SESSION['message_type'] = "danger";
}

// 5. Redirecionar de volta para a lista
header('Location: pesquisar_amostras.php');
exit();

?>