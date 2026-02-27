<?php
// site_conexao.php - Conexão central para o portal SSO
// Usada por index.php, login.php, recover_password.php, sso_redirect.php

// Reutiliza as credenciais do sistema-cotacoes para consistência
$host = "localhost";
$usuario = "root"; // Em produção seria u849249951_innovasell
$senha = "";       // Em produção seria a senha real
$banco = "u849249951_innovasell";
$porta = 3306;

// Configuração de ambiente local vs produção
// Se estiver no servidor real, pode precisar ajustar as credenciais
// Por enquanto, assumimos que as credenciais do events/conexao.php são as corretas

if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
    // Ambiente Local
} else {
    // Ambiente Produção (Innovasell Cloud)
    $usuario = "u849249951_innovasell";
    $senha = "Invti@169"; // Senha corrigida
}

// Conexão MySQLi
$conn = new mysqli($host, $usuario, $senha, $banco, $porta);
if ($conn->connect_error) {
    die("Erro de conexão (MySQLi): " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Conexão PDO (para reutilização de scripts modernos)
try {
    $dsn = "mysql:host=$host;port=$porta;dbname=$banco;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $usuario, $senha, $options);
} catch (PDOException $e) {
    die("Erro de conexão (PDO): " . $e->getMessage());
}
?>