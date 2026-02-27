<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

try {
    echo "Inicializando PloomesHelper...\n";
    $ploomes = new PloomesHelper();

    $cnpjTeste = "23890658000111";
    echo "1. Buscando cliente por CNPJ '$cnpjTeste'...\n";
    $cliente = $ploomes->getClientByCnpj($cnpjTeste);

    if (!$cliente) {
        die("ERRO: Cliente Innovasell não encontrado. Impossível testar criação de interação.\n");
    }

    echo "Cliente encontrado: " . $cliente['Name'] . " (ID: " . $cliente['Id'] . ")\n";

    echo "2. Tentando criar interação (POST /InteractionRecords)...\n";

    // Tenta com TypeId 1 (Padrão?)
    try {
        $result = $ploomes->createInteraction($cliente['Id'], "Teste de Integração - Debug 403");
        echo "SUCESSO! Interação criada. ID: " . $result['Id'] . "\n";
    } catch (Exception $e) {
        echo "FALHA AO CRIAR INTERAÇÃO:\n";
        echo $e->getMessage() . "\n";

        // Vamos tentar listar os tipos de interação para ver se 1 é válido
        echo "\n3. Listando Tipos de Interação disponíveis...\n";
        // Helper method doesn't exist, using reflection or raw request if possible?
        // Let's add a temporary raw request here if we can't acces private method.
        // Actually, I can just instantiation a new helper or modify it temporarily.
        // But first, let's see the full error from step 2.
    }

} catch (Exception $e) {
    echo "ERRO GERAL: " . $e->getMessage() . "\n";
}
?>