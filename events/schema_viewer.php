<?php
// events/schema_viewer.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'conexao.php';

function showTable($conn, $table)
{
    echo "<div class='card mb-4'><div class='card-header bg-dark text-white'>Table: $table</div><div class='card-body'><table class='table table-bordered table-sm'>";
    echo "<thead class='table-light'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead><tbody>";

    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $val)
                echo "<td>" . htmlspecialchars($val ?? 'NULL') . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='6' class='text-danger'>Error: " . $conn->error . "</td></tr>";
    }
    echo "</tbody></table></div></div>";
}
?>
<!DOCTYPE html>
<html>

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Schema Viewer</title>
</head>

<body class="p-4 bg-light">
    <h1>Database Schema Inspector</h1>
    <p>Database:
        <?= $banco ?>
    </p>
    <?php
    showTable($conn, 'cot_representante');
    showTable($conn, 'users');
    showTable($conn, 'usuarios');
    // Also check for any existing portal permission tables
    showTable($conn, 'cot_menu_permissoes');
    ?>
</body>

</html>