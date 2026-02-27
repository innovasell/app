<?php
require_once '../db.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['batch_id']) || !isset($_FILES['sellers_csv'])) {
        throw new Exception("Dados incompletos.");
    }

    $batchId = $_POST['batch_id'];

    if ($_FILES['sellers_csv']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Erro no upload do arquivo.");
    }

    $csvFile = $_FILES['sellers_csv']['tmp_name'];
    $sellersMap = [];
    $debugLog = [];
    $rowsProcessed = 0;

    // Detect line ending and delimiter
    $content = file_get_contents($csvFile);
    $lines = preg_split('/\r\n|\r|\n/', $content);

    // Simple delimiter detection on first non-empty line
    $delimiter = ';';
    foreach ($lines as $line) {
        if (trim($line) === '')
            continue;
        if (strpos($line, ';') !== false) {
            $delimiter = ';';
        } elseif (strpos($line, ',') !== false) {
            $delimiter = ',';
        } elseif (strpos($line, "\t") !== false) {
            $delimiter = "\t";
        }
        break;
    }

    $debugLog[] = "Delimiter detected: '$delimiter'";

    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        // We will NOT strictly skip header check. We'll try to guess if it's a header or data.
        // But for safety, let's assume if the first column is not numeric, it might be a header.

        $rowIdx = 0;
        while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            $rowIdx++;
            if (count($data) < 2)
                continue;

            $col0 = trim($data[0]); // NFE
            $col1 = trim($data[1]); // Seller

            // Handle encoding issues (fix for J?ssica -> Jéssica)
            $col1 = mb_convert_encoding($col1, 'UTF-8', 'ISO-8859-1, UTF-8');
            // NFE is usually numeric but no harm ensuring it's not messed up if it has weird chars
            $col0 = mb_convert_encoding($col0, 'UTF-8', 'ISO-8859-1, UTF-8');

            // Skip potential headers or empty rows
            if (empty($col0) || empty($col1))
                continue;
            if ($rowIdx == 1 && !is_numeric($col0)) {
                $debugLog[] = "Skipping header row: $col0 | $col1";
                continue;
            }

            $sellersMap[$col0] = $col1;
            $rowsProcessed++;
        }
        fclose($handle);
    }

    if (empty($sellersMap)) {
        throw new Exception("Arquivo CSV vazio ou formato incorreto (Delimitador: $delimiter). Processed $rowsProcessed rows.");
    }

    // Update items in batch
    $updatedCount = 0;
    $stmtUpdate = $pdo->prepare("UPDATE com_imported_items SET seller_name = :seller WHERE batch_id = :batch AND nfe_number = :nfe");

    foreach ($sellersMap as $nfe => $seller) {
        // Try exact match
        $stmtUpdate->execute([':seller' => $seller, ':batch' => $batchId, ':nfe' => $nfe]);
        $rows = $stmtUpdate->rowCount();

        // If not found, try padded (leading zeros)
        if ($rows == 0) {
            // Assuming NFE might be '22750' and DB has '00000022750' or vice versa? 
            // Usually XML NFE is 9 digits? 
            $padded9 = str_pad($nfe, 9, '0', STR_PAD_LEFT);
            $padded6 = str_pad($nfe, 6, '0', STR_PAD_LEFT);

            if ($padded9 !== $nfe) {
                $stmtUpdate->execute([':seller' => $seller, ':batch' => $batchId, ':nfe' => $padded9]);
                $rows += $stmtUpdate->rowCount();
            }
            if ($rows == 0 && $padded6 !== $nfe) {
                $stmtUpdate->execute([':seller' => $seller, ':batch' => $batchId, ':nfe' => $padded6]);
                $rows += $stmtUpdate->rowCount();
            }
        }
        $updatedCount += $rows;
    }

    echo json_encode([
        'success' => true,
        'message' => "Processado: $rowsProcessed linhas. Atualizados: $updatedCount registros.",
        'updated_count' => $updatedCount,
        'debug' => $debugLog
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
