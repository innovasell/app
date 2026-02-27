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
    echo "Body: " . substr($response, 0, 100) . "...\n";
}

$name = "INNOVASELL";
$safeName = str_replace("'", "''", $name);

// 1. Exact query from PloomesHelper::findClient
$params = [
    '$filter' => "Name ge '$safeName'",
    '$top' => 30,
    '$orderby' => 'Name asc',
    '$select' => 'Id,Name,CNPJ,CPF,CNPJ_CPF'
];
$q1 = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
testRaw("1. PloomesHelper (Current)", $q1);

// 2. Same query WITHOUT $select
$params2 = [
    '$filter' => "Name ge '$safeName'",
    '$top' => 30,
    '$orderby' => 'Name asc'
];
$q2 = http_build_query($params2, '', '&', PHP_QUERY_RFC3986);
testRaw("2. Without \$select", $q2);

// 3. Same query with 'select' (no $)
$params3 = [
    '$filter' => "Name ge '$safeName'",
    '$top' => 30,
    '$orderby' => 'Name asc',
    'select' => 'Id,Name,CNPJ,CPF,CNPJ_CPF'
];
$q3 = http_build_query($params3, '', '&', PHP_QUERY_RFC3986);
testRaw("3. With 'select' (no $)", $q3);

?>