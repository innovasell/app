<?php
// recover_password.php - Solicitar recuperação de senha
session_start();
require_once 'site_conexao.php';
require_once 'sistema-cotacoes/GraphMailer.php'; // Reutilizando a classe existente

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if ($email) {
        // Verifica se usuário existe
        $stmt = $conn->prepare("SELECT id, nome, email FROM cot_representante WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            // Gerar Token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Salvar no banco
            $upd = $conn->prepare("UPDATE cot_representante SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $upd->bind_param("ssi", $token, $expires, $user['id']);

            if ($upd->execute()) {
                // Enviar Email via Azure Graph API
                $config = require 'sistema-cotacoes/config_graph.php';
                $mailer = new GraphMailer($config);

                $link = "https://innovasell.cloud/reset_password.php?token=$token";

                $htmlBody = "
                <div style='font-family: Arial, sans-serif; color: #333;'>
                    <h2>Recuperação de Senha</h2>
                    <p>Olá, {$user['nome']}.</p>
                    <p>Recebemos uma solicitação para redefinir sua senha no portal Innovasell Cloud.</p>
                    <p>Clique no botão abaixo para criar uma nova senha:</p>
                    <p>
                        <a href='$link' style='background-color: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Redefinir Senha</a>
                    </p>
                    <p><small>Se o botão não funcionar, copie e cole este link no navegador:<br>$link</small></p>
                    <p>Este link expira em 1 hora.</p>
                    <hr>
                    <p><small>Se você não solicitou isso, ignore este e-mail.</small></p>
                </div>";

                $result = $mailer->sendEmail($email, "Recuperação de Senha - Innovasell Cloud", $htmlBody);

                if ($result['success']) {
                    $msg = "Um link de recuperação foi enviado para seu e-mail.";
                    $msgType = "success";
                } else {
                    $msg = "Erro ao enviar e-mail: " . $result['error'];
                    $msgType = "danger";
                }
            } else {
                $msg = "Erro ao processar solicitação no banco.";
                $msgType = "danger";
            }
        } else {
            // Por segurança, dizemos que enviamos mesmo que o email não exista (previne enumeração)
            // Mas para uso interno/dev, vou ser sincero por enquanto ou manter o padrão
            $msg = "Se o e-mail estiver cadastrado, você receberá um link em instantes.";
            $msgType = "info";
        }
    } else {
        $msg = "Por favor, informe um e-mail válido.";
        $msgType = "warning";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Innovasell</title>
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
        <h4 class="text-center mb-3">Esqueceu a senha?</h4>
        <p class="text-center text-muted small mb-4">Digite seu e-mail para receber um link de redefinição.</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?> small">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Enviar Link</button>
        </form>
        <div class="text-center mt-3">
            <a href="login.php" class="text-decoration-none small">Voltar para o Login</a>
        </div>
    </div>
</body>

</html>