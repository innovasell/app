<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Testing config.php inclusion...<br>";

try {
    require_once 'gerador-formulas/config.php';
    echo "config.php included successfully.<br>";
} catch (Throwable $e) {
    echo "CRITICAL ERROR loading config.php: " . $e->getMessage() . "<br>";
    exit;
}

echo "Testing Database Connection...<br>";
echo "Host: $db_host<br>";
echo "User: $db_user<br>";
// Don't echo pass
echo "Name: $db_name<br>";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully to MySQL via mysqli!";
?>