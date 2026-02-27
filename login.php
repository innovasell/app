<?php
// login.php - Tela de Login Unificada (SSO)
session_start();
require_once 'site_conexao.php';

// Se já estiver logado, redireciona para dashboard
if (isset($_SESSION['sso_user'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $conn->prepare("SELECT * FROM cot_representante WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            // Verificar Senha
            // Se a senha no banco não for hash (legado), precisamos tratar
            // OU se for hash BCRYPT validamos com password_verify

            $senhaValida = false;
            if (password_verify($password, $user['senha'])) {
                $senhaValida = true;
            }
            // BACKDOOR LEGADO: Se a senha não for hash e bater com texto plano (PERIGOSO, mas mantendo compatibilidade temporária se necessário)
            // O usuário pediu para "profissionalizar", então vamos tentar forçar o hash.
            // Mas se for o primeiro login, a senha pode ser texto plano? O usuário disse: "vou cadastrar previamente... no primeiro login será obrigado a cadastrar uma senha"
            // Vamos assumir que cadastros novos já entram com hash ou senha temporária.

            // Lógica de Migração MD5 (se houver) ou Texto Plano para Hash
            // Se falhar o verify, tentamos ver se é MD5 ou Texto Plano
            elseif (md5($password) === $user['senha']) {
                // Senha é MD5 legado. Logamos e pedimos troca.
                $senhaValida = true;
                // Forçar troca de senha para atualizar hash
                $conn->query("UPDATE cot_representante SET force_changepass = 1 WHERE id = " . $user['id']);
                $user['force_changepass'] = 1;
            } elseif ($password === $user['senha']) {
                // Senha é Texto Plano (Legado ou Cadastro Admin simples).
                $senhaValida = true;
                $conn->query("UPDATE cot_representante SET force_changepass = 1 WHERE id = " . $user['id']);
                $user['force_changepass'] = 1;
            }

            if ($senhaValida) {
                // Login OK
                $_SESSION['sso_user'] = [
                    'id' => $user['id'],
                    'nome' => $user['nome'],
                    'sobrenome' => $user['sobrenome'],
                    'email' => $user['email'],
                    'admin' => $user['admin'],
                    'acessos' => [
                        'expedicao' => $user['acesso_expedicao'] ?? 0,
                        'cotacoes' => $user['acesso_cotacoes'] ?? 0,
                        'faq' => $user['acesso_faq'] ?? 0,
                        'comissoes' => $user['acesso_comissoes'] ?? 0,
                        'formulas' => $user['acesso_formulas'] ?? 0,
                        'viagens' => $user['acesso_viagens'] ?? 0
                    ]
                ];

                // Check Force Change Password
                if (isset($user['force_changepass']) && $user['force_changepass'] == 1) {
                    header("Location: change_password.php");
                    exit;
                }

                header("Location: index.php");
                exit;
            } else {
                $error = "E-mail ou senha incorretos.";
            }
        } else {
            $error = "E-mail ou senha incorretos.";
        }
    } else {
        $error = "Preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Innovasell Cloud</title>
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

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo img {
            max-height: 50px;
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

        .form-control {
            padding: 0.6rem;
        }

        .forgot-link {
            font-size: 0.85rem;
            text-decoration: none;
            color: #6c757d;
        }

        .forgot-link:hover {
            color: #0d6efd;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="logo">
            <img src="assets/img/logo.png" alt="Innovasell Cloud"
                onerror="this.src='https://via.placeholder.com/150x50?text=Innovasell'">
        </div>

        <h4 class="text-center mb-4 fw-bold text-dark">Acesso aos Portais</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger text-center small">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold">E-MAIL CORPORATIVO</label>
                <input type="email" name="email" class="form-control" placeholder="nome@innovasell.com.br" required
                    autofocus>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between">
                    <label class="form-label text-muted small fw-bold">SENHA</label>
                    <div>
                        <a href="primeiro_acesso.php" class="forgot-link me-2">Primeiro Acesso?</a>
                        <a href="recover_password.php" class="forgot-link">Esqueceu?</a>
                    </div>
                </div>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-2">Entrar</button>
        </form>
    </div>
</body>

</html>