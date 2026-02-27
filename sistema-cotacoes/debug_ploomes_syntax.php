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

// 1. Sem $ (filter=...)
testRaw("1. filter (no $)", "filter=CNPJ_CPF%20eq%20%2723890658000111%27");

// 2. Integer check (No quotes)
testRaw("2. Integer check (Id ne 0)", '$filter=Id%20ne%200');

// 3. Simple Name
testRaw("3. Simple Name", '$filter=Name%20eq%20%27Teste%27');

// 4. Double Quotes
testRaw("4. Double Quotes", '$filter=Name%20eq%20%22Teste%22');

// 5. Encoded Quotes (%27) vs Literal (') ?? 
// Note: file_get_contents might encode ' if we don't.
// Let's try raw single quote
testRaw("5. Literal Single Quote", '$filter=Name%20eq%20\'Teste\'');

?>