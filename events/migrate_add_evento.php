<?php
/**
 * Migration Script - Add evento_visita field
 * Adds the evento_visita field to track events/visits for each expense
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Migration: Adding evento_visita field</h2>";

// Check if column already exists
$checkSql = "SHOW COLUMNS FROM viagem_express_expenses LIKE 'evento_visita'";
$result = $conn->query($checkSql);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Column 'evento_visita' already exists. Skipping.</p>";
} else {
    // Add the evento_visita column
    $alterSql = "ALTER TABLE viagem_express_expenses 
                 ADD COLUMN evento_visita TEXT NULL 
                 COMMENT 'Nome do Evento ou Visita associado à despesa' 
                 AFTER num_fatura";

    if ($conn->query($alterSql) === TRUE) {
        echo "<p style='color: green;'>✓ Column 'evento_visita' added successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding column: " . $conn->error . "</p>";
    }
}

// Verify num_fatura exists and has proper type
$checkFaturaSql = "SHOW COLUMNS FROM viagem_express_expenses LIKE 'num_fatura'";
$resultFatura = $conn->query($checkFaturaSql);

if ($resultFatura->num_rows > 0) {
    echo "<p style='color: green;'>✓ Column 'num_fatura' already exists.</p>";
} else {
    echo "<p style='color: red;'>✗ Warning: Column 'num_fatura' does not exist! Check database schema.</p>";
}

// Add index for evento_visita for better query performance
$indexCheckSql = "SHOW INDEX FROM viagem_express_expenses WHERE Key_name = 'idx_evento_visita'";
$indexResult = $conn->query($indexCheckSql);

if ($indexResult->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Index 'idx_evento_visita' already exists. Skipping.</p>";
} else {
    $addIndexSql = "ALTER TABLE viagem_express_expenses ADD INDEX idx_evento_visita (evento_visita(100))";

    if ($conn->query($addIndexSql) === TRUE) {
        echo "<p style='color: green;'>✓ Index 'idx_evento_visita' added successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding index: " . $conn->error . "</p>";
    }
}

echo "<hr>";
echo "<h3>Migration Complete!</h3>";
echo "<p><a href='index.php'>← Back to Dashboard</a></p>";

$conn->close();
?>