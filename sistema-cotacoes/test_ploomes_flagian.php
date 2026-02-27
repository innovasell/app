<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

try {
    echo "Inicializando PloomesHelper (Flagian/LegalName Verification)...\n";
    $ploomes = new PloomesHelper();

    // Teste Real: FLAGIAN (Legal) -> ANNA PEGOVA (Name)
    $name = "FLAGIAN IMPORTACAO E EXPORTACAO LTDA";
    $cnpj = "46.394.094/0001-21";

    echo "1. Buscando Cliente: '$name' + CNPJ '$cnpj'...\n";
    $cliente = $ploomes->findClient($name, $cnpj);

    if ($cliente) {
        echo "   SUCESSO! Cliente encontrado:\n";
        echo "   ID: " . $cliente['Id'] . "\n";
        echo "   Nome: " . $cliente['Name'] . "\n";
        echo "   LegalName: " . ($cliente['LegalName'] ?? 'N/A') . "\n";
    } else {
        echo "   FALHA: Cliente não encontrado.\n";
    }

} catch (Exception $e) {
    echo "   ERRO EXCEÇÃO: " . $e->getMessage() . "\n";
}
?>