<?php
// primeiro_acesso.php - Primeiro Acesso (Ativação de Conta / Auto-Cadastro)
session_start();
require_once 'site_conexao.php';
require_once 'sistema-cotacoes/GraphMailer.php';

$msg = '';
$msgType = '';
$redirectToLogin = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Verifica se usuário existe
        $stmt = $conn->prepare("SELECT id, nome, email, senha FROM cot_representante WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hour')); // 24h para ativar

        if ($user) {
            // USUÁRIO EXISTE
            // Se já tem senha definida, consideramos "Cadastro Ativo"
            if (!empty($user['senha'])) {
                $msg = "Este e-mail já possui cadastro ativo.<br>Caso tenha esquecido a senha, utilize a opção 'Esqueceu a senha?' no login.";
                $msgType = "warning";
                $redirectToLogin = true;
            } else {
                // Existe mas não tem senha (ex: pré-cadastrado pelo admin) -> Enviar Link
                $upd = $conn->prepare("UPDATE cot_representante SET reset_token = ?, reset_expires = ? WHERE id = ?");
                $upd->bind_param("ssi", $token, $expires, $user['id']);

                if ($upd->execute()) {
                    enviarEmailAtivacao($email, $user['nome'], $token);
                    $msg = "Enviamos um link de ativação para <strong>$email</strong>.<br>Verifique sua caixa de entrada.";
                    $msgType = "success";
                } else {
                    $msg = "Erro ao processar solicitação.";
                    $msgType = "danger";
                }
            }
        } else {
            // USUÁRIO NÃO EXISTE -> PRIMEIRO ACESSO (Self-Registration)

            // Tentar extrair um nome amigável do email (ex: joao.silva -> Joao Silva)
            $parts = explode('@', $email);
            $userPart = $parts[0];
            $generatedName = ucwords(str_replace(['.', '_', '-'], ' ', $userPart));

            // Inserir novo usuário (sem senha)
            // Define acesso básico (admin=0)
            $stmtInsert = $conn->prepare("INSERT INTO cot_representante (nome, email, admin, reset_token, reset_expires) VALUES (?, ?, 0, ?, ?)");
            $stmtInsert->bind_param("ssss", $generatedName, $email, $token, $expires);

            if ($stmtInsert->execute()) {
                enviarEmailAtivacao($email, $generatedName, $token);
                $msg = "Cadastro inicial realizado! Enviamos um link para criar sua senha em <strong>$email</strong>.";
                $msgType = "success";
            } else {
                $msg = "Erro ao criar cadastro. Tente novamente ou contate o suporte.";
                $msgType = "danger";
            }
        }
    } else {
        $msg = "Por favor, informe um e-mail válido.";
        $msgType = "warning";
    }
}

function enviarEmailAtivacao($email, $nome, $token)
{
    global $msg, $msgType;
    $config = require 'sistema-cotacoes/config_graph.php';
    $mailer = new GraphMailer($config);

    $link = "https://innovasell.cloud/reset_password.php?token=$token";

    $htmlBody = "
    <div style='font-family: Arial, sans-serif; color: #333;'>
        <h2>Bem-vindo ao Innovasell Cloud!</h2>
        <p>Olá, {$nome}.</p>
        <p>Recebemos sua solicitação de Primeiro Acesso.</p>
        <p>Para concluir seu cadastro e definir sua senha, clique no botão abaixo:</p>
        <p align='center'>
            <a href='$link' style='background-color: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Criar Minha Senha</a>
        </p>
        <p><small>Link: <a href='$link'>$link</a></small></p>
        <p>Este link é válido por 24 horas.</p>
        <hr>
        <p><small>Equipe Innovasell</small></p>
    </div>";

    $mailer->sendEmail($email, "Ativação de Conta - Innovasell Cloud", $htmlBody);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primeiro Acesso - Innovasell</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: url('https://images.unsplash.com/photo-1595853035070-59a39fe84de3?q=80&w=2560&auto=format&fit=crop') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-custom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: #0d6efd;
            border: none;
            padding: 0.6rem;
            font-weight: 600;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
        }
    </style>
</head>

<body>
    <div class="card-custom">
        <h4 class="text-center mb-3 fw-bold text-dark">Primeiro Acesso</h4>
        <p class="text-center text-muted small mb-4">Novo na Innovasell? Digite seu e-mail para criar sua conta.</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?> small text-center">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <?php if (!$redirectToLogin && $msgType !== 'success'): ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">E-MAIL CORPORATIVO</label>
                    <input type="email" name="email" class="form-control" placeholder="nome@innovasell.com.br" required
                        autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-2">Criar Acesso</button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="login.php" class="text-decoration-none small text-muted">Já tenho conta</a>
        </div>
    </div>
</body>

</html>