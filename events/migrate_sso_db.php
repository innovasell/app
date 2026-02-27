<?php
// events/migrate_sso_db.php
require_once 'conexao.php';

function addColumn($conn, $table, $col, $def)
{
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE $table ADD COLUMN $col $def";
        if ($conn->query($sql)) {
            echo "✅ Column '$col' added to '$table'.<br>";
        } else {
            echo "❌ Error adding '$col': " . $conn->error . "<br>";
        }
    } else {
        echo "ℹ️ Column '$col' already exists in '$table'.<br>";
    }
}

echo "<h2>Migrando Banco de Dados para SSO</h2>";

// 1. Password Recovery & Security
addColumn($conn, 'cot_representante', 'force_changepass', "TINYINT(1) DEFAULT 1 COMMENT '1=Obrigar troca, 0=OK'");
addColumn($conn, 'cot_representante', 'reset_token', "VARCHAR(100) NULL");
addColumn($conn, 'cot_representante', 'reset_expires', "DATETIME NULL");

// 2. Portal Permissions
addColumn($conn, 'cot_representante', 'acesso_expedicao', "TINYINT(1) DEFAULT 0");
addColumn($conn, 'cot_representante', 'acesso_cotacoes', "TINYINT(1) DEFAULT 0");
addColumn($conn, 'cot_representante', 'acesso_faq', "TINYINT(1) DEFAULT 0");
addColumn($conn, 'cot_representante', 'acesso_comissoes', "TINYINT(1) DEFAULT 0");
addColumn($conn, 'cot_representante', 'acesso_formulas', "TINYINT(1) DEFAULT 0");
addColumn($conn, 'cot_representante', 'acesso_viagens', "TINYINT(1) DEFAULT 0");

echo "<br><strong>Migração concluída!</strong>";
?>