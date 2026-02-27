<?php
require_once 'conexao.php';

function showTableSchema($pdo, $table)
{
    echo "<h3>Tabela: $table</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Key</th><th>Extra</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Show sample data (first 5 rows) to check ID patterns
        $stmt = $pdo->query("SELECT * FROM $table LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<div style='overflow-x: auto; margin-bottom: 20px;'>";
        echo "<h4>Amostra de Dados ($table)</h4>";
        if ($rows) {
            echo "<table border='1' style='font-size: 12px;'><tr>";
            foreach (array_keys($rows[0]) as $k)
                echo "<th>$k</th>";
            echo "</tr>";
            foreach ($rows as $r) {
                echo "<tr>";
                foreach ($r as $v)
                    echo "<td>" . htmlspecialchars(substr($v, 0, 50)) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "Tabela vazia.";
        }
        echo "</div>";

        // Count Zeros
        $count = $pdo->query("SELECT COUNT(*) FROM $table WHERE id = 0 OR id IS NULL")->fetchColumn();
        echo "<p><strong>Registros com ID zero ou nulo:</strong> $count</p>";

    } catch (PDOException $e) {
        echo "Erro ao ler $table: " . $e->getMessage();
    }
    echo "<hr>";
}

echo "<h2>Análise de Estrutura e Dados</h2>";
showTableSchema($pdo, 'cot_cotacoes_importadas');
showTableSchema($pdo, 'cot_cenarios_importacao');
showTableSchema($pdo, 'cot_cenarios_itens');
?>