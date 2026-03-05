<?php
$type = $_GET['type'] ?? '';

if ($type === 'movimentacoes') {
    $file = __DIR__ . '/../templates/template_movimentacoes.csv';
    $filename = 'template_movimentacoes.csv';
} elseif ($type === 'pedidos') {
    $file = __DIR__ . '/../templates/template_pedidos.csv';
    $filename = 'template_pedidos.csv';
} else {
    die("Tipo de modelo inválido.");
}

if (!file_exists($file)) {
    die("Arquivo não encontrado.");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Adiciona BOM (Byte Order Mark) para forçar o Excel a ler os acentos em UTF-8 corretamente
echo "\xEF\xBB\xBF";

$content = file_get_contents($file);

// Verifica se já existe um BOM no próprio arquivo para não duplicar
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    $content = substr($content, 3);
}

echo $content;
exit;
