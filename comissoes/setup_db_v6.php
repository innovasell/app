<?php
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';

try {
    echo "<h2>Starting DB Setup V6...</h2>";

    // 1. Create fin_ptax_rates table
    $sqlPtax = "CREATE TABLE IF NOT EXISTS fin_ptax_rates (
        data_cotacao DATE PRIMARY KEY,
        cotacao_compra DECIMAL(10,4),
        cotacao_venda DECIMAL(10,4),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sqlPtax);
    echo "Table 'fin_ptax_rates' checked/created.<br>";

    // 2. Add columns to com_imported_items
    $columns = $pdo->query("SHOW COLUMNS FROM com_imported_items")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('unit_price_usd', $columns)) {
        $pdo->exec("ALTER TABLE com_imported_items ADD COLUMN unit_price_usd DECIMAL(18,4) NULL AFTER unit_price");
        echo "Column 'unit_price_usd' added.<br>";
    } else {
        echo "Column 'unit_price_usd' already exists.<br>";
    }

    if (!in_array('ptax_rate', $columns)) {
        $pdo->exec("ALTER TABLE com_imported_items ADD COLUMN ptax_rate DECIMAL(10,4) NULL AFTER unit_price_usd");
        echo "Column 'ptax_rate' added.<br>";
    } else {
        echo "Column 'ptax_rate' already exists.<br>";
    }

    echo "<h3>Database setup v6 complete.</h3>";

} catch (PDOException $e) {
    die("DB Setup Error: " . $e->getMessage());
}
