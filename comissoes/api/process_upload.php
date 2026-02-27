<?php
require_once '../db.php';

header('Content-Type: application/json');

// Ensure upload directory exists
$uploadDir = __DIR__ . '/../temp_uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Function to fetch PTAX range
function fetchPtaxRange($start, $end)
{
    // Format: MM-DD-YYYY
    $startFmt = DateTime::createFromFormat('Y-m-d', $start)->format('m-d-Y');
    $endFmt = DateTime::createFromFormat('Y-m-d', $end)->format('m-d-Y');

    $url = "https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoDolarPeriodo(dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?@dataInicial='{$startFmt}'&@dataFinalCotacao='{$endFmt}'&\$top=100&\$format=json";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For compatibility
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $rates = [];

    if (isset($data['value']) && is_array($data['value'])) {
        foreach ($data['value'] as $item) {
            // "dataHoraCotacao": "2023-10-01 13:11:00.0"
            $datePart = substr($item['dataHoraCotacao'], 0, 10);
            $rates[$datePart] = [
                'compra' => $item['cotacaoCompra'],
                'venda' => $item['cotacaoVenda']
            ];
        }
    }
    return $rates;
}

// Function to normalize packaging
function normalizePackaging($rawString)
{
    $rawString = strtoupper($rawString);
    $normalized = null;

    // Regex for: Number + Space? + Unit
    if (preg_match('/(\d+[\.,]?\d*)\s*(KG|GR|G|L|LT|ML|DZ|UN)/', $rawString, $matches)) {
        $value = (float) str_replace(',', '.', $matches[1]); // Handle comma decimal
        $unit = $matches[2];

        // Conversion logic
        switch ($unit) {
            case 'KG':
            case 'L':
            case 'LT':
            case 'DZ':
            case 'UN':
                $normalized = $value; // Assume base unit
                break;
            case 'GR':
            case 'G':
            case 'ML':
                $normalized = $value / 1000; // Convert to Base (KG/L)
                break;
        }
    }
    return $normalized;
}

