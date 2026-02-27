<?php
// Versão simplificada e robusta do process_upload.php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => '', 'data' => []];

try {
    // Log para debug
    error_log("=== INICIO DO UPLOAD ===");

    // Verifica se o arquivo foi enviado
    if (!isset($_FILES['csv_file'])) {
        throw new Exception('Nenhum arquivo foi enviado (FILES não definido).');
    }

    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido (MAX_FILE_SIZE).',
            UPLOAD_ERR_PARTIAL => 'O upload do arquivo foi feito parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta o diretório temporário.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Uma extensão PHP interrompeu o upload.',
        ];
        $errorCode = $_FILES['csv_file']['error'];
        $errorMsg = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : 'Erro desconhecido no upload.';
        throw new Exception($errorMsg);
    }

    $file = $_FILES['csv_file'];
    $filePath = $file['tmp_name'];

    error_log("Arquivo recebido: " . $file['name'] . " (" . $file['size'] . " bytes)");

    // Verifica se é um arquivo CSV
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileExtension !== 'csv') {
        throw new Exception('O arquivo deve ser do tipo CSV (extensão .csv).');
    }

    // Conecta ao banco de dados
    $conn = new mysqli("localhost", "u849249951_innovasell", "Invti@169", "u849249951_innovasell", "3306");

    if ($conn->connect_error) {
        throw new Exception('Erro de conexão com o banco: ' . $conn->connect_error);
    }

    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception('Erro ao definir charset: ' . $conn->error);
    }

    error_log("Conexão com banco OK");

    // Gera batch ID
    $batchId = 'BATCH_' . date('YmdHis') . '_' . uniqid();

    error_log("Batch ID: " . $batchId);

    // Abre o arquivo
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Não foi possível abrir o arquivo CSV.');
    }

    $lineCount = 0;
    $importedCount = 0;
    $skippedCount = 0;
    $categoryCounts = [
        'Passagem Aérea' => 0,
        'Hotel' => 0,
        'Seguro' => 0,
        'Outros' => 0,
        'Não Categorizado' => 0
    ];

    // Lê header
    $header = fgetcsv($handle, 0, ';');
    if (!$header) {
        fclose($handle);
        throw new Exception('O arquivo CSV está vazio ou mal formatado.');
    }

    error_log("Header lido com " . count($header) . " colunas");

    // Prepara statement
    $sql = "INSERT INTO viagem_express_expenses (
        cod_cliente, cliente, dt_emissao, produto, total, 
        categoria_despesa, categoria_auto, batch_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        fclose($handle);
        throw new Exception('Erro ao preparar statement: ' . $conn->error);
    }

    error_log("Statement preparado");

    // Função helper para parsear data
    function parseDate($dateStr)
    {
        if (empty($dateStr))
            return null;
        $parts = explode('/', $dateStr);
        if (count($parts) === 3) {
            return sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);
        }
        return null;
    }

    // Função helper para parsear decimal
    function parseDecimal($value)
    {
        if (empty($value))
            return 0.00;
        $value = str_replace([' ', 'R$', '[', ']'], '', $value);
        if (preg_match('/^([0-9.,]+)/', $value, $matches)) {
            $value = $matches[1];
        }
        $value = str_replace(',', '.', $value);
        return floatval($value);
    }

    // Função de categorização
    function categorizarDespesa($produto)
    {
        $produto = strtoupper($produto);
        if (preg_match('/(AÉREO|AEREO|PASSAGEM|FLIGHT|VOO)/i', $produto)) {
            return 'Passagem Aérea';
        }
        if (preg_match('/(HOTEL|HOSPEDAGEM|ACCOMMODATION|HOTELARIA)/i', $produto)) {
            return 'Hotel';
        }
        if (preg_match('/(SEGURO|INSURANCE)/i', $produto)) {
            return 'Seguro';
        }
        if (!empty($produto)) {
            return 'Outros';
        }
        return 'Não Categorizado';
    }

    // Processa linhas
    while (($row = fgetcsv($handle, 0, ';')) !== FALSE) {
        $lineCount++;

        // Pula linhas vazias
        if (empty($row[0]) && empty($row[1]) && empty($row[11])) {
            $skippedCount++;
            continue;
        }

        $codCliente = $row[0] ?? '';
        $cliente = $row[1] ?? '';
        $dtEmissao = parseDate($row[2] ?? '');
        $produto = $row[11] ?? '';
        $total = parseDecimal($row[31] ?? 0);
        $categoria = categorizarDespesa($produto);
        $categoriaAuto = 1;

        $categoryCounts[$categoria]++;

        $stmt->bind_param(
            'ssssdsss',
            $codCliente,
            $cliente,
            $dtEmissao,
            $produto,
            $total,
            $categoria,
            $categoriaAuto,
            $batchId
        );

        if ($stmt->execute()) {
            $importedCount++;
        } else {
            $skippedCount++;
            error_log("Erro linha " . ($lineCount + 1) . ": " . $stmt->error);
        }
    }

    fclose($handle);
    $stmt->close();
    $conn->close();

    error_log("Processamento concluído: $importedCount importadas, $skippedCount ignoradas");

    $response['success'] = true;
    $response['message'] = "Importação concluída com sucesso!";
    $response['data'] = [
        'batch_id' => $batchId,
        'total_lines' => $lineCount,
        'imported' => $importedCount,
        'skipped' => $skippedCount,
        'categories' => $categoryCounts
    ];

} catch (Exception $e) {
    error_log("ERRO: " . $e->getMessage());
    $response['message'] = 'Erro: ' . $e->getMessage();
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>