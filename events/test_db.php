<?php
// Script de teste direto sem servidor web
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TESTE DE CONEXÃO ===\n";

$conn = new mysqli("localhost", "u849249951_innovasell", "Invti@169", "u849249951_innovasell", "3306");

if ($conn->connect_error) {
    die("ERRO: " . $conn->connect_error . "\n");
}

echo "✓ Conexão OK\n";

if (!$conn->set_charset("utf8mb4")) {
    die("ERRO ao definir charset: " . $conn->error . "\n");
}

echo "✓ Charset OK\n";

// Testa se a tabela existe
$result = $conn->query("SHOW TABLES LIKE 'viagem_express_expenses'");
if ($result->num_rows == 0) {
    echo "⚠ TABELA NÃO EXISTE! Execute setup_db.php primeiro\n";
} else {
    echo "✓ Tabela existe\n";

    // Mostra estrutura
    $result = $conn->query("DESCRIBE viagem_express_expenses");
    echo "\n=== ESTRUTURA DA TABELA ===\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

$conn->close();
echo "\n=== FIM DO TESTE ===\n";
?>