<?php
// Senha que queremos usar
$senhaPlana = 'Invti@169';

// Gera o hash seguro
$hash = password_hash($senhaPlana, PASSWORD_DEFAULT);

// Exibe o hash na tela
echo "<h1>Hash de Senha Gerado</h1>";
echo "<p>Use este hash para atualizar o banco de dados:</p>";
echo "<pre style='background-color:#f0f0f0; padding:10px; border:1px solid #ccc; word-wrap:break-word;'>" . htmlspecialchars($hash) . "</pre>";
?>