try {
    if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Falha no upload do arquivo.");
    }

    $zipFile = $_FILES['zip_file']['tmp_name'];
    $zip = new ZipArchive;

    if ($zip->open($zipFile) !== TRUE) {
        throw new Exception("Não foi possível abrir o arquivo ZIP.");
    }

    // Get Active Sales CFOPs
    $stmtCfop = $pdo->query("SELECT cfop FROM com_cfop_rules WHERE is_active = 1");
    $salesCfops = $stmtCfop->fetchAll(PDO::FETCH_COLUMN);
    $validCfops = $salesCfops ? array_flip($salesCfops) : [];

    // Process Sellers CSV if uploaded
    $sellersMap = [];
    if (isset($_FILES['sellers_csv']) && $_FILES['sellers_csv']['error'] === UPLOAD_ERR_OK) {
        $csvFile = $_FILES['sellers_csv']['tmp_name'];
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ";"); // Skip header
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                // Assoc NF (0) -> Seller (1)
                // Normalize NF (remove logic leading zeros if needed, but usually exact match)
                if (isset($data[0]) && isset($data[1])) {
                    $sellersMap[trim($data[0])] = trim($data[1]);
                }
            }
            fclose($handle);
        }
    }

    $processedCount = 0;
    $importedCount = 0;
    $ignoredCount = 0;

    // Generate Batch ID (YmdHis)
    $batchId = date('YmdHis');

    // Generate Batch ID (YmdHis)
    $batchId = date('YmdHis');

    // --- PTAX LOGIC START ---

    // 1. Scan XMLs to get Date Range
    $minDate = null;
    $maxDate = null;

    // We need to iterate ZIP twice? Or store files in temp?
    // ZipArchive doesn't support easy re-iteration without close/open or index access.
    // We already loop by index later. Let's do a quick pre-scan.

    $parsedInfo = []; // Store basic info to avoid re-parsing logic if possible, or just date

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xml')
            continue;

        // Fast read date
        $xmlContent = $zip->getFromIndex($i);
        // Regex is faster than simplexml for just one field
        if (preg_match('/<dhEmi>(.*?)<\/dhEmi>/', $xmlContent, $m) || preg_match('/<dEmi>(.*?)<\/dEmi>/', $xmlContent, $m)) {
            $d = substr($m[1], 0, 10);
            if ($d) {
                if (!$minDate || $d < $minDate)
                    $minDate = $d;
                if (!$maxDate || $d > $maxDate)
                    $maxDate = $d;
            }
        }
    }

    $ptaxCache = [];

    if ($minDate && $maxDate) {
        // 2. Check DB for existing rates
        $stmtRates = $pdo->prepare("SELECT data_cotacao, cotacao_venda FROM fin_ptax_rates WHERE data_cotacao BETWEEN :start AND :end");
        $stmtRates->execute([':start' => $minDate, ':end' => $maxDate]);
        $rows = $stmtRates->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $ptaxCache[$r['data_cotacao']] = (float) $r['cotacao_venda'];
        }

        // 3. Find missing dates (Naive: just fetch range if any missing? Or fetch whole range from API if not complete?)
        // Easiest: Fetch whole range from API if we have gaps, or just fetch everything from API for the range to fill DB.
        // Let's simplified: Fetch range from API, Upsert to DB.

        $apiRates = fetchPtaxRange($minDate, $maxDate);

        if (!empty($apiRates)) {
            $stmtUpsert = $pdo->prepare("INSERT INTO fin_ptax_rates (data_cotacao, cotacao_compra, cotacao_venda) VALUES (:d, :c, :v) ON DUPLICATE KEY UPDATE cotacao_compra=:c, cotacao_venda=:v");

            foreach ($apiRates as $dateStr => $vals) {
                // Formatting date to Y-m-d
                $gameDate = date('Y-m-d', strtotime($dateStr));

                $stmtUpsert->execute([
                    ':d' => $gameDate,
                    ':c' => $vals['compra'],
                    ':v' => $vals['venda']
                ]);
                $ptaxCache[$gameDate] = (float) $vals['venda'];
            }
        }
    }

    // Prepare statement for price lookup
    $stmtLookup = $pdo->prepare("SELECT preco_net_usd FROM cot_price_list 
                                 WHERE codigo LIKE :cod AND embalagem = :pkg 
                                 LIMIT 1");

    // Prepare Insert Statement ONCE
    // Added seller_name, unit_price_usd, ptax_rate
    $stmt = $pdo->prepare("INSERT INTO com_imported_items 
        (batch_id, xml_filename, nfe_number, nfe_date, cfop, product_code_original, product_code_9, product_name, packaging_extracted, packaging_validated, quantity, unit_price, total_value, cost_price, status, average_term, seller_name, unit_price_usd, ptax_rate) 
        VALUES 
        (:batch_id, :xml_file, :nfe, :date, :cfop, :code_orig, :code_9, :name, :pkg, :pkg, :qty, :unit, :total, :cost, :status, :average_term, :seller, :unit_usd, :ptax)");

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $fileinfo = pathinfo($filename);

        if (strtolower($fileinfo['extension']) === 'xml') {
            $processedCount++;
            $xmlContent = $zip->getFromIndex($i);
            $xml = @simplexml_load_string($xmlContent);

            if (!$xml)
                continue;

            $ns = $xml->getNamespaces(true);
            $xml->registerXPathNamespace('nfe', $ns[''] ?? 'http://www.portalfiscal.inf.br/nfe');

            // Extract Date
            $dhEmi = (string) ($xml->xpath('//nfe:ide/nfe:dhEmi')[0] ?? '');
            if (!$dhEmi) {
                // Fallback to dEmi for older NFe
                $dhEmi = (string) ($xml->xpath('//nfe:ide/nfe:dEmi')[0] ?? '');
            }
            $nfeDate = $dhEmi ? substr($dhEmi, 0, 10) : null;

            foreach ($xml->xpath('//nfe:det') as $det) {
                $prod = $det->prod;
                $cfop = (string) $prod->CFOP;

                if (isset($validCfops[$cfop])) {
                    $nfeNumber = (string) $xml->xpath('//nfe:ide/nfe:nNF')[0] ?? '';
                    $prodCode = (string) $prod->cProd;
                    $prodName = (string) $prod->xProd;
                    $qty = (float) $prod->qCom;
                    $unitPrice = (float) $prod->vUnCom;
                    $totalValue = (float) $prod->vProd;

                    $code9 = substr($prodCode, 0, 9);

                    // Logic: Extraction & Normalization
                    $packagingDisplay = '';

                    if (preg_match_all('/\(([^)]*?)\)/', $prodName, $matches)) {
                        foreach (array_reverse($matches[1]) as $content) {
                            $upper = strtoupper($content);
                            if (preg_match('/(\d+[\.,]?\d*)\s*(KG|GR|G|L|LT|ML|DZ|UN)/', $upper)) {
                                $normalizedVal = normalizePackaging($content);
                                if ($normalizedVal !== null) {
                                    $packagingDisplay = (string) $normalizedVal;
                                }
                                break;
                            }
                        }
                    }

                    // Extract Duplicates and Calculate Weighted Average Term (PM)
                    $averageTerm = 0;

                    if ($nfeDate) {
                        try {
                            $emissionDate = new DateTime(substr($nfeDate, 0, 10));

                            // Try finding dups with multiple strategies
                            // 1. Standard with namespace
                            $dups = $xml->xpath('//nfe:dup');
                            // 2. Without namespace (rare but possible depending on parser)
                            if (empty($dups)) {
                                $dups = $xml->xpath('//dup');
                            }
                            // 3. Ignore namespace using local-name()
                            if (empty($dups)) {
                                $dups = $xml->xpath('//*[local-name()="dup"]');
                            }

                            $weightedSum = 0;
                            $totalDupValue = 0;

                            foreach ($dups as $dup) {
                                $dueDateStr = (string) $dup->dVenc;
                                $dupVal = (float) $dup->vDup;

                                if ($dueDateStr && $dupVal > 0) {
                                    $dueDate = new DateTime($dueDateStr);
                                    $interval = $emissionDate->diff($dueDate);

                                    // %r%a gives signed days. If negative, treat as 0
                                    $days = (int) $interval->format('%r%a');

                                    if ($days < 0)
                                        $days = 0;

                                    $weightedSum += ($days * $dupVal);
                                    $totalDupValue += $dupVal;
                                }
                            }

                            if ($totalDupValue > 0) {
                                $averageTerm = $weightedSum / $totalDupValue;
                            }

                        } catch (Exception $e) {
                            // Date error
                        }
                    }



                    // Lookup Price
                    $costPrice = null;
                    $status = 'pending';

                    if ($packagingDisplay) {
                        $stmtLookup->execute([':cod' => $code9 . '%', ':pkg' => $packagingDisplay]);
                        $priceRow = $stmtLookup->fetch(PDO::FETCH_ASSOC);
                        if ($priceRow) {
                            $costPrice = $priceRow['preco_net_usd'];
                            $status = 'validated';
                        }
                    }

                    // Lookup seller
                    $sellerName = $sellersMap[str_pad($nfeNumber, 9, '0', STR_PAD_LEFT)] ?? ($sellersMap[$nfeNumber] ?? null);

                    // Calculate USD Unit Price
                    $ptaxUsed = $ptaxCache[$nfeDate] ?? null;
                    $unitUsd = null;

                    if ($ptaxUsed && $ptaxUsed > 0) {
                        $unitUsd = $unitPrice / $ptaxUsed;
                    }

                    // Execute Insert
                    $stmt->execute([
                        ':batch_id' => $batchId,
                        ':xml_file' => $filename,
                        ':nfe' => $nfeNumber,
                        ':date' => $nfeDate,
                        ':cfop' => $cfop,
                        ':code_orig' => $prodCode,
                        ':code_9' => $code9,
                        ':name' => $prodName,
                        ':pkg' => $packagingDisplay,
                        ':qty' => $qty,
                        ':unit' => $unitPrice,
                        ':total' => $totalValue,
                        ':cost' => $costPrice,
                        ':status' => $status,
                        ':average_term' => $averageTerm,
                        ':seller' => $sellerName,
                        ':unit_usd' => $unitUsd,
                        ':ptax' => $ptaxUsed
                    ]);

                    $importedCount++;
                } else {
                    $ignoredCount++;
                }
            }
        }
    }

    $zip->close();

    echo json_encode([
        'success' => true,
        'message' => 'Upload processado com sucesso!',
        'processed_count' => $processedCount,
        'imported_count' => $importedCount,
        'ignored_count' => $ignoredCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
