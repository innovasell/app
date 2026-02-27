<?php
require_once 'conexao.php';
$stmt = $pdo->query("DESCRIBE cot_estoque");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
?>