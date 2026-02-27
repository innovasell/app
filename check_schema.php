<?php
require 'sistema-cotacoes/conexao.php';
try {
    $stmt = $pdo->query('DESCRIBE cot_price_list');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
