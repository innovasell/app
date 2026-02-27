<?php
$host = "127.0.0.1";
$usuario = "root";
$senha = "";

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $usuario, $senha);
    $stmt = $pdo->query("SHOW DATABASES");
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available Databases:\n";
    foreach ($dbs as $db) {
        echo "- $db\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>