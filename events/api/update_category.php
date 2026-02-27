<?php
/**
 * API para atualizar a categoria de uma despesa manualmente
 * 
 * Permite que o usuário edite a categoria automaticamente atribuída
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$response = ['success' => false, 'message' => ''];

try {
    // Lê os dados enviados via POST
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id']) || !isset($data['categoria'])) {
        throw new Exception('Parâmetros obrigatórios ausentes (id, categoria).');
    }

    $id = intval($data['id']);
    $categoria = $data['categoria'];

    // Valida a categoria
    $categoriasValidas = ['Passagem Aérea', 'Hotel', 'Seguro', 'Transporte', 'Outros', 'Não Categorizado'];
    if (!in_array($categoria, $categoriasValidas)) {
        throw new Exception('Categoria inválida.');
    }

    // Atualiza a categoria e marca como editado manualmente
    $stmt = $conn->prepare("
        UPDATE viagem_express_expenses 
        SET categoria_despesa = ?, 
            categoria_auto = 0
        WHERE id = ?
    ");

    $stmt->bind_param('si', $categoria, $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Categoria atualizada com sucesso!';
        } else {
            throw new Exception('Nenhum registro foi atualizado. Verifique o ID.');
        }
    } else {
        throw new Exception('Erro ao executar a atualização.');
    }

    $stmt->close();

} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
?>