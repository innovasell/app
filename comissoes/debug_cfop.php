<?php
function normalizeHeader($h) {
    return mb_strtoupper(trim(str_replace(['"', "'"], "", $h)), 'UTF-8');
}
function readCsvRows($filepath) {
    $handle = fopen($filepath, "r");
    $firstLine = fgets($handle);
    rewind($handle);
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    $delimiter = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

    $rows = [];
    $header = fgetcsv($handle, 0, $delimiter);
    $rows['header'] = $header;
    $rows['data'] = [];

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rows['data'][] = $data;
    }
    fclose($handle);
    return $rows;
}

$file = __DIR__ . '/templates/template_movimentacoes.csv';
$csv = readCsvRows($file);
print_r(array_map('normalizeHeader', $csv['header']));
