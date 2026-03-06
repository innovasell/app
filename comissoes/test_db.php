<?php
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';
$stmt = $pdo->query("SELECT codigo, produto, embalagem, preco_net_usd FROM cot_price_list WHERE codigo LIKE '005005001%' LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
