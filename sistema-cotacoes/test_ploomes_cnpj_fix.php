<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

try {
    echo "Inicializando PloomesHelper (RFC3986 Encoding Check)...\n";
    $ploomes = new PloomesHelper();

    $cnpjTeste = "23890658000111";

    echo "Buscando por CNPJ '$cnpjTeste'...\n";
    $cliente = $ploomes->getClientByCnpj($cnpjTeste);

    if ($cliente) {
        echo "SUCESSO! Cliente encontrado:\n";
        echo "ID: " . $cliente['Id'] . "\n";
        echo "Nome: " . $cliente['Name'] . "\n";
    } else {
        echo "FALHA: Cliente não encontrado ou erro persistente.\n";
    }

} catch (Exception $e) {
    echo "ERRO EXCEÇÃO: " . $e->getMessage() . "\n";
}
?>