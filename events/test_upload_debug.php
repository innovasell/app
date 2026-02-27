<?php
// Script de teste para ver o que process_upload.php está retornando

$url = 'https://innovasell.cloud/events/api/process_upload.php';

// Cria um arquivo CSV de teste
$csvContent = "COD;CLIENTE;DATA\n001;Teste;01/01/2026";
$tmpFile = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tmpFile, $csvContent);

// Simula o upload
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csv_file' => new CURLFile($tmpFile, 'text/csv', 'test.csv'),
    'num_fatura' => 'FT_TEST'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

unlink($tmpFile);

echo "HTTP Code: $httpCode\n\n";
echo "Response Length: " . strlen($response) . "\n\n";
echo "First 500 chars:\n";
echo substr($response, 0, 500) . "\n\n";
echo "Last 200 chars:\n";
echo substr($response, -200) . "\n\n";
echo "Full Response:\n";
echo $response;
?>