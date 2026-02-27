<?php
// Script para testar diretamente o que process_upload.php está retornando

// Simula o ambiente
$_POST['num_fatura'] = 'FT00004210';
$_FILES['csv_file'] = [
    'name' => 'test.csv',
    'type' => 'text/csv',
    'tmp_name' => __DIR__ . '/FT00004210_202512191734291438.csv',
    'error' => UPLOAD_ERR_OK,
    'size' => filesize(__DIR__ . '/FT00004210_202512191734291438.csv')
];

// Captura toda a saída
ob_start();
include __DIR__ . '/api/process_upload.php';
$output = ob_get_clean();

// Mostra informações de debug
echo "=== DEBUG OUTPUT ===\n";
echo "Output Length: " . strlen($output) . "\n";
echo "First 200 chars:\n";
echo substr($output, 0, 200) . "\n\n";

if (strlen($output) > 200) {
    echo "Last 200 chars:\n";
    echo substr($output, -200) . "\n\n";
}

echo "Hex of first 50 bytes:\n";
echo bin2hex(substr($output, 0, 50)) . "\n\n";

echo "=== FULL OUTPUT ===\n";
echo $output;
?>