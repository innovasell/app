<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Fatal Error: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método inválido.");
    if (!isset($_FILES['xml_nfe']) || $_FILES['xml_nfe']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Arquivo XML não enviado ou inválido.");
    }

    $xmlContent = file_get_contents($_FILES['xml_nfe']['tmp_name']);
    if (!$xmlContent) throw new Exception("Não foi possível ler o arquivo XML.");

    // Remove namespace para facilitar XPath
    $xmlContent = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xmlContent);
    $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);
    if (!$xml) throw new Exception("XML inválido ou malformado.");

    $steps = []; // Array de passos explicativos

    // ─── ETAPA 1: Dados da NF ─────────────────────────────────────────────────
    $ide = $xml->NFe->infNFe->ide ?? $xml->infNFe->ide ?? null;
    if (!$ide) throw new Exception("Estrutura XML não reconhecida (tag <ide> não encontrada).");

    $nNF    = (string)($ide->nNF ?? '');
    $serie  = (string)($ide->serie ?? '');
    $dhEmi  = (string)($ide->dhEmi ?? '');
    $cfop_xml = '';
    $data_nf_str = '';

    if ($dhEmi) {
        $dt = new DateTime($dhEmi);
        $data_nf_str = $dt->format('Y-m-d');
        $data_nf_fmt = $dt->format('d/m/Y');
    }

    $dest = $xml->NFe->infNFe->dest ?? $xml->infNFe->dest ?? null;
    $clienteNome = $dest ? (string)($dest->xNome ?? '') : '';

    // Infos complementares da tag infAdic
    $infAdic = $xml->NFe->infNFe->infAdic ?? $xml->infNFe->infAdic ?? null;
    $infCpl  = $infAdic ? (string)($infAdic->infCpl ?? '') : '';

    // Extrai dólar do infCpl: "DOLAR DO FATURAMENTO: 5,1674"
    $ptax_manual = 0;
    if (preg_match('/DOLAR DO FATURAMENTO[:\s]+([\d.,]+)/i', $infCpl, $mPtax)) {
        $ptax_manual = (float) str_replace(',', '.', $mPtax[1]);
    }

    // Extrai representante do infCpl: "CONTATO: NAIARA"
    $representante_manual = '';
    if (preg_match('/CONTATO[:\s]+([^\|;]+)/i', $infCpl, $mRep)) {
        $representante_manual = trim($mRep[1]);
    }

    // Extrai prazo: "PAGAMENTO: A VISTA" ou busca por vencimento na cobrança
    $pm_dias = 0;
    $pm_fonte = 'Não encontrado';

    $cobr = $xml->NFe->infNFe->cobr ?? $xml->infNFe->cobr ?? null;
    if ($cobr && isset($cobr->dup) && $data_nf_str) {
        $datas_vcto = [];
        foreach ($cobr->dup as $dup) {
            $dVenc = (string)($dup->dVenc ?? '');
            if ($dVenc) {
                $dtV = new DateTime($dVenc);
                $dtBase = new DateTime($data_nf_str);
                $datas_vcto[] = $dtBase->diff($dtV)->days;
            }
        }
        if (count($datas_vcto) > 0) {
            $pm_dias   = array_sum($datas_vcto) / count($datas_vcto);
            $pm_fonte  = count($datas_vcto) . ' parcela(s) na tag <cobr> do XML';
        }
    }

    $steps[] = [
        'titulo' => '📄 Etapa 1 — Dados da Nota Fiscal',
        'cor'    => 'primary',
        'linhas' => [
            "Número da NF: <strong>{$serie}/{$nNF}</strong>",
            "Data de Emissão: <strong>{$data_nf_fmt}</strong>",
            "Cliente (Destinatário): <strong>{$clienteNome}</strong>",
            "Contato/Representante identificado no rodapé: <strong>" . ($representante_manual ?: '(não encontrado)') . "</strong>",
            "CFOP: será extraído de cada item abaixo.",
        ]
    ];

    // ─── ETAPA 2: Itens da NF ─────────────────────────────────────────────────
    $itens_brutos = $xml->NFe->infNFe->det ?? $xml->infNFe->det ?? [];
    $itens_resultado = [];

    $cfopsValidos = [];
    try {
        $r = $pdo->query("SELECT cfop FROM com_cfop_rules WHERE is_active = 1");
        $cfopsValidos = $r->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $ex) {}
    if (empty($cfopsValidos)) {
        $cfopsValidos = ['5102', '5123', '6102', '6123', '6106', '6110', '5106', '5119'];
    }

    foreach ($itens_brutos as $det) {
        $prod   = $det->prod;
        $imp    = $det->imposto;

        $cfop_item  = (string)($prod->CFOP ?? '');
        $cProd      = (string)($prod->cProd ?? '');
        $xProd      = (string)($prod->xProd ?? '');
        $qCom       = (float)($prod->qCom ?? 0);
        $vProd      = (float)($prod->vProd ?? 0);
        $vDesc_item = (float)($prod->vDesc ?? 0);
        $valor_bruto = $vProd - $vDesc_item;

        // Impostos
        $icms   = (float)($imp->ICMS->ICMS00->vICMS ?? $imp->ICMS->ICMS10->vICMS ?? $imp->ICMS->ICMS20->vICMS ?? 0);
        $pis    = (float)($imp->PIS->PISAliq->vPIS ?? $imp->PIS->PISNT->vPIS ?? 0);
        $cofins = (float)($imp->COFINS->COFINSAliq->vCOFINS ?? $imp->COFINS->COFINSNT->vCOFINS ?? 0);

        $cfop_valido = in_array($cfop_item, $cfopsValidos);

        // Extrai embalagem
        $embalagem_raw = '';
        $embalagem_display = '';
        $embCandidatos = [];
        if (preg_match('/\(([^)]+)\)[^(]*$/', $xProd, $mEmb)) {
            $embalagem_raw = trim($mEmb[1]);
            $embalagem_display = $embalagem_raw;

            // Gera candidatos (mesma lógica do process_commission)
            if (preg_match('/^([\d.,]+)\s*(.*)$/', $embalagem_raw, $numMatch)) {
                $numNorm  = str_replace(',', '.', $numMatch[1]);
                $numFloat = (float)$numNorm;
                $unidade  = trim($numMatch[2]);
                $alternativas = [
                    $numNorm . ($unidade ? ' ' . $unidade : ''),
                    number_format($numFloat, 3, '.', '') . ($unidade ? ' ' . $unidade : ''),
                    number_format($numFloat, 0, '.', '') . ($unidade ? ' ' . $unidade : ''),
                    number_format($numFloat, 2, '.', '') . ($unidade ? ' ' . $unidade : ''),
                    rtrim(rtrim(number_format($numFloat, 3, '.', ''), '0'), '.') . ($unidade ? ' ' . $unidade : ''),
                ];
                $unidadeUp = strtoupper($unidade);
                if (in_array($unidadeUp, ['G', 'GR', 'GRS', 'GRAMAS', 'GRAMA'])) {
                    $numKg = $numFloat / 1000;
                    $alternativas = array_merge($alternativas, [
                        number_format($numKg, 3, '.', '') . ' KG',
                        number_format($numKg, 4, '.', '') . ' KG',
                        rtrim(rtrim(number_format($numKg, 4, '.', ''), '0'), '.') . ' KG',
                    ]);
                }
                $embCandidatos = array_values(array_unique($alternativas));
            }
        }

        // Código limpo
        $codigo9 = substr(trim($cProd), 0, 9);

        // Busca Price List
        $preco_lista_usd = 0;
        $preco_lista_brl = 0;
        $priceRow = null;
        $emb_encontrada = '';

        if (!empty($embCandidatos)) {
            $placeholders = implode(',', array_fill(0, count($embCandidatos), '?'));
            $params = array_merge(["{$codigo9}%"], array_values($embCandidatos));
            $stmtP = $pdo->prepare("SELECT preco_net_usd, embalagem FROM cot_price_list WHERE codigo LIKE ? AND embalagem IN ($placeholders) ORDER BY id DESC LIMIT 1");
            $stmtP->execute($params);
            $priceRow = $stmtP->fetch(PDO::FETCH_ASSOC);
        }

        $lista_nao_encontrada = 1;
        if ($priceRow && $priceRow['preco_net_usd'] > 0) {
            $preco_lista_usd  = (float)$priceRow['preco_net_usd'];
            $emb_encontrada   = $priceRow['embalagem'];
            $lista_nao_encontrada = 0;
        }

        // PTAX: prioridade = manual do infCpl → cache BD → API Olinda
        $ptax_usado  = 0;
        $ptax_fonte  = '';

        if ($ptax_manual > 0) {
            $ptax_usado = $ptax_manual;
            $ptax_fonte = "Extraído do rodapé da NF (infCpl): <strong>R$ " . number_format($ptax_manual, 4, ',', '.') . "</strong>";
        } elseif ($data_nf_str && !$lista_nao_encontrada) {
            // Cache BD
            $stmtPtax = $pdo->prepare("SELECT cotacao_venda FROM fin_ptax_rates WHERE data_cotacao = ?");
            $stmtPtax->execute([$data_nf_str]);
            $ptaxRow = $stmtPtax->fetch(PDO::FETCH_ASSOC);
            if ($ptaxRow && $ptaxRow['cotacao_venda'] > 0) {
                $ptax_usado = (float)$ptaxRow['cotacao_venda'];
                $ptax_fonte = "Cache do banco de dados (PTAX do dia {$data_nf_fmt}): <strong>R$ " . number_format($ptax_usado, 4, ',', '.') . "</strong>";
            } else {
                // API Olinda
                $startFmt = (new DateTime($data_nf_str))->format('m-d-Y');
                $url = "https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoDolarPeriodo(dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?@dataInicial='{$startFmt}'&@dataFinalCotacao='{$startFmt}'&\$top=1&\$format=json";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                $resp = curl_exec($ch);
                curl_close($ch);
                $apiData = json_decode($resp, true);
                if (!empty($apiData['value'])) {
                    $ptax_usado = (float)($apiData['value'][0]['cotacaoVenda'] ?? 0);
                    $ptax_fonte = "API Banco Central (Olinda) para {$data_nf_fmt}: <strong>R$ " . number_format($ptax_usado, 4, ',', '.') . "</strong>";
                }
                // Fallback: PTAX mais próxima anterior
                if ($ptax_usado == 0) {
                    $stmtAlt = $pdo->prepare("SELECT cotacao_venda, data_cotacao FROM fin_ptax_rates WHERE data_cotacao <= ? ORDER BY data_cotacao DESC LIMIT 1");
                    $stmtAlt->execute([$data_nf_str]);
                    $rowAlt = $stmtAlt->fetch(PDO::FETCH_ASSOC);
                    if ($rowAlt) {
                        $ptax_usado = (float)$rowAlt['cotacao_venda'];
                        $ptax_fonte = "Fallback: PTAX mais recente anterior ({$rowAlt['data_cotacao']}): <strong>R$ " . number_format($ptax_usado, 4, ',', '.') . "</strong>";
                    }
                }
            }
        }

        if ($ptax_usado > 0 && $preco_lista_usd > 0) {
            $preco_lista_brl = $preco_lista_usd * $ptax_usado;
        }

        // Venda Net
        $venda_net   = $valor_bruto - $icms - $pis - $cofins;
        $preco_net_un = $qCom > 0 ? $venda_net / $qCom : 0;

        // Desconto
        $desconto_brl = 0;
        $desconto_pct = 0;
        if ($preco_lista_brl > 0) {
            $desconto_brl = $preco_lista_brl - $preco_net_un;
            $desconto_pct = max(0, $desconto_brl / $preco_lista_brl);
        }

        // Matriz % Base
        if ($lista_nao_encontrada) {
            $comissao_base_pct = 0;
            $regra_aplicada = 'Sem Price List — não é possível calcular';
        } elseif ($desconto_pct <= 0) {
            $comissao_base_pct = 0.0100; $regra_aplicada = 'Desconto ≤ 0% → 1,00%';
        } elseif ($desconto_pct <= 0.05) {
            $comissao_base_pct = 0.0090; $regra_aplicada = 'Desconto ≤ 5% → 0,90%';
        } elseif ($desconto_pct <= 0.10) {
            $comissao_base_pct = 0.0070; $regra_aplicada = 'Desconto ≤ 10% → 0,70%';
        } elseif ($desconto_pct <= 0.15) {
            $comissao_base_pct = 0.0050; $regra_aplicada = 'Desconto ≤ 15% → 0,50%';
        } elseif ($desconto_pct <= 0.20) {
            $comissao_base_pct = 0.0040; $regra_aplicada = 'Desconto ≤ 20% → 0,40%';
        } else {
            $comissao_base_pct = 0.0025; $regra_aplicada = 'Desconto > 20% → 0,25% ⚠️ Requer aprovação';
        }

        // Ajuste PM
        $diff_semanas    = ($pm_dias - 28) / 7;
        $ajuste_prazo    = -($diff_semanas * 0.0005);
        $comissao_final  = max(0.0005, $comissao_base_pct + $ajuste_prazo);

        if ($lista_nao_encontrada) $comissao_final = 0;

        // Valor comissão
        $valor_comissao = $venda_net * $comissao_final;
        $flag_teto      = $valor_comissao > 25000;
        $flag_aprov     = ($desconto_pct > 0.20 || $pm_dias > 42);
        $valor_final    = $valor_comissao;
        $premio         = 0;

        if ($flag_teto) {
            $excedente  = $valor_comissao - 25000;
            $premio     = $excedente * 0.10;
            $valor_final = 25000 + $premio;
        }

        // ─── Monta passos por item ──────────────────────────────────────────
        $item_steps = [];

        // Passo A: Dados do item
        $item_steps[] = [
            'subtitulo' => 'A — Identificação do Produto',
            'cor' => 'dark',
            'linhas' => [
                "Código: <strong>{$cProd}</strong> &nbsp;|&nbsp; Código normalizado (9 dígitos): <strong>{$codigo9}</strong>",
                "Descrição: <strong>{$xProd}</strong>",
                "Embalagem extraída (último par de parênteses): <strong>" . ($embalagem_display ?: 'Não encontrada') . "</strong>",
                "CFOP: <strong>{$cfop_item}</strong> — " . ($cfop_valido
                    ? "<span class='text-success fw-bold'>✔ CFOP válido para comissão</span>"
                    : "<span class='text-danger fw-bold'>✖ CFOP NÃO comissionável — item ignorado</span>"),
                "Qtde: <strong>" . number_format($qCom, 4, ',', '.') . " UN</strong>",
            ]
        ];

        if (!$cfop_valido) {
            $itens_resultado[] = ['cfop_invalido' => true, 'cfop' => $cfop_item, 'produto' => $xProd, 'steps' => $item_steps];
            continue;
        }

        // Passo B: Venda Net
        $item_steps[] = [
            'subtitulo' => 'B — Cálculo da Venda Net (Base da Comissão)',
            'cor' => 'success',
            'linhas' => [
                "Valor Bruto do item (vProd): <strong>R$ " . number_format($valor_bruto, 2, ',', '.') . "</strong>",
                "(-) ICMS: <strong>R$ " . number_format($icms, 2, ',', '.') . "</strong>",
                "(-) PIS: <strong>R$ " . number_format($pis, 2, ',', '.') . "</strong>",
                "(-) COFINS: <strong>R$ " . number_format($cofins, 2, ',', '.') . "</strong>",
                "<strong>= Venda Net: R$ " . number_format($venda_net, 2, ',', '.') . "</strong>",
                "Preço Net Unitário (Venda Net / Qtde): R$ " . number_format($preco_net_un, 4, ',', '.'),
            ]
        ];

        // Passo C: Price List
        $pl_linhas = [
            "Buscando na <code>cot_price_list</code>: código LIKE <code>{$codigo9}%</code>, embalagem em: <code>" . implode(', ', array_slice($embCandidatos, 0, 5)) . "</code>",
        ];
        if ($lista_nao_encontrada) {
            $pl_linhas[] = "<span class='text-danger fw-bold'>✖ Produto NÃO encontrado na Price List — comissão = 0</span>";
        } else {
            $pl_linhas[] = "<span class='text-success fw-bold'>✔ Encontrado!</span> Embalagem na PL: <strong>{$emb_encontrada}</strong>";
            $pl_linhas[] = "Preço Net (USD) na Price List: <strong>USD " . number_format($preco_lista_usd, 4, ',', '.') . "</strong>";
        }
        $item_steps[] = ['subtitulo' => 'C — Price List', 'cor' => $lista_nao_encontrada ? 'secondary' : 'info', 'linhas' => $pl_linhas];

        // Passo D: PTAX
        if (!$lista_nao_encontrada) {
            $ptax_linhas = [
                "Data da NF: <strong>{$data_nf_fmt}</strong>",
                "PTAX utilizada: {$ptax_fonte}",
                "Preço Lista em BRL: USD " . number_format($preco_lista_usd, 4, ',', '.') . " × R$ " . number_format($ptax_usado, 4, ',', '.') . " = <strong>R$ " . number_format($preco_lista_brl, 2, ',', '.') . "</strong>",
            ];
            $item_steps[] = ['subtitulo' => 'D — Conversão PTAX (USD → BRL)', 'cor' => 'warning', 'linhas' => $ptax_linhas];

            // Passo E: Desconto
            $item_steps[] = [
                'subtitulo' => 'E — Cálculo do Desconto',
                'cor'       => 'danger',
                'linhas'    => [
                    "P.Lista BRL: R$ " . number_format($preco_lista_brl, 4, ',', '.') . " — P.Net Unitário: R$ " . number_format($preco_net_un, 4, ',', '.'),
                    "Desconto BRL = Lista − Net = R$ " . number_format($desconto_brl, 4, ',', '.'),
                    "Desconto % = Desc.BRL / P.Lista = <strong>" . number_format($desconto_pct * 100, 2, ',', '.') . "%</strong>",
                ]
            ];
        }

        // Passo F: % Base
        $item_steps[] = [
            'subtitulo' => 'F — % Comissão Base (Matriz de Desconto)',
            'cor'       => 'primary',
            'linhas'    => [
                "Desconto: <strong>" . number_format($desconto_pct * 100, 2, ',', '.') . "%</strong>",
                "Regra aplicada: <strong>{$regra_aplicada}</strong>",
                "% Base: <strong>" . number_format($comissao_base_pct * 100, 2, ',', '.') . "%</strong>",
            ]
        ];

        // Passo G: Ajuste PM
        $item_steps[] = [
            'subtitulo' => 'G — Ajuste por Prazo Médio (PM)',
            'cor'       => 'secondary',
            'linhas'    => [
                "PM: <strong>" . number_format($pm_dias, 1, ',', '.') . " dias</strong> — Fonte: {$pm_fonte}",
                "Baseline: 28 dias. Diferença: " . number_format($pm_dias - 28, 1, ',', '.') . " dias = " . number_format($diff_semanas, 2, ',', '.') . " semanas",
                "Ajuste = −(semanas × 0,05%) = <strong>" . number_format($ajuste_prazo * 100, 4, ',', '.') . "%</strong> " . ($ajuste_prazo >= 0 ? "<span class='text-success'>(bônus por prazo curto)</span>" : "<span class='text-danger'>(penalidade por prazo longo)</span>"),
                "% Final = Base + Ajuste = " . number_format($comissao_base_pct * 100, 2, ',', '.') . "% + (" . number_format($ajuste_prazo * 100, 4, ',', '.') . "%) = <strong class='text-primary'>" . number_format($comissao_final * 100, 4, ',', '.') . "%</strong>",
            ]
        ];

        // Passo H: Valor Final
        $h_linhas = [
            "Venda Net: R$ " . number_format($venda_net, 2, ',', '.'),
            "× % Final: " . number_format($comissao_final * 100, 4, ',', '.') . "%",
            "= Comissão Bruta: <strong>R$ " . number_format($valor_comissao, 2, ',', '.') . "</strong>",
        ];
        if ($flag_teto) {
            $h_linhas[] = "⚠️ <strong>TETO ATINGIDO</strong> — Comissão excede R$ 25.000,00";
            $h_linhas[] = "Excedente: R$ " . number_format($valor_comissao - 25000, 2, ',', '.') . " → Prêmio (10%): R$ " . number_format($premio, 2, ',', '.');
            $h_linhas[] = "Comissão Final = R$ 25.000,00 + R$ " . number_format($premio, 2, ',', '.') . " = <strong class='text-success'>R$ " . number_format($valor_final, 2, ',', '.') . "</strong>";
        } else {
            $h_linhas[] = "<span class='text-success fw-bold fs-6'>✔ Valor Final da Comissão: R$ " . number_format($valor_final, 2, ',', '.') . "</span>";
        }
        if ($flag_aprov) {
            $h_linhas[] = "⚠️ <span class='text-danger fw-bold'>Este item requer APROVAÇÃO (Desc > 20% ou PM > 42 dias)</span>";
        }
        $item_steps[] = ['subtitulo' => 'H — Valor Final da Comissão', 'cor' => 'success', 'linhas' => $h_linhas];

        $itens_resultado[] = [
            'cfop_invalido'  => false,
            'produto'        => $xProd,
            'codigo'         => $cProd,
            'cfop'           => $cfop_item,
            'venda_net'      => $venda_net,
            'valor_comissao' => $valor_final,
            'flag_teto'      => $flag_teto,
            'flag_aprovacao' => $flag_aprov,
            'sem_lista'      => (bool)$lista_nao_encontrada,
            'steps'          => $item_steps,
        ];
    }

    $steps[] = [
        'titulo' => '📦 Etapa 2 — Prazo Médio (PM)',
        'cor'    => 'secondary',
        'linhas' => [
            "Fonte: {$pm_fonte}",
            "PM calculado: <strong>" . number_format($pm_dias, 1, ',', '.') . " dias</strong>",
            "Baseline do sistema: 28 dias. Penalidade: -0,05% / semana acima; Bônus: +0,05% / semana abaixo.",
        ]
    ];

    ob_end_clean();
    echo json_encode([
        'success'     => true,
        'nf'          => "{$serie}/{$nNF}",
        'data'        => $data_nf_fmt ?? '',
        'cliente'     => $clienteNome,
        'representante' => $representante_manual,
        'pm_dias'     => round($pm_dias, 1),
        'steps_globais' => $steps,
        'itens'       => $itens_resultado,
    ]);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
