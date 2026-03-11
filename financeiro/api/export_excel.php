<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
ini_set('display_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        ob_clean();
        header('Content-Type: text/html');
        echo "<h2>Erro Fatal Capturado:</h2>";
        echo "<pre>" . print_r($error, true) . "</pre>";
    }
});

$uniqid = $_GET['session_id'] ?? null;
if (!$uniqid) die("ID da sessão de exportação não fornecido.");

$tempResultJson = sys_get_temp_dir() . "/nfe_resultado_{$uniqid}.json";
$tempResultJson2 = "/tmp/nfe_resultado_{$uniqid}.json"; // Fallback para hostinger
if (!file_exists($tempResultJson) && file_exists($tempResultJson2)) $tempResultJson = $tempResultJson2;

if (!file_exists($tempResultJson)) {
    die("Sessão expirada, arquivo não encontrado ou exportação já realizada. Realizou o upload?");
}

$dataStr = file_get_contents($tempResultJson);
$data = json_decode($dataStr, true);

if (!$data || empty($data['nfs'])) die("Nenhum dado válido para exportar.");
$regime = $data['regime'] ?? 'presumido';
$nfs = $data['nfs'];

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
$spreadsheet->removeSheetByIndex(0);

// Estilos
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0A1E42']],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER]
];
$linhaImparStyle = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F4F8']]];

/* -----------------------------------------------------------------------------------------
   Aba 1: Capa das NF-es (Mapeamento Gigante)
----------------------------------------------------------------------------------------- */
$sheet1 = $spreadsheet->createSheet();
$sheet1->setTitle('Geral NF-es');

