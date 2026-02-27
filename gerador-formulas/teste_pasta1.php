<?php
// teste_pasta.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Teste de Permissão de Escrita na Pasta Temporária</h2>";

// Define o caminho para a pasta temp, exatamente como o mPDF faria.
$tempDir = __DIR__ . '/temp';
$testeFile = $tempDir . '/teste_de_escrita.txt';

echo "Tentando escrever na pasta: <code>" . htmlspecialchars($tempDir) . "</code><br>";

// 1. A pasta existe?
if (!is_dir($tempDir)) {
    die("<strong style='color:red;'>ERRO CRÍTICO: A pasta 'temp' não existe neste local. Crie a pasta manualmente.</strong>");
} else {
    echo "<strong style='color:green;'>SUCESSO:</strong> A pasta 'temp' foi encontrada.<br>";
}

// 2. A pasta é gravável?
if (!is_writable($tempDir)) {
    die("<strong style='color:red;'>ERRO CRÍTICO: A pasta 'temp' existe, mas o PHP não tem permissão para escrever nela. Verifique as permissões (chmod 775 ou similar).</strong>");
} else {
    echo "<strong style='color:green;'>SUCESSO:</strong> O PHP reporta que a pasta é gravável.<br>";
}

// 3. O teste de escrita real funciona?
echo "Tentando criar o arquivo: <code>" . htmlspecialchars($testeFile) . "</code><br>";
$resultado = @file_put_contents($testeFile, 'O mPDF consegue escrever aqui.');

if ($resultado === false) {
    die("<strong style='color:red;'>ERRO CRÍTICO: A tentativa de escrita falhou! Isso confirma que o mPDF não consegue usar a pasta. A causa mais provável são permissões de pasta incorretas ou políticas de segurança do servidor (como SELinux).</strong>");
} else {
    echo "<strong style-='color:green; font-size: 20px;'>SUCESSO TOTAL!</strong> O arquivo foi criado com sucesso.<br>";
    // Limpa o arquivo de teste
    @unlink($testeFile);
    echo "Teste concluído e arquivo de teste removido.";
}