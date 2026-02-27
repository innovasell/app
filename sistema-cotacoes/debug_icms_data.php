<?php
require_once 'conexao.php';

echo "<h2>Origins in cot_estoque</h2>";
$origins = $pdo->query("SELECT DISTINCT origem, COUNT(*) as count FROM cot_estoque GROUP BY origem")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($origins);
echo "</pre>";

echo "<h2>cot_icms Content</h2>";
$icms = $pdo->query("SELECT * FROM cot_icms")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($icms);
echo "</pre>";
?>