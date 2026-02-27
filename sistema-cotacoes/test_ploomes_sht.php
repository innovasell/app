<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

try {
    echo "Inicializando PloomesHelper (SHT Fallback Verification)...\n";
    $ploomes = new PloomesHelper();

    // Teste Real: SHT INDUSTRIA -> SHT COSMETICOS
    $name = "SHT INDUSTRIA E COMERCIO COSMETICOS";
    $cnpj = "25.317.411/0001-36";

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