<?php
require_once 'conexao.php';

try {
    $pdo->exec("ALTER TABLE cot_price_list ADD COLUMN lead_time VARCHAR(100) DEFAULT NULL AFTER embalagem");
    echo "Coluna 'lead_time' adicionada com sucesso ou já existente.";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>