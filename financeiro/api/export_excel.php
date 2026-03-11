<?php
// Exige que o autoloader instanciado pelo Composer aponte para nossa pasta vendor na raiz `app/`
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

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

if (!$uniqid) {
    die("ID da sessão de exportação não fornecido.");
}

$tempResultJson = sys_get_temp_dir() . "/nfe_resultado_{$uniqid}.json";

if (!file_exists($tempResultJson)) {
    die("Sessão expirada, arquivo não encontrado ou exportação já realizada. Por favor, processe novamente o arquivo.");
}

$dataStr = file_get_contents($tempResultJson);
$data = json_decode($dataStr, true);

if (!$data || empty($data['nfs'])) {
    die("Nenhum dado válido para exportar.");
}

$regime = $data['regime'] ?? 'presumido';
$nfs = $data['nfs'];

// Cria Planilha
$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
$spreadsheet->removeSheetByIndex(0);

/* =========================================================================
   Aba 1: Resumo por NF
========================================================================= */
$sheet1 = $spreadsheet->createSheet();
$sheet1->setTitle('Resumo por NF');

$headers1 = [
    'A1' => 'Chave NF-e', 'B1' => 'Número NF', 'C1' => 'Data Emissão',
    'D1' => 'CNPJ Emitente', 'E1' => 'Nome Emitente', 'F1' => 'CNPJ Destinatário', 'G1' => 'Nome Destinatário',
    'H1' => 'Qtd Itens', 'I1' => 'Total Produtos (R$)', 'J1' => 'Total Desconto (R$)',
    'K1' => 'Total Frete (R$)', 'L1' => 'Total ICMS (R$)', 'M1' => 'Total IPI (R$)',
    'N1' => 'Total PIS (R$)', 'O1' => 'Total COFINS (R$)', 'P1' => 'Total NF (R$)',
    'Q1' => 'Regime Tributário'
];

// Estilo Header Padrão
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF0A1E42'] // #0a1e42
    ],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER]
];
$totalStyle = [
    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF40883C'] // #40883c
    ],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
];
$linhaImparStyle = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F4F8']]];

foreach ($headers1 as $cell => $val) {
    $sheet1->setCellValue($cell, $val);
}
$sheet1->getStyle('A1:Q1')->applyFromArray($headerStyle);
$sheet1->getRowDimension(1)->setRowHeight(25);

$row1 = 2;
// Acumuladores Coluna
$sumProd = $sumDesc = $sumFrete = $sumIcms = $sumIpi = $sumPis = $sumCofins = $sumNf = 0;

