<?php
/**
 * API para excluir uma despesa
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$response = ['success' => false, 'message' => ''];

try {
    // Lê o corpo da requisição JSON.
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id']) || empty($input['id'])) {
        throw new Exception('ID da despesa não fornecido.');
    }

    $id = intval($input['id']);

    // Prepara statement de exclusão
    $stmt = $conn->prepare("DELETE FROM viagem_express_expenses WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Despesa excluída com sucesso!';
        } else {
            throw new Exception('Despesa não encontrada.');
        }
    } else {
        throw new Exception('Erro ao excluir despesa: ' . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
?>