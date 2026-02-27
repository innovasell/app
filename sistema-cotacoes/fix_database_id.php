<?php
require_once 'conexao.php';

try {
    echo "Iniciando correção da tabela cot_cotacoes_importadas...<br>";

    // 1. Check if ID column exists, if not create it (unlikely, but safe)
    // We assume it exists but has duplicates (0).

    // 2. Re-sequence IDs to ensure they are unique
    echo "Re-sequenciando IDs existentes...<br>";
    $sql_reindex = "SET @count = 0; UPDATE cot_cotacoes_importadas SET id = @count:= @count + 1;";
    $pdo->exec($sql_reindex);
    echo "IDs re-sequenciados.<br>";

    // 3. Alter table to make ID Auto-Increment Primary Key
    // We try to grab the max ID first to set auto_increment value correctly? No, MySQL handles it.
    echo "Aplicando AUTO_INCREMENT e PRIMARY KEY...<br>";
    $sql_alter = "ALTER TABLE cot_cotacoes_importadas MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY;";
    $pdo->exec($sql_alter);

    echo "<h3 style='color: green;'>Sucesso! A tabela agora possui IDs únicos e automáticos.</h3>";
    echo "Novos itens receberão IDs ex: 100, 101... e não mais 0.<br>";
    echo "O problema de exclusão em massa foi resolvido para FUTURAS ações.";

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Erro: " . $e->getMessage() . "</h3>";
    echo "Possível causa: A coluna ID já é chave primária ou outro erro de estrutura.<br>";
    echo "Tente rodar manualmente no Banco de Dados se este script falhar.";
}
?>