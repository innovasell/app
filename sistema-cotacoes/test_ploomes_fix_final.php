<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

try {
    echo "Inicializando PloomesHelper (Robust Search Verification 403 Fix)...\n";
    $ploomes = new PloomesHelper();

    // Teste Real: INNOVASELL + CNPJ
    $name = "INNOVASELL";
    $cnpj = "23890658000111"; // 23.890.658/0001-11

    echo "1. Buscando Cliente: '$name' + CNPJ '$cnpj'...\n";
    $cliente = $ploomes->findClient($name, $cnpj);

    if ($cliente) {
        echo "   SUCESSO! Cliente encontrado:\n";
        echo "   ID: " . $cliente['Id'] . "\n";
        echo "   Nome: " . $cliente['Name'] . "\n";
        // echo "   CNPJ: " . ($cliente['CNPJ'] ?? 'N/A') . "\n";
    } else {
        echo "   FALHA: Cliente não encontrado.\n";
    }

} catch (Exception $e) {
    echo "   ERRO EXCEÇÃO: " . $e->getMessage() . "\n";
}
?>