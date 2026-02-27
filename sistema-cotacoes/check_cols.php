<?php
require 'conexao.php';
try {
    $stmt = $pdo->query("DESCRIBE cot_cenarios_itens");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . "\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
?>