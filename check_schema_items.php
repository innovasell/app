<?php
require 'sistema-cotacoes/conexao.php';
try {
    $stmt = $pdo->query('DESCRIBE com_imported_items');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