$colsNF = [
    'Chave NF-e' => 'chNFe', 'cUF' => 'ide_cUF', 'cNF' => 'ide_cNF', 'natOp' => 'ide_natOp', 'mod' => 'ide_mod', 'serie' => 'ide_serie', 'nNF' => 'nNF',
    'dhEmi' => 'dhEmi', 'dhSaiEnt' => 'ide_dhSaiEnt', 'tpNF' => 'ide_tpNF', 'idDest' => 'ide_idDest', 'cMunFG' => 'ide_cMunFG',
    'tpImp' => 'ide_tpImp', 'tpEmis' => 'ide_tpEmis', 'cDV' => 'ide_cDV', 'tpAmb' => 'ide_tpAmb', 'finNFe' => 'ide_finNFe',
    'indFinal' => 'ide_indFinal', 'indPres' => 'ide_indPres', 'procEmi' => 'ide_procEmi', 'verProc' => 'ide_verProc', 'NFref' => 'ide_NFref',
    
    'Emit CNPJ' => 'cnpj_emit', 'Emit xNome' => 'nome_emit', 'Emit xFant' => 'emit_xFant', 'Emit xLgr' => 'emit_xLgr', 'Emit nro' => 'emit_nro',
    'Emit xCpl' => 'emit_xCpl', 'Emit xBairro' => 'emit_xBairro', 'Emit cMun' => 'emit_cMun', 'Emit xMun' => 'emit_xMun', 'Emit UF' => 'emit_UF',
    'Emit CEP' => 'emit_CEP', 'Emit cPais' => 'emit_cPais', 'Emit xPais' => 'emit_xPais', 'Emit fone' => 'emit_fone', 'Emit IE' => 'emit_IE', 'Emit CRT' => 'emit_CRT',
    
    'Dest CNPJ/CPF' => 'cnpj_dest', 'Dest Nome' => 'nome_dest', 'Dest xLgr' => 'dest_xLgr', 'Dest nro' => 'dest_nro', 'Dest xBairro' => 'dest_xBairro',
    'Dest cMun' => 'dest_cMun', 'Dest xMun' => 'dest_xMun', 'Dest UF' => 'dest_UF', 'Dest cPais' => 'dest_cPais', 'Dest xPais' => 'dest_xPais',
    'Dest fone' => 'dest_fone', 'Dest indIEDest' => 'dest_indIEDest', 'autXML CNPJ' => 'autXML_CNPJ',
    
    'Tot vProd' => 'v_prod', 'Tot vDesc' => 'v_desc', 'Tot vFrete' => 'v_frete', 'Tot vSeg' => 'tot_vSeg', 'Tot vOutro' => 'tot_vOutro',
    'Tot vII' => 'tot_vII', 'Tot vIPI' => 'v_ipi', 'Tot vIPIDevol' => 'tot_vIPIDevol', 'Tot vPIS' => 'v_pis', 'Tot vCOFINS' => 'v_cofins',
    'Tot vICMS' => 'v_icms', 'Tot vICMSDeson' => 'tot_vICMSDeson', 'Tot vFCP' => 'tot_vFCP', 'Tot vBCST' => 'tot_vBCST', 'Tot vST' => 'tot_vST',
    'Tot vFCPST' => 'tot_vFCPST', 'Tot vFCPSTRet' => 'tot_vFCPSTRet', 'Tot vNF' => 'v_nf',
    
    'IBSCBS vBC' => 'tot_IBSCBSTot_vBCIBSCBS', 'IBSCBS vIBS' => 'tot_IBSCBSTot_vIBS', 'IBSCBS vCBS' => 'tot_IBSCBSTot_vCBS',
    
    'Transp modFrete' => 'transp_modFrete', 'Transp qVol' => 'transp_qVol', 'Transp esp' => 'transp_esp', 'Transp pesoL' => 'transp_pesoL', 'Transp pesoB' => 'transp_pesoB',
    'Pag indPag' => 'pag_indPag', 'Pag tPag' => 'pag_tPag', 'Pag vPag' => 'pag_vPag',
    
    'InfCpl' => 'infAdic_infCpl',
    'RespTec CNPJ' => 'infRespTec_CNPJ', 'RespTec xContato' => 'infRespTec_xContato', 'RespTec email' => 'infRespTec_email', 'RespTec fone' => 'infRespTec_fone',
    
    'Prot tpAmb' => 'prot_tpAmb', 'Prot verAplic' => 'prot_verAplic', 'Prot dhRecbto' => 'prot_dhRecbto', 'Prot nProt' => 'prot_nProt',
    'Prot digVal' => 'prot_digVal', 'Prot cStat' => 'prot_cStat', 'Prot xMotivo' => 'prot_xMotivo'
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
        $val = $nf[$key] ?? '';
        if (is_numeric($val) && strpos($title, 'Tot ') === 0) {
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

/* -----------------------------------------------------------------------------------------
   Aba 2: Detalhes dos Itens
----------------------------------------------------------------------------------------- */
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Detalhe por Produto');

$colsItens = [
    'Chave NF' => 'nf_chNFe', 'Número NF' => 'nf_nNF', 'Data Emissão' => 'nf_dhEmi', 'Emitente' => 'nf_nome_emit',
    'nItem' => 'n_item', 'cProd' => 'c_prod', 'cEAN' => 'cEAN', 'xProd' => 'x_prod', 'NCM' => 'ncm', 'CFOP' => 'cfop',
    'uCom' => 'u_com', 'qCom' => 'q_com', 'vUnCom' => 'v_un_com', 'vProd' => 'v_prod', 'vDesc' => 'v_desc',
    'cEANTrib' => 'cEANTrib', 'uTrib' => 'uTrib', 'qTrib' => 'qTrib', 'vUnTrib' => 'vUnTrib', 'indTot' => 'indTot',
    
    'DI nDI' => 'di_nDI', 'DI dDI' => 'di_dDI', 'DI xLocDesemb' => 'di_xLocDesemb', 'DI UFDesemb' => 'di_UFDesemb', 'DI dDesemb' => 'di_dDesemb',
    'DI tpViaTransp' => 'di_tpViaTransp', 'DI vAFRMM' => 'di_vAFRMM', 'DI tpIntermedio' => 'di_tpIntermedio', 'DI cExportador' => 'di_cExportador',
    'DI nAdicao' => 'di_nAdicao', 'DI nSeqAdic' => 'di_nSeqAdic', 'DI cFabricante' => 'di_cFabricante',
    
    'ICMS orig' => 'origICMS', 'ICMS CST' => 'cstICMS', 'ICMS modBC' => 'modBcICMS', 'ICMS vBC' => 'bc_icms', 'ICMS pICMS' => 'p_icms', 'ICMS vICMS' => 'v_icms',
    
    'IPI cEnq' => 'cEnqIPI', 'IPI CST' => 'cstIPI', 'IPI vBC' => 'bc_ipi', 'IPI pIPI' => 'p_ipi', 'IPI vIPI' => 'v_ipi',
    
    'II vBC' => 'bcII', 'II vDespAdu' => 'vDespAduII', 'II vII' => 'vII', 'II vIOF' => 'vIOFII',
    
    'PIS.Calc BC' => 'bc_pis_cofins', 'PIS.Calc %' => 'p_pis', 'PIS.Calc vPIS' => 'v_pis',
    'PIS.XML CST' => 'cstPIS_xml', 'PIS.XML vBC' => 'bcPIS_xml', 'PIS.XML pPIS' => 'pPIS_xml', 'PIS.XML vPIS' => 'vPIS_xml',
    
    'COFINS.Calc BC' => 'bc_pis_cofins', 'COFINS.Calc %' => 'p_cofins', 'COFINS.Calc vCOFINS' => 'v_cofins',
    'COFINS.XML CST' => 'cstCOFINS_xml', 'COFINS.XML vBC' => 'bcCOFINS_xml', 'COFINS.XML pCOFINS' => 'pCOFINS_xml', 'COFINS.XML vCOFINS' => 'vCOFINS_xml',
    
    'IBSCBS CST' => 'cstIBSCBS', 'IBSCBS cClassTrib' => 'cClassTribIBS', 'IBSCBS vBC' => 'bcIBSCBS', 'IBSCBS pIBSUF' => 'pIBSUF',
    'IBSCBS vIBSUF' => 'vIBSUF', 'IBSCBS pIBSMun' => 'pIBSMun', 'IBSCBS vIBSMun' => 'vIBSMun', 'IBSCBS vIBS' => 'vIBS',
    'IBSCBS pCBS' => 'pCBS', 'IBSCBS vCBS' => 'vCBS',
    'Regime Tributário do Calculo' => 'nf_regime'
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
        $c = 1;
        foreach ($colsItens as $title => $key) {
            if (strpos($key, 'nf_') === 0) {
                // Info da Capa
                $val = $nf[str_replace('nf_', '', $key)] ?? ($key === 'nf_regime' ? strtoupper($regime) : '');
            } else if (strpos($key, 'di_') === 0) {
                // Info do Array DI
                $val = $it['di'][str_replace('di_', '', $key)] ?? '';
            } else {
                // Info do Item
                $val = $it[$key] ?? '';
            }
            
            if (is_numeric($val) && (strpos($title, 'v') === 0 || strpos($title, 'BC') !== false || strpos($title, 'vProd') !== false)) {
                $sheet2->setCellValueExplicit([$c, $rowNum], $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet2->getStyle([$c, $rowNum])->getNumberFormat()->setFormatCode('"R$ "#,##0.00_-');
            } else if (is_numeric($val) && strpos($title, 'p') === 0) {
                $v = floatval($val) > 1 ? floatval($val)/100 : floatval($val);
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
$spreadsheet->setActiveSheetIndex(0);

// Deleta Session
if (file_exists($tempResultJson)) @unlink($tempResultJson);

$regimeText = ($regime === 'real') ? 'LucroReal' : 'LucroPresumido';
$nomeArquivo = "NFe_{$regimeText}_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
ob_clean();
$writer->save('php://output');
exit;
