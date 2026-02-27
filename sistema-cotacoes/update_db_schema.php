<?php
require_once 'conexao.php';

try {
    $pdo->exec("ALTER TABLE cot_estoque ADD COLUMN IF NOT EXISTS ativo TINYINT(1) DEFAULT 1;");
    echo "Column 'ativo' added successfully or already exists.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>