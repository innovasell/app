<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
ob_start();
ini_set('display_errors', 0);
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $error['message']]);
    }
});

session_start();
require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';
require_once __DIR__ . '/../config/regime_tributario.php';

$regime = $_POST['regime'] ?? $_SESSION['regime_tributario'] ?? 'presumido';
$_SESSION['regime_tributario'] = $regime;

// 'entrada' (tpNF=0), 'saida' (tpNF=1), 'auto' (sem filtro)
$tipo = $_POST['tipo'] ?? 'auto';
if (!in_array($tipo, ['entrada', 'saida', 'auto'])) $tipo = 'auto';

if (!isset($_FILES['arquivo_zip'])) {
    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado.']);
    exit;
}

$file = $_FILES['arquivo_zip'];
$extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($extensao !== 'zip') {
    echo json_encode(['success' => false, 'error' => 'Envie apenas arquivos .zip']);
    exit;
}

// 1. Descompactar o ZIP
$zipDir = sys_get_temp_dir() . '/nfe_' . uniqid();
if (!mkdir($zipDir, 0777, true)) {
    echo json_encode(['success' => false, 'error' => 'Falha ao criar diretório temporário.']);
    exit;
}

$zip = new ZipArchive;
if ($zip->open($file['tmp_name']) === true) {
    $zip->extractTo($zipDir);
    $zip->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Falha ao descompactar o arquivo ZIP.']);
    exit;
}

// 2. Coletar arquivos XML
$files = glob($zipDir . '/*.xml');
if (empty($files)) {
    $files = glob($zipDir . '/**/*.xml');
}
if (empty($files)) {
    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo .xml encontrado dentro do ZIP.']);
    exit;
}

// 3. Status Stats
$stats = [
    'processadas' => 0,
    'ignoradas_canceladas' => 0,
    'ignoradas_duplicadas' => 0,
    'erros' => 0,
    'log' => [],
    'session_id' => uniqid(),
    'nfs' => []
];

// 4. Detecção de Canceladas primeiro
$chaves_canceladas = [];
foreach ($files as $arquivo) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string(file_get_contents($arquivo));
    if ($xml !== false && isset($xml->retEvento)) {
        if ((string)$xml->retEvento->infEvento->tpEvento === '110111') {
            $ch = (string)$xml->retEvento->infEvento->chNFe;
            if (!empty($ch)) $chaves_canceladas[] = $ch;
        }
    }
}

$stmt = $pdo->query("SELECT chNFe FROM fin_notas");
$chaves_bd = $stmt->fetchAll(PDO::FETCH_COLUMN);

$nfe_resultados = [];
$dbLoteId = null;
$stmtLote = $pdo->prepare("INSERT INTO fin_lotes (regime, total_nfs) VALUES (?, 0)");
$stmtLote->execute([$regime]);
$dbLoteId = $pdo->lastInsertId();
$qts_inseridas_db = 0;

