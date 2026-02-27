<?php
// fix_db_safe_v2.php
// Script seguro para corrigir IDs zerados em orçamentos e cenários
// ATENÇÃO: Acesse pelo navegador

require 'conexao.php';

function fixTableId($pdo, $table)
{
    echo "<h3>Analisando Tabela: $table</h3>";

    try {
        // 1. Verifica se a coluna ID existe e se é auto_increment
        $stmt = $pdo->query("DESCRIBE $table");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $idCol = null;
        foreach ($cols as $c) {
            if ($c['Field'] === 'id' || $c['Field'] === 'ID') {
                $idCol = $c;
                break;
            }
        }

        if (!$idCol) {
            echo "Coluna 'id' não existe. Criando...<br>";
            $pdo->exec("ALTER TABLE $table ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST");
            echo "<span style='color:green'>Sucesso: Coluna criada e populada.</span><br>";
            return;
        }

        echo "Coluna 'id' existe. Status: " . ($idCol['Extra'] == 'auto_increment' ? 'OK (Auto Increment)' : 'SEM Auto Increment') . "<br>";

        // Se já é auto_increment, teoricamente está ok, mas pode ter buracos ou zeros se foi forçado.
        if ($idCol['Extra'] == 'auto_increment') {
            echo "A tabela parece estar configurada corretamente.<br>";
            // Verificar zeros
            $zeros = $pdo->query("SELECT COUNT(*) FROM $table WHERE id = 0")->fetchColumn();
            if ($zeros > 0) {
                echo "ATENÇÃO: Existem $zeros registros com ID=0. Isso é incomum em auto_increment.<br>";
                // Se é auto_increment e tem zeros, algo está errado.
            }
            return;
        }

        // Se NÃO é auto_increment, precisamos corrigir.
        // O problema: Se houver duplicatas (vários ID=0), não podemos simplesmente adicionar PRIMARY KEY.

        // 2. Verificar duplicatas no ID
        $duplicatas = $pdo->query("SELECT id, COUNT(*) as c FROM $table GROUP BY id HAVING c > 1")->fetchAll();
        $temZeros = $pdo->query("SELECT COUNT(*) FROM $table WHERE id = 0")->fetchColumn();

        if (count($duplicatas) > 0 || $temZeros > 0) {
            echo "Detectados problemas: " . count($duplicatas) . " IDs duplicados e $temZeros registros com ID zero.<br>";
            echo "Iniciando processo de reindexação SEGUR0...<br>";

            // Estratégia:
            // a. Renomear a coluna id atual para id_old (para backup)
            // b. Criar nova coluna id corretamente
            // c. (Opcional) Dropar id_old depois ou manter

            // Verificando se já existe id_backup
            $chk = $pdo->query("SHOW COLUMNS FROM $table LIKE 'id_old'")->fetch();
            if (!$chk) {
                $pdo->exec("ALTER TABLE $table CHANGE COLUMN id id_old INT");
                echo "Coluna antiga renomeada para 'id_old'.<br>";
            } else {
                echo "Coluna id_old já existe. Continuando...<br>";
                $pdo->exec("ALTER TABLE $table DROP COLUMN id"); // Se existir um id conflitante
            }

            // Criar nova ID
            $pdo->exec("ALTER TABLE $table ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST");
            echo "<span style='color:green'>Sucesso: Nova coluna ID criada e reindexada sequencialmente. Todos os registros agora têm IDs únicos.</span><br>";
            echo "Nota: Os IDs antigos estão salvos na coluna 'id_old' para conferência.<br>";

        } else {
            // Se não tem duplicatas, pode tentar converter direto
            echo "Sem duplicatas. Convertendo para Auto Increment...<br>";
            $pdo->exec("ALTER TABLE $table MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY");
            echo "<span style='color:green'>Sucesso.</span><br>";
        }

    } catch (PDOException $e) {
        echo "<span style='color:red'>Erro: " . $e->getMessage() . "</span><br>";
    }
    echo "<hr>";
}

echo "<h2>Ferramenta de Correção de IDs de Banco de Dados</h2>";
echo "<p>Este script irá garantir que as tabelas tenham um ID único e sequencial.</p>";

fixTableId($pdo, 'cot_cotacoes_importadas'); // Orçamentos
fixTableId($pdo, 'cot_cenarios_importacao'); // Header Cenários (Importante!)

echo "<br><a href='index.php' class='btn'>Voltar ao Sistema</a>";
?>