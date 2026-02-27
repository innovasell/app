<?php
require 'c:\Users\hecto\Documents\H Hansen\innovasellcloud\events\conexao.php';

function descTable($conn, $table)
{
    echo "TABLE: $table\n";
    $sql = "DESCRIBE $table";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo $row['Field'] . " | " . $row['Type'] . "\n";
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    echo "\n";
}

descTable($conn, 'cot_representante');
descTable($conn, 'users');
descTable($conn, 'usuarios');
?>