<?php
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';

try {
    // Table: com_cfop_rules
    $pdo->exec("CREATE TABLE IF NOT EXISTS com_cfop_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cfop VARCHAR(10) NOT NULL UNIQUE,
        description VARCHAR(255),
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Table: com_imported_items
    $pdo->exec("CREATE TABLE IF NOT EXISTS com_imported_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        xml_filename VARCHAR(255),
        nfe_number VARCHAR(50),
        cfop VARCHAR(10),
        product_code_original VARCHAR(100),
        product_code_9 VARCHAR(9),
        product_name VARCHAR(255),
        packaging_extracted VARCHAR(100),
        packaging_validated VARCHAR(100),
        quantity DECIMAL(15,4),
        unit_price DECIMAL(15,4),
        total_value DECIMAL(15,4),
        cost_price DECIMAL(15,4) NULL,
        status ENUM('pending', 'validated') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Tables created successfully.";

} catch (PDOException $e) {
    die("DB Setup Error: " . $e->getMessage());
}
