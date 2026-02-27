<?php
ini_set('memory_limit', '1024M');
set_time_limit(0);

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbName = 'u849249951_innovasell';
$sqlFile = __DIR__ . '/gerador-formulas/sql_db/u849249951_innovasell.sql';

echo "Restoring database...\n";

try {
    // 1. Connect and Create DB
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Creating database '$dbName' if not exists...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
    $pdo->exec("USE `$dbName`");

    // 2. Read SQL File
    if (!file_exists($sqlFile)) {
        die("Error: SQL file not found at $sqlFile\n");
    }

    echo "Reading SQL file (this may take a moment)...\n";
    // For 40MB, reading entirely into memory is usually fine with 1GB limit.
    // However, splitting by ; is naive if ; is inside strings.
    // But phpMyAdmin dumps are usually well formatted with one query per line or clear limits.
    // Let's try executing line by line or chunked?
    // Actually, command line mysql is best if available.
    // Let's try running mysql command first? No, I promised PHP.

    // Naive split approach for restoration if mysql binary isn't guaranteed.
    // BUT, big INSERT statements can span multiple lines.
    // The dump seems to use standard formatting.

    // Let's check if 'mysql' command exists via shell_exec?
    $output = shell_exec('mysql --version');
    if ($output) {
        echo "MySQL binary found: $output\nUsing system command for import (faster/safer)...\n";
        // Attempt system command
        // Note: Windows paths might need quotes.
        $cmd = "mysql -h $host -u $user \"$dbName\" < \"$sqlFile\"";
        echo "Executing: $cmd\n";
        $ret = null;
        system($cmd, $ret);
        if ($ret === 0) {
            echo "Import SUCCESS via mysql command!\n";
            exit(0);
        } else {
            echo "Import via command failed (code $ret). Falling back to PHP PDO...\n";
        }
    } else {
        echo "MySQL binary not found in PATH. Using PHP PDO...\n";
    }

    // PHP Implementation (Fallback)
    // Read file line by line
    $handle = fopen($sqlFile, "r");
    if ($handle) {
        $query = '';
        $count = 0;
        while (($line = fgets($handle)) !== false) {
            $trimLine = trim($line);
            if ($trimLine == '' || strpos($trimLine, '--') === 0 || strpos($trimLine, '/*') === 0) {
                continue;
            }

            $query .= $line;
            if (substr(trim($line), -1, 1) == ';') {
                try {
                    $pdo->exec($query);
                    $count++;
                    if ($count % 50 == 0)
                        echo ".";
                    if ($count % 1000 == 0)
                        echo " $count queries executed\n";
                } catch (Exception $e) {
                    echo "\nError in query: " . substr($query, 0, 100) . "...\n" . $e->getMessage() . "\n";
                    // continue? usually yes for dumps
                }
                $query = '';
            }
        }
        fclose($handle);
        echo "\nDone! $count queries executed.\n";
    } else {
        echo "Error opening file.\n";
    }

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}
?>