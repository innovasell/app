<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';

try {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) $body = $_POST;

    $batch_id = isset($body['batch_id']) ? (int)$body['batch_id'] : 0;
    $itens    = isset($body['itens']) && is_array($body['itens']) ? $body['itens'] : [];

    if (!$batch_id || empty($itens)) {
        throw new Exception("Lote ou itens não fornecidos.");
    }

    $sucessos = 0;
    $falhas = 0;

    // Para chamar as funções internas, fazemos fetch do item pelo local host internamente
    // Ou replicamos a regra de ptax + comissão aqui. Melhor atualizar direto usando os parametros enviados do frontend
    
    foreach ($itens as $i) {
        $id = (int)$i['id'];
        $codigo9 = substr(trim($i['codigo']), 0, 9);
        $ptax = (float)($i['ptax_usado'] ?? 0);
        $match = $i['match'][0] ?? null; // A primeira embalagem disponivel encontrada
        
        if (!$match) {
            $falhas++;
            continue;
        }

        // Tenta buscar o preço correspondente
        $stmtPrice = $pdo->prepare("SELECT preco_net_usd FROM cot_price_list WHERE codigo LIKE ? AND embalagem = ? ORDER BY id DESC LIMIT 1");
        $stmtPrice->execute(["{$codigo9}%", $match]);
        $priceRow = $stmtPrice->fetch(PDO::FETCH_ASSOC);

        if (!$priceRow || $priceRow['preco_net_usd'] <= 0) {
            $falhas++;
            continue; // Se não encontrou o preço ou preço for 0
        }

        $precoUsd = (float)$priceRow['preco_net_usd'];
        $precoBrl = $precoUsd * $ptax;

        // Fazer a chamada HTTP loopback para o proprio update_commission_item
        // Dessa forma garantimos que as regras de negócio de comissionamento são exatamente as oficiais
        $payload = [
            'id' => $id,
            'action' => 'update',
            'embalagem' => $match,
            'preco_lista_usd' => $precoUsd,
            'preco_lista_brl' => $precoBrl
        ];

        // Vamos atualizar diretamente no BD e depois reusar o recalculo (ou fazer o request interno)
        // Mais rapido eh fazer request para a API na mesma maquina ou usar cURL/file_get_contents
        // Mas como a autenticacao eh via session/cookie, o PHP vai rejeitar
        // A solucao melhor é extrair a logica de recalculo, ou apenas setar os campos bases aqui
        // Mas update_commission_item espera `id` e vai se auto recalcular se `preco_lista_brl` for alterado
        
        // Simplesmente damos update nesses 3 campos base, e entao chamamos o script atualizador via include
        // Mas como update_commission... captura json_encode, isso quebraria.
        // O ideal é replicar o recálculo:
        
        $cur = $pdo->prepare("SELECT * FROM com_commission_items WHERE id=?");
        $cur->execute([$id]);
        $row = $cur->fetch(PDO::FETCH_ASSOC);

        if (!$row) { $falhas++; continue; }

        $venda_net      = (float)$row['venda_net'];
        $pm_dias        = (float)$row['pm_dias'];
        $preco_net_un   = (float)$row['preco_net_un'];

        $desconto_brl = max(0, $precoBrl - $preco_net_un);
        $desconto_pct = $precoBrl > 0 ? $desconto_brl / $precoBrl : 0;

        $comissao_base_pct = 0.01;
        if ($desconto_pct <= 0)        $comissao_base_pct = 0.0100;
        elseif ($desconto_pct <= 0.05) $comissao_base_pct = 0.0090;
        elseif ($desconto_pct <= 0.10) $comissao_base_pct = 0.0070;
        elseif ($desconto_pct <= 0.15) $comissao_base_pct = 0.0050;
        elseif ($desconto_pct <= 0.20) $comissao_base_pct = 0.0040;
        else                           $comissao_base_pct = 0.0025;

        $pm_semanas = $pm_dias / 7;
        $ajuste_prazo_pct = -((int) round(($pm_dias - 28) / 7) * 0.0005); // semanas inteiras — múltiplo de 0,05%
        $comissao_final_pct = max(0.0005, $comissao_base_pct + $ajuste_prazo_pct);

        $valor_comissao = $venda_net * $comissao_final_pct;
        $flag_teto = $valor_comissao > 25000 ? 1 : 0;
        if ($flag_teto) {
            $valor_comissao = 25000 + ($valor_comissao - 25000) * 0.10;
        }
        $valor_comissao = ceil($valor_comissao); // Sempre inteiro, arredondado para cima
        $flag_aprovacao = ($desconto_pct > 0.20 || $pm_dias > 42) ? 1 : 0;

        $upd = $pdo->prepare("UPDATE com_commission_items SET 
            embalagem = ?, 
            preco_lista_usd = ?, 
            preco_lista_brl = ?, 
            desconto_brl = ?, 
            desconto_pct = ?, 
            comissao_base_pct = ?, 
            pm_semanas = ?, 
            ajuste_prazo_pct = ?, 
            comissao_final_pct = ?, 
            valor_comissao = ?, 
            flag_teto = ?, 
            flag_aprovacao = ?, 
            lista_nao_encontrada = 0 
            WHERE id = ?");

        $execResult = $upd->execute([
            $match,
            round($precoUsd, 4),
            round($precoBrl, 4),
            round($desconto_brl, 4),
            round($desconto_pct, 4),
            round($comissao_base_pct, 4),
            round($pm_semanas, 4),
            round($ajuste_prazo_pct, 4),
            round($comissao_final_pct, 4),
            $valor_comissao, // ceil() já aplicado acima
            $flag_teto,
            $flag_aprovacao,
            $id
        ]);
        
        if ($execResult) $sucessos++;
        else $falhas++;
    }

    ob_end_clean();
    echo json_encode(['success' => true, 'sucessos' => $sucessos, 'falhas' => $falhas]);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
