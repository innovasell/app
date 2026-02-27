<?php
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';

try {
    // Add batch_id to com_imported_items
    $columns = $pdo->query("SHOW COLUMNS FROM com_imported_items LIKE 'batch_id'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE com_imported_items ADD COLUMN batch_id VARCHAR(20) NOT NULL AFTER id, ADD INDEX (batch_id)");
    }

    echo "Tables updated successfully.";

} catch (PDOException $e) {
    die("DB Setup Error: " . $e->getMessage());
}
