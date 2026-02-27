<?php
require_once 'conexao.php';

try {
    $stmt = $pdo->query("DESCRIBE cot_cotacoes_importadas");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Schema de cot_cotacoes_importadas</h2>";
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        foreach ($col as $val) {
            echo "<td>" . htmlspecialchars($val) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>