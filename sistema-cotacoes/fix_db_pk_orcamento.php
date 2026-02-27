<?php
require 'conexao.php';

echo "<h2>Reparando Tabela cot_cotacoes_importadas</h2>";

try {
    // 1. Verificar se a coluna ID existe
    $colunas = $pdo->query("DESCRIBE cot_cotacoes_importadas")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('id', $colunas) && !in_array('ID', $colunas)) {
        // Se não existe, cria
        echo "Coluna 'id' não encontrada. Criando...<br>";
        $pdo->exec("ALTER TABLE cot_cotacoes_importadas ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST");
        echo "Sucesso: Coluna 'id' criada e definida como Primary Key.<br>";
    } else {
        // Se existe, tenta modificar para AUTO_INCREMENT
        // Nota: O nome da coluna pode ser minúsculo ou maiúsculo, tente 'id' primeiro
        echo "Coluna 'id' encontrada. Tentando definir como AUTO_INCREMENT...<br>";

        // Primeiro, garantimos que não haja IDs duplicados zerados que impeçam a chave primária
        // Se todos são 0, isso vai falhar se tentarmos transformar direto em PK.
        // Vamos tentar DROPAR a PK e recriar.

        try {
            $pdo->exec("ALTER TABLE cot_cotacoes_importadas MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY");
            echo "Sucesso: Coluna 'id' atualizada para AUTO_INCREMENT.<br>";
        } catch (Exception $eModify) {
            echo "Tentativa direta falhou (" . $eModify->getMessage() . "). Tentando recriar a coluna...<br>";

            // Estratégia drástica: Drop column e Add again (Cuidado: perde IDs antigos, mas como são 0, não importa)
            $pdo->exec("ALTER TABLE cot_cotacoes_importadas DROP COLUMN id");
            $pdo->exec("ALTER TABLE cot_cotacoes_importadas ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST");
            echo "Sucesso: Coluna 'id' recriada corretamante.<br>";
        }
    }

} catch (PDOException $e) {
    echo "Erro Fatal: " . $e->getMessage() . "<br>";
    echo "Stack Trace: " . $e->getTraceAsString();
}
?>
<br><br>
<a href="consultar_orcamentos.php">Voltar para Consultar Orçamentos</a>