<?php
// Tentar conectar como root sem senha (padrão XAMPP)
$host = "127.0.0.1";
$usuario = "root";
$senha = "";
$banco = "u849249951_innovasell"; // Tentando o mesmo nome de banco

try {
    $pdo = new PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    echo "ROOT CONNECTION SUCCESS";
} catch (PDOException $e) {
    // Tenta conectar sem especificar o banco, só para ver se o usuario funciona
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $usuario, $senha);
        echo "ROOT USER WORKS (DATABASE NAME MIGHT BE WRONG)";
    } catch (PDOException $e2) {
        echo "ROOT FAILED: " . $e2->getMessage();
    }
}
?>