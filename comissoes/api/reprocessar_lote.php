<?php
/**
 * api/reprocessar_lote.php
 * Reprocessa todos os itens de um lote recalculando comissões com PTAX correta.
 *
 * Regras de preservação:
 * - Itens com lista_nao_encontrada=0 E preco_lista_usd > 0: re-busca PTAX e recalcula preco_lista_brl
 * - Itens com lista_nao_encontrada=0 E preco_lista_usd = 0 (set manual via modal sem USD):
 *   preserva preco_lista_brl, só recalcula percentuais
 * - Itens com lista_nao_encontrada=1: ignora recálculo, retorna como warning
 * - Itens com pm_dias = 0: recalcula mas retorna warning pedindo preenchimento manual
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Fatal: ' . $e['message']]);
    }
});

require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';

try {
    $body     = json_decode(file_get_contents('php://input'), true) ?: [];
    $batch_id = isset($body['batch_id']) ? (int)$body['batch_id'] : 0;
    if (!$batch_id) throw new Exception('batch_id não fornecido.');

    // Verifica se o lote existe
    $stmtB = $pdo->prepare("SELECT id, nome FROM com_commission_batches WHERE id = ?");
    $stmtB->execute([$batch_id]);
    $batch = $stmtB->fetch(PDO::FETCH_ASSOC);
    if (!$batch) throw new Exception("Lote #{$batch_id} não encontrado.");

    // Garante coluna ptax_nf (seguro repetir)
    $pdo->exec("ALTER TABLE com_commission_items ADD COLUMN IF NOT EXISTS ptax_nf DECIMAL(10,4) NOT NULL DEFAULT 0");

    // Busca todos os itens do lote
    $stmtItems = $pdo->prepare("SELECT * FROM com_commission_items WHERE batch_id = ? ORDER BY id");
    $stmtItems->execute([$batch_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) throw new Exception("Nenhum item encontrado no lote.");

    // PTAX cache por data
    $ptaxCache = [];

    // Helper: busca PTAX com prioridade: cache → BD → API Olinda → null
    function getPtax($data_nf, &$ptaxCache, $pdo) {
        if (!$data_nf) return null;
        if (isset($ptaxCache[$data_nf])) return $ptaxCache[$data_nf];

        // BD
        $s = $pdo->prepare("SELECT cotacao_venda FROM fin_ptax_rates WHERE data_cotacao = ?");
        $s->execute([$data_nf]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if ($r && $r['cotacao_venda'] > 0) {
            $ptaxCache[$data_nf] = (float)$r['cotacao_venda'];
            return $ptaxCache[$data_nf];
        }

        // API Olinda
        $fmt = (new DateTime($data_nf))->format('m-d-Y');
        $url = "https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoDolarPeriodo(dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?@dataInicial='{$fmt}'&@dataFinalCotacao='{$fmt}'&\$top=1&\$format=json";
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 8]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $api = json_decode($resp, true);
        if (!empty($api['value'])) {
            $ptax = (float)($api['value'][0]['cotacaoVenda'] ?? 0);
            if ($ptax > 0) {
                // Salva no BD para próximas consultas
                $ins = $pdo->prepare("INSERT INTO fin_ptax_rates (data_cotacao, cotacao_compra, cotacao_venda) VALUES (?,?,?) ON DUPLICATE KEY UPDATE cotacao_venda=VALUES(cotacao_venda)");
                $ins->execute([$data_nf, $api['value'][0]['cotacaoCompra'] ?? 0, $ptax]);
                $ptaxCache[$data_nf] = $ptax;
                return $ptax;
            }
        }

        // Fallback: PTAX mais recente anterior à data
        $sf = $pdo->prepare("SELECT cotacao_venda, data_cotacao FROM fin_ptax_rates WHERE data_cotacao <= ? ORDER BY data_cotacao DESC LIMIT 1");
        $sf->execute([$data_nf]);
        $rf = $sf->fetch(PDO::FETCH_ASSOC);
        if ($rf) {
            $ptaxCache[$data_nf] = (float)$rf['cotacao_venda'];
            return $ptaxCache[$data_nf];
        }

        return null;
    }

    // Statement de UPDATE
    $stmtUpd = $pdo->prepare("UPDATE com_commission_items SET
        preco_lista_brl   = ?,
        desconto_brl      = ?,
        desconto_pct      = ?,
        comissao_base_pct = ?,
        pm_dias           = ?,
        pm_semanas        = ?,
        ajuste_prazo_pct  = ?,
        comissao_final_pct= ?,
        valor_comissao    = ?,
        flag_aprovacao    = ?,
        flag_teto         = ?,
        lista_nao_encontrada = ?
        WHERE id = ?");

    $resultados = [
        'recalculados' => 0,
        'ignorados_sem_lista' => 0,
        'warnings' => []
    ];

    foreach ($items as $item) {
        $id               = (int)$item['id'];
        $data_nf          = $item['data_nf'];
        $venda_net        = (float)$item['venda_net'];
        $preco_net_un     = (float)$item['preco_net_un'];
        $qtde             = (float)$item['qtde'];
        $valor_bruto      = (float)$item['valor_bruto'];
        $preco_bruto_un   = $qtde > 0 ? $valor_bruto / $qtde : 0;
        $pm_dias          = (float)$item['pm_dias'];
        $sem_lista        = (int)$item['lista_nao_encontrada'];
        $preco_lista_usd  = (float)($item['preco_lista_usd'] ?? 0);
        $preco_lista_brl  = (float)$item['preco_lista_brl'];
        $ptax_nf          = (float)($item['ptax_nf'] ?? 0);
        $nfe_label        = $item['nfe'] . ' | ' . ($item['codigo'] ?? '') . ' ' . ($item['embalagem'] ?? '');

        // ── Itens sem lista e sem preço manual: ignorar ──────────────────────
        if ($sem_lista == 1 && $preco_lista_brl <= 0) {
            $resultados['ignorados_sem_lista']++;
            $resultados['warnings'][] = [
                'tipo'    => 'sem_lista',
                'item_id' => $id,
                'nfe'     => $nfe_label,
                'msg'     => 'Produto sem Price List. Associe manualmente via diagnóstico S/Lista antes de reprocessar.'
            ];
            continue;
        }

        // ── Recalcula Preço Lista BRL ─────────────────────────────────────────
        // Prioridade PTAX: ptax_nf (DOLAR DO FATURAMENTO da infCpl) → BCB API
        $ptax_usada = null;

        if ($preco_lista_usd > 0) {
            // Tem USD → usa ptax_nf gravada no item; cai para BCB se não tiver
            $ptax_usada = $ptax_nf > 0 ? $ptax_nf : getPtax($data_nf, $ptaxCache, $pdo);
            if ($ptax_usada) {
                $preco_lista_brl = $preco_lista_usd * $ptax_usada;
            } else {
                // Sem PTAX: preserva BRL atual, emite warning
                $resultados['warnings'][] = [
                    'tipo'    => 'sem_ptax',
                    'item_id' => $id,
                    'nfe'     => $nfe_label,
                    'msg'     => "PTAX não encontrada para {$data_nf}. Preço lista BRL preservado (R$ " . number_format($preco_lista_brl, 2, ',', '.') . "). Preencha manualmente se necessário."
                ];
            }
        } else {
            // Sem USD (definido manualmente sem USD): preserva BRL que já foi editado
            if ($preco_lista_brl <= 0) {
                $resultados['warnings'][] = [
                    'tipo'    => 'sem_preco',
                    'item_id' => $id,
                    'nfe'     => $nfe_label,
                    'msg'     => 'Preço lista zerado e sem USD cadastrado. Edite manualmente o item.'
                ];
                continue;
            }
        }

        // ── Warning: PM = 0 ──────────────────────────────────────────────────
        $pm_corrigido = false;
        if ($pm_dias <= 0) {
            $resultados['warnings'][] = [
                'tipo'    => 'sem_pm',
                'item_id' => $id,
                'nfe'     => $nfe_label,
                'msg'     => "Prazo Médio = 0 dias para NF {$item['nfe']}. Baseline de 28 dias aplicado (sem ajuste de prazo). Edite manualmente o campo PM se souber o prazo correto (ex: 35 dias para 28/35/42d)."
            ];
            $pm_dias = 28; // salva 28 no banco para sincronizar exibição/cálculo
            $pm_corrigido = true;
        }

        // ── Recalcula percentuais ─────────────────────────────────────────────
        // Desconto em USD quando possível (mesmo dólar para venda e price list)
        if ($preco_lista_usd > 0 && $ptax_usada > 0) {
            $preco_bruto_usd = $preco_bruto_un / $ptax_usada;
            $desconto_usd    = max(0, $preco_lista_usd - $preco_bruto_usd);
            $desconto_pct    = $desconto_usd / $preco_lista_usd;
            $desconto_brl    = $desconto_usd * $ptax_usada;
        } else {
            // Fallback BRL (itens sem USD ou sem PTAX)
            $desconto_brl = max(0, $preco_lista_brl - $preco_bruto_un);
            $desconto_pct = $preco_lista_brl > 0 ? $desconto_brl / $preco_lista_brl : 0;
        }
        if ($desconto_pct < 0) $desconto_pct = 0;

        if      ($desconto_pct <= 0.00) $base = 0.0100;
        elseif  ($desconto_pct < 0.05)  $base = 0.0090;
        elseif  ($desconto_pct < 0.10)  $base = 0.0070;
        elseif  ($desconto_pct < 0.15)  $base = 0.0050;
        elseif  ($desconto_pct < 0.20)  $base = 0.0040;
        else                             $base = 0.0025;

        $pm_semanas       = $pm_dias / 7;
        $ajuste           = (4 - (int) floor($pm_dias / 7)) * 0.0005; // semanas completas (floor), baseline 4
        $comissao_final   = max(0.0005, $base + $ajuste);

        if ($preco_lista_brl <= 0) $comissao_final = 0;

        $valor_comissao = $venda_net * $comissao_final;
        $flag_teto      = $valor_comissao > 25000 ? 1 : 0;
        if ($flag_teto) {
            $valor_comissao = 25000 + ($valor_comissao - 25000) * 0.10;
        }
        $valor_comissao = ceil($valor_comissao); // Sempre inteiro, arredondado para cima
        $flag_aprov = ($desconto_pct > 0.20 || $pm_dias > 42) ? 1 : 0;
        $nova_sem_lista = ($preco_lista_brl <= 0) ? 1 : 0;

        $stmtUpd->execute([
            round($preco_lista_brl, 4),
            round($desconto_brl, 4),
            round($desconto_pct, 4),
            round($base, 4),
            round($pm_dias, 4),        // pm_dias (corrigido para 28 se era 0)
            round($pm_semanas, 4),
            round($ajuste, 4),
            round($comissao_final, 4),
            $valor_comissao, // ceil() já aplicado acima
            $flag_aprov,
            $flag_teto,
            $nova_sem_lista,
            $id
        ]);

        $resultados['recalculados']++;
    }

    ob_end_clean();
    echo json_encode([
        'success'               => true,
        'total'                 => count($items),
        'recalculados'          => $resultados['recalculados'],
        'ignorados_sem_lista'   => $resultados['ignorados_sem_lista'],
        'warnings'              => $resultados['warnings'],
        'tem_warnings'          => !empty($resultados['warnings'])
    ]);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
