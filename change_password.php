<?php
// change_password.php - Troca de senha obrigatória (force_changepass) ou voluntária
session_start();
require_once 'site_conexao.php';

if (!isset($_SESSION['sso_user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['sso_user'];
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['pass1'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';

    if ($pass1 === $pass2 && !empty($pass1)) {
        if (strlen($pass1) < 6) {
            $msg = "A senha deve ter pelo menos 6 caracteres.";
            $msgType = "warning";
        } else {
            $hash = password_hash($pass1, PASSWORD_BCRYPT);

            // Atualizar senha e remover flag de força
            $upd = $conn->prepare("UPDATE cot_representante SET senha = ?, force_changepass = 0 WHERE id = ?");
            $upd->bind_param("si", $hash, $user['id']);

            if ($upd->execute()) {
                $msg = "Senha atualizada com sucesso!";
                $msgType = "success";

                // Atualizar sessão local para tirar a flag se existisse
                // Mas a flag só é verificada no login. 
                // Redirect para index
                echo "<meta http-equiv='refresh' content='2;url=index.php'>";
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
    <title>Alterar Senha</title>
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
        <h4 class="text-center mb-4">Definir Senha</h4>
        <p class="text-muted small text-center">
            Para sua segurança, por favor defina uma nova senha.
        </p>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?> small">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <?php if ($msgType !== 'success'): ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nova Senha</label>
                    <input type="password" name="pass1" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar Senha</label>
                    <input type="password" name="pass2" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary w-100">Salvar Nova Senha</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>