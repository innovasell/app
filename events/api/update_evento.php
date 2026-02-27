<?php
/**
 * API para atualizar o campo evento_visita de uma despesa
 */

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

try {
    // Usa conexão centralizada
    require_once __DIR__ . '/../conexao.php';

    // Lê dados do POST
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id']) || !isset($input['evento_visita'])) {
        throw new Exception('Parâmetros inválidos. ID e evento_visita são obrigatórios.');
    }

    $id = intval($input['id']);
    $eventoVisita = trim($input['evento_visita']);
    $numFatura = isset($input['num_fatura']) ? trim($input['num_fatura']) : null;
    $produto = isset($input['produto']) ? trim($input['produto']) : null;
    $passageiro = isset($input['passageiro']) ? trim($input['passageiro']) : null;
    $dtEmissao = isset($input['dt_emissao']) ? trim($input['dt_emissao']) : null;
    $total = isset($input['total']) ? floatval($input['total']) : null;

    // Atualiza o registro
    $sql = "UPDATE viagem_express_expenses 
            SET evento_visita = ?, 
                num_fatura = ?,
                produto = ?,
                passageiro = ?,
                dt_emissao = ?,
                total = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar statement: ' . $conn->error);
    }

    $stmt->bind_param('sssssdi', $eventoVisita, $numFatura, $produto, $passageiro, $dtEmissao, $total, $id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Detalhes atualizados com sucesso!';
    } else {
        throw new Exception('Erro ao atualizar: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("ERRO UPDATE EVENTO: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>