<?php
require_once 'conexao.php';
echo "<h2>cot_cotacoes_importadas</h2>";
$stmt = $pdo->query("DESCRIBE cot_cotacoes_importadas");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}

echo "<h2>cot_price_list</h2>";
$stmt = $pdo->query("DESCRIBE cot_price_list");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
?>