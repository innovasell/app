<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

// Acesso à classe via Reflection para testar método privado ou fazer requisição manual?
// Vamos fazer manual para ter controle total
require_once __DIR__ . '/config_ploomes.php';

function rawRequest($endpoint)
{
    $url = PLOOMES_API_URL . $endpoint;
    $headers = [
        'User-Key: ' . PLOOMES_USER_KEY,
        'Content-Type: application/json'
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

    echo "URL: $url\n";
    // echo "Response: " . substr($response, 0, 200) . "...\n";

    // Check status
    $statusLine = $http_response_header[0];
    echo "Status: $statusLine\n";

    if (strpos($statusLine, '200') !== false) {
        return true;
    }
    return false;
}

echo "--- TESTE 1: Listar 1 Contato (Sem Filtro) ---\n";
rawRequest("/Contacts?\$top=1");

echo "\n--- TESTE 2: Filtro CNPJ (Encoding Padrão - space as +) ---\n";
$cnpj = "23890658000111";
$params = ['$filter' => "CNPJ_CPF eq '$cnpj'"];
$query = http_build_query($params); // Default +
rawRequest("/Contacts?$query");

echo "\n--- TESTE 3: Filtro CNPJ (Encoding RFC3986 - space as %20) ---\n";
$query2 = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
rawRequest("/Contacts?$query2");

echo "\n--- TESTE 4: Filtro Manual (Espaços literais - não recomendado mas para teste) ---\n";
// Browsers/Curl handle this, file_get_contents might fail if not encoded, but let's try raw %20
$query3 = "\$filter=CNPJ_CPF%20eq%20'$cnpj'";
rawRequest("/Contacts?$query3");

?>