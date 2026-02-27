<?php
require_once 'gerador-formulas/config.php';

// Verify file existence to ensure path is correct relative to execution
if (!file_exists('gerador-formulas/config.php')) {
    die("Erro: Execute este script na raiz do projeto.");
}

echo "<h1>Adicionar Coluna QSP</h1>";

$sql = "ALTER TABLE ingredientes ADD COLUMN qsp TINYINT(1) DEFAULT 0";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color:green;'>Coluna 'qsp' adicionada com sucesso ou já existente.</p>";
} else {
    // Check if error is "Duplicate column name"
    if (strpos($conn->error, "Duplicate column name") !== false) {
        echo "<p style='color:orange;'>A coluna 'qsp' já existe.</p>";
    } else {
        echo "<p style='color:red;'>Erro ao adicionar coluna: " . $conn->error . "</p>";
    }
}
?>