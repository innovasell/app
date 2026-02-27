<?php
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';

try {
    // Update com_imported_items: Add nfe_date if not exists
    $columns = $pdo->query("SHOW COLUMNS FROM com_imported_items LIKE 'nfe_date'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE com_imported_items ADD COLUMN nfe_date DATE NULL AFTER nfe_number");
    }

    // Table: com_sales_base (Final Storage)
    $pdo->exec("CREATE TABLE IF NOT EXISTS com_sales_base (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nfe_number VARCHAR(50),
        nfe_date DATE,
        cfop VARCHAR(10),
        product_code_9 VARCHAR(9),
        product_name VARCHAR(255),
        packaging VARCHAR(50),
        quantity DECIMAL(15,4),
        unit_price DECIMAL(15,4),
        total_value DECIMAL(15,4),
        cost_price DECIMAL(15,4),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Tables updated successfully.";

} catch (PDOException $e) {
    die("DB Setup Error: " . $e->getMessage());
}
