<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbName = 'u849249951_innovasell';
$outputFile = __DIR__ . '/gerador-formulas/sql_db/export_formulacoes_new.sql';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check for mysql dump command availability
    $output = shell_exec('mysql --version');
    if ($output) {
        echo "MySQL binary found. Exporting new data...\n";
        // Export only data for the 4 tables, no create info (to append)
        $tables = "formulacoes sub_formulacoes fases ingredientes ativos_destaque";
        // We really only want the *new* data (ID > X), but since we re-created the whole DB locally...
        // If the user wants to sync to PROD, they should probably drop/create there too OR append.
        // Assuming they want to REPLACE their online data with this full import:

        $cmd = "mysqldump -h $host -u $user \"$dbName\" $tables --hex-blob --default-character-set=utf8mb4 > \"$outputFile\"";
        echo "Executing: $cmd\n";
        system("cmd /c \"C:\xampp\mysql\bin\mysqldump.exe -h $host -u $user $dbName $tables --hex-blob --default-character-set=utf8mb4 > \"$outputFile\"\"");
        echo "Export created at: $outputFile\n";
    } else {
        echo "mysqldump not found or path issue. Please ensure XAMPP mysql/bin is in PATH.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>