foreach ($nfs as $nf) {
    $sheet1->setCellValue("A{$row1}", $nf['chNFe']);
    $sheet1->setCellValueExplicit("B{$row1}", $nf['nNF'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet1->setCellValue("C{$row1}", $nf['dhEmi']); // ideal format date in excel
    $sheet1->setCellValueExplicit("D{$row1}", $nf['cnpj_emit'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet1->setCellValue("E{$row1}", $nf['nome_emit']);
    $sheet1->setCellValueExplicit("F{$row1}", $nf['cnpj_dest'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet1->setCellValue("G{$row1}", $nf['nome_dest']);
    $sheet1->setCellValue("H{$row1}", $nf['qtd_itens']);
    
    $sheet1->setCellValue("I{$row1}", $nf['v_prod']);
    $sheet1->setCellValue("J{$row1}", $nf['v_desc']);
    $sheet1->setCellValue("K{$row1}", $nf['v_frete']);
    $sheet1->setCellValue("L{$row1}", $nf['v_icms']);
    $sheet1->setCellValue("M{$row1}", $nf['v_ipi']);
    $sheet1->setCellValue("N{$row1}", $nf['v_pis']);
    $sheet1->setCellValue("O{$row1}", $nf['v_cofins']);
    $sheet1->setCellValue("P{$row1}", $nf['v_nf']);
    $sheet1->setCellValue("Q{$row1}", strtoupper($regime));

    // Formatação de Moedas
    $sheet1->getStyle("I{$row1}:P{$row1}")->getNumberFormat()->setFormatCode('"R$ "#,##0.00_-');

    if ($row1 % 2 !== 0) {
        $sheet1->getStyle("A{$row1}:Q{$row1}")->applyFromArray($linhaImparStyle);
    }

    $sumProd += $nf['v_prod']; 
    $sumDesc += $nf['v_desc']; 
    $sumFrete += $nf['v_frete'];
    $sumIcms += $nf['v_icms'];
    $sumIpi += $nf['v_ipi'];
    $sumPis += $nf['v_pis'];
    $sumCofins += $nf['v_cofins'];
    $sumNf += $nf['v_nf'];
    
    $row1++;
}

// Totais da Aba 1
$sheet1->setCellValue("A{$row1}", "TOTAIS");
$sheet1->mergeCells("A{$row1}:H{$row1}");
$sheet1->getStyle("A{$row1}:H{$row1}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$sheet1->setCellValue("I{$row1}", $sumProd);
$sheet1->setCellValue("J{$row1}", $sumDesc);
$sheet1->setCellValue("K{$row1}", $sumFrete);
$sheet1->setCellValue("L{$row1}", $sumIcms);
$sheet1->setCellValue("M{$row1}", $sumIpi);
$sheet1->setCellValue("N{$row1}", $sumPis);
$sheet1->setCellValue("O{$row1}", $sumCofins);
$sheet1->setCellValue("P{$row1}", $sumNf);
$sheet1->getStyle("I{$row1}:P{$row1}")->getNumberFormat()->setFormatCode('"R$ "#,##0.00_-');
$sheet1->getStyle("A{$row1}:Q{$row1}")->applyFromArray($totalStyle);
$sheet1->getRowDimension($row1)->setRowHeight(25);

// Auto-Size Aba 1
foreach (range('A','Q') as $col) {
    if(!in_array($col, ['E','G'])) {
        $sheet1->getColumnDimension($col)->setAutoSize(true);
    } else {
        $sheet1->getColumnDimension($col)->setWidth(35);
    }
}
$sheet1->freezePane('A2');

/* =========================================================================
   Aba 2: Detalhe por Produto
========================================================================= */
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Detalhe por Produto');

$headers2 = [
    'A1' => 'Nº NF', 'B1' => 'Data Emissão', 'C1' => 'CNPJ Emitente', 'D1' => 'Nome Emitente',
    'E1' => 'Nº Item', 'F1' => 'Código Prod.', 'G1' => 'Descrição', 'H1' => 'NCM', 'I1' => 'CFOP',
    'J1' => 'Unidade', 'K1' => 'Qtd', 'L1' => 'Valor Unit. (R$)', 'M1' => 'Valor Prod. (R$)',
    'N1' => 'Desconto (R$)', 'O1' => 'BC PIS/COFINS (R$)', 'P1' => '% PIS', 'Q1' => 'Valor PIS (R$)',
    'R1' => '% COFINS', 'S1' => 'Valor COFINS (R$)', 'T1' => 'BC ICMS (R$)', 'U1' => '% ICMS',
    'V1' => 'Valor ICMS (R$)', 'W1' => 'BC IPI (R$)', 'X1' => '% IPI', 'Y1' => 'Valor IPI (R$)',
    'Z1' => 'Regime Tributário'
];

foreach ($headers2 as $cell => $val) {
    $sheet2->setCellValue($cell, $val);
}
$sheet2->getStyle('A1:Z1')->applyFromArray($headerStyle);
$sheet2->getRowDimension(1)->setRowHeight(25);

$row2 = 2;
// Acumuladores Column Produtos
$sProdVal = $sDesc = $sVpis = $sVcofins = $sVicms = $sVipi = 0;

foreach ($nfs as $nf) {
    foreach ($nf['itens'] as $it) {
        $sheet2->setCellValueExplicit("A{$row2}", $nf['nNF'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet2->setCellValue("B{$row2}", $nf['dhEmi']);
        $sheet2->setCellValueExplicit("C{$row2}", $nf['cnpj_emit'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet2->setCellValue("D{$row2}", $nf['nome_emit']);
        
        $sheet2->setCellValue("E{$row2}", $it['n_item']);
        $sheet2->setCellValueExplicit("F{$row2}", $it['c_prod'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet2->setCellValue("G{$row2}", $it['x_prod']);
        $sheet2->setCellValueExplicit("H{$row2}", $it['ncm'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet2->setCellValueExplicit("I{$row2}", $it['cfop'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        
        $sheet2->setCellValue("J{$row2}", $it['u_com']);
        $sheet2->setCellValue("K{$row2}", $it['q_com']);
        $sheet2->setCellValue("L{$row2}", $it['v_un_com']);
        $sheet2->setCellValue("M{$row2}", $it['v_prod']);
        $sheet2->setCellValue("N{$row2}", $it['v_desc']);
        
        $sheet2->setCellValue("O{$row2}", $it['bc_pis_cofins']);
        $sheet2->setCellValue("P{$row2}", $it['p_pis'] / 100);
        $sheet2->setCellValue("Q{$row2}", $it['v_pis']);
        $sheet2->setCellValue("R{$row2}", $it['p_cofins'] / 100);
        $sheet2->setCellValue("S{$row2}", $it['v_cofins']);
        
        $sheet2->setCellValue("T{$row2}", $it['bc_icms']);
        $sheet2->setCellValue("U{$row2}", $it['p_icms'] / 100);
        $sheet2->setCellValue("V{$row2}", $it['v_icms']);
        
        $sheet2->setCellValue("W{$row2}", $it['bc_ipi']);
        $sheet2->setCellValue("X{$row2}", $it['p_ipi'] / 100);
        $sheet2->setCellValue("Y{$row2}", $it['v_ipi']);
        
        $sheet2->setCellValue("Z{$row2}", strtoupper($regime));

        // Forms and Styles
        $sheet2->getStyle("K{$row2}:K{$row2}")->getNumberFormat()->setFormatCode('#,##0.0000');
        $sheet2->getStyle("L{$row2}:Y{$row2}")->getNumberFormat()->setFormatCode('"R$ "#,##0.00_-');
        
        // Percentagens (P, R, U, X)
        $sheet2->getStyle("P{$row2}")->getNumberFormat()->setFormatCode('0.00%');
        $sheet2->getStyle("R{$row2}")->getNumberFormat()->setFormatCode('0.00%');
        $sheet2->getStyle("U{$row2}")->getNumberFormat()->setFormatCode('0.00%');
        $sheet2->getStyle("X{$row2}")->getNumberFormat()->setFormatCode('0.00%');

        if ($row2 % 2 !== 0) {
            $sheet2->getStyle("A{$row2}:Z{$row2}")->applyFromArray($linhaImparStyle);
        }

        $sProdVal += $it['v_prod'];
        $sDesc += $it['v_desc'];
        $sVpis += $it['v_pis'];
        $sVcofins += $it['v_cofins'];
        $sVicms += $it['v_icms'];
        $sVipi += $it['v_ipi'];

        $row2++;
    }
}

// Totais da Aba 2
$sheet2->setCellValue("A{$row2}", "TOTAIS GERAIS DOS ITENS");
$sheet2->mergeCells("A{$row2}:L{$row2}");
$sheet2->getStyle("A{$row2}:L{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$sheet2->setCellValue("M{$row2}", $sProdVal);
$sheet2->setCellValue("N{$row2}", $sDesc);
$sheet2->setCellValue("Q{$row2}", $sVpis);
$sheet2->setCellValue("S{$row2}", $sVcofins);
$sheet2->setCellValue("V{$row2}", $sVicms);
$sheet2->setCellValue("Y{$row2}", $sVipi);

$sheet2->getStyle("M{$row2}:Y{$row2}")->getNumberFormat()->setFormatCode('"R$ "#,##0.00_-');
$sheet2->getStyle("A{$row2}:Z{$row2}")->applyFromArray($totalStyle);
$sheet2->getRowDimension($row2)->setRowHeight(25);

// Auto-Size Aba 2
foreach (range('A','Z') as $col) {
    if($col === 'G') {
        $sheet2->getColumnDimension($col)->setWidth(45);
    } else {
        $sheet2->getColumnDimension($col)->setAutoSize(true);
    }
}
$sheet2->freezePane('A2');
$spreadsheet->setActiveSheetIndex(0);

// Apagar o arquivo provisório /tmp do Servidor (Evitando leak de inode local)
unlink($tempResultJson);

/* =========================================================================
   Geração para Download Output Browser
========================================================================= */
$regimeText = ($regime === 'real') ? 'LucroReal' : 'LucroPresumido';
$nomeArquivo = "NFe_{$regimeText}_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);

ob_clean();
$writer->save('php://output');
exit;