// 5. Loop de Processamento
foreach ($files as $arquivo) {
    libxml_use_internal_errors(true);
    $xmlContent = file_get_contents($arquivo);
    $xml = simplexml_load_string($xmlContent);
    $filename = basename($arquivo);

    if ($xml === false) {
        $stats['erros']++;
        $stats['log'][] = ['tipo' => 'erro', 'msg' => "$filename ignorado — XML inválido"];
        continue;
    }

    $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
    $nfeNode = $xml->xpath('//nfe:NFe');

    if (isset($xml->evento) || isset($xml->retEvento) || empty($nfeNode)) continue;

    $infNFe = $nfeNode[0]->infNFe;
    $chNFe = str_replace('NFe', '', (string)$infNFe->attributes()['Id']);

    // Filtro por tipo de NF (entrada=0, saida=1)
    $tpNF = (string)($infNFe->ide->tpNF ?? '');
    if ($tipo === 'entrada' && $tpNF !== '0') {
        $stats['log'][] = ['tipo' => 'ignorada', 'msg' => "$filename ignorado — não é NF-e de Entrada (tpNF=$tpNF)"];
        continue;
    }
    if ($tipo === 'saida' && $tpNF !== '1') {
        $stats['log'][] = ['tipo' => 'ignorada', 'msg' => "$filename ignorado — não é NF-e de Saída (tpNF=$tpNF)"];
        continue;
    }

    if (in_array($chNFe, $chaves_canceladas)) {
        $stats['ignoradas_canceladas']++;
        $stats['log'][] = ['tipo' => 'ignorada', 'msg' => "NF $chNFe ignorada — cancelada"];
        continue;
    }

    $ja_existe = in_array($chNFe, $chaves_bd);
    if ($ja_existe) {
        $stats['ignoradas_duplicadas']++;
        $stats['log'][] = ['tipo' => 'ignorada', 'msg' => "NF $chNFe lida apenas para Excel — já existente no banco"];
    }

    // EXTRAÇÃO
    $ide       = $infNFe->ide;
    $emit      = $infNFe->emit;
    $dest      = $infNFe->dest;
    $total     = $infNFe->total;
    $transp    = $infNFe->transp;
    $pag       = $infNFe->pag;
    $cobr      = $infNFe->cobr ?? null;
    $infAdic   = $infNFe->infAdic ?? null;
    $infRespTec = $infNFe->infRespTec ?? null;
    $protNFe   = $xml->protNFe->infProt ?? null;

    $nNF   = (string) $ide->nNF;
    $dhEmi = substr((string) $ide->dhEmi, 0, 10);

    $cnpjEmit = (string) $emit->CNPJ;
    $nomeEmit = (string) $emit->xNome;
    $cnpjDest = (string) ($dest->CNPJ ?? $dest->CPF ?? $dest->idEstrangeiro ?? '');
    $nomeDest = (string) $dest->xNome;

    $tot = $total->ICMSTot;
    $vProdNF  = (float) ($tot->vProd  ?? 0);
    $vDescNF  = (float) ($tot->vDesc  ?? 0);
    $vFreteNF = (float) ($tot->vFrete ?? 0);
    $vNF      = (float) ($tot->vNF    ?? 0);

    $totPIS = $totCOFINS = $totICMS = $totIPI = 0;
    $itens_db = [];

    foreach ($infNFe->det as $det) {
        $nItem  = (int)    $det->attributes()['nItem'];
        $prod   = $det->prod;
        $cProd  = (string) $prod->cProd;
        $xProd  = (string) $prod->xProd;
        $NCM    = (string) $prod->NCM;
        $CFOP   = (string) $prod->CFOP;
        $uCom   = (string) $prod->uCom;
        $qCom   = (float)  ($prod->qCom   ?? 0);
        $vUnCom = (float)  ($prod->vUnCom ?? 0);
        $vProd  = (float)  ($prod->vProd  ?? 0);
        $vDesc  = (float)  ($prod->vDesc  ?? 0);

        // Rastro (rastreabilidade de lote — pode ter múltiplos)
        $rastro = [];
        if (isset($prod->rastro)) {
            foreach ($prod->rastro as $r) {
                $rastro[] = [
                    'nLote' => (string)($r->nLote ?? ''),
                    'qLote' => (string)($r->qLote ?? ''),
                    'dFab'  => (string)($r->dFab  ?? ''),
                    'dVal'  => (string)($r->dVal  ?? '')
                ];
            }
        }

        // ICMS
        $bcICMS = 0; $pICMS = 0; $vICMS = 0;
        $origICMS = ''; $cstICMS = ''; $modBcICMS = '';
        if (isset($det->imposto->ICMS)) {
            foreach ($det->imposto->ICMS->children() as $grupo) {
                $origICMS  = (string) ($grupo->orig  ?? '');
                $cstICMS   = (string) ($grupo->CST   ?? $grupo->CSOSN ?? '');
                $modBcICMS = (string) ($grupo->modBC ?? '');
                $bcICMS = (float) ($grupo->vBC   ?? $bcICMS);
                $pICMS  = (float) ($grupo->pICMS ?? $pICMS);
                $vICMS  = (float) ($grupo->vICMS ?? $vICMS);
            }
        }

        // IPI
        $bcIPI = 0; $pIPI = 0; $vIPI = 0; $cEnqIPI = ''; $cstIPI = '';
        if (isset($det->imposto->IPI)) {
            $cEnqIPI = (string) ($det->imposto->IPI->cEnq ?? '');
            if (isset($det->imposto->IPI->IPITrib)) {
                $cstIPI = (string) ($det->imposto->IPI->IPITrib->CST ?? '');
                $bcIPI = (float) ($det->imposto->IPI->IPITrib->vBC  ?? 0);
                $pIPI  = (float) ($det->imposto->IPI->IPITrib->pIPI ?? 0);
                $vIPI  = (float) ($det->imposto->IPI->IPITrib->vIPI ?? 0);
            }
        }

        // II
        $bcII = 0; $vDespAduII = 0; $vII = 0; $vIOFII = 0;
        if (isset($det->imposto->II)) {
            $bcII       = (float) ($det->imposto->II->vBC      ?? 0);
            $vDespAduII = (float) ($det->imposto->II->vDespAdu ?? 0);
            $vII        = (float) ($det->imposto->II->vII      ?? 0);
            $vIOFII     = (float) ($det->imposto->II->vIOF     ?? 0);
        }

        // PIS (XML)
        $cstPIS_xml = ''; $bcPIS_xml = 0; $pPIS_xml = 0; $vPIS_xml = 0;
        if (isset($det->imposto->PIS)) {
            foreach ($det->imposto->PIS->children() as $pG) {
                $cstPIS_xml = (string) ($pG->CST  ?? '');
                $bcPIS_xml  = (float)  ($pG->vBC  ?? 0);
                $pPIS_xml   = (float)  ($pG->pPIS ?? 0);
                $vPIS_xml   = (float)  ($pG->vPIS ?? 0);
            }
        }

        // COFINS (XML)
        $cstCOFINS_xml = ''; $bcCOFINS_xml = 0; $pCOFINS_xml = 0; $vCOFINS_xml = 0;
        if (isset($det->imposto->COFINS)) {
            foreach ($det->imposto->COFINS->children() as $cG) {
                $cstCOFINS_xml = (string) ($cG->CST    ?? '');
                $bcCOFINS_xml  = (float)  ($cG->vBC    ?? 0);
                $pCOFINS_xml   = (float)  ($cG->pCOFINS ?? 0);
                $vCOFINS_xml   = (float)  ($cG->vCOFINS ?? 0);
            }
        }

        // IBS/CBS
        $cstIBSCBS = ''; $cClassTribIBS = ''; $bcIBSCBS = 0;
        $pIBSUF = 0; $vIBSUF = 0; $pIBSMun = 0; $vIBSMun = 0; $vIBS = 0; $pCBS = 0; $vCBS = 0;
        if (isset($det->imposto->IBSCBS)) {
            $ibs = $det->imposto->IBSCBS;
            $cstIBSCBS    = (string) ($ibs->CST        ?? '');
            $cClassTribIBS = (string) ($ibs->cClassTrib ?? '');
            $bcIBSCBS = (float) ($ibs->gIBSCBS->vBC    ?? 0);
            $pIBSUF   = (float) ($ibs->gIBSCBS->gIBSUF->pIBSUF  ?? $ibs->gIBSUF->pIBSUF  ?? 0);
            $vIBSUF   = (float) ($ibs->gIBSCBS->gIBSUF->vIBSUF  ?? $ibs->gIBSUF->vIBSUF  ?? 0);
            $pIBSMun  = (float) ($ibs->gIBSCBS->gIBSMun->pIBSMun ?? $ibs->gIBSMun->pIBSMun ?? 0);
            $vIBSMun  = (float) ($ibs->gIBSCBS->gIBSMun->vIBSMun ?? $ibs->gIBSMun->vIBSMun ?? 0);
            $vIBS     = (float) ($ibs->gIBSCBS->vIBS   ?? $ibs->vIBS ?? 0);
            $pCBS     = (float) ($ibs->gIBSCBS->gCBS->pCBS ?? $ibs->gCBS->pCBS ?? 0);
            $vCBS     = (float) ($ibs->gIBSCBS->gCBS->vCBS ?? $ibs->gCBS->vCBS ?? 0);
        }

        // PIS/COFINS calculado (compatibilidade DB)
        $bcPisCofins = $vProd - $vDesc;
        [$vPIS, $pPIS, $vCOFINS, $pCOFINS] = calcularPisCofins($bcPisCofins, $regime);

        $totPIS    += $vPIS;
        $totCOFINS += $vCOFINS;
        $totICMS   += $vICMS;
        $totIPI    += $vIPI;

        // DI
        $arrDI = [];
        if (isset($prod->DI)) {
            foreach ($prod->DI as $diNode) {
                $arrDI = [
                    'nDI'         => (string)($diNode->nDI         ?? ''),
                    'dDI'         => (string)($diNode->dDI         ?? ''),
                    'xLocDesemb'  => (string)($diNode->xLocDesemb  ?? ''),
                    'UFDesemb'    => (string)($diNode->UFDesemb    ?? ''),
                    'dDesemb'     => (string)($diNode->dDesemb     ?? ''),
                    'tpViaTransp' => (string)($diNode->tpViaTransp ?? ''),
                    'vAFRMM'      => (float) ($diNode->vAFRMM      ?? 0),
                    'tpIntermedio' => (string)($diNode->tpIntermedio ?? ''),
                    'cExportador' => (string)($diNode->cExportador  ?? ''),
                    'nAdicao'     => (string)($diNode->adi->nAdicao  ?? ''),
                    'nSeqAdic'    => (string)($diNode->adi->nSeqAdic ?? ''),
                    'cFabricante' => (string)($diNode->adi->cFabricante ?? '')
                ];
                break;
            }
        }

        $itens_db[] = [
            'n_item'        => $nItem,
            'c_prod'        => $cProd,
            'x_prod'        => $xProd,
            'ncm'           => $NCM,
            'cfop'          => $CFOP,
            'u_com'         => $uCom,
            'q_com'         => $qCom,
            'v_un_com'      => $vUnCom,
            'v_prod'        => $vProd,
            'v_desc'        => $vDesc,
            'bc_pis_cofins' => $bcPisCofins,
            'p_pis'         => $pPIS,
            'v_pis'         => $vPIS,
            'p_cofins'      => $pCOFINS,
            'v_cofins'      => $vCOFINS,
            'bc_icms'       => $bcICMS,
            'p_icms'        => $pICMS,
            'v_icms'        => $vICMS,
            'bc_ipi'        => $bcIPI,
            'p_ipi'         => $pIPI,
            'v_ipi'         => $vIPI,
            // Extra Excel
            'cEAN'          => (string)($prod->cEAN    ?? ''),
            'cEANTrib'      => (string)($prod->cEANTrib ?? ''),
            'uTrib'         => (string)($prod->uTrib   ?? ''),
            'qTrib'         => (float) ($prod->qTrib   ?? 0),
            'vUnTrib'       => (float) ($prod->vUnTrib ?? 0),
            'indTot'        => (string)($prod->indTot  ?? ''),
            'origICMS'      => $origICMS, 'cstICMS' => $cstICMS, 'modBcICMS' => $modBcICMS,
            'cEnqIPI'       => $cEnqIPI,  'cstIPI'  => $cstIPI,
            'bcII'          => $bcII, 'vDespAduII' => $vDespAduII, 'vII' => $vII, 'vIOFII' => $vIOFII,
            'cstPIS_xml'    => $cstPIS_xml, 'bcPIS_xml' => $bcPIS_xml, 'pPIS_xml' => $pPIS_xml, 'vPIS_xml' => $vPIS_xml,
            'cstCOFINS_xml' => $cstCOFINS_xml, 'bcCOFINS_xml' => $bcCOFINS_xml, 'pCOFINS_xml' => $pCOFINS_xml, 'vCOFINS_xml' => $vCOFINS_xml,
            'cstIBSCBS'     => $cstIBSCBS, 'cClassTribIBS' => $cClassTribIBS, 'bcIBSCBS' => $bcIBSCBS,
            'pIBSUF'        => $pIBSUF, 'vIBSUF' => $vIBSUF, 'pIBSMun' => $pIBSMun, 'vIBSMun' => $vIBSMun,
            'vIBS'          => $vIBS, 'pCBS' => $pCBS, 'vCBS' => $vCBS,
            'di'            => $arrDI,
            // Rastro (rastreabilidade)
            'rastro'        => $rastro
        ];
    }

    // autXML
    $autXML_CNPJ = [];
    if (isset($infNFe->autXML)) {
        foreach ($infNFe->autXML as $aut) {
            if (isset($aut->CNPJ)) $autXML_CNPJ[] = (string)$aut->CNPJ;
            if (isset($aut->CPF))  $autXML_CNPJ[] = (string)$aut->CPF;
        }
    }

    // Cobrança (cobr): fatura + duplicatas
    $cobr_fat  = [];
    $cobr_dups = [];
    if ($cobr) {
        if (isset($cobr->fat)) {
            $cobr_fat = [
                'nFat' => (string)($cobr->fat->nFat  ?? ''),
                'vOrig' => (float)($cobr->fat->vOrig ?? 0),
                'vDesc' => (float)($cobr->fat->vDesc ?? 0),
                'vLiq'  => (float)($cobr->fat->vLiq  ?? 0)
            ];
        }
        foreach ($cobr->dup ?? [] as $dup) {
            $cobr_dups[] = [
                'nDup'  => (string)($dup->nDup  ?? ''),
                'dVenc' => (string)($dup->dVenc ?? ''),
                'vDup'  => (float) ($dup->vDup  ?? 0)
            ];
        }
    }

    // Múltiplas formas de pagamento
    $pag_detalhes = [];
    if (isset($pag->detPag)) {
        foreach ($pag->detPag as $dp) {
            $pag_detalhes[] = [
                'indPag' => (string)($dp->indPag ?? ''),
                'tPag'   => (string)($dp->tPag   ?? ''),
                'vPag'   => (float) ($dp->vPag   ?? 0)
            ];
        }
    }

    $tipoResolvido = ($tpNF === '1') ? 'saida' : 'entrada';

    $nfStruct = [
        "chNFe"     => $chNFe,     "nNF" => $nNF,        "dhEmi" => $dhEmi,
        "cnpj_emit" => $cnpjEmit,  "nome_emit" => $nomeEmit,
        "cnpj_dest" => $cnpjDest,  "nome_dest" => $nomeDest,
        "qtd_itens" => count($itens_db),
        "v_prod"    => $vProdNF,   "v_desc" => $vDescNF,  "v_frete" => $vFreteNF,
        "v_icms"    => $totICMS,   "v_ipi" => $totIPI,    "v_pis" => $totPIS, "v_cofins" => $totCOFINS, "v_nf" => $vNF,
        "itens"     => $itens_db,
        "tipo"      => $tipoResolvido,

        // ide
        'ide_cUF'      => (string)($ide->cUF      ?? ''),
        'ide_cNF'      => (string)($ide->cNF      ?? ''),
        'ide_natOp'    => (string)($ide->natOp    ?? ''),
        'ide_mod'      => (string)($ide->mod      ?? ''),
        'ide_serie'    => (string)($ide->serie    ?? ''),
        'ide_dhSaiEnt' => (string)($ide->dhSaiEnt ?? ''),
        'ide_tpNF'     => (string)($ide->tpNF     ?? ''),
        'ide_idDest'   => (string)($ide->idDest   ?? ''),
        'ide_cMunFG'   => (string)($ide->cMunFG   ?? ''),
        'ide_tpImp'    => (string)($ide->tpImp    ?? ''),
        'ide_tpEmis'   => (string)($ide->tpEmis   ?? ''),
        'ide_cDV'      => (string)($ide->cDV      ?? ''),
        'ide_tpAmb'    => (string)($ide->tpAmb    ?? ''),
        'ide_finNFe'   => (string)($ide->finNFe   ?? ''),
        'ide_indFinal' => (string)($ide->indFinal ?? ''),
        'ide_indPres'  => (string)($ide->indPres  ?? ''),
        'ide_procEmi'  => (string)($ide->procEmi  ?? ''),
        'ide_verProc'  => (string)($ide->verProc  ?? ''),
        'ide_NFref'    => (string)($ide->NFref->refNFe ?? ''),

        // emit
        'emit_xFant'   => (string)($emit->xFant          ?? ''),
        'emit_xLgr'    => (string)($emit->enderEmit->xLgr    ?? ''),
        'emit_nro'     => (string)($emit->enderEmit->nro     ?? ''),
        'emit_xCpl'    => (string)($emit->enderEmit->xCpl    ?? ''),
        'emit_xBairro' => (string)($emit->enderEmit->xBairro ?? ''),
        'emit_cMun'    => (string)($emit->enderEmit->cMun    ?? ''),
        'emit_xMun'    => (string)($emit->enderEmit->xMun    ?? ''),
        'emit_UF'      => (string)($emit->enderEmit->UF      ?? ''),
        'emit_CEP'     => (string)($emit->enderEmit->CEP     ?? ''),
        'emit_cPais'   => (string)($emit->enderEmit->cPais   ?? ''),
        'emit_xPais'   => (string)($emit->enderEmit->xPais   ?? ''),
        'emit_fone'    => (string)($emit->enderEmit->fone    ?? ''),
        'emit_IE'      => (string)($emit->IE  ?? ''),
        'emit_CRT'     => (string)($emit->CRT ?? ''),

        // dest
        'dest_xLgr'    => (string)($dest->enderDest->xLgr    ?? ''),
        'dest_nro'     => (string)($dest->enderDest->nro     ?? ''),
        'dest_xCpl'    => (string)($dest->enderDest->xCpl    ?? ''),
        'dest_xBairro' => (string)($dest->enderDest->xBairro ?? ''),
        'dest_cMun'    => (string)($dest->enderDest->cMun    ?? ''),
        'dest_xMun'    => (string)($dest->enderDest->xMun    ?? ''),
        'dest_UF'      => (string)($dest->enderDest->UF      ?? ''),
        'dest_CEP'     => (string)($dest->enderDest->CEP     ?? ''),
        'dest_cPais'   => (string)($dest->enderDest->cPais   ?? ''),
        'dest_xPais'   => (string)($dest->enderDest->xPais   ?? ''),
        'dest_fone'    => (string)($dest->enderDest->fone    ?? ''),
        'dest_indIEDest' => (string)($dest->indIEDest ?? ''),
        'dest_IE'      => (string)($dest->IE ?? ''),
        'autXML_CNPJ'  => implode(', ', $autXML_CNPJ),

        // totais
        'tot_vICMSDeson'        => (float)($tot->vICMSDeson  ?? 0),
        'tot_vFCP'              => (float)($tot->vFCP        ?? 0),
        'tot_vBCST'             => (float)($tot->vBCST       ?? 0),
        'tot_vST'               => (float)($tot->vST         ?? 0),
        'tot_vFCPST'            => (float)($tot->vFCPST      ?? 0),
        'tot_vFCPSTRet'         => (float)($tot->vFCPSTRet   ?? 0),
        'tot_vSeg'              => (float)($tot->vSeg        ?? 0),
        'tot_vII'               => (float)($tot->vII         ?? 0),
        'tot_vIPIDevol'         => (float)($tot->vIPIDevol   ?? 0),
        'tot_vOutro'            => (float)($tot->vOutro      ?? 0),
        'tot_IBSCBSTot_vBCIBSCBS' => (float)($total->IBSCBSTot->vBCIBSCBS ?? 0),
        'tot_IBSCBSTot_vIBS'    => (float)($total->IBSCBSTot->gIBS->vIBS   ?? $total->IBSCBSTot->vIBS ?? 0),
        'tot_IBSCBSTot_vCBS'    => (float)($total->IBSCBSTot->gCBS->vCBS   ?? 0),

        // transp
        'transp_modFrete' => (string)($transp->modFrete   ?? ''),
        'transp_xNome'    => (string)($transp->transporta->xNome ?? ''),
        'transp_qVol'     => (string)($transp->vol->qVol  ?? ''),
        'transp_esp'      => (string)($transp->vol->esp   ?? ''),
        'transp_pesoL'    => (float) ($transp->vol->pesoL ?? 0),
        'transp_pesoB'    => (float) ($transp->vol->pesoB ?? 0),

        // pagamento (primeiro detPag para compatibilidade)
        'pag_indPag' => (string)($pag->detPag->indPag ?? ''),
        'pag_tPag'   => (string)($pag->detPag->tPag   ?? ''),
        'pag_vPag'   => (float) ($pag->detPag->vPag   ?? 0),
        'pag_detalhes' => $pag_detalhes,

        // cobr (cobrança)
        'cobr_fat'  => $cobr_fat,
        'cobr_dups' => $cobr_dups,

        // infAdic / responsável técnico
        'infAdic_infCpl'        => (string)($infAdic->infCpl          ?? ''),
        'infRespTec_CNPJ'       => (string)($infRespTec->CNPJ         ?? ''),
        'infRespTec_xContato'   => (string)($infRespTec->xContato     ?? ''),
        'infRespTec_email'      => (string)($infRespTec->email        ?? ''),
        'infRespTec_fone'       => (string)($infRespTec->fone         ?? ''),

        // protocolo
        'prot_tpAmb'    => (string)($protNFe->tpAmb    ?? ''),
        'prot_verAplic' => (string)($protNFe->verAplic ?? ''),
        'prot_dhRecbto' => (string)($protNFe->dhRecbto ?? ''),
        'prot_nProt'    => (string)($protNFe->nProt    ?? ''),
        'prot_digVal'   => (string)($protNFe->digVal   ?? ''),
        'prot_cStat'    => (string)($protNFe->cStat    ?? ''),
        'prot_xMotivo'  => (string)($protNFe->xMotivo  ?? '')
    ];

    $nfe_resultados[] = $nfStruct;

    $stats['nfs'][] = [
        "chNFe"     => $chNFe,    "nNF" => $nNF,       "dhEmi" => $dhEmi,
        "cnpj_emit" => $cnpjEmit, "nome_emit" => $nomeEmit,
        "cnpj_dest" => $cnpjDest, "nome_dest" => $nomeDest,
        "qtd_itens" => count($itens_db), "v_nf" => $vNF,
        "tipo"      => $tipoResolvido
    ];

    if (!$ja_existe) {
        try {
            $pdo->beginTransaction();

            $sqlNF = "INSERT INTO fin_notas (lote_id, tipo, chNFe, nNF, dhEmi, cnpj_emit, nome_emit, cnpj_dest, nome_dest, v_prod, v_desc, v_frete, v_icms, v_ipi, v_pis, v_cofins, v_nf)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtNF = $pdo->prepare($sqlNF);
            $stmtNF->execute([
                $dbLoteId, $tipoResolvido, $chNFe, $nNF, $dhEmi, $cnpjEmit, $nomeEmit, $cnpjDest, $nomeDest,
                $vProdNF, $vDescNF, $vFreteNF, $totICMS, $totIPI, $totPIS, $totCOFINS, $vNF
            ]);
            $notaId = $pdo->lastInsertId();

            $sqlItem = "INSERT INTO fin_itens (nota_id, n_item, c_prod, x_prod, ncm, cfop, u_com, q_com, v_un_com, v_prod, v_desc, bc_pis_cofins, p_pis, v_pis, p_cofins, v_cofins, bc_icms, p_icms, v_icms, bc_ipi, p_ipi, v_ipi)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtItem = $pdo->prepare($sqlItem);
            foreach ($itens_db as $item) {
                $stmtItem->execute([
                    $notaId, $item['n_item'], $item['c_prod'], $item['x_prod'], $item['ncm'], $item['cfop'],
                    $item['u_com'], $item['q_com'], $item['v_un_com'], $item['v_prod'], $item['v_desc'],
                    $item['bc_pis_cofins'], $item['p_pis'], $item['v_pis'], $item['p_cofins'], $item['v_cofins'],
                    $item['bc_icms'], $item['p_icms'], $item['v_icms'], $item['bc_ipi'], $item['p_ipi'], $item['v_ipi']
                ]);
            }

            $pdo->commit();
            $chaves_bd[] = $chNFe;
            $stats['processadas']++;
            $qts_inseridas_db++;
            $stats['log'][] = ['tipo' => 'ok', 'msg' => "NF $nNF ($tipoResolvido) processada — " . count($itens_db) . " itens armazenados"];

        } catch (Exception $e) {
            $pdo->rollBack();
            $stats['erros']++;
            $stats['log'][] = ['tipo' => 'erro', 'msg' => "Erro DB na NF $nNF: " . $e->getMessage()];
        }
    }
}

