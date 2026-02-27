<?php
// sso_redirect.php
// Responsável por "traduzir" a sessão única (SSO) para as sessões específicas de cada sistema legado

session_start();
require_once 'site_conexao.php';

if (!isset($_SESSION['sso_user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['sso_user'];
$target = $_GET['target'] ?? '';

// Mapeamento de Destinos
switch ($target) {
    case 'cotacoes':
        if ($user['admin'] == 1 || ($user['acessos']['cotacoes'] ?? 0) == 1) {
            // Sessão Legada do Sistema de Cotações (conforme login.php original)
            $_SESSION['representante_email'] = $user['email'];
            $_SESSION['representante_nome'] = $user['nome'];
            $_SESSION['representante_sobrenome'] = $user['sobrenome'];
            $_SESSION['admin'] = $user['admin'];

            header("Location: sistema-cotacoes/bi.php"); // Ou index.php?
            exit;
        }
        break;

    case 'viagens': // Events
        if (($user['acessos']['viagens'] ?? 0) == 1) {
            // Sessão Legada do Events (events/login.php)
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['nome'] . ' ' . $user['sobrenome'],
                'email' => $user['email'],
                'role' => ($user['admin'] == 1) ? 'admin' : 'user'
            ];
            // Importante: O events verifica isset($_SESSION['user']), então isso basta.

            header("Location: events/index.php");
            exit;
        }
        break;

    case 'faq':
        if (($user['acessos']['faq'] ?? 0) == 1) {
            // Sessão Legada do FAQ (faq/index.php)
            // Sincronização com tabela local 'users' do FAQ para garantir ID e Role corretos

            $email = $user['email'];
            $localUserQuery = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
            if ($localUserQuery) {
                $localUserQuery->bind_param("s", $email);
                $localUserQuery->execute();
                $localUserResult = $localUserQuery->get_result();
                $localUser = $localUserResult->fetch_assoc();

                if ($localUser) {
                    // Usuário já existe na tabela local do FAQ
                    // Usamos a role local, pois é lá que estão os "vínculos de admin" específicos
                    $localId = $localUser['id'];
                    $localRole = $localUser['role'];
                } else {
                    // Usuário não existe, vamos criar
                    // Se for Admin Global, entra como Admin no FAQ também. Se não, entra como usuário.
                    $localRole = ($user['admin'] == 1) ? 'admin' : 'usuario';
                    $name = $user['nome'] . ' ' . $user['sobrenome'];
                    $passHash = password_hash('sso_auto_generated_' . uniqid(), PASSWORD_DEFAULT); // Senha dummy

                    $insertUser = $conn->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $insertUser->bind_param("ssss", $name, $email, $passHash, $localRole);
                    if ($insertUser->execute()) {
                        $localId = $insertUser->insert_id;
                    } else {
                        // Fallback em caso de erro (improvável)
                        $localId = $user['id'];
                    }
                }
            } else {
                // Tabela users pode não existir ou erro de query (fallback)
                $localId = $user['id'];
                $localRole = ($user['admin'] == 1) ? 'admin' : 'usuario';
            }

            $_SESSION['user'] = [
                'id' => $localId,
                'name' => $user['nome'] . ' ' . $user['sobrenome'],
                'email' => $user['email'],
                'role' => $localRole
            ];

            header("Location: faq/index.php");
            exit;
        }
        break;

    case 'expedicao':
        if (($user['acessos']['expedicao'] ?? 0) == 1) {
            header("Location: exp/");
            exit;
        }
        break;

    case 'comissoes':
        if (($user['acessos']['comissoes'] ?? 0) == 1) {
            header("Location: comissoes/index.php");
            exit;
        }
        break;

    case 'formulas':
        if (($user['acessos']['formulas'] ?? 0) == 1) {
            header("Location: gerador-formulas/index.php");
            exit;
        }
        break;
}

// Se chegou aqui, acesso negado ou target inválido
echo "Acesso Negado ou Portal Inválido para o alvo: " . htmlspecialchars($target);
echo "<br><a href='index.php'>Voltar</a>";
exit;
?>