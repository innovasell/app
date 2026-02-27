<?php
/**
 * Arquivo de conexão específico do módulo Events
 * NÃO usa o sistema-cotacoes/conexao.php para evitar poluir JSON
 * 
 * IMPORTANTE: Este arquivo está no .gitignore..
 */

$host = "localhost";
$porta = "3306";
$usuario = "u849249951_innovasell";
$senha = "Invti@169";
$banco = "u849249951_innovasell";

// Conexão mysqli (usada pela maioria dos scripts do events)
// Adicionado (int)$porta para garantir compatibilidade com servidor
$conn = new mysqli($host, $usuario, $senha, $banco, (int) $porta);

if ($conn->connect_error) {
    error_log("Erro de conexão mysqli: " . $conn->connect_error);
    throw new Exception("Erro de conexão com o banco de dados");
}

if (!$conn->set_charset("utf8mb4")) {
    error_log("Erro ao definir charset - " . $conn->error);
    throw new Exception("Erro ao configurar charset do banco de dados");
}

// Conexão PDO (se necessário)
try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ];
    // Adicionada porta ao DSN igual ao sistema-cotacoes
    $pdo = new PDO("mysql:host=$host;port=$porta;dbname=$banco;charset=utf8mb4", $usuario, $senha, $options);
} catch (PDOException $e) {
    error_log("Erro de conexão PDO: " . $e->getMessage());
    throw new Exception("Erro de conexão com o banco de dados");
}
