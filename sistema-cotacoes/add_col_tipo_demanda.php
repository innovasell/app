<?php
require_once 'conexao.php';

try {
    $pdo->exec("ALTER TABLE cot_cenarios_itens ADD COLUMN tipo_demanda VARCHAR(50) DEFAULT NULL AFTER produto");
    echo "Coluna 'tipo_demanda' adicionada com sucesso!";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "A coluna 'tipo_demanda' já existe.";
    } else {
        echo "Erro ao alterar tabela: " . $e->getMessage();
    }
}
?>