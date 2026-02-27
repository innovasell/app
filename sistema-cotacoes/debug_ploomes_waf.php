<?php
require_once __DIR__ . '/config_ploomes.php';

function testRaw($desc, $queryString)
{
    echo "\n--- $desc ---\n";
    $url = PLOOMES_API_URL . "/Contacts?" . $queryString;
    // echo "URL: $url\n";

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

    if (strpos($statusLine, '200') !== false) {
        $json = json_decode($response, true);
        if (isset($json['value']) && count($json['value']) > 0) {
            echo "First Result: " . $json['value'][0]['Name'] . " (CNPJ: " . ($json['value'][0]['CNPJ_CPF'] ?? 'N/A') . ")\n";
        } else {
            echo "Result: Empty List\n";
        }
    }
}

$cnpj = "23890658000111"; // Innovasell

// 1. $filter only (NO select)
$q1 = '$filter=CNPJ_CPF%20eq%20%27' . $cnpj . '%27';
testRaw("1. \$filter only (CNPJ unformatted)", $q1);

// 2. filter only (NO select, no $)
$q2 = 'filter=CNPJ_CPF%20eq%20%27' . $cnpj . '%27';
testRaw("2. filter only (no $)", $q2);

// 3. $filter AND select (no $ on select)
$q3 = '$filter=CNPJ_CPF%20eq%20%27' . $cnpj . '%27&select=Id,Name';
testRaw("3. \$filter AND select (no $)", $q3);

// 4. Encoded $ (%24) filter w/o select
$q4 = '%24filter=CNPJ_CPF%20eq%20%27' . $cnpj . '%27';
testRaw("4. %24filter only", $q4);

// 5. Name search with $filter
$q5 = '$filter=Name%20eq%20%27INNOVASELL%27';
testRaw("5. \$filter Name only", $q5);

?>