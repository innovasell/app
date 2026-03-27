<?php
/**
 * api/process_upload.php — Processa ZIP de XMLs NF-e
 * Grava em com_commission_batches + com_commission_items (mesmo fluxo do CSV)
 * para aparecer no lote_detalhes.php com cálculo completo de comissão.
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);
ini_set('max_execution_time', 600);
ini_set('memory_limit', '512M');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Fatal: ' . $e['message']]);
    }
});

require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';

// ─── Helpers ──────────────────────────────────────────────────────────────────
function fetchPtaxRange($start, $end) {
    $startFmt = (new DateTime($start))->format('m-d-Y');
    $endFmt   = (new DateTime($end))->format('m-d-Y');
    $url = "https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoDolarPeriodo(dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?@dataInicial='{$startFmt}'&@dataFinalCotacao='{$endFmt}'&\$top=100&\$format=json";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 15]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($resp, true);
    $rates = [];
    if (isset($data['value'])) {
        foreach ($data['value'] as $item) {
            $d = substr($item['dataHoraCotacao'], 0, 10);
            $rates[$d] = ['compra' => $item['cotacaoCompra'], 'venda' => $item['cotacaoVenda']];
        }
    }
    return $rates;
}

try {
    if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Falha no upload do arquivo ZIP.");
    }

    $zip = new ZipArchive;
    if ($zip->open($_FILES['zip_file']['tmp_name']) !== true) {
        throw new Exception("Não foi possível abrir o arquivo ZIP.");
    }

    // Nome do lote
    $nomeLote = isset($_POST['nome_lote']) && trim($_POST['nome_lote']) !== ''
        ? trim($_POST['nome_lote'])
        : 'ZIP ' . date('d/m/Y H:i');

    // CFOPs válidos
    $stmtCfop = $pdo->query("SELECT cfop FROM com_cfop_rules WHERE is_active = 1");
    $cfopsValidos = $stmtCfop->fetchAll(PDO::FETCH_COLUMN);
    if (empty($cfopsValidos)) {
        $cfopsValidos = ['5102', '5123', '6102', '6123', '6106', '6110', '5106', '5119'];
    }

    // Sellers CSV opcional
    $sellersMap = [];
    if (isset($_FILES['sellers_csv']) && $_FILES['sellers_csv']['error'] === UPLOAD_ERR_OK) {
        if (($h = fopen($_FILES['sellers_csv']['tmp_name'], 'r')) !== false) {
            fgetcsv($h, 1000, ';'); // header
            while (($row = fgetcsv($h, 1000, ';')) !== false) {
                if (isset($row[0], $row[1])) $sellersMap[trim($row[0])] = trim($row[1]);
            }
            fclose($h);
        }
    }

    // ── Pré-scan de datas para buscar PTAX ───────────────────────────────────
    $minDate = null; $maxDate = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xml') continue;
        $content = $zip->getFromIndex($i);
        if (preg_match('/<dhEmi>(.*?)<\/dhEmi>/', $content, $m) || preg_match('/<dEmi>(.*?)<\/dEmi>/', $content, $m)) {
            $d = substr($m[1], 0, 10);
            if ($d) {
                if (!$minDate || $d < $minDate) $minDate = $d;
                if (!$maxDate || $d > $maxDate) $maxDate = $d;
            }
        }
    }

    // Cache PTAX: BD + API Olinda
    $ptaxCache = [];
    if ($minDate && $maxDate) {
        $s = $pdo->prepare("SELECT data_cotacao, cotacao_venda FROM fin_ptax_rates WHERE data_cotacao BETWEEN ? AND ?");
        $s->execute([$minDate, $maxDate]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ptaxCache[$r['data_cotacao']] = (float)$r['cotacao_venda'];
        }
        $apiRates = fetchPtaxRange($minDate, $maxDate);
        if (!empty($apiRates)) {
            $upsert = $pdo->prepare("INSERT INTO fin_ptax_rates (data_cotacao, cotacao_compra, cotacao_venda) VALUES (?,?,?) ON DUPLICATE KEY UPDATE cotacao_compra=VALUES(cotacao_compra), cotacao_venda=VALUES(cotacao_venda)");
            foreach ($apiRates as $dt => $vals) {
                $upsert->execute([$dt, $vals['compra'], $vals['venda']]);
                $ptaxCache[$dt] = (float)$vals['venda'];
            }
        }
    }

    // Garante coluna ptax_nf (executa uma única vez, seguro repetir)
    $pdo->exec("ALTER TABLE com_commission_items ADD COLUMN IF NOT EXISTS ptax_nf DECIMAL(10,4) NOT NULL DEFAULT 0");

    // Cria lote em com_commission_batches
    $pdo->beginTransaction();
    $stmtBatch = $pdo->prepare("INSERT INTO com_commission_batches (periodo, nome) VALUES (?,?)");
    $stmtBatch->execute([date('Y-m'), $nomeLote]);
    $batchId = $pdo->lastInsertId();

    // Prepared statements
    $stmtPL = $pdo->prepare("SELECT preco_net_usd, embalagem FROM cot_price_list WHERE codigo LIKE ? AND embalagem IN (%s) ORDER BY id DESC LIMIT 1");

    $stmtInsert = $pdo->prepare("INSERT INTO com_commission_items
        (batch_id, nfe, data_nf, cfop, codigo, descricao, embalagem, fabricante, representante, cliente,
         qtde, valor_bruto, icms, pis, cofins, venda_net, preco_net_un, preco_lista_brl, preco_lista_usd,
         desconto_brl, desconto_pct, comissao_base_pct, pm_dias, pm_semanas,
         ajuste_prazo_pct, comissao_final_pct, valor_comissao, flag_aprovacao, flag_teto, lista_nao_encontrada,
         vencimentos_json, ptax_nf)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $processedCount = 0;
    $importedCount  = 0;
    $ignoredCount   = 0;
    $warnItems      = []; // itens com dados faltando

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xml') continue;
        $processedCount++;

        $xmlContent = $zip->getFromIndex($i);
        // Remove namespace para facilitar XPath
        $xmlClean = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xmlContent);
        $xml = @simplexml_load_string($xmlClean, 'SimpleXMLElement', LIBXML_NOERROR);
        if (!$xml) { $ignoredCount++; continue; }

        // Identifica estrutura (nfeProc ou NFe direto)
        $infNFe = $xml->NFe->infNFe ?? $xml->infNFe ?? null;
        if (!$infNFe) { $ignoredCount++; continue; }

        $ide = $infNFe->ide;
        $nNF  = (string)($ide->nNF ?? '');
        $serie = (string)($ide->serie ?? '1');
        $nfe_label = "{$serie}/{$nNF}";

        // Data emissão
        $dhEmi = (string)($ide->dhEmi ?? $ide->dEmi ?? '');
        $data_nf = $dhEmi ? substr($dhEmi, 0, 10) : null;

        // Destinatário
        $dest = $infNFe->dest;
        $cliente = $dest ? (string)($dest->xNome ?? '') : '';

        // infCpl: extrai PTAX e representante
        $infCpl = (string)($infNFe->infAdic->infCpl ?? '');
        $ptax_nf = 0;
        if ($infCpl && preg_match('/DOLAR DO FATURAMENTO[:\s]+([\d.,]+)/i', $infCpl, $mP)) {
            $ptax_nf = (float) str_replace(',', '.', $mP[1]);
        }
        $representante = '';
        if ($infCpl && preg_match('/CONTATO[:\s]+([^\|;]+)/i', $infCpl, $mR)) {
            $representante = trim($mR[1]);
        }

        // Seller override do CSV
        $sellerFromCsv = $sellersMap[str_pad($nNF, 9, '0', STR_PAD_LEFT)]
            ?? $sellersMap[$nNF]
            ?? null;
        if ($sellerFromCsv) $representante = $sellerFromCsv;

        // PM: calcula das duplicatas <cobr><dup> e preserva detalhes para auditoria
        $pm_dias = 0;
        $vencimentosArr = [];
        $cobr = $infNFe->cobr ?? null;
        if ($cobr && $data_nf) {
            $dups   = $cobr->dup ?? [];
            $totalVal = 0; $somaP = 0;
            $dtBase = new DateTime($data_nf);
            foreach ($dups as $dup) {
                $dVenc = (string)($dup->dVenc ?? '');
                $vDup  = (float)($dup->vDup ?? 0);
                if ($dVenc && $vDup > 0) {
                    $dtV  = new DateTime($dVenc);
                    $diff = (int)$dtBase->diff($dtV)->days;
                    $somaP    += $diff * $vDup;
                    $totalVal += $vDup;
                    $vencimentosArr[] = ['data' => $dtV->format('d/m/Y'), 'valor' => $vDup, 'dias' => $diff];
                }
            }
            if ($totalVal > 0) $pm_dias = $somaP / $totalVal;
        }
        $vencimentos_json_nf = !empty($vencimentosArr) ? json_encode($vencimentosArr, JSON_UNESCAPED_UNICODE) : null;

        // ── Processa cada item <det> ──────────────────────────────────────────
        foreach ($infNFe->det as $det) {
            $prod = $det->prod;
            $imp  = $det->imposto;

            $cfop       = (string)($prod->CFOP ?? '');
            $cProd      = (string)($prod->cProd ?? '');
            $xProd      = (string)($prod->xProd ?? '');
            $qCom       = (float)($prod->qCom ?? 0);
            $vProd      = (float)($prod->vProd ?? 0);
            $vDesc_item = (float)($prod->vDesc ?? 0);
            $valor_bruto = $vProd - $vDesc_item;

            // Filtra CFOP
            if (!in_array($cfop, $cfopsValidos)) { $ignoredCount++; continue; }
            if ($valor_bruto <= 0) { $ignoredCount++; continue; }

            // Impostos
            $icms   = (float)($imp->ICMS->ICMS00->vICMS ?? $imp->ICMS->ICMS10->vICMS ?? $imp->ICMS->ICMS20->vICMS ?? $imp->ICMS->ICMS30->vICMS ?? 0);
            $pis    = (float)($imp->PIS->PISAliq->vPIS ?? $imp->PIS->PISNT->vPIS ?? $imp->PIS->PISOutr->vPIS ?? 0);
            $cofins = (float)($imp->COFINS->COFINSAliq->vCOFINS ?? $imp->COFINS->COFINSNT->vCOFINS ?? $imp->COFINS->COFINSOutr->vCOFINS ?? 0);

            $venda_net   = $valor_bruto - $icms - $pis - $cofins;
            $preco_net_un = $qCom > 0 ? $venda_net / $qCom : 0;
            $codigo9      = substr(trim($cProd), 0, 9);

            // Embalagem (mesmo algoritmo do process_commission.php)
            $embalagem_display = '';
            $embCandidatos = [];
            if (preg_match('/\(([^)]+)\)[^(]*$/', $xProd, $mEmb)) {
                $emb_raw = trim($mEmb[1]);
                $embalagem_display = "({$emb_raw})";
                if (preg_match('/^([\d.,]+)\s*(.*)$/', $emb_raw, $numM)) {
                    $numNorm   = str_replace(',', '.', $numM[1]);
                    $numFloat  = (float)$numNorm;
                    $unidade   = trim($numM[2]);
                    $alts = [
                        $numNorm . ($unidade ? " $unidade" : ''),
                        number_format($numFloat, 3, '.', '') . ($unidade ? " $unidade" : ''),
                        number_format($numFloat, 0, '.', '') . ($unidade ? " $unidade" : ''),
                        number_format($numFloat, 2, '.', '') . ($unidade ? " $unidade" : ''),
                        rtrim(rtrim(number_format($numFloat, 3, '.', ''), '0'), '.') . ($unidade ? " $unidade" : ''),
                    ];
                    $unUp = strtoupper($unidade);
                    if (in_array($unUp, ['G', 'GR', 'GRS', 'GRAMAS', 'GRAMA'])) {
                        $kg = $numFloat / 1000;
                        $alts = array_merge($alts, [
                            number_format($kg, 3, '.', '') . ' KG',
                            number_format($kg, 4, '.', '') . ' KG',
                            rtrim(rtrim(number_format($kg, 4, '.', ''), '0'), '.') . ' KG',
                        ]);
                    }
                    $embCandidatos = array_values(array_unique($alts));
                }
            }

            // Busca Price List
            $preco_lista_usd = 0;
            $preco_lista_brl = 0;
            $lista_nao_encontrada = 1;
            $emb_encontrada = '';

            if (!empty($embCandidatos)) {
                $ph = implode(',', array_fill(0, count($embCandidatos), '?'));
                $params = array_merge(["{$codigo9}%"], array_values($embCandidatos));
                $stPL = $pdo->prepare("SELECT preco_net_usd, embalagem FROM cot_price_list WHERE codigo LIKE ? AND embalagem IN ($ph) ORDER BY id DESC LIMIT 1");
                $stPL->execute($params);
                $plRow = $stPL->fetch(PDO::FETCH_ASSOC);
                if ($plRow && $plRow['preco_net_usd'] > 0) {
                    $preco_lista_usd = (float)$plRow['preco_net_usd'];
                    $emb_encontrada  = $plRow['embalagem'];
                    $lista_nao_encontrada = 0;
                }
            }

            // Warnings para dados em falta (acumula mas não bloqueia)
            $itemWarnings = [];

            // PTAX: 1) infCpl 2) cache BD/API 3) fallback anterior
            $ptax_usado = 0;
            if ($ptax_nf > 0) {
                $ptax_usado = $ptax_nf;
            } elseif ($data_nf && isset($ptaxCache[$data_nf])) {
                $ptax_usado = $ptaxCache[$data_nf];
            } elseif ($data_nf) {
                // Fallback: PTAX mais recente anterior
                $sf = $pdo->prepare("SELECT cotacao_venda FROM fin_ptax_rates WHERE data_cotacao <= ? ORDER BY data_cotacao DESC LIMIT 1");
                $sf->execute([$data_nf]);
                $rf = $sf->fetch(PDO::FETCH_ASSOC);
                if ($rf) $ptax_usado = (float)$rf['cotacao_venda'];
            }

            if ($ptax_usado <= 0 && !$lista_nao_encontrada) {
                $itemWarnings[] = "PTAX não encontrada para {$data_nf}";
            }

            if ($preco_lista_usd > 0 && $ptax_usado > 0) {
                $preco_lista_brl = $preco_lista_usd * $ptax_usado;
            }

            if ($lista_nao_encontrada) {
                $itemWarnings[] = "Produto não encontrado na Price List (emb: " . implode('/', array_slice($embCandidatos, 0, 2)) . ")";
            }

            // PM warning
            if ($pm_dias <= 0) {
                $itemWarnings[] = "PM = 0d (duplicatas não encontradas no XML). Edite manualmente.";
                $pm_dias_calc = 28; // baseline
            } else {
                $pm_dias_calc = $pm_dias;
            }

            if (!empty($itemWarnings)) {
                $warnItems[] = ['nfe' => $nfe_label, 'produto' => substr($xProd, 0, 50), 'avisos' => $itemWarnings];
            }

            // Desconto — comparação em USD: converte preço bruto de venda para USD
            $preco_bruto_un = $qCom > 0 ? $valor_bruto / $qCom : 0;
            $desconto_brl = 0; $desconto_pct = 0;
            if ($preco_lista_usd > 0 && $ptax_usado > 0) {
                $preco_bruto_usd = $preco_bruto_un / $ptax_usado;
                $desconto_usd    = max(0, $preco_lista_usd - $preco_bruto_usd);
                $desconto_pct    = $desconto_usd / $preco_lista_usd;
                $desconto_brl    = $desconto_usd * $ptax_usado;
            } elseif ($preco_lista_brl > 0) {
                // Fallback BRL (sem PTAX disponível)
                $desconto_brl = max(0, $preco_lista_brl - $preco_bruto_un);
                $desconto_pct = $desconto_brl / $preco_lista_brl;
            }

            // Matriz % base
            if ($lista_nao_encontrada) {
                $comissao_base_pct = 0;
            } elseif ($desconto_pct <= 0)       { $comissao_base_pct = 0.0100; }
            elseif ($desconto_pct <= 0.05)       { $comissao_base_pct = 0.0090; }
            elseif ($desconto_pct <= 0.10)       { $comissao_base_pct = 0.0070; }
            elseif ($desconto_pct <= 0.15)       { $comissao_base_pct = 0.0050; }
            elseif ($desconto_pct <= 0.20)       { $comissao_base_pct = 0.0040; }
            else                                  { $comissao_base_pct = 0.0025; }

            // Ajuste PM — semanas inteiras para garantir múltiplo de 0,05%
            $pm_semanas      = $pm_dias_calc / 7;
            $ajuste_prazo    = -((int) round(($pm_dias_calc - 28) / 7) * 0.0005);
            $comissao_final  = max(0.0005, $comissao_base_pct + $ajuste_prazo);
            if ($lista_nao_encontrada) $comissao_final = 0;

            $valor_comissao = $venda_net * $comissao_final;
            $flag_teto      = $valor_comissao > 25000 ? 1 : 0;
            if ($flag_teto) $valor_comissao = 25000 + ($valor_comissao - 25000) * 0.10;
            $valor_comissao = ceil($valor_comissao); // Sempre inteiro, arredondado para cima
            $flag_aprovacao = ($desconto_pct > 0.20 || $pm_dias_calc > 42) ? 1 : 0;

            // Fabricante
            $fabricante = '';

            $stmtInsert->execute([
                $batchId, $nfe_label, $data_nf, $cfop, $codigo9,
                $xProd, $embalagem_display, $fabricante, $representante, $cliente,
                $qCom, $valor_bruto, $icms, $pis, $cofins,
                round($venda_net, 2), round($preco_net_un, 4),
                round($preco_lista_brl, 4), round($preco_lista_usd, 4),
                round($desconto_brl, 4), round($desconto_pct, 4),
                round($comissao_base_pct, 4),
                round($pm_dias_calc, 4), round($pm_semanas, 4),
                round($ajuste_prazo, 4), round($comissao_final, 4),
                $valor_comissao, // ceil() já aplicado acima
                $flag_aprovacao, $flag_teto, $lista_nao_encontrada,
                $vencimentos_json_nf,
                round($ptax_usado, 4)
            ]);

            $importedCount++;
        }
    }

    $zip->close();
    $pdo->commit();

    ob_end_clean();
    echo json_encode([
        'success'         => true,
        'message'         => 'Upload processado com sucesso!',
        'batch_id'        => $batchId,
        'processed_count' => $processedCount,
        'imported_count'  => $importedCount,
        'ignored_count'   => $ignoredCount,
        'warnings'        => $warnItems,
        'tem_warnings'    => !empty($warnItems),
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
