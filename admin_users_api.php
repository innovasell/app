<?php
// admin_users_api.php
session_start();
require_once 'site_conexao.php';

header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['sso_user']) || $_SESSION['sso_user']['admin'] != 1) {
    echo json_encode(['success' => false, 'error' => 'Acesso Negado']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'list') {
        $sql = "SELECT id, nome, sobrenome, email, admin, force_changepass, 
                acesso_expedicao, acesso_cotacoes, acesso_faq, acesso_comissoes, acesso_formulas, acesso_viagens 
                FROM cot_representante ORDER BY nome";
        $res = $conn->query($sql);
        $users = [];
        while ($row = $res->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode($users);
    } elseif ($action === 'toggle') {
        $id = (int) $_POST['id'];
        $perm = $_POST['perm']; // Nome da coluna
        $val = (int) $_POST['val'];

        // Whitelist de colunas permitidas para evitar SQL Injection
        $allowed = ['acesso_expedicao', 'acesso_cotacoes', 'acesso_faq', 'acesso_comissoes', 'acesso_formulas', 'acesso_viagens'];
        if (!in_array($perm, $allowed)) {
            throw new Exception("Permissão inválida");
        }

        $stmt = $conn->prepare("UPDATE cot_representante SET $perm = ? WHERE id = ?");
        $stmt->bind_param("ii", $val, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception($conn->error);
        }
    } elseif ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $nome = $_POST['nome'];
        $sobrenome = $_POST['sobrenome'];
        $email = $_POST['email'];
        $senha = $_POST['senha'] ?? '';
        $admin = isset($_POST['admin']) ? 1 : 0;
        $force = isset($_POST['force_changepass']) ? 1 : 0;

        if ($id) {
            // Update
            $sql = "UPDATE cot_representante SET nome=?, sobrenome=?, email=?, admin=?, force_changepass=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssiii", $nome, $sobrenome, $email, $admin, $force, $id);
            $stmt->execute();

            // Senha opcional no update
            if (!empty($senha)) {
                $hash = password_hash($senha, PASSWORD_BCRYPT);
                $conn->query("UPDATE cot_representante SET senha = '$hash' WHERE id = " . (int) $id);
            }
        } else {
            // Insert
            if (empty($senha))
                $senha = 'Innova@2025'; // Senha padrão se vazia
            $hash = password_hash($senha, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("INSERT INTO cot_representante (nome, sobrenome, email, senha, admin, force_changepass) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssii", $nome, $sobrenome, $email, $hash, $admin, $force);
            $stmt->execute();
        }

        echo json_encode(['success' => true]);
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        // Proteção para não se auto-deletar
        if ($id == $_SESSION['sso_user']['id']) {
            throw new Exception("Você não pode excluir a si mesmo.");
        }
        $conn->query("DELETE FROM cot_representante WHERE id = $id");
        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>