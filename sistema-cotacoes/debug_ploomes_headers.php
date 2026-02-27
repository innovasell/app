<?php
require_once __DIR__ . '/classes/PloomesHelper.php';
require_once __DIR__ . '/config_ploomes.php';

function testRequest($desc, $path)
{
    echo "\n--- $desc ---\n";
    echo "Path: $path\n";
    $url = PLOOMES_API_URL . $path;

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

    if (strpos($statusLine, '200') === false) {
        echo "Response: " . substr($response, 0, 300) . "...\n";
    } else {
        echo "Response: OK (Len: " . strlen($response) . ")\n";
    }
}

// 1. Teste básico de novo
testRequest("1. Lista Simples", "/Contacts?\$top=1");

// 2. Teste com Filtro (RFC3986)
$params = ['$filter' => "CNPJ_CPF eq '23890658000111'"];
$q = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
testRequest("2. Filtro CNPJ (Generic)", "/Contacts?$q");

// 3. Teste com ID (se soubermos um ID valido, mas vamos tentar filtro por Nome simples)
$params2 = ['$filter' => "Name ne null"];
$q2 = http_build_query($params2, '', '&', PHP_QUERY_RFC3986);
testRequest("3. Filtro Genérico (Name ne null)", "/Contacts?$q2");

// 4. Teste InteractionTypes
testRequest("4. InteractionTypes", "/InteractionRecordTypes");

?>