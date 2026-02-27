<?php
// Fix Schema: Add 'Transporte' to ENUM
require_once 'config.php';

echo "<h2>Atualizando Schema do Banco de Dados...</h2>";

// 1. Alterar a coluna categoria_despesa
$sql = "ALTER TABLE viagem_express_expenses 
        MODIFY COLUMN categoria_despesa ENUM('Passagem Aérea', 'Hotel', 'Seguro', 'Transporte', 'Outros', 'Não Categorizado') DEFAULT 'Não Categorizado'";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green'>✓ Coluna categoria_despesa atualizada com sucesso! Agora inclui 'Transporte'.</p>";
} else {
    echo "<p style='color: red'>✗ Erro ao atualizar coluna: " . $conn->error . "</p>";
}

echo "<p><a href='index.php'>Voltar para o Dashboard</a></p>";
$conn->close();
?>