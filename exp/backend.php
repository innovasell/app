<?php
header("Access-Control-Allow-Origin: *");
// Limites altos de tempo para upload de iPad
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

// ==============================
// URL DO WEBHOOK N8N
// ==============================
// ==============================
// URL DO WEBHOOK N8N
// ==============================
// ALTERADO PARA PRODUÇÃO (webhook-test -> webhook)
$webhook_url = "https://bcgiannini.app.n8n.cloud/webhook/coleta-ipad";

function rearrayFiles(&$file_post)
{
    $file_ary = array();
    if (!isset($file_post['name']) || !is_array($file_post['name']))
        return $file_ary;
    $keys = array_keys($file_post);
    for ($i = 0; $i < count($file_post['name']); $i++) {
        foreach ($keys as $key) {
            $file_ary[$i][$key] = $file_post[$key][$i];
        }
    }
    return $file_ary;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // DEBUG: Logar recebimento
    $log = "========================================\n";
    $log .= "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
    $log .= "POST Data: " . print_r($_POST, true) . "\n";
    $log .= "FILES Data: " . print_r($_FILES, true) . "\n";

    $postFields = [
        'data' => date('Y-m-d H:i:s'),
        'colaborador' => $_POST['colaborador'] ?? '',
        'nf' => $_POST['nf'] ?? '',
        'motorista_nome' => $_POST['motorista_nome'] ?? '',
        'motorista_cpf' => $_POST['motorista_cpf'] ?? '',
        'motorista_placa' => $_POST['motorista_placa'] ?? ''
    ];

    // Processar Mercadoria
    if (!empty($_FILES['fotos_mercadoria'])) {
        $files = rearrayFiles($_FILES['fotos_mercadoria']);
        foreach ($files as $idx => $f) {
            if ($f['error'] === 0) {
                $postFields["foto_mercadoria_$idx"] = new CURLFile($f['tmp_name'], $f['type'], $f['name']);
            }
        }
    }

    // Processar Coleta
    if (!empty($_FILES['fotos_coleta'])) {
        $files = rearrayFiles($_FILES['fotos_coleta']);
        foreach ($files as $idx => $f) {
            if ($f['error'] === 0) {
                $postFields["foto_coleta_$idx"] = new CURLFile($f['tmp_name'], $f['type'], $f['name']);
            }
        }
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);

    $result = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $log .= "CURL Info: HTTP $httpCode | Error: $err\n";
    $log .= "Response: $result\n";
    file_put_contents('debug_upload.txt', $log, FILE_APPEND);

    if ($err) {
        http_response_code(500);
        echo "Erro Curl: $err";
    } else {
        echo "Sucesso n8n: $result";
    }

} else {
    echo "Método inválido";
}
?>