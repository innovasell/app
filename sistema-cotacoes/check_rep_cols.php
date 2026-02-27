<?php
require 'conexao.php';
$stmt = $pdo->query("DESCRIBE cot_representante");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo implode("\n", $cols);
?>