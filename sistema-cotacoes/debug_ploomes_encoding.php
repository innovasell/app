<?php
require_once __DIR__ . '/config_ploomes.php';

function testRaw($desc, $queryString)
{
    echo "\n--- $desc ---\n";
    $url = PLOOMES_API_URL . "/Contacts?" . $queryString;
    echo "URL: $url\n";

    $headers = [
        'User-Key: ' . PLOOMES_USER_KEY,
        'Content-Type: application/json',
        'User-Agent: InnovasellDebug/1.0'
    ];

    $options = [
        'http' => [
            'header' => implode("\r\n", $headers),
            'method' => 'GET',
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    $statusLine = $http_response_header[0];
    echo "Status: $statusLine\n";
}

$cnpj = "23890658000111";

// 1. Literal $filter (não codificado o $)
// Espaços como %20 (RFC3986 para valores)
$q1 = '$filter=CNPJ_CPF%20eq%20%27' . $cnpj . '%27';
testRaw("1. Literal \$filter", $q1);

// 2. Encoded %24filter
$q2 = '%24filter=CNPJ_CPF%20eq%20%27' . $cnpj . '%27';
testRaw("2. Encoded %24filter", $q2);

?>