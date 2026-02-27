<?php
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';

try {
    // Add seller_name to com_imported_items
    $columns = $pdo->query("SHOW COLUMNS FROM com_imported_items LIKE 'seller_name'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE com_imported_items ADD COLUMN seller_name VARCHAR(255) NULL AFTER status");
        echo "Column 'seller_name' added successfully.<br>";
    } else {
        echo "Column 'seller_name' already exists.<br>";
    }

    echo "Database setup v5 complete.";

} catch (PDOException $e) {
    die("DB Setup Error: " . $e->getMessage());
}
