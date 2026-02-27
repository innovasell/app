<?php
// Script de diagnóstico de conexão - Retorno em TEXTO (não JSON)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$porta = "3306";
$usuario = "u849249951_innovasell";
$senha = "Invti@169";
$banco = "u849249951_innovasell";

echo "<h1>Diagnóstico de Conexão</h1>";
echo "<p>Host: $host | Porta: $porta | Usuário: $usuario | Banco: $banco</p>";
echo "<hr>";

// TESTE 1: MySQLi padrão (sem porta explícita)
echo "<h3>Teste 1: MySQLi (Sem porta explícita)</h3>";
try {
    $conn1 = new mysqli($host, $usuario, $senha, $banco);
    if ($conn1->connect_error) {
        echo "<span style='color:red'>ERRO: " . $conn1->connect_error . "</span>";
    } else {
        echo "<span style='color:green'>SUCESSO! Host info: " . $conn1->host_info . "</span>";
        $conn1->close();
    }
} catch (Exception $e) {
    echo "<span style='color:red'>EXCEPTION: " . $e->getMessage() . "</span>";
}

// TESTE 2: MySQLi (COM porta explícita)
echo "<h3>Teste 2: MySQLi (Com porta $porta)</h3>";
try {
    $conn2 = new mysqli($host, $usuario, $senha, $banco, (int) $porta);
    if ($conn2->connect_error) {
        echo "<span style='color:red'>ERRO: " . $conn2->connect_error . "</span>";
    } else {
        echo "<span style='color:green'>SUCESSO! Host info: " . $conn2->host_info . "</span>";
        $conn2->close();
    }
} catch (Exception $e) {
    echo "<span style='color:red'>EXCEPTION: " . $e->getMessage() . "</span>";
}

// TESTE 3: PDO (Igual ao sistema-cotacoes)
echo "<h3>Teste 3: PDO (DSN: mysql:host=$host;port=$porta;dbname=$banco)</h3>";
try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ];
    $pdo = new PDO("mysql:host=$host;port=$porta;dbname=$banco;charset=utf8mb4", $usuario, $senha, $options);
    echo "<span style='color:green'>SUCESSO! Atributos: " . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) . "</span>";
} catch (PDOException $e) {
    echo "<span style='color:red'>ERRO PDO: " . $e->getMessage() . "</span>";
}

echo "<hr>";
echo "<p>Diagnóstico finalizado.</p>";
?>