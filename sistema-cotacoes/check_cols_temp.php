<?php
require_once 'conexao.php';
$stmt = $pdo->query("DESCRIBE cot_price_list");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($cols);
?>