// Update DB Lote
if ($dbLoteId && $qts_inseridas_db > 0) {
    $pdo->query("UPDATE fin_lotes SET total_nfs = $qts_inseridas_db WHERE id = $dbLoteId");
} else if ($dbLoteId && $qts_inseridas_db === 0) {
    $pdo->query("DELETE FROM fin_lotes WHERE id = $dbLoteId");
}

$tempResultJson = sys_get_temp_dir() . "/nfe_resultado_{$stats['session_id']}.json";
file_put_contents($tempResultJson, json_encode([
    'regime' => $regime,
    'tipo'   => $tipo,
    'nfs'    => $nfe_resultados
]));

// Limpar temp
$tempfiles = glob($zipDir . '/*');
foreach ($tempfiles as $f) { if (is_file($f)) unlink($f); }
rmdir($zipDir);

ob_clean();
echo json_encode([
    'success' => true,
    'regime'  => $regime,
    'tipo'    => $tipo,
    'total_arquivos'       => count($files),
    'processadas'          => $stats['processadas'],
    'erros'                => $stats['erros'],
    'ignoradas_canceladas' => $stats['ignoradas_canceladas'],
    'ignoradas_duplicadas' => $stats['ignoradas_duplicadas'],
    'log'        => $stats['log'],
    'session_id' => $stats['session_id'],
    'nfs'        => $stats['nfs']
]);
exit;
