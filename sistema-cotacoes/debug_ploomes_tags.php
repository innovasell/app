<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

echo "Fetching Tags...\n";

// Raw Request to /Tags
require_once __DIR__ . '/config_ploomes.php';
$url = PLOOMES_API_URL . "/Tags";
$headers = ['User-Key: ' . PLOOMES_USER_KEY, 'Content-Type: application/json'];
$ctx = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'method' => 'GET', 'ignore_errors' => true]]);
$res = file_get_contents($url, false, $ctx);
$json = json_decode($res, true);

if (isset($json['value'])) {
    foreach ($json['value'] as $t) {
        echo "ID: " . $t['Id'] . " | Name: " . $t['Name'] . " | Color: " . ($t['Color'] ?? 'N/A') . "\n";
    }
} else {
    echo "Error: $res\n";
}
?>