<?php
// Teste simples para ver o que process_upload.php retorna
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TESTE DE CONEXÃO ===\n\n";

// Testa incluir o conexao.php
ob_start();
try {
    require_once __DIR__ . '/conexao.php';
    $output = ob_get_clean();

    echo "Output do conexao.php:\n";
    echo "Length: " . strlen($output) . "\n";
    if (!empty($output)) {
        echo "PROBLEMA! Output detectado:\n";
        echo $output . "\n";
        echo "Hex: " . bin2hex(substr($output, 0, 100)) . "\n";
    } else {
        echo "OK - Nenhum output gerado\n";
    }

    echo "\nConexão mysqli existe? " . (isset($conn) ? "SIM" : "NÃO") . "\n";
    echo "Conexão PDO existe? " . (isset($pdo) ? "SIM" : "NÃO") . "\n";

} catch (Exception $e) {
    ob_end_clean();
    echo "ERRO ao incluir conexao.php: " . $e->getMessage() . "\n";
}
?>