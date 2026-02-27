<?php
require 'conexao.php';

try {
    // Verificar se a coluna já existe
    $stmt = $pdo->query("SHOW COLUMNS FROM cot_representante LIKE 'telefone'");
    $existe = $stmt->fetch();

    if (!$existe) {
        // Adicionar coluna
        $sql = "ALTER TABLE cot_representante ADD COLUMN telefone VARCHAR(20) NULL AFTER email";
        $pdo->exec($sql);
        echo "Coluna 'telefone' adicionada com sucesso.";
    } else {
        echo "Coluna 'telefone' já existe.";
    }

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>