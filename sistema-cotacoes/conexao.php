<?php
// Configuração para Hostinger (Atualizado)
$host = "localhost";
$porta = "3306"; // Porta padrão MySQL
$usuario = "u849249951_innovasell";
$senha = "Invti@169";
$banco = "u849249951_innovasell";

try {
    // Definindo timeout para não travar se o firewall bloquear
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5, // Tenta por 5 segundos antes de desistir
    ];

    $pdo = new PDO("mysql:host=$host;port=$porta;dbname=$banco;charset=utf8mb4", $usuario, $senha, $options);

    // echo "Conexão remota realizada com sucesso!";

} catch (PDOException $e) {
    // Isso vai nos dizer se é erro de SENHA ou de BLOQUEIO (IP)
    echo "<b>Ambiente Detectado:</b> " . PHP_OS . "<br>";
    echo "<b>Host Tentado:</b> $host<br>";
    echo "<b>Usuário:</b> $usuario<br>";
    die("<b>Erro na conexão:</b> " . $e->getMessage());
}
?>