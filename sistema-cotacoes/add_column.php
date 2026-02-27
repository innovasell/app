<?php
require_once 'conexao.php';
try {
    $pdo->query("ALTER TABLE cot_cotacoes_importadas ADD COLUMN `PRICE LIST` DECIMAL(10,4) DEFAULT NULL");
    echo "Coluna 'PRICE LIST' adicionada com sucesso.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Coluna 'PRICE LIST' já existe.";
    } else {
        die("Erro ao adicionar coluna: " . $e->getMessage());
    }
}
?>