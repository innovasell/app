<?php
session_start();
require_once 'conexao.php';

// 1. Segurança: Verificar se o usuário está logado E se é administrador
if (!isset($_SESSION['representante_email']) || !isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
    $_SESSION['message'] = "Erro: Você não tem permissão para executar esta ação.";
    $_SESSION['message_type'] = "danger";
    header('Location: consultar_amostras.php');
    exit();
}

// 2. Validar os dados recebidos via GET
$pedido_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$novo_status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS);
$status_permitidos = ['Enviado', 'Cancelado'];

if (!$pedido_id || !$novo_status || !in_array($novo_status, $status_permitidos)) {
    $_SESSION['message'] = "Erro: Dados inválidos para atualização de status.";
    $_SESSION['message_type'] = "danger";
    header('Location: consultar_amostras.php');
    exit();
}

// 3. Atualizar o status no banco de dados
try {
    $sql = "UPDATE pedidos_amostra SET status = :status WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':status', $novo_status, PDO::PARAM_STR);
    $stmt->bindParam(':id', $pedido_id, PDO::PARAM_INT);
    $stmt->execute();

    $acao = ($novo_status === 'Enviado') ? 'aprovado' : 'reprovado';
    $_SESSION['message'] = "Pedido de amostra {$acao} com sucesso!";
    $_SESSION['message_type'] = "success";

} catch (PDOException $e) {
    $_SESSION['message'] = "Erro ao atualizar o status do pedido: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
}

// --- NOVIDADE: Lógica de Redirecionamento Inteligente ---
// Verifica se uma URL de retorno foi fornecida, senão usa um padrão seguro.
$redirect_url = $_GET['return_url'] ?? 'consultar_amostras.php';
// Validação simples para evitar redirecionamento aberto (open redirect)
if (parse_url($redirect_url, PHP_URL_HOST) === null) {
    header('Location: ' . $redirect_url);
} else {
    // Se a URL de retorno parecer suspeita (contiver um host), redireciona para a página padrão.
    header('Location: consultar_amostras.php');
}
exit();
?>