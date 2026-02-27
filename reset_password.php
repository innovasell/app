<?php
// reset_password.php - Redefinir a senha com Token
session_start();
require_once 'site_conexao.php';

$msg = '';
$msgType = '';
$token = $_GET['token'] ?? '';
$validToken = false;

// Validar Token
if ($token) {
    $stmt = $conn->prepare("SELECT id FROM cot_representante WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $validToken = true;
    } else {
        $msg = "Este link é inválido ou expirou.";
        $msgType = "danger";
    }
}

// Processar Nova Senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $pass1 = $_POST['pass1'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';

    if ($pass1 === $pass2 && !empty($pass1)) {
        if (strlen($pass1) < 6) {
            $msg = "A senha deve ter pelo menos 6 caracteres.";
            $msgType = "warning";
        } else {
            // Atualizar senha com hash seguro
            $hash = password_hash($pass1, PASSWORD_BCRYPT);

            // Limpar token e atualizar senha
            $upd = $conn->prepare("UPDATE cot_representante SET senha = ?, reset_token = NULL, reset_expires = NULL, force_changepass = 0 WHERE reset_token = ?");
            $upd->bind_param("ss", $hash, $token);

            if ($upd->execute()) {
                $msg = "Senha alterada com sucesso! Você será redirecionado...";
                $msgType = "success";
                echo "<meta http-equiv='refresh' content='3;url=login.php'>";
            } else {
                $msg = "Erro ao atualizar senha.";
                $msgType = "danger";
            }
        }
    } else {
        $msg = "As senhas não coincidem.";
        $msgType = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Redefinir Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .card-custom {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            background: white;
        }
    </style>
</head>

<body>
    <div class="card-custom">
        <h4 class="text-center mb-4">Nova Senha</h4>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?> small">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <?php if ($validToken && $msgType !== 'success'): ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nova Senha</label>
                    <input type="password" name="pass1" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar Senha</label>
                    <input type="password" name="pass2" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-success w-100">Salvar Senha</button>
            </form>
        <?php elseif (!$validToken): ?>
            <div class="text-center">
                <a href="recover_password.php" class="btn btn-primary">Solicitar Novo Link</a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>