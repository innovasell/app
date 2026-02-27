<?php
// logout_sso.php
session_start();

// Destrói todas as variáveis de sessão (limpa login SSO e dos portais legados se estiverem na mesma sessão PHP)
$_SESSION = array();

// Se necessário, mata o cookie da sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Location: login.php");
exit;
?>