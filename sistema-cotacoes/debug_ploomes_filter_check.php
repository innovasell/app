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

// 1. Check current implementation (filter without $)
// Expectation: Returns Valmari (or random), PROVING it is ignored.
$q1 = 'filter=CNPJ_CPF%20eq%20%27' . $cnpj . '%27&$select=Id,Name,CNPJ_CPF';
testRaw("1. Current Impl (filter no $)", $q1);

// 2. Test $filter with Name (Innovasell)
// Did this trigger 403 before? Name eq 'Teste' passed.
$q2 = '$filter=Name%20eq%20%27INNOVASELL%27&$select=Id,Name,CNPJ_CPF';
testRaw("2. \$filter=Name eq INNOVASELL", $q2);

// 3. Test $filter with startswith Name
$q3 = '$filter=startswith(Name,%27INNOVASELL%27)&$select=Id,Name,CNPJ_CPF';
testRaw("3. \$filter=startswith(Name, INNOVASELL)", $q3);

// 4. Test $filter with CNPJ formatted
// Maybe WAF hates unformatted number strings?
$cnpjFmt = "23.890.658/0001-11";
$q4 = '$filter=CNPJ_CPF%20eq%20%27' . $cnpjFmt . '%27&$select=Id,Name,CNPJ_CPF';
testRaw("4. \$filter with Formatted CNPJ", $q4);

// 5. Test $filter with CNPJ using contains
$q5 = '$filter=contains(CNPJ_CPF,%2723890658000111%27)&$select=Id,Name,CNPJ_CPF';
testRaw("5. \$filter=contains(CNPJ)", $q5);

?>