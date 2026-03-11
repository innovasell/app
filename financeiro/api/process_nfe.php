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

if (!isset($_FILES['arquivo_zip'])) {
    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado.']);
    exit;
}

$file = $_FILES['arquivo_zip'];
$extensoes_permitidas = ['zip'];
$extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($extensao, $extensoes_permitidas)) {
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
    // Pode estar dentro de uma pasta
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

// 4. Detecção de Canceladas Primeiro
$chaves_canceladas = [];
foreach ($files as $arquivo) {
    libxml_use_internal_errors(true);
    $xmlContent = file_get_contents($arquivo);
    $xml = simplexml_load_string($xmlContent);
    
    if ($xml !== false && isset($xml->retEvento)) {
        if ((string)$xml->retEvento->infEvento->tpEvento === '110111') {
            $chCancelada = (string)$xml->retEvento->infEvento->chNFe;
            if(!empty($chCancelada)){
                $chaves_canceladas[] = $chCancelada;
            }
        }
    }
}

// Puxar as chaves que já estão no BD para não duplicar, base neste Lote
// O usuário pode re-enviar NFs em Lotes diferentes sem sobrescrever no banco pra esse modulo, 
// a duplicação se recusa se já existir no banco `fin_notas` global
$stmt = $pdo->query("SELECT chNFe FROM fin_notas");
$chaves_bd = $stmt->fetchAll(PDO::FETCH_COLUMN);

// O Arrays locais desta sessão XML pra ir para Excel
$nfe_resultados = [];
$dbLoteId = null;

// Crio o lote se ao menos 1 arquivo veio
$stmtLote = $pdo->prepare("INSERT INTO fin_lotes (regime, total_nfs) VALUES (?, 0)");
$stmtLote->execute([$regime]);
$dbLoteId = $pdo->lastInsertId();

$qts_inseridas_db = 0;

// 5. Loop de Processamento Real
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

    // Se for Evento, pula
    if (isset($xml->evento) || isset($xml->retEvento) || empty($nfeNode)) {
        continue;
    }

    // Pega o nó padrao infNFe
    $infNFe = $nfeNode[0]->infNFe;
    $chNFe = str_replace('NFe', '', (string)$infNFe->attributes()['Id']);

    if (in_array($chNFe, $chaves_canceladas)) {
        $stats['ignoradas_canceladas']++;
        $stats['log'][] = ['tipo' => 'ignorada', 'msg' => "NF $chNFe ignorada — cancelada"];
        continue;
    }

    if (in_array($chNFe, $chaves_bd)) {
        $stats['ignoradas_duplicadas']++;
        $stats['log'][] = ['tipo' => 'ignorada', 'msg' => "NF $chNFe ignorada — já existente no banco"];
        continue;
    }

    // Extraction Dados NF
    $nNF   = (string) $infNFe->ide->nNF;
    $dhEmi = substr((string) $infNFe->ide->dhEmi, 0, 10); // YYYY-MM-DD
    $tpNF  = (string) $infNFe->ide->tpNF; // 0=Entrada, 1=Saída

    // Emitente
    $cnpjEmit = (string) $infNFe->emit->CNPJ;
    $nomeEmit = (string) $infNFe->emit->xNome;

    // Destinatário
    $cnpjDest = (string) ($infNFe->dest->CNPJ ?? $infNFe->dest->CPF ?? '');
    $nomeDest = (string) $infNFe->dest->xNome;

    // Totais
    $tot = $infNFe->total->ICMSTot;
    $vProdNF  = (float) ($tot->vProd  ?? 0);
    $vDescNF  = (float) ($tot->vDesc  ?? 0);
    $vFreteNF = (float) ($tot->vFrete ?? 0);
    $vNF      = (float) ($tot->vNF    ?? 0);

    // Variáveis Acumuladoras Globais (caso não exista nodo ICMSTot)
    $totPIS = $totCOFINS = $totICMS = $totIPI = 0;
    $itens_db = [];

    // Loop de Itens Detalhe
    foreach ($infNFe->det as $det) {
        $nItem  = (int)    $det->attributes()['nItem'];
        $cProd  = (string) $det->prod->cProd;
        $xProd  = (string) $det->prod->xProd;
        $NCM    = (string) $det->prod->NCM;
        $CFOP   = (string) $det->prod->CFOP;
        $uCom   = (string) $det->prod->uCom;
        $qCom   = (float)  ($det->prod->qCom   ?? 0);
        $vUnCom = (float)  ($det->prod->vUnCom ?? 0); 
        $vProd  = (float)  ($det->prod->vProd  ?? 0);
        $vDesc  = (float)  ($det->prod->vDesc  ?? 0);

        // ICMS
        $bcICMS = 0; $pICMS = 0; $vICMS = 0;
        if (isset($det->imposto->ICMS)) {
            foreach ($det->imposto->ICMS->children() as $grupo) {
                $bcICMS = (float) ($grupo->vBC   ?? $bcICMS);
                $pICMS  = (float) ($grupo->pICMS ?? $pICMS);
                $vICMS  = (float) ($grupo->vICMS ?? $vICMS);
            }
        }

        // IPI
        $bcIPI = 0; $pIPI = 0; $vIPI = 0;
        if (isset($det->imposto->IPI->IPITrib)) {
            $bcIPI = (float) ($det->imposto->IPI->IPITrib->vBC  ?? 0);
            $pIPI  = (float) ($det->imposto->IPI->IPITrib->pIPI ?? 0);
            $vIPI  = (float) ($det->imposto->IPI->IPITrib->vIPI ?? 0);
        }

        // PIS / COFINS
        $bcPisCofins = $vProd - $vDesc;
        [$vPIS, $pPIS, $vCOFINS, $pCOFINS] = calcularPisCofins($bcPisCofins, $regime);

        $totPIS    += $vPIS;
        $totCOFINS += $vCOFINS;
        $totICMS   += $vICMS;
        $totIPI    += $vIPI;

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
        ];
    }

    // Salvar NF Array Pro Retorno Front e Excel Temporario
    $nfStruct = [
        "chNFe"      => $chNFe,
        "nNF"        => $nNF,
        "dhEmi"      => $dhEmi,
        "cnpj_emit"  => $cnpjEmit,
        "nome_emit"  => $nomeEmit,
        "cnpj_dest"  => $cnpjDest,
        "nome_dest"  => $nomeDest,
        "qtd_itens"  => count($itens_db),
        "v_prod"     => $vProdNF,
        "v_desc"     => $vDescNF,
        "v_frete"    => $vFreteNF,
        "v_icms"     => $totICMS,
        "v_ipi"      => $totIPI,
        "v_pis"      => $totPIS,
        "v_cofins"   => $totCOFINS,
        "v_nf"       => $vNF,
        "itens"      => $itens_db
    ];
    
    $nfe_resultados[] = $nfStruct;
    $stats['nfs'][] = $nfStruct; // pra devolver pro js renderizar

    // Inserção Banco DB
    try {
        $pdo->beginTransaction();
        
        $sqlNF = "INSERT INTO fin_notas (lote_id, chNFe, nNF, dhEmi, cnpj_emit, nome_emit, cnpj_dest, nome_dest, v_prod, v_desc, v_frete, v_icms, v_ipi, v_pis, v_cofins, v_nf) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtNF = $pdo->prepare($sqlNF);
        $stmtNF->execute([
            $dbLoteId, $chNFe, $nNF, $dhEmi, $cnpjEmit, $nomeEmit, $cnpjDest, $nomeDest,
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
        $stats['log'][] = ['tipo' => 'ok', 'msg' => "NF $nNF processada — ".count($itens_db)." itens"];

    } catch (Exception $e) {
        $pdo->rollBack();
        $stats['erros']++;
        $stats['log'][] = ['tipo' => 'erro', 'msg' => "Erro de Banco de Dados ao transacionar o XML para NF $nNF."];
    }
}

// Update DB Lote total
if ($dbLoteId && $qts_inseridas_db > 0) {
    $pdo->query("UPDATE fin_lotes SET total_nfs = $qts_inseridas_db WHERE id = $dbLoteId");
} else if ($dbLoteId && $qts_inseridas_db === 0) {
    // Apago Lote se nenhuma nota aproveitou
    $pdo->query("DELETE FROM fin_lotes WHERE id = $dbLoteId");
}

// Slvar Array de Exportação num JSON temp na maquina Local (Hostinger)
$tempResultJson = sys_get_temp_dir() . "/nfe_resultado_{$stats['session_id']}.json";
$fp = fopen($tempResultJson, 'w');
fwrite($fp, json_encode([
    'regime' => $regime,
    'nfs' => $nfe_resultados
]));
fclose($fp);

// Limpeza de ZIP extraidos
$tempfiles = glob($zipDir . '/*');
foreach($tempfiles as $f){
    if(is_file($f)) {
        unlink($f);
    }
}
rmdir($zipDir);

ob_clean(); // Clean any accidental PHP Warnings
echo json_encode([
    'success' => true,
    'regime' => $regime,
    'total_arquivos' => count($files),
    'processadas' => $stats['processadas'],
    'erros' => $stats['erros'],
    'ignoradas_canceladas' => $stats['ignoradas_canceladas'],
    'ignoradas_duplicadas' => $stats['ignoradas_duplicadas'],
    'log' => $stats['log'],
    'session_id' => $stats['session_id'],
    'nfs' => $stats['nfs']
]);
exit;
