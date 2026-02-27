<?php
require_once __DIR__ . '/classes/PloomesHelper.php';

try {
    echo "Inicializando PloomesHelper (Tags & Content Verification)...\n";
    $ploomes = new PloomesHelper();

    // 1. Find Client (Innovasell)
    $name = "INNOVASELL";
    $cnpj = "23890658000111";
    $cliente = $ploomes->findClient($name, $cnpj);

    if (!$cliente) {
        die("ERRO: Cliente Innovasell não encontrado para o teste.\n");
    }

    echo "Cliente encontrado: " . $cliente['Name'] . " (ID: " . $cliente['Id'] . ")\n";

    // 2. Prepare Content
    $num_orcamento = "TESTE-" . date('His');
    $conteudo = "Orçamento #$num_orcamento criado/atualizado (TESTE VIA SCRIPT).\n\n";
    $conteudo .= "Resumo do Pedido:\n";
    $conteudo .= "- PRODUTO TESTE A | Qtd: 10 kg | Net: 10.00 | Full: 12.00\n";
    $conteudo .= "- PRODUTO TESTE B | Qtd: 5 kg | Net: 50.00 | Full: 60.00\n";

    // 3. Create Interaction with Tag
    echo "Criando interação com Tag 0_COTAÇÃO (136473)...\n";
    $res = $ploomes->createInteraction($cliente['Id'], $conteudo, 1, [136473]);

    if ($res) {
        echo "SUCESSO! Interação criada.\n";
        echo "ID da Interação: " . ($res['Id'] ?? 'N/A') . "\n";
        print_r($res);
    } else {
        echo "FALHA ao criar interação.\n";
    }

} catch (Exception $e) {
    echo "ERRO EXCEÇÃO: " . $e->getMessage() . "\n";
}
?>