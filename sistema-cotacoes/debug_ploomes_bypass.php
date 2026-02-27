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
            echo "Result[0]: " . $json['value'][0]['Name'] . " [" . ($json['value'][0]['CNPJ_CPF'] ?? 'NoCNPJ') . "]\n";
        } else {
            echo "Result: Empty List\n";
        }
    }
}

$cnpj = "23890658000111";
$cnpjFmt = "23.890.658/0001-11";

// 1. Standard $filter with formatted CNPJ
testRaw("1. \$filter CNPJ Formatted", '$filter=CNPJ_CPF%20eq%20%27' . $cnpjFmt . '%27');

// 2. Standard $filter with unformatted CNPJ
testRaw("2. \$filter CNPJ Unformatted", '$filter=CNPJ_CPF%20eq%20%27' . $cnpj . '%27');

// 3. Spaced out $filter (extra spaces)
testRaw("3. \$filter Extra Spaces", '$filter=CNPJ_CPF%20%20eq%20%20%27' . $cnpj . '%27');

// 4. Parens
testRaw("4. \$filter Parens", '$filter=(CNPJ_CPF%20eq%20%27' . $cnpj . '%27)');

// 5. URL Encoded Key %24filter
testRaw("5. %24filter", '%24filter=CNPJ_CPF%20eq%20%27' . $cnpj . '%27');

// 6. 'Search' parameter (OData v4 optional)
testRaw("6. \$search", '$search=%22' . $cnpj . '%22');

// 7. 'q' parameter (Some APIs use this)
testRaw("7. parameter q", 'q=' . $cnpj);

// 8. Try filtering by another field to confirm WAF logic
testRaw("8. \$filter Name eq 'INNOVASELL'", '$filter=Name%20eq%20%27INNOVASELL%27');

?>