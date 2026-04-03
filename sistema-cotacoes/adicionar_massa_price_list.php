<?php
session_start();
require_once 'conexao.php';

ini_set('max_execution_time', 300);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['arquivo_csv_massa'])) {
    header("Location: gerenciar_price_list.php");
    exit();
}

$file = $_FILES['arquivo_csv_massa'];

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
    // Auto-migration: garantir coluna lead_time
    $checkCol = $pdo->query("SHOW COLUMNS FROM cot_price_list LIKE 'lead_time'");
    if ($checkCol->rowCount() == 0) {
        $pdo->exec("ALTER TABLE cot_price_list ADD COLUMN lead_time VARCHAR(100) DEFAULT NULL AFTER embalagem");
    }

    $pdo->beginTransaction();

    $sql = "INSERT INTO cot_price_list (fabricante, classificacao, codigo, produto, fracionado, embalagem, lead_time, preco_net_usd)
            VALUES (:fab, :class, :cod, :prod, :frac, :emb, :lead, :price)";
    $stmt = $pdo->prepare($sql);

    // Ler cabeçalho e mapear colunas
    $header = fgetcsv($handle, 0, ";");

    $idxFab = $idxClass = $idxCod = $idxProd = $idxFrac = $idxEmb = $idxPrice = $idxLead = -1;

    foreach ($header as $i => $col) {
        $c = mb_strtoupper(trim($col), 'UTF-8');
        if (strpos($c, 'FABRICANTE') !== false)       $idxFab   = $i;
        elseif (strpos($c, 'CLASSIFICA') !== false)   $idxClass = $i;
        elseif (strpos($c, 'COD') !== false)          $idxCod   = $i;
        elseif ($c === 'PRODUTO')                     $idxProd  = $i;
        elseif (strpos($c, 'FRACIONADO') !== false)   $idxFrac  = $i;
        elseif (strpos($c, 'EMBALAGEM') !== false)    $idxEmb   = $i;
        elseif (strpos($c, 'NET USD') !== false)      $idxPrice = $i;
        elseif (strpos($c, 'LEAD') !== false || strpos($c, 'DISPONIBILIDADE') !== false) $idxLead = $i;
    }

    // Fallback para produto
    if ($idxProd === -1) {
        foreach ($header as $i => $col) {
            $c = mb_strtoupper(trim($col), 'UTF-8');
            if (strpos($c, 'PRODUTO') !== false && $i !== $idxCod) {
                $idxProd = $i;
                break;
            }
        }
    }

    if ($idxPrice === -1) {
        throw new Exception("Coluna 'NET USD' não encontrada no CSV.");
    }

    $inseridos = 0;
    while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
        $fabricante    = ($idxFab   >= 0 && isset($data[$idxFab]))   ? trim($data[$idxFab])   : '';
        $classificacao = ($idxClass >= 0 && isset($data[$idxClass])) ? trim($data[$idxClass]) : '';
        $codigo        = ($idxCod   >= 0 && isset($data[$idxCod]))   ? trim($data[$idxCod])   : '';
        $produto       = ($idxProd  >= 0 && isset($data[$idxProd]))  ? trim($data[$idxProd])  : '';
        $fracionado    = ($idxFrac  >= 0 && isset($data[$idxFrac]))  ? trim($data[$idxFrac])  : '';
        $lead_time     = ($idxLead  >= 0 && isset($data[$idxLead]))  ? trim($data[$idxLead])  : '';

        // Normalizar encoding
        foreach (['fabricante','classificacao','codigo','produto','fracionado','lead_time'] as $var) {
            $$var = mb_convert_encoding($$var, 'UTF-8', 'ISO-8859-1, UTF-8');
        }

        $rawEmb   = ($idxEmb   >= 0 && isset($data[$idxEmb]))   ? $data[$idxEmb]   : '0';
        $rawPrice = ($idxPrice >= 0 && isset($data[$idxPrice])) ? $data[$idxPrice] : '0';

        $embalagem = str_replace(',', '.', $rawEmb);
        $preco     = str_replace(',', '.', $rawPrice);

        if (!is_numeric($embalagem)) $embalagem = 0;
        if (!is_numeric($preco))     $preco     = 0;

        // Pular linha vazia (sem produto e sem preço)
        if ($produto === '' && $preco == 0) continue;

        $stmt->execute([
            ':fab'   => $fabricante,
            ':class' => $classificacao,
            ':cod'   => $codigo,
            ':prod'  => $produto,
            ':frac'  => $fracionado,
            ':emb'   => $embalagem,
            ':lead'  => $lead_time,
            ':price' => $preco
        ]);
        $inseridos++;
    }

    $pdo->commit();
    fclose($handle);

    header("Location: gerenciar_price_list.php?sucesso_massa=" . $inseridos);
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    fclose($handle);
    header("Location: gerenciar_price_list.php?erro=" . urlencode("Erro na adição em massa: " . $e->getMessage()));
    exit();
}
