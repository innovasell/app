<?php
require_once 'sistema-cotacoes/conexao.php';

try {
    $stmt = $pdo->query("SELECT 1");
    if ($stmt) {
        echo "PHP CONNECTED SUCCESS\n";
    }
} catch (Exception $e) {
    echo "PHP CONNECTED FAILED: " . $e->getMessage() . "\n";
}
?>