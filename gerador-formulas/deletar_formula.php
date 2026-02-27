<?php
// Suprime erros de PHP que poderiam quebrar o JSON. O erro real será capturado no 'catch'.
error_reporting(0);
ini_set('display_errors', 0);

// Define que a resposta será SEMPRE no formato JSON, a primeira coisa a ser feita.
header('Content-Type: application/json');
require_once 'config.php';

// Resposta padrão
$response = ['success' => false, 'message' => 'ID da formulação não fornecido ou inválido.'];

try {
    // 1. VERIFICAR SE UM ID FOI FORNECIDO E É VÁLIDO
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('ID da formulação não fornecido ou é inválido.');
    }
    $id = intval($_GET['id']);

    // 2. INICIAR TRANSAÇÃO
    $conn->begin_transaction();

    // A ORDEM DA EXCLUSÃO É IMPORTANTE (filhos e netos primeiro)

    // 2.1 DELETAR OS ATIVOS EM DESTAQUE (filho direto de 'formulacoes')
    $stmt_del_ativos = $conn->prepare("DELETE FROM ativos_destaque WHERE formulacao_id = ?");
    $stmt_del_ativos->bind_param("i", $id);
    $stmt_del_ativos->execute();
    
    // ====================== INÍCIO DA CORREÇÃO ======================
    
    // 2.2 DELETAR OS INGREDIENTES (netos)
    // Deleta todos os ingredientes que pertencem a fases de sub-formulações desta formulação.
    $stmt_del_ingredientes = $conn->prepare("
        DELETE i FROM ingredientes i
        JOIN fases f ON i.fase_id = f.id
        JOIN sub_formulacoes sf ON f.sub_formulacao_id = sf.id
        WHERE sf.formulacao_id = ?
    ");
    $stmt_del_ingredientes->bind_param("i", $id);
    $stmt_del_ingredientes->execute();
    
    // 2.3 DELETAR AS FASES (filhos de 'sub_formulacoes')
    // Deleta todas as fases que pertencem a sub-formulações desta formulação.
    $stmt_del_fases = $conn->prepare("
        DELETE f FROM fases f
        JOIN sub_formulacoes sf ON f.sub_formulacao_id = sf.id
        WHERE sf.formulacao_id = ?
    ");
    $stmt_del_fases->bind_param("i", $id);
    $stmt_del_fases->execute();
    
    // 2.4 DELETAR AS SUB-FORMULAÇÕES (filhos de 'formulacoes')
    $stmt_del_sub = $conn->prepare("DELETE FROM sub_formulacoes WHERE formulacao_id = ?");
    $stmt_del_sub->bind_param("i", $id);
    $stmt_del_sub->execute();

    // ======================= FIM DA CORREÇÃO ========================
    
    // 2.5 FINALMENTE, DELETAR A FORMULAÇÃO PRINCIPAL (pai de todos)
    $stmt_del_formula = $conn->prepare("DELETE FROM formulacoes WHERE id = ?");
    $stmt_del_formula->bind_param("i", $id);
    $stmt_del_formula->execute();
    
    // 3. SE TUDO DEU CERTO, CONFIRMAR AS ALTERAÇÕES
    $conn->commit();
    $response = ['success' => true, 'message' => 'Formulação excluída com sucesso.'];

} catch (Throwable $exception) { // Captura qualquer tipo de erro
    // Se estivermos em uma transação, desfaz tudo
    if ($conn->errno) {
        $conn->rollback();
    }
    // Define um código de erro do servidor
    http_response_code(500); 
    $response = ['success' => false, 'message' => $exception->getMessage()];
}

// Imprime a resposta como JSON e encerra o script
echo json_encode($response);
exit();
?>