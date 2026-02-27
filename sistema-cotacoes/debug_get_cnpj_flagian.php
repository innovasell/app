<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

// Helper to fetch CNPJ
function getCnpj($search)
{
    echo "\n--- Searching for '$search' ---\n";
    $safeName = str_replace("'", "''", $search);
    $params = [
        '$filter' => "LegalName ge '$safeName'",
        '$top' => 1,
        '$orderby' => "LegalName asc"
    ]; // SELECT IS REMOVED TO AVOID WAF
    // But wait, if I don't select, I get all fields? Yes.

    // RAW GET
    require_once __DIR__ . '/config_ploomes.php';
    $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $url = PLOOMES_API_URL . "/Contacts?" . $qs;
    $headers = ['User-Key: ' . PLOOMES_USER_KEY, 'Content-Type: application/json'];
    $ctx = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'method' => 'GET', 'ignore_errors' => true]]);
    $res = file_get_contents($url, false, $ctx);
    $json = json_decode($res, true);

    if (isset($json['value']) && count($json['value']) > 0) {
        $c = $json['value'][0];
        echo "Found: " . $c['Name'] . "\n";
        echo "Legal: " . $c['LegalName'] . "\n";
        echo "CNPJ: " . ($c['CNPJ_CPF'] ?? $c['CNPJ'] ?? $c['CPF'] ?? 'N/A') . "\n";
    } else {
        echo "Not found.\n";
    }
}

getCnpj("FLAGIAN");
?>