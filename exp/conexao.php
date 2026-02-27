<?php
/**
 * Arquivo de conexão do módulo EXP
 * Atualizado para usar conexão centralizada do sistema-cotacoes
 */

// Inclui a conexão centralizada do sistema-cotacoes
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';

// Para compatibilidade com código que usa mysqli ao invés de PDO
// Cria uma conexão mysqli usando as mesmas credenciais
if (!isset($conn)) {
    $conn = new mysqli($host, $usuario, $senha, $banco, $porta);

    if ($conn->connect_error) {
        die("Erro de conexão: " . $conn->connect_error);
    }

    if (!$conn->set_charset("utf8mb4")) {
        die("Erro ao definir charset: " . $conn->error);
    }
}

// Agora temos disponíveis:
// $pdo - conexão PDO (do sistema-cotacoes/conexao.php)
// $conn - conexão mysqli (criada acima para compatibilidade)