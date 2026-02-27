<?php
/**
 * auth.php - Funções de autenticação e sessão
 */

// Inicia sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    $savePath = ini_get('session.save_path');
    if (!$savePath || !is_writable($savePath)) {
        session_save_path(sys_get_temp_dir());
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 86400, // 24 horas
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

// Verifica se usuário está logado
function is_logged()
{
    return isset($_SESSION['user']);
}

// Retorna dados do usuário logado
function user()
{
    return $_SESSION['user'] ?? null;
}

// Verifica se usuário é admin
function is_admin()
{
    return is_logged() && (user()['role'] === 'admin');
}

// Requer login (redireciona para login se não logado)
function require_login()
{
    if (!is_logged()) {
        header('Location: login.php');
        exit;
    }
}

// Requer admin (redireciona se não for admin)
function require_admin()
{
    require_login();
    if (!is_admin()) {
        header('Location: index.php');
        exit;
    }
}

// Gera token CSRF
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// Verifica token CSRF
function check_csrf($token)
{
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// Faz logout
function logout()
{
    session_destroy();
    header('Location: login.php');
    exit;
}
?>