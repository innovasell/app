<?php
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';

try {
    // Add average_term to com_imported_items
    $columns = $pdo->query("SHOW COLUMNS FROM com_imported_items LIKE 'average_term'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE com_imported_items ADD COLUMN average_term DECIMAL(10,2) NULL AFTER cost_price");
        echo "Column 'average_term' added successfully.<br>";
    } else {
        echo "Column 'average_term' already exists.<br>";
    }

    echo "Database setup v4 complete.";

} catch (PDOException $e) {
    die("DB Setup Error: " . $e->getMessage());
}
