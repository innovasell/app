<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

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
            echo "Results Found: " . count($json['value']) . "\n";
            foreach (array_slice($json['value'], 0, 5) as $c) {
                echo " - Name: " . $c['Name'] . " | LegalName: " . ($c['LegalName'] ?? 'N/A') . "\n";
            }
        } else {
            echo "Result: Empty List\n";
        }
    } else {
        echo "Error Response: " . substr($response, 0, 100) . "\n";
    }
}

$localName = "FLAGIAN"; // Part of "FLAGIAN IMPORTACAO..."

// 1. Test filtering by LegalName >= 'FLAGIAN'
// Expected: Should find "ANNA PEGOVA" (Name) because its LegalName is "FLAGIAN..."
$q1 = '$filter=LegalName%20ge%20%27' . $localName . '%27&$top=10&$orderby=LegalName';
testRaw("1. LegalName ge 'FLAGIAN'", $q1);

// 2. Test filtering by LegalName (no $)
$q2 = 'filter=LegalName%20ge%20%27' . $localName . '%27&$top=10&$orderby=LegalName';
testRaw("2. LegalName ge 'FLAGIAN' (no $)", $q2);

?>