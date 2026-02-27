<?php
/**
 * API de gerenciamento de usuários
 */

require_once '../auth.php';
require_once '../config.php';
require_admin(); // Apenas admins podem usar esta API

header('Content-Type: application/json; charset=utf-8');
$response = ['success' => false, 'message' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'create':
            // Criar novo usuário
            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';
            $role = $input['role'] ?? 'usuario';

            if (empty($name) || empty($email) || empty($password)) {
                throw new Exception('Preencha todos os campos obrigatórios.');
            }

            // Verifica se email já existe
            $stmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()['c'] > 0) {
                throw new Exception('Este email já está cadastrado.');
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $name, $email, $password_hash, $role);
            $stmt->execute();

            $response['success'] = true;
            $response['message'] = 'Usuário criado com sucesso!';
            break;

        case 'update':
            // Atualizar usuário
            $id = intval($input['id'] ?? 0);
            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';
            $role = $input['role'] ?? 'usuario';

            if ($id <= 0 || empty($name) || empty($email)) {
                throw new Exception('Dados inválidos.');
            }

            // Verifica se email já existe (em outro usuário)
            $stmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param('si', $email, $id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()['c'] > 0) {
                throw new Exception('Este email já está cadastrado.');
            }

            if (!empty($password)) {
                // Atualiza com senha
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password_hash = ?, role = ? WHERE id = ?");
                $stmt->bind_param('ssssi', $name, $email, $password_hash, $role, $id);
            } else {
                // Atualiza sem senha
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->bind_param('sssi', $name, $email, $role, $id);
            }

            $stmt->execute();

            $response['success'] = true;
            $response['message'] = 'Usuário atualizado com sucesso!';
            break;

        case 'delete':
            // Excluir usuário
            $id = intval($input['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID inválido.');
            }

            // Não pode excluir o próprio usuário
            if ($id == user()['id']) {
                throw new Exception('Você não pode excluir sua própria conta.');
            }

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            $response['success'] = true;
            $response['message'] = 'Usuário excluído com sucesso!';
            break;

        default:
            throw new Exception('Ação inválida.');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
?>