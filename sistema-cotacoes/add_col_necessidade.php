<?php
require 'conexao.php';

try {
    // Verificar se a coluna já existe
    $stmt = $pdo->query("SHOW COLUMNS FROM cot_cenarios_itens LIKE 'necessidade_cliente'");
    $existe = $stmt->fetch();

    if (!$existe) {
        // Adicionar columna
        $sql = "ALTER TABLE cot_cenarios_itens ADD COLUMN necessidade_cliente VARCHAR(255) NULL AFTER data_necessidade";
        $pdo->exec($sql);
        echo "Coluna 'necessidade_cliente' adicionada com sucesso.";
    } else {
        echo "Coluna 'necessidade_cliente' já existe.";
    }

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>