<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
ini_set('display_errors', 0);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        ob_clean();
        header('Content-Type: text/html');
        echo "<h2>Erro Fatal:</h2><pre>" . print_r($error, true) . "</pre>";
    }
});

$uniqid = $_GET['session_id'] ?? null;
if (!$uniqid) die("ID da sessão de exportação não fornecido.");

$tempResultJson = sys_get_temp_dir() . "/nfe_resultado_{$uniqid}.json";
$tempResultJson2 = "/tmp/nfe_resultado_{$uniqid}.json";
if (!file_exists($tempResultJson) && file_exists($tempResultJson2)) $tempResultJson = $tempResultJson2;

if (!file_exists($tempResultJson)) {
    die("Sessão expirada ou exportação já realizada. Faça o upload novamente.");
}

$data = json_decode(file_get_contents($tempResultJson), true);
if (!$data || empty($data['nfs'])) die("Nenhum dado válido para exportar.");

$regime = $data['regime'] ?? 'presumido';
$tipo   = $data['tipo']   ?? 'auto';
$nfs    = $data['nfs'];

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
$spreadsheet->removeSheetByIndex(0);

// Estilos
$headerStyle = [
    'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0A1E42']],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF1A365D']]]
];
$linhaImparStyle = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F4F8']]];
$saidaHeaderStyle = [
    'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF40883C']],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF2C5E29']]]
];

/* ================================================================
   ABA 1 — Geral NF-es (capa)
================================================================ */
$sheet1 = $spreadsheet->createSheet();
$sheet1->setTitle('Geral NF-es');

$colsNF = [
    // Chave / IDE
    'Tipo NF-e'      => 'tipo',
    'Chave NF-e'     => 'chNFe',
    'cUF'            => 'ide_cUF',
    'cNF'            => 'ide_cNF',
    'natOp'          => 'ide_natOp',
    'mod'            => 'ide_mod',
    'serie'          => 'ide_serie',
    'nNF'            => 'nNF',
    'dhEmi'          => 'dhEmi',
    'dhSaiEnt'       => 'ide_dhSaiEnt',
    'tpNF'           => 'ide_tpNF',
    'idDest'         => 'ide_idDest',
    'cMunFG'         => 'ide_cMunFG',
    'tpImp'          => 'ide_tpImp',
    'tpEmis'         => 'ide_tpEmis',
    'cDV'            => 'ide_cDV',
    'tpAmb'          => 'ide_tpAmb',
    'finNFe'         => 'ide_finNFe',
    'indFinal'       => 'ide_indFinal',
    'indPres'        => 'ide_indPres',
    'procEmi'        => 'ide_procEmi',
    'verProc'        => 'ide_verProc',
    'NFref'          => 'ide_NFref',

    // Emitente
    'Emit CNPJ'      => 'cnpj_emit',
    'Emit xNome'     => 'nome_emit',
    'Emit xFant'     => 'emit_xFant',
    'Emit xLgr'      => 'emit_xLgr',
    'Emit nro'       => 'emit_nro',
    'Emit xCpl'      => 'emit_xCpl',
    'Emit xBairro'   => 'emit_xBairro',
    'Emit cMun'      => 'emit_cMun',
    'Emit xMun'      => 'emit_xMun',
    'Emit UF'        => 'emit_UF',
    'Emit CEP'       => 'emit_CEP',
    'Emit cPais'     => 'emit_cPais',
    'Emit xPais'     => 'emit_xPais',
    'Emit fone'      => 'emit_fone',
    'Emit IE'        => 'emit_IE',
    'Emit CRT'       => 'emit_CRT',

    // Destinatário
    'Dest CNPJ/CPF'  => 'cnpj_dest',
    'Dest Nome'      => 'nome_dest',
    'Dest xLgr'      => 'dest_xLgr',
    'Dest nro'       => 'dest_nro',
    'Dest xCpl'      => 'dest_xCpl',
    'Dest xBairro'   => 'dest_xBairro',
    'Dest cMun'      => 'dest_cMun',
    'Dest xMun'      => 'dest_xMun',
    'Dest UF'        => 'dest_UF',
    'Dest CEP'       => 'dest_CEP',
    'Dest cPais'     => 'dest_cPais',
    'Dest xPais'     => 'dest_xPais',
    'Dest fone'      => 'dest_fone',
    'Dest indIEDest' => 'dest_indIEDest',
    'Dest IE'        => 'dest_IE',
    'autXML CNPJ'    => 'autXML_CNPJ',

    // Totais
    'Tot vProd'      => 'v_prod',
    'Tot vDesc'      => 'v_desc',
    'Tot vFrete'     => 'v_frete',
    'Tot vSeg'       => 'tot_vSeg',
    'Tot vOutro'     => 'tot_vOutro',
    'Tot vII'        => 'tot_vII',
    'Tot vIPI'       => 'v_ipi',
    'Tot vIPIDevol'  => 'tot_vIPIDevol',
    'Tot vPIS'       => 'v_pis',
    'Tot vCOFINS'    => 'v_cofins',
    'Tot vICMS'      => 'v_icms',
    'Tot vICMSDeson' => 'tot_vICMSDeson',
    'Tot vFCP'       => 'tot_vFCP',
    'Tot vBCST'      => 'tot_vBCST',
    'Tot vST'        => 'tot_vST',
    'Tot vFCPST'     => 'tot_vFCPST',
    'Tot vFCPSTRet'  => 'tot_vFCPSTRet',
    'Tot vNF'        => 'v_nf',

    // IBS/CBS total
    'IBSCBS vBC'     => 'tot_IBSCBSTot_vBCIBSCBS',
    'IBSCBS vIBS'    => 'tot_IBSCBSTot_vIBS',
    'IBSCBS vCBS'    => 'tot_IBSCBSTot_vCBS',

    // Transporte
    'Transp modFrete' => 'transp_modFrete',
    'Transp Transportadora' => 'transp_xNome',
    'Transp qVol'    => 'transp_qVol',
    'Transp esp'     => 'transp_esp',
    'Transp pesoL'   => 'transp_pesoL',
    'Transp pesoB'   => 'transp_pesoB',

    // Pagamento
    'Pag indPag'     => 'pag_indPag',
    'Pag tPag'       => 'pag_tPag',
    'Pag vPag'       => 'pag_vPag',

    // Cobrança — Fatura
    'Cobr Fat nFat'  => 'cobr_fat_nFat',
    'Cobr Fat vOrig' => 'cobr_fat_vOrig',
    'Cobr Fat vDesc' => 'cobr_fat_vDesc',
    'Cobr Fat vLiq'  => 'cobr_fat_vLiq',
    'Cobr Qtd Dup'   => 'cobr_qtd_dup',

    // Info adicional / responsável
    'InfCpl'              => 'infAdic_infCpl',
    'RespTec CNPJ'        => 'infRespTec_CNPJ',
    'RespTec xContato'    => 'infRespTec_xContato',
    'RespTec email'       => 'infRespTec_email',
    'RespTec fone'        => 'infRespTec_fone',

    // Protocolo
    'Prot tpAmb'     => 'prot_tpAmb',
    'Prot verAplic'  => 'prot_verAplic',
    'Prot dhRecbto'  => 'prot_dhRecbto',
    'Prot nProt'     => 'prot_nProt',
    'Prot digVal'    => 'prot_digVal',
    'Prot cStat'     => 'prot_cStat',
    'Prot xMotivo'   => 'prot_xMotivo'
];

