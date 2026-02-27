<?php
session_start();
require_once 'conexao.php';

ini_set('max_execution_time', 300); // 5 minutes limit for large files

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['arquivo_csv'])) {
    $file = $_FILES['arquivo_csv'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        header("Location: gerenciar_price_list.php?erro=" . urlencode("Erro no upload do arquivo."));
        exit();
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'csv') {
        header("Location: gerenciar_price_list.php?erro=" . urlencode("Apenas arquivos CSV são permitidos."));
        exit();
    }

    $handle = fopen($file['tmp_name'], "r");
    if ($handle === false) {
        header("Location: gerenciar_price_list.php?erro=" . urlencode("Não foi possível abrir o arquivo."));
        exit();
    }

    try {
        // Auto-migration: Check if lead_time column exists, if not add it
        $checkCol = $pdo->query("SHOW COLUMNS FROM cot_price_list LIKE 'lead_time'");
        if ($checkCol->rowCount() == 0) {
            $pdo->exec("ALTER TABLE cot_price_list ADD COLUMN lead_time VARCHAR(100) DEFAULT NULL AFTER embalagem");
        }

        $pdo->beginTransaction();

        // 1. Truncate table
        $pdo->exec("TRUNCATE TABLE cot_price_list");

        // 2. Prepare Insert
        $sql = "INSERT INTO cot_price_list (fabricante, classificacao, codigo, produto, fracionado, embalagem, lead_time, preco_net_usd) VALUES (:fab, :class, :cod, :prod, :frac, :emb, :lead, :price)";
        $stmt = $pdo->prepare($sql);

        // 3. Read CSV
        $header = fgetcsv($handle, 0, ";"); // Header row

        // Map columns dynamically
        $idxFab = -1;
        $idxClass = -1;
        $idxCod = -1;
        $idxProd = -1;
        $idxFrac = -1;
        $idxEmb = -1;
        $idxPrice = -1;
        $idxLead = -1;

        foreach ($header as $i => $col) {
            $c = mb_strtoupper(trim($col), 'UTF-8');
            if (strpos($c, 'FABRICANTE') !== false)
                $idxFab = $i;
            elseif (strpos($c, 'CLASSIFICA') !== false)
                $idxClass = $i;
            elseif (strpos($c, 'COD') !== false)
                $idxCod = $i; // Assumes "COD PRODUTO" or "CODIGO"
            elseif ($c === 'PRODUTO')
                $idxProd = $i; // Strict for Product Name
            elseif (strpos($c, 'FRACIONADO') !== false)
                $idxFrac = $i;
            elseif (strpos($c, 'EMBALAGEM') !== false)
                $idxEmb = $i;
            elseif (strpos($c, 'NET USD') !== false)
                $idxPrice = $i;
            elseif (strpos($c, 'LEAD') !== false || strpos($c, 'DISPONIBILIDADE') !== false)
                $idxLead = $i;
        }

        // Fallback for Product if strict failed (e.g. "DESCRIÇÃO DO PRODUTO")
        if ($idxProd === -1) {
            foreach ($header as $i => $col) {
                $c = mb_strtoupper(trim($col), 'UTF-8');
                if (strpos($c, 'PRODUTO') !== false && $i !== $idxCod) {
                    $idxProd = $i;
                    break;
                }
            }
        }

        // Validate critical columns
        if ($idxPrice === -1) {
            throw new Exception("Coluna 'NET USD' não encontrada no CSV.");
        }

        while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
            $fabricante = ($idxFab >= 0 && isset($data[$idxFab])) ? trim($data[$idxFab]) : '';
            $classificacao = ($idxClass >= 0 && isset($data[$idxClass])) ? trim($data[$idxClass]) : '';
            $codigo = ($idxCod >= 0 && isset($data[$idxCod])) ? trim($data[$idxCod]) : '';
            $produto = ($idxProd >= 0 && isset($data[$idxProd])) ? trim($data[$idxProd]) : '';
            $fracionado = ($idxFrac >= 0 && isset($data[$idxFrac])) ? trim($data[$idxFrac]) : '';
            $lead_time = ($idxLead >= 0 && isset($data[$idxLead])) ? trim($data[$idxLead]) : '';

            // Convert character encoding to UTF-8
            $fabricante = mb_convert_encoding($fabricante, 'UTF-8', 'ISO-8859-1, UTF-8');
            $classificacao = mb_convert_encoding($classificacao, 'UTF-8', 'ISO-8859-1, UTF-8');
            $codigo = mb_convert_encoding($codigo, 'UTF-8', 'ISO-8859-1, UTF-8');
            $produto = mb_convert_encoding($produto, 'UTF-8', 'ISO-8859-1, UTF-8');
            $fracionado = mb_convert_encoding($fracionado, 'UTF-8', 'ISO-8859-1, UTF-8');
            $lead_time = mb_convert_encoding($lead_time, 'UTF-8', 'ISO-8859-1, UTF-8');

            $rawEmb = ($idxEmb >= 0 && isset($data[$idxEmb])) ? $data[$idxEmb] : '0';
            $rawPrice = ($idxPrice >= 0 && isset($data[$idxPrice])) ? $data[$idxPrice] : '0';

            $embalagem = str_replace(',', '.', $rawEmb);
            $preco = str_replace(',', '.', $rawPrice);

            // Sanitize
            if (!is_numeric($embalagem))
                $embalagem = 0;
            if (!is_numeric($preco))
                $preco = 0;

            $stmt->execute([
                ':fab' => $fabricante,
                ':class' => $classificacao,
                ':cod' => $codigo,
                ':prod' => $produto,
                ':frac' => $fracionado,
                ':emb' => $embalagem,
                ':lead' => $lead_time,
                ':price' => $preco
            ]);
        }

        $pdo->commit();
        fclose($handle);

        file_put_contents('last_upload_price_list.txt', date('d/m/Y H:i:s'));

        header("Location: gerenciar_price_list.php?sucesso=1");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        fclose($handle);
        $msg = "Erro na importação: " . $e->getMessage();
        header("Location: gerenciar_price_list.php?erro=" . urlencode($msg));
        exit();
    }

} else {
    header("Location: gerenciar_price_list.php");
    exit();
}
?>