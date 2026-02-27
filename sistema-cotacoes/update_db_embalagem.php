<?php
require_once 'conexao.php';

try {
    // Check if column exists
    $check = $pdo->query("SHOW COLUMNS FROM cot_cenarios_itens LIKE 'embalagem'");
    if ($check->rowCount() == 0) {
        $sql = "ALTER TABLE cot_cenarios_itens ADD COLUMN embalagem VARCHAR(50) DEFAULT NULL AFTER unidade";
        $pdo->exec($sql);
        echo "Coluna 'embalagem' adicionada com sucesso na tabela 'cot_cenarios_itens'.\n";
    } else {
        echo "Coluna 'embalagem' já existe na tabela 'cot_cenarios_itens'.\n";
    }
} catch (PDOException $e) {
    echo "Erro ao atualizar banco de dados: " . $e->getMessage() . "\n";
}
?>