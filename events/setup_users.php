<?php
/**
 * Setup de usuários - Criar tabela e usuário root
 * Execute este arquivo UMA VEZ para configurar o sistema
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
$response = ['success' => false, 'message' => '', 'data' => []];

try {
    // Cria tabela de usuários
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql);
    $response['data'][] = 'Tabela users criada/verificada';

    // Verifica se já existe o usuário root
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE email = ?");
    $email = 'hector.hansen@innovasell.com.br';
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['c'] == 0) {
        // Cria usuário root
        $name = 'Hector Hansen';
        $password_hash = password_hash('Invti@169', PASSWORD_DEFAULT);
        $role = 'admin';

        $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $name, $email, $password_hash, $role);
        $stmt->execute();

        $response['data'][] = 'Usuário root criado com sucesso';
    } else {
        $response['data'][] = 'Usuário root já existe';
    }

    $response['success'] = true;
    $response['message'] = 'Setup concluído com sucesso!';

} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
?>