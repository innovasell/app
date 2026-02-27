<?php
require_once __DIR__ . '/config_ploomes.php';

function testRaw($desc, $queryString)
{
    echo "\n--- $desc ---\n";
    $url = PLOOMES_API_URL . "/Contacts?" . $queryString;

    $headers = [
        'User-Key: ' . PLOOMES_USER_KEY,
        'Content-Type: application/json',
        'User-Agent: InnovasellDebug/1.0'
    ];

    $options = [
        'http' => [
            'header' => implode("\r\n", $headers),
            'method' => 'GET', // GET
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    $statusLine = $http_response_header[0];
    echo "Status: $statusLine\n";

    if (strpos($statusLine, '200') !== false) {
        $json = json_decode($response, true);
        if (isset($json['value']) && count($json['value']) > 0) {
            echo "First Result Keys:\n";
            print_r(array_keys($json['value'][0]));

            // Check specific keys
            $c = $json['value'][0];
            echo "\nValues check:\n";
            echo "Name: " . $c['Name'] . "\n";
            echo "CNPJ_CPF: " . ($c['CNPJ_CPF'] ?? 'NULL/MISSING') . "\n";
            echo "Cnpj_Cpf: " . ($c['Cnpj_Cpf'] ?? 'NULL/MISSING') . "\n";
            echo "CPF_CNPJ: " . ($c['CPF_CNPJ'] ?? 'NULL/MISSING') . "\n";
            echo "Document: " . ($c['Document'] ?? 'NULL/MISSING') . "\n";
        }
    }
}

// Fetch 1 contact using Name filter (Safe)
testRaw("Dump Client Structure", '$filter=Name%20ge%20%27INNOVASELL%27&$top=1');
?>