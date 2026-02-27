<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbName = 'u849249951_innovasell';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Cleaning up invalid data (ID 0)...\n";

    // Cleanup children first
    $pdo->exec("DELETE FROM ingredientes WHERE fase_id = 0");
    $pdo->exec("DELETE FROM fases WHERE sub_formulacao_id = 0");
    $pdo->exec("DELETE FROM sub_formulacoes WHERE formulacao_id = 0");
    $pdo->exec("DELETE FROM ativos_destaque WHERE formulacao_id = 0");
    $pdo->exec("DELETE FROM formulacoes WHERE id = 0");

    // Also clean any table where id itself is 0 (if inserted)
    $tables = ['ingredientes', 'fases', 'sub_formulacoes', 'formulacoes', 'ativos_destaque'];
    foreach ($tables as $t) {
        $pdo->exec("DELETE FROM $t WHERE id = 0");
    }

    echo "Data cleaned.\n";

    echo "Fixing Keys and Auto Increment...\n";

    foreach ($tables as $t) {
        echo "Processing $t...\n";

        // Check if PK exists
        $stmt = $pdo->query("SHOW KEYS FROM $t WHERE Key_name = 'PRIMARY'");
        if ($stmt->rowCount() == 0) {
            echo "  Adding PRIMARY KEY to $t...\n";
            try {
                $pdo->exec("ALTER TABLE $t ADD PRIMARY KEY (id)");
            } catch (Exception $e) {
                echo "  Failed to add PK: " . $e->getMessage() . "\n";
            }
        }

        // Add Auto Increment
        echo "  Adding AUTO_INCREMENT to $t...\n";
        try {
            $pdo->exec("ALTER TABLE $t MODIFY id int(11) NOT NULL AUTO_INCREMENT");
        } catch (Exception $e) {
            echo "  Failed to add AI: " . $e->getMessage() . "\n";
        }
    }

    echo "Schema fixed!\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}
?>