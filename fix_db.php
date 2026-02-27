<?php
require_once 'comissoes/db.php';

echo "<h2>Verificando Banco de Dados...</h2>";

try {
    // 1. Check/Add batch_id to com_imported_items
    $col = $pdo->query("SHOW COLUMNS FROM com_imported_items LIKE 'batch_id'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE com_imported_items ADD COLUMN batch_id VARCHAR(20) NOT NULL AFTER id");
        $pdo->exec("ALTER TABLE com_imported_items ADD INDEX (batch_id)");
        echo "<div style='color:green'> [SUCESSO] Coluna 'batch_id' adicionada.</div><br>";
    } else {
        echo "<div style='color:blue'> [OK] Coluna 'batch_id' já existe.</div><br>";
    }

    // 2. Check/Add nfe_date to com_imported_items
    $colDate = $pdo->query("SHOW COLUMNS FROM com_imported_items LIKE 'nfe_date'")->fetch();
    if (!$colDate) {
        $pdo->exec("ALTER TABLE com_imported_items ADD COLUMN nfe_date DATE NULL AFTER nfe_number");
        echo "<div style='color:green'> [SUCESSO] Coluna 'nfe_date' adicionada.</div><br>";
    } else {
        echo "<div style='color:blue'> [OK] Coluna 'nfe_date' já existe.</div><br>";
    }

    // 3. Check com_sales_base
    $tableSales = $pdo->query("SHOW TABLES LIKE 'com_sales_base'")->fetch();
    if (!$tableSales) {
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
        echo "<div style='color:green'> [SUCESSO] Tabela 'com_sales_base' criada.</div><br>";
    } else {
        echo "<div style='color:blue'> [OK] Tabela 'com_sales_base' já existe.</div><br>";
    }

    echo "<h3>Tudo pronto! Pode voltar e testar o upload.</h3>";
    echo "<a href='comissoes/upload.php'>Voltar para Upload</a>";

} catch (PDOException $e) {
    echo "<div style='color:red'> [ERRO] " . $e->getMessage() . "</div>";
}
