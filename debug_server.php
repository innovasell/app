<?php
echo "<h1>Estrutura de Diretórios</h1>";
echo "<p>Diretório Atual: " . __DIR__ . "</p>";

$files = scandir(__DIR__);
echo "<ul>";
foreach ($files as $file) {
    if ($file == '.' || $file == '..')
        continue;
    $type = is_dir($file) ? "[DIR]" : "[FILE]";
    echo "<li><strong>$file</strong> $type</li>";
}
echo "</ul>";
