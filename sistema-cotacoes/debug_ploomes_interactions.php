<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

// Acesso manual para listar tipos, já que não temos método no Helper
require_once __DIR__ . '/config_ploomes.php';

function rawGet($endpoint)
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
    return json_decode($response, true);
}

try {
    echo "--- DIAGNÓSTICO DE INTERAÇÃO ---\n";

    // 1. Verificar Tipos de Interação Disponíveis
    echo "1. Listando Tipos de Interação (/InteractionRecordTypes)...\n";
    $types = rawGet("/InteractionRecordTypes?\$select=Id,Name");

    if (isset($types['value'])) {
        echo "Tipos encontrados:\n";
        foreach ($types['value'] as $t) {
            echo " - ID: " . $t['Id'] . " | Nome: " . $t['Name'] . "\n";
        }
    } else {
        echo "ALERTA: Não foi possível listar tipos. Resposta: " . json_encode($types) . "\n";
    }

    // 2. Tentar Criar Interação com Cliente Conhecido
    echo "\n2. Testando Criação de Interação...\n";
    $ploomes = new PloomesHelper();

    // Buscar cliente (sabemos que busca CNPJ funciona, mas vamos validar)
    $cliente = $ploomes->getClientByCnpj("23890658000111");

    if ($cliente) {
        echo "Cliente ID: " . $cliente['Id'] . "\n";

        // Tentar criar com TypeId = 1 (Padrão)
        echo "Tentando criar com TypeId = 1...\n";
        try {
            $res = $ploomes->createInteraction($cliente['Id'], "Debug Script Teste", 1);
            echo "SUCESSO (Type 1)! ID: " . $res['Id'] . "\n";
        } catch (Exception $e) {
            echo "FALHA (Type 1): " . $e->getMessage() . "\n";

            // Se falhar e tivermos outros tipos, tentar o primeiro da lista
            if (isset($types['value']) && count($types['value']) > 0) {
                $firstType = $types['value'][0];
                if ($firstType['Id'] != 1) {
                    echo "Tentando com TypeId = " . $firstType['Id'] . " (" . $firstType['Name'] . ")...\n";
                    try {
                        $res = $ploomes->createInteraction($cliente['Id'], "Debug Script Teste Backup", $firstType['Id']);
                        echo "SUCESSO (Type " . $firstType['Id'] . ")! ID: " . $res['Id'] . "\n";
                    } catch (Exception $e2) {
                        echo "FALHA (Type " . $firstType['Id'] . "): " . $e2->getMessage() . "\n";
                    }
                }
            }
        }
    } else {
        echo "ERRO CRÍTICO: Cliente não encontrado para teste de interação.\n";
    }

} catch (Exception $e) {
    echo "ERRO GERAL: " . $e->getMessage() . "\n";
}
?>