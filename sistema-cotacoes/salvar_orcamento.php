<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acesso inválido.");
}

// Coleta de Dados
$data = $_POST['data'] ?? date('Y-m-d');
$cliente = strtoupper($_POST['cliente'] ?? '');
$cnpj = $_POST['cnpj'] ?? ''; // Novo campo
$uf = $_POST['uf'] ?? '';
$suframa = $_POST['suframa'] ?? 'Não';
$suspensao_ipi = $_POST['suspensao_ipi'] ?? 'Não';
$cotado_por = strtoupper($_POST['cotado_por'] ?? '');
$dolar = str_replace(',', '.', $_POST['dolar'] ?? '');
$itens = $_POST['itens'] ?? [];
$incluir_net = $_POST['incluir_net'] ?? 'false';
$num_orcamento = time(); // Usa timestamp Unix para caber em INT(11) (até 2038)

try {
    $pdo->beginTransaction();
    $sql_insert = "INSERT INTO `cot_cotacoes_importadas` (`DATA`, `RAZÃO SOCIAL`, `UF`, `COD DO PRODUTO`, `PRODUTO`, `UNIDADE`, `ORIGEM`, `NCM`, `VOLUME`, `EMBALAGEM_KG`, `IPI %`, `ICMS`, `PREÇO NET USD/KG`, `PREÇO FULL USD/KG`, `SUFRAMA`, `SUSPENCAO_IPI`, `COTADO_POR`, `DOLAR COTADO`, `NUM_ORCAMENTO`, `DISPONIBILIDADE`, `PRICE LIST`) VALUES (:data, :razao_social, :uf, :codigo, :produto, :unidade, :origem, :ncm, :volume, :embalagem, :ipi, :icms, :preco_net, :preco_full, :suframa, :suspensao_ipi, :cotado_por, :dolar, :num_orcamento, :disponibilidade, :price_list)";
    $stmt_insert = $pdo->prepare($sql_insert);

    foreach ($itens as $item) {
        if (empty($item['codigo']))
            continue;
        $stmt_insert->execute([
            ':data' => $data,
            ':razao_social' => $cliente,
            ':uf' => $uf,
            ':codigo' => $item['codigo'],
            ':produto' => $item['produto'],
            ':unidade' => $item['unidade'],
            ':origem' => $item['origem'],
            ':ncm' => $item['ncm'],
            ':volume' => str_replace(',', '.', $item['volume']),
            ':embalagem' => str_replace(',', '.', $item['embalagem']),
            ':ipi' => str_replace(',', '.', $item['ipi']),
            ':icms' => str_replace(',', '.', $item['icms']),
            ':preco_net' => str_replace(',', '.', $item['preco_net']),
            ':preco_full' => str_replace(',', '.', $item['preco_full']),
            ':suframa' => $suframa,
            ':suspensao_ipi' => $suspensao_ipi,
            ':cotado_por' => $cotado_por,
            ':dolar' => $dolar,
            ':num_orcamento' => $num_orcamento,
            ':disponibilidade' => $item['disponibilidade'],
            ':price_list' => !empty($item['valor_price_list']) ? str_replace(',', '.', $item['valor_price_list']) : null
        ]);
    }
    $pdo->commit();

    // --- Disparo de E-mail via Server-Side (Direct Include) ---
    // (Mais robusto que CURL no mesmo servidor)
    // Precisamos definir $_GET para o script funcionar como se fosse chamado v ia URL
    $_GET['num'] = $num_orcamento;
    $_GET['email'] = $_SESSION['representante_email'] ?? '';
    $_GET['incluir_net'] = ($incluir_net === 'true') ? 'true' : 'false';

    // Output buffering para capturar o HTML gerado pelo enviar_orcamento e não quebrar o header location
    ob_start();
    try {
        include 'enviar_orcamento.php';
    } catch (Throwable $e) {
        // Logar erro mas não parar o redirecionamento
        file_put_contents(__DIR__ . '/tmp/log_critico_email.txt', date('Y-m-d H:i:s') . " - Erro fatal no include: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    $output_email = ob_get_clean(); // Limpa o buffer para não enviar HTML antes do header

    // Log do resultado (opcional)
    file_put_contents(__DIR__ . '/tmp/log_include_envio.txt', date('Y-m-d H:i:s') . " - Include executado. Output size: " . strlen($output_email) . "\n", FILE_APPEND);
    // ------------------------------------------------

    // Redireciona de volta com os parâmetros para o JavaScript acionar o próximo passo
    // --- Ploomes CRM Integration (Non-Blocking) ---
    $ploomes_params = "";
    try {
        require_once __DIR__ . '/classes/PloomesHelper.php';
        $ploomes = new PloomesHelper();

        // 1. Tentar encontrar o cliente
        // Utiliza nova estratégia robusta (Range Search + PHP Filter) que requer Nome e CNPJ
        $ploomesContact = $ploomes->findClient($cliente, $cnpj);

        if ($ploomesContact) {
            // 2. Criar Interação
            $conteudoInteracao = "Orçamento #$num_orcamento criado/atualizado.\n\n";
            $conteudoInteracao .= "Resumo do Orçamento:\n";

            foreach ($itens as $i) {
                if (empty($i['produto']))
                    continue;
                $qtd = $i['volume'] ?? '0';
                $un = $i['unidade'] ?? '';
                $net = isset($i['preco_net']) ? str_replace(',', '.', $i['preco_net']) : null;
                $full = isset($i['preco_full']) ? str_replace(',', '.', $i['preco_full']) : null;
                $produto = $i['produto'];

                // Format prices if numeric, otherwise show '-'
                $netStr = is_numeric($net) ? "USD " . number_format((float) $net, 2, '.', '') : '-';
                $fullStr = is_numeric($full) ? "USD " . number_format((float) $full, 2, '.', '') : '-';

                $conteudoInteracao .= "- $produto | Qtd: $qtd $un | Net: $netStr | Full: $fullStr\n";
            }

            $conteudoInteracao .= "\nVer em innovasell.cloud";

            // Tag ID 136473 = 0_COTAÇÃO
            $ploomes->createInteraction($ploomesContact['Id'], $conteudoInteracao, 1, [136473]);
            $ploomes_params = "&ploomes_status=success";
        } else {
            // Cliente não encontrado
            $msg = urlencode("Cliente '$cliente' não encontrado no Ploomes.");
            $ploomes_params = "&ploomes_status=warning&ploomes_msg=$msg";
        }
    } catch (Exception $e) {
        // Erro na integração (API fora, timeout, auth errada)
        // Logar erro e avisar user sem travar
        $erroMsg = urlencode("Erro ao conectar no Ploomes: " . substr($e->getMessage(), 0, 100));
        file_put_contents(__DIR__ . '/tmp/ploomes_errors.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
        $ploomes_params = "&ploomes_status=error&ploomes_msg=$erroMsg";
    }
    // ----------------------------------------------

    // Redireciona de volta com os parâmetros para o JavaScript acionar o próximo passo
    header("Location: incluir_orcamento.php?sucesso=1&num_orcamento=$num_orcamento&incluir_net=$incluir_net$ploomes_params");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    die("Erro ao salvar orçamento: " . $e->getMessage());
}
?>