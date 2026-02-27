<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

// Helper local para debug
function debugSearch($desc, $nameQuery)
{
    echo "\n--- $desc ---\n";
    echo "Query Name: '$nameQuery'\n";

    $ploomes = new PloomesHelper();
    // Bypass visibility just for debug or use reflection?
    // Let's us findClient public method.

    // Simulating no CNPJ match first to see what 'candidates' would be found if we just dumped matches?
    // Actually findClient returns a single match.
    // We want to see the list.
    // Let's use a raw request function copy-pasted for debug speed.
    $safeName = str_replace("'", "''", $nameQuery);
    $params = [
        '$filter' => "Name ge '$safeName'",
        '$top' => 10,
        '$orderby' => 'Name asc'
    ];
    $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    // RAW GET
    require_once __DIR__ . '/config_ploomes.php';
    $url = PLOOMES_API_URL . "/Contacts?" . $qs;
    $headers = ['User-Key: ' . PLOOMES_USER_KEY, 'Content-Type: application/json', 'User-Agent: Debug'];
    $ctx = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'method' => 'GET', 'ignore_errors' => true]]);
    $res = file_get_contents($url, false, $ctx);
    $json = json_decode($res, true);

    if (isset($json['value'])) {
        foreach ($json['value'] as $c) {
            echo " - Name: " . str_pad($c['Name'], 30) . " | LegalName: " . ($c['LegalName'] ?? 'N/A') . "\n";
        }
    } else {
        echo "Error: " . substr($res, 0, 100) . "\n";
    }
}

// Case from User
$localName = "SHT INDUSTRIA E COMERCIO COSMETICOS";

// 1. Current Strategy
debugSearch("1. Current: Name >= '$localName'", $localName);

// 2. First Word Strategy
$firstWord = explode(' ', $localName)[0];
debugSearch("2. First Word: Name >= '$firstWord'", $firstWord);

?>