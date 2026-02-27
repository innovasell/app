<?php
require 'sistema-cotacoes/conexao.php';
try {
    $stmt = $pdo->query("SELECT id, codigo, embalagem, preco_net_usd FROM cot_price_list LIMIT 10");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