$colIndex = 1;
foreach ($colsNF as $title => $key) {
    $sheet1->setCellValue([$colIndex, 1], $title);
    $colIndex++;
}
$sheet1->getStyle([1, 1, count($colsNF), 1])->applyFromArray($headerStyle);

$rowNum = 2;
foreach ($nfs as $nf) {
    $c = 1;
    foreach ($colsNF as $title => $key) {
        // Campos derivados de sub-arrays
        if ($key === 'cobr_fat_nFat')  { $val = $nf['cobr_fat']['nFat']  ?? ''; }
        elseif ($key === 'cobr_fat_vOrig') { $val = $nf['cobr_fat']['vOrig'] ?? ''; }
        elseif ($key === 'cobr_fat_vDesc') { $val = $nf['cobr_fat']['vDesc'] ?? ''; }
        elseif ($key === 'cobr_fat_vLiq')  { $val = $nf['cobr_fat']['vLiq']  ?? ''; }
        elseif ($key === 'cobr_qtd_dup')   { $val = count($nf['cobr_dups'] ?? []); }
        else { $val = $nf[$key] ?? ''; }

        $isTotCol = strpos($title, 'Tot ') === 0 || in_array($key, ['pag_vPag', 'transp_pesoL', 'transp_pesoB', 'cobr_fat_vOrig', 'cobr_fat_vDesc', 'cobr_fat_vLiq']);
        if (is_numeric($val) && $val !== '' && $isTotCol) {
            $sheet1->setCellValueExplicit([$c, $rowNum], $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet1->getStyle([$c, $rowNum])->getNumberFormat()->setFormatCode('"R$ "#,##0.00_-');
        } else {
            $sheet1->setCellValueExplicit([$c, $rowNum], $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $c++;
    }
    if ($rowNum % 2 !== 0) $sheet1->getStyle([1, $rowNum, count($colsNF), $rowNum])->applyFromArray($linhaImparStyle);
    $rowNum++;
}
$sheet1->freezePane('A2');

/* ================================================================
   ABA 2 — Detalhe por Produto
================================================================ */
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Detalhe por Produto');

$colsItens = [
    'Tipo NF-e'      => 'nf_tipo',
    'Chave NF'       => 'nf_chNFe',
    'Número NF'      => 'nf_nNF',
    'Data Emissão'   => 'nf_dhEmi',
    'Emitente'       => 'nf_nome_emit',
    'Destinatário'   => 'nf_nome_dest',

    'nItem'          => 'n_item',
    'cProd'          => 'c_prod',
    'cEAN'           => 'cEAN',
    'xProd'          => 'x_prod',
    'NCM'            => 'ncm',
    'CFOP'           => 'cfop',
    'uCom'           => 'u_com',
    'qCom'           => 'q_com',
    'vUnCom'         => 'v_un_com',
    'vProd'          => 'v_prod',
    'vDesc'          => 'v_desc',
    'cEANTrib'       => 'cEANTrib',
    'uTrib'          => 'uTrib',
    'qTrib'          => 'qTrib',
    'vUnTrib'        => 'vUnTrib',
    'indTot'         => 'indTot',

    // Rastro (rastreabilidade)
    'Rastro nLote'   => 'rastro_nLote',
    'Rastro qLote'   => 'rastro_qLote',
    'Rastro dFab'    => 'rastro_dFab',
    'Rastro dVal'    => 'rastro_dVal',

    // DI (importação)
    'DI nDI'         => 'di_nDI',
    'DI dDI'         => 'di_dDI',
    'DI xLocDesemb'  => 'di_xLocDesemb',
    'DI UFDesemb'    => 'di_UFDesemb',
    'DI dDesemb'     => 'di_dDesemb',
    'DI tpViaTransp' => 'di_tpViaTransp',
    'DI vAFRMM'      => 'di_vAFRMM',
    'DI tpIntermedio' => 'di_tpIntermedio',
    'DI cExportador' => 'di_cExportador',
    'DI nAdicao'     => 'di_nAdicao',
    'DI nSeqAdic'    => 'di_nSeqAdic',
    'DI cFabricante' => 'di_cFabricante',

    // ICMS
    'ICMS orig'      => 'origICMS',
    'ICMS CST'       => 'cstICMS',
    'ICMS modBC'     => 'modBcICMS',
    'ICMS vBC'       => 'bc_icms',
    'ICMS pICMS'     => 'p_icms',
    'ICMS vICMS'     => 'v_icms',

    // IPI
    'IPI cEnq'       => 'cEnqIPI',
    'IPI CST'        => 'cstIPI',
    'IPI vBC'        => 'bc_ipi',
    'IPI pIPI'       => 'p_ipi',
    'IPI vIPI'       => 'v_ipi',

    // II
    'II vBC'         => 'bcII',
    'II vDespAdu'    => 'vDespAduII',
    'II vII'         => 'vII',
    'II vIOF'        => 'vIOFII',

    // PIS Calculado
    'PIS.Calc BC'    => 'bc_pis_cofins',
    'PIS.Calc %'     => 'p_pis',
    'PIS.Calc vPIS'  => 'v_pis',
    // PIS XML
    'PIS.XML CST'    => 'cstPIS_xml',
    'PIS.XML vBC'    => 'bcPIS_xml',
    'PIS.XML pPIS'   => 'pPIS_xml',
    'PIS.XML vPIS'   => 'vPIS_xml',

    // COFINS Calculado
    'COFINS.Calc BC'      => 'bc_pis_cofins',
    'COFINS.Calc %'       => 'p_cofins',
    'COFINS.Calc vCOFINS' => 'v_cofins',
    // COFINS XML
    'COFINS.XML CST'      => 'cstCOFINS_xml',
    'COFINS.XML vBC'      => 'bcCOFINS_xml',
    'COFINS.XML pCOFINS'  => 'pCOFINS_xml',
    'COFINS.XML vCOFINS'  => 'vCOFINS_xml',

    // IBS/CBS
    'IBSCBS CST'        => 'cstIBSCBS',
    'IBSCBS cClassTrib' => 'cClassTribIBS',
    'IBSCBS vBC'        => 'bcIBSCBS',
    'IBSCBS pIBSUF'     => 'pIBSUF',
    'IBSCBS vIBSUF'     => 'vIBSUF',
    'IBSCBS pIBSMun'    => 'pIBSMun',
    'IBSCBS vIBSMun'    => 'vIBSMun',
    'IBSCBS vIBS'       => 'vIBS',
    'IBSCBS pCBS'       => 'pCBS',
    'IBSCBS vCBS'       => 'vCBS',

    'Regime Tributário' => 'nf_regime'
];

$colIndex = 1;
foreach ($colsItens as $title => $key) {
    $sheet2->setCellValue([$colIndex, 1], $title);
    $colIndex++;
}
$sheet2->getStyle([1, 1, count($colsItens), 1])->applyFromArray($headerStyle);

$rowNum = 2;
foreach ($nfs as $nf) {
    foreach ($nf['itens'] ?? [] as $it) {
        // Primeiro rastro do item (mais comum: apenas 1)
        $primeiroRastro = $it['rastro'][0] ?? [];

        $c = 1;
        foreach ($colsItens as $title => $key) {
            if (strpos($key, 'nf_') === 0) {
                $nfKey = str_replace('nf_', '', $key);
                $val = ($nfKey === 'regime') ? strtoupper($regime) : ($nf[$nfKey] ?? '');
            } elseif (strpos($key, 'di_') === 0) {
                $val = $it['di'][str_replace('di_', '', $key)] ?? '';
            } elseif (strpos($key, 'rastro_') === 0) {
                $val = $primeiroRastro[str_replace('rastro_', '', $key)] ?? '';
            } else {
                $val = $it[$key] ?? '';
            }

            $isValCol = (strpos($title, 'v') === 0 && strpos($title, 'vBC') === false) || strpos($title, 'BC') !== false
                        || in_array($key, ['v_prod', 'v_desc', 'v_un_com', 'vUnTrib', 'di_vAFRMM', 'bcIBSCBS', 'vIBSUF', 'vIBSMun', 'vIBS', 'vCBS', 'bcII', 'vDespAduII', 'vII', 'vIOFII']);
            $isPctCol = strpos($title, 'p') === 0 || strpos($title, '%') !== false;

            if (is_numeric($val) && $val !== '' && $isValCol) {
                $sheet2->setCellValueExplicit([$c, $rowNum], $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet2->getStyle([$c, $rowNum])->getNumberFormat()->setFormatCode('"R$ "#,##0.00_-');
            } elseif (is_numeric($val) && $val !== '' && $isPctCol) {
                $v = floatval($val) > 1 ? floatval($val) / 100 : floatval($val);
                $sheet2->setCellValueExplicit([$c, $rowNum], $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet2->getStyle([$c, $rowNum])->getNumberFormat()->setFormatCode('0.00%');
            } else {
                $sheet2->setCellValueExplicit([$c, $rowNum], $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $c++;
        }
        if ($rowNum % 2 !== 0) $sheet2->getStyle([1, $rowNum, count($colsItens), $rowNum])->applyFromArray($linhaImparStyle);
        $rowNum++;
    }
}
$sheet2->freezePane('A2');

/* ================================================================
   ABA 3 — Duplicatas (cobr/dup) — uma linha por parcela
================================================================ */
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Duplicatas (Cobranças)');

$colsDup = [
    'Tipo NF-e'   => 'tipo',
    'Chave NF-e'  => 'chNFe',
    'Número NF'   => 'nNF',
    'Data Emissão' => 'dhEmi',
    'Emitente'    => 'nome_emit',
    'Destinatário' => 'nome_dest',
    'Fat nFat'    => 'fat_nFat',
    'Fat vOrig'   => 'fat_vOrig',
    'Fat vDesc'   => 'fat_vDesc',
    'Fat vLiq'    => 'fat_vLiq',
    'Dup nDup'    => 'dup_nDup',
    'Dup dVenc'   => 'dup_dVenc',
    'Dup vDup'    => 'dup_vDup'
];

$colIndex = 1;
foreach ($colsDup as $title => $key) {
    $sheet3->setCellValue([$colIndex, 1], $title);
    $colIndex++;
}
$sheet3->getStyle([1, 1, count($colsDup), 1])->applyFromArray($saidaHeaderStyle);

$rowNum = 2;
$hasAnyCobr = false;
foreach ($nfs as $nf) {
    $dups = $nf['cobr_dups'] ?? [];
    if (empty($dups)) {
        // NF sem duplicatas — ainda mostra fatura se existir
        if (!empty($nf['cobr_fat'])) {
            $hasAnyCobr = true;
            $c = 1;
            foreach ($colsDup as $title => $key) {
                $val = match($key) {
                    'tipo'      => $nf['tipo']            ?? '',
                    'chNFe'     => $nf['chNFe']           ?? '',
                    'nNF'       => $nf['nNF']             ?? '',
                    'dhEmi'     => $nf['dhEmi']           ?? '',
                    'nome_emit' => $nf['nome_emit']        ?? '',
                    'nome_dest' => $nf['nome_dest']        ?? '',
                    'fat_nFat'  => $nf['cobr_fat']['nFat']  ?? '',
                    'fat_vOrig' => $nf['cobr_fat']['vOrig'] ?? '',
                    'fat_vDesc' => $nf['cobr_fat']['vDesc'] ?? '',
                    'fat_vLiq'  => $nf['cobr_fat']['vLiq']  ?? '',
                    'dup_nDup'  => '',
                    'dup_dVenc' => '',
                    'dup_vDup'  => '',
                    default     => ''
                };
                $isVal = in_array($key, ['fat_vOrig', 'fat_vDesc', 'fat_vLiq', 'dup_vDup']);
                if ($isVal && is_numeric($val) && $val !== '') {
                    $sheet3->setCellValueExplicit([$c, $rowNum], $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $sheet3->getStyle([$c, $rowNum])->getNumberFormat()->setFormatCode('"R$ "#,##0.00_-');
                } else {
                    $sheet3->setCellValueExplicit([$c, $rowNum], $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $c++;
            }
            if ($rowNum % 2 !== 0) $sheet3->getStyle([1, $rowNum, count($colsDup), $rowNum])->applyFromArray($linhaImparStyle);
            $rowNum++;
        }
        continue;
    }

    $hasAnyCobr = true;
    foreach ($dups as $dup) {
        $c = 1;
        foreach ($colsDup as $title => $key) {
            $val = match($key) {
                'tipo'      => $nf['tipo']              ?? '',
                'chNFe'     => $nf['chNFe']             ?? '',
                'nNF'       => $nf['nNF']               ?? '',
                'dhEmi'     => $nf['dhEmi']             ?? '',
                'nome_emit' => $nf['nome_emit']          ?? '',
                'nome_dest' => $nf['nome_dest']          ?? '',
                'fat_nFat'  => $nf['cobr_fat']['nFat']  ?? '',
                'fat_vOrig' => $nf['cobr_fat']['vOrig'] ?? '',
                'fat_vDesc' => $nf['cobr_fat']['vDesc'] ?? '',
                'fat_vLiq'  => $nf['cobr_fat']['vLiq']  ?? '',
                'dup_nDup'  => $dup['nDup']  ?? '',
                'dup_dVenc' => $dup['dVenc'] ?? '',
                'dup_vDup'  => $dup['vDup']  ?? '',
                default     => ''
            };
            $isVal = in_array($key, ['fat_vOrig', 'fat_vDesc', 'fat_vLiq', 'dup_vDup']);
            if ($isVal && is_numeric($val) && $val !== '') {
                $sheet3->setCellValueExplicit([$c, $rowNum], $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet3->getStyle([$c, $rowNum])->getNumberFormat()->setFormatCode('"R$ "#,##0.00_-');
            } else {
                $sheet3->setCellValueExplicit([$c, $rowNum], $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $c++;
        }
        if ($rowNum % 2 !== 0) $sheet3->getStyle([1, $rowNum, count($colsDup), $rowNum])->applyFromArray($linhaImparStyle);
        $rowNum++;
    }
}
$sheet3->freezePane('A2');
if (!$hasAnyCobr) {
    $sheet3->setCellValue([1, 2], 'Nenhuma duplicata encontrada nas NF-es processadas.');
}

/* ================================================================
   Auto-size colunas (all sheets)
================================================================ */
foreach ([$sheet1, $sheet2, $sheet3] as $sh) {
    foreach ($sh->getColumnIterator() as $col) {
        $sh->getColumnDimension($col->getColumnIndex())->setAutoSize(true);
    }
}

$spreadsheet->setActiveSheetIndex(0);

// Deletar arquivo temporário
if (file_exists($tempResultJson)) @unlink($tempResultJson);

// Nome do arquivo
$regimeText = ($regime === 'real') ? 'LucroReal' : 'LucroPresumido';
$tipoText   = match($tipo) { 'entrada' => 'Entrada', 'saida' => 'Saida', default => 'Todos' };
$nomeArquivo = "NFe_{$tipoText}_{$regimeText}_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
ob_clean();
$writer->save('php://output');
exit;
