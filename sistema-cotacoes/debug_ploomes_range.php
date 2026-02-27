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
        if (isset($json['value'])) {
            $count = count($json['value']);
            echo "Results Found: $count\n";
            foreach (array_slice($json['value'], 0, 5) as $c) {
                echo " - " . $c['Name'] . " [CNPJ: " . ($c['CNPJ_CPF'] ?? 'N/A') . "]\n";
            }
        } else {
            echo "Result: Empty/Invalid JSON\n";
        }
    }
}

// 1. Test Name >= 'INNOVASELL' (ge)
// This should return INNOVASELL... and everything after it.
// We limit to top 20 to avoid fetching too much.
testRaw("1. Name ge 'INNOVASELL'", '$filter=Name%20ge%20%27INNOVASELL%27&$top=20&$orderby=Name');

// 2. Test Name >= 'INNOVASELL ' (with space)
testRaw("2. Name ge 'INNOVASELL '", '$filter=Name%20ge%20%27INNOVASELL%20%27&$top=20&$orderby=Name');

?>