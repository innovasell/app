<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json');

// Check admin permission
if (!isset($_SESSION['grupo']) || $_SESSION['grupo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$id = $_POST['id'] ?? '';
$nome = trim($_POST['nome'] ?? '');
$sobrenome = trim($_POST['sobrenome'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$senha = $_POST['senha'] ?? '';
$grupo = $_POST['grupo'] ?? 'geral';
$admin = isset($_POST['admin']) ? 1 : 0;

// Validations
if (empty($nome) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Nome e Email são obrigatórios.']);
    exit;
}

try {
    if (empty($id)) {
        // CREATE
        if (empty($senha)) {
            echo json_encode(['success' => false, 'message' => 'Senha obrigatória para novo usuário.']);
            exit;
        }

        // Check email exists
        $stmtChk = $pdo->prepare("SELECT id FROM cot_representante WHERE email = ?");
        $stmtChk->execute([$email]);
        if ($stmtChk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email já cadastrado.']);
            exit;
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $sql = "INSERT INTO cot_representante (nome, sobrenome, email, telefone, senha, grupo, admin, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $sobrenome, $email, $telefone, $senhaHash, $grupo, $admin]);

    } else {
        // UPDATE

        // Check email conflict
        $stmtChk = $pdo->prepare("SELECT id FROM cot_representante WHERE email = ? AND id != ?");
        $stmtChk->execute([$email, $id]);
        if ($stmtChk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email já utilizado por outro usuário.']);
            exit;
        }

        $sql = "UPDATE cot_representante SET nome=?, sobrenome=?, email=?, telefone=?, grupo=?, admin=?";
        $params = [$nome, $sobrenome, $email, $telefone, $grupo, $admin];

        if (!empty($senha)) {
            $sql .= ", senha=?";
            $params[] = password_hash($senha, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id=?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro DB: ' . $e->getMessage()]);
}
?>