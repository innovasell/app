<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html'); exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['arquivo_csv'])) {
    header('Location: atualizar_budget.php'); exit();
}

$file = $_FILES['arquivo_csv'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    header("Location: atualizar_budget.php?erro=" . urlencode("Erro no upload do arquivo.")); exit();
}
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    header("Location: atualizar_budget.php?erro=" . urlencode("Apenas arquivos CSV são permitidos.")); exit();
}

// ─── Mapa de detecção de colunas ─────────────────────────────────────────────
// Chave: fragmento para busca (uppercase), Valor: campo no array de linha
$colMap = [
    'CNPJ'               => 'cnpj',
    'RAZAO SOCIAL'       => 'razao_social',
    'RAZÃO SOCIAL'       => 'razao_social',
    'CLIENTE ORIGEM'     => 'cliente_origem',
    'TERCEIRISTA'        => 'terceirista',
    'FABRICANTE'         => 'fabricante',
    'CONCAT'             => 'concat',
    'TIPO'               => 'tipo',
    'VENDEDOR AJUSTADO'  => 'vendedor_ajustado',   // deve vir ANTES de VENDEDOR
    'EMBALAGEM'          => 'embalagem',
    // KG
    'TOTAL KG'           => 'kg_historico',
    'KG REALIZADO 2025'  => 'kg_realizado_2025',
    'KG ORÇADO 2026'     => 'kg_orcado_2026',
    'KG ORCADO 2026'     => 'kg_orcado_2026',
    'KG REALIZADO 2026'  => 'kg_realizado_2026',
    // Preços BRL
    'PREÇO REALIZADO ENTRE'   => 'preco_hist_brl',
    'PRECO REALIZADO ENTRE'   => 'preco_hist_brl',
    'PREÇO REALIZADO 2025'    => 'preco_2025_brl',
    'PRECO REALIZADO 2025'    => 'preco_2025_brl',
    'REAJUSTE SUGERIDO'       => 'reajuste_sugerido',
    'PREÇO SUGERIDO '         => 'preco_sugerido_brl',   // espaço intencional para prioridade
    'PRECO SUGERIDO '         => 'preco_sugerido_brl',
    'PREÇO ORÇADO 2026'       => 'preco_orcado_2026_brl',
    'PRECO ORCADO 2026'       => 'preco_orcado_2026_brl',
    'PREÇO REALIZADO 2026'    => 'preco_realizado_2026_brl',
    'PRECO REALIZADO 2026'    => 'preco_realizado_2026_brl',
    // Preços USD
    'PREÇO REALIZADO ENTRE 17' => 'preco_hist_usd',   // fallback — mapeado abaixo por posição USD
    'PREÇO REALIZADO 2025 (MÉ' => 'preco_2025_usd',
    'PREÇO SUGERIDO  USD'      => 'preco_sugerido_usd',
    'PREÇO ORÇADO 2026  USD'   => 'preco_orcado_2026_usd',
    'PREÇO REALIZADO 2026  USD'=> 'preco_realizado_2026_usd',
    // Venda NET
    'VENDA NET REALIZADO 2025' => 'venda_net_2025',
    'VENDA NET  ORÇADO 2026'   => 'venda_net_orcado_2026',
    'VENDA NET ORCADO 2026'    => 'venda_net_orcado_2026',
    'VENDA NET  REALIZADO 2026'=> 'venda_net_realizado_2026',
    // Custo
    'CUSTO UNT REALIZADO 2025' => 'custo_unt_realizado_2025',
    'CUSTO UNT ORCADO DANI'    => 'custo_unt_orcado_dani',
    'CUSTO UNT ORÇADO DANI'    => 'custo_unt_orcado_dani',
    'COMPARATIVO CUSTO'        => 'comp_custo_dani',
    'CUSTO UNT  ORÇADO 2026'   => 'custo_unt_orcado_2026',
    'CUSTO UNT  ORCADO 2026'   => 'custo_unt_orcado_2026',
    'CUSTO UNT  REALIZADO 2026'=> 'custo_unt_realizado_2026',
    'CUSTO TOTAL REALIZADO 2025'  => 'custo_total_2025',
    'CUSTO TOTAL  ORÇADO 2026'    => 'custo_total_orcado_2026',
    'CUSTO TOTAL  ORCADO 2026'    => 'custo_total_orcado_2026',
    'CUSTO TOTAL  REALIZADO 2026' => 'custo_total_realizado_2026',
    // Lucro
    'LUCRO LIQUIDO REALIZADO 2025'=> 'lucro_liq_2025',
    'LUCRO LIQUIDO ORÇADO 2026'   => 'lucro_liq_orcado_2026',
    'LUCRO LIQUIDO ORCADO 2026'   => 'lucro_liq_orcado_2026',
    'LUCRO LIQUIDO REALIZADO 2026'=> 'lucro_liq_realizado_2026',
    // GM
    'GM% REALIZADO 2025'    => 'gm_2025',
    'GM% ORÇADO 2026'       => 'gm_orcado_2026',
    'GM% ORCADO 2026'       => 'gm_orcado_2026',
    'GM% REALIZADO 2026'    => 'gm_realizado_2026',
    // EXW/LANDED
    'LOTE ECONÔMICO'        => 'lote_economico_kg',
    'LOTE ECONOMICO'        => 'lote_economico_kg',
    'EXW 2026 (KG) USD'     => 'exw_2026_kg_usd',
    'EXW 2026 (TOTAL) USD'  => 'exw_2026_total_usd',
    'LANDED 2026 (KG) USD'  => 'landed_2026_kg_usd',
    'LANDED 2026 (TOTAL)'   => 'landed_2026_total',
    // Outros
    'COME TARIOS SUPPLY'    => 'comentarios_supply',
    'COME'                  => 'comentarios_supply',
    'PREÇO AJUSTADO'        => 'preco_ajustado',
    'PRECO AJUSTADO'        => 'preco_ajustado',
];

// Produto mapeado separadamente (muitas colunas contêm "PRECO" — queremos só "PRODUTO")
$colProduto = 'produto';
// Vendedor (genérico, mapear depois de vendedor_ajustado)
$colVendedor = 'vendedor';

ini_set('max_execution_time', 300);

$handle = fopen($file['tmp_name'], 'r');
// Detecta BOM UTF-8
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($handle);

try {
    // ─── Detectar separador ────────────────────────────────────────────────────
    $firstLine = fgets($handle);
    rewind($handle);
    $bom2 = fread($handle, 3);
    if ($bom2 !== "\xEF\xBB\xBF") rewind($handle);
    $sep = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

    // ─── Ler header ───────────────────────────────────────────────────────────
    $header = fgetcsv($handle, 0, $sep);
    if (!$header) throw new Exception("Arquivo CSV vazio ou inválido.");

    // Normalizar headers: uppercase + remove acentos para comparações
    $headerNorm = array_map(function($h) {
        $h = mb_convert_encoding(trim($h), 'UTF-8', 'ISO-8859-1, UTF-8');
        return mb_strtoupper($h, 'UTF-8');
    }, $header);

    // ─── Montar índice de colunas ─────────────────────────────────────────────
    $idx = []; // $idx['campo_db'] = posição no CSV

    // Produto: coluna cujo header normalizado seja exatamente 'PRODUTO'
    foreach ($headerNorm as $i => $h) {
        if ($h === 'PRODUTO') { $idx[$colProduto] = $i; break; }
    }
    // Fallback produto
    if (!isset($idx[$colProduto])) {
        foreach ($headerNorm as $i => $h) {
            if (strpos($h, 'PRODUTO') !== false) { $idx[$colProduto] = $i; break; }
        }
    }

    // Vendedor ajustado primeiro (contém "VENDEDOR"), depois vendedor genérico
    foreach ($headerNorm as $i => $h) {
        if (strpos($h, 'VENDEDOR AJUSTADO') !== false) { $idx['vendedor_ajustado'] = $i; break; }
    }
    foreach ($headerNorm as $i => $h) {
        if ($h === 'VENDEDOR' || (strpos($h, 'VENDEDOR') !== false && !isset($idx['vendedor_ajustado']))) {
            if (!isset($idx['vendedor'])) $idx['vendedor'] = $i;
        }
    }
    // Vendedor: header = exatamente "VENDEDOR"
    foreach ($headerNorm as $i => $h) {
        if ($h === 'VENDEDOR') { $idx['vendedor'] = $i; break; }
    }

    // Demais mapeamentos por fragmento
    foreach ($colMap as $fragment => $campo) {
        if (isset($idx[$campo])) continue; // já mapeado
        $fragNorm = mb_strtoupper($fragment, 'UTF-8');
        foreach ($headerNorm as $i => $h) {
            if (strpos($h, $fragNorm) !== false) {
                $idx[$campo] = $i;
                break;
            }
        }
    }

    // ─── Prepare UPSERT ───────────────────────────────────────────────────────
    $campos = ['cnpj','razao_social','cliente_origem','terceirista','produto','fabricante',
               'concat','tipo','vendedor','vendedor_ajustado','embalagem',
               'kg_historico','kg_realizado_2025','kg_orcado_2026','kg_realizado_2026',
               'preco_hist_brl','preco_2025_brl','reajuste_sugerido','preco_sugerido_brl',
               'preco_orcado_2026_brl','preco_realizado_2026_brl',
               'preco_hist_usd','preco_2025_usd','preco_sugerido_usd','preco_orcado_2026_usd','preco_realizado_2026_usd',
               'venda_net_2025','venda_net_orcado_2026','venda_net_realizado_2026',
               'custo_unt_realizado_2025','custo_unt_orcado_dani','comp_custo_dani','custo_unt_orcado_2026','custo_unt_realizado_2026',
               'custo_total_2025','custo_total_orcado_2026','custo_total_realizado_2026',
               'lucro_liq_2025','lucro_liq_orcado_2026','lucro_liq_realizado_2026',
               'gm_2025','gm_orcado_2026','gm_realizado_2026',
               'lote_economico_kg','exw_2026_kg_usd','exw_2026_total_usd','landed_2026_kg_usd','landed_2026_total',
               'comentarios_supply','preco_ajustado'];

    $placeholders = ':' . implode(', :', $campos);
    $updates = implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", array_filter($campos, fn($c) => $c !== 'concat')));

    $sql = "INSERT INTO `cot_budget_cliente` (`" . implode('`, `', $campos) . "`)
            VALUES ($placeholders)
            ON DUPLICATE KEY UPDATE $updates, `updated_at` = CURRENT_TIMESTAMP";
    $stmt = $pdo->prepare($sql);

    // ─── Helpers ──────────────────────────────────────────────────────────────
    function getVal($data, $idx, $campo) {
        if (!isset($idx[$campo])) return null;
        $v = trim($data[$idx[$campo]] ?? '');
        if ($v === '' || $v === '-') return null;
        return mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1, UTF-8');
    }
    function getNum($data, $idx, $campo) {
        $v = getVal($data, $idx, $campo);
        if ($v === null) return null;
        $v = str_replace(['.', ' '], ['', ''], $v); // remove separador de milhar
        $v = str_replace(',', '.', $v);
        $v = preg_replace('/[^0-9.\-]/', '', $v);
        return is_numeric($v) ? $v : null;
    }

    // ─── Processar linhas ─────────────────────────────────────────────────────
    $pdo->beginTransaction();
    $inserted = 0;

    while (($data = fgetcsv($handle, 0, $sep)) !== false) {
        if (empty(array_filter($data))) continue; // linha em branco

        $concat = getVal($data, $idx, 'concat');
        if (empty($concat)) continue; // sem concat = linha inválida

        $params = [
            ':cnpj'                    => getVal($data, $idx, 'cnpj'),
            ':razao_social'            => getVal($data, $idx, 'razao_social'),
            ':cliente_origem'          => getVal($data, $idx, 'cliente_origem'),
            ':terceirista'             => getVal($data, $idx, 'terceirista'),
            ':produto'                 => getVal($data, $idx, 'produto'),
            ':fabricante'              => getVal($data, $idx, 'fabricante'),
            ':concat'                  => $concat,
            ':tipo'                    => getVal($data, $idx, 'tipo'),
            ':vendedor'                => getVal($data, $idx, 'vendedor'),
            ':vendedor_ajustado'       => getVal($data, $idx, 'vendedor_ajustado'),
            ':embalagem'               => getNum($data, $idx, 'embalagem'),
            ':kg_historico'            => getNum($data, $idx, 'kg_historico'),
            ':kg_realizado_2025'       => getNum($data, $idx, 'kg_realizado_2025'),
            ':kg_orcado_2026'          => getNum($data, $idx, 'kg_orcado_2026'),
            ':kg_realizado_2026'       => getNum($data, $idx, 'kg_realizado_2026'),
            ':preco_hist_brl'          => getNum($data, $idx, 'preco_hist_brl'),
            ':preco_2025_brl'          => getNum($data, $idx, 'preco_2025_brl'),
            ':reajuste_sugerido'       => getNum($data, $idx, 'reajuste_sugerido'),
            ':preco_sugerido_brl'      => getNum($data, $idx, 'preco_sugerido_brl'),
            ':preco_orcado_2026_brl'   => getNum($data, $idx, 'preco_orcado_2026_brl'),
            ':preco_realizado_2026_brl'=> getNum($data, $idx, 'preco_realizado_2026_brl'),
            ':preco_hist_usd'          => getNum($data, $idx, 'preco_hist_usd'),
            ':preco_2025_usd'          => getNum($data, $idx, 'preco_2025_usd'),
            ':preco_sugerido_usd'      => getNum($data, $idx, 'preco_sugerido_usd'),
            ':preco_orcado_2026_usd'   => getNum($data, $idx, 'preco_orcado_2026_usd'),
            ':preco_realizado_2026_usd'=> getNum($data, $idx, 'preco_realizado_2026_usd'),
            ':venda_net_2025'          => getNum($data, $idx, 'venda_net_2025'),
            ':venda_net_orcado_2026'   => getNum($data, $idx, 'venda_net_orcado_2026'),
            ':venda_net_realizado_2026'=> getNum($data, $idx, 'venda_net_realizado_2026'),
            ':custo_unt_realizado_2025'=> getNum($data, $idx, 'custo_unt_realizado_2025'),
            ':custo_unt_orcado_dani'   => getNum($data, $idx, 'custo_unt_orcado_dani'),
            ':comp_custo_dani'         => getNum($data, $idx, 'comp_custo_dani'),
            ':custo_unt_orcado_2026'   => getNum($data, $idx, 'custo_unt_orcado_2026'),
            ':custo_unt_realizado_2026'=> getNum($data, $idx, 'custo_unt_realizado_2026'),
            ':custo_total_2025'        => getNum($data, $idx, 'custo_total_2025'),
            ':custo_total_orcado_2026' => getNum($data, $idx, 'custo_total_orcado_2026'),
            ':custo_total_realizado_2026'=> getNum($data, $idx, 'custo_total_realizado_2026'),
            ':lucro_liq_2025'          => getNum($data, $idx, 'lucro_liq_2025'),
            ':lucro_liq_orcado_2026'   => getNum($data, $idx, 'lucro_liq_orcado_2026'),
            ':lucro_liq_realizado_2026'=> getNum($data, $idx, 'lucro_liq_realizado_2026'),
            ':gm_2025'                 => getNum($data, $idx, 'gm_2025'),
            ':gm_orcado_2026'          => getNum($data, $idx, 'gm_orcado_2026'),
            ':gm_realizado_2026'       => getNum($data, $idx, 'gm_realizado_2026'),
            ':lote_economico_kg'       => getNum($data, $idx, 'lote_economico_kg'),
            ':exw_2026_kg_usd'         => getNum($data, $idx, 'exw_2026_kg_usd'),
            ':exw_2026_total_usd'      => getNum($data, $idx, 'exw_2026_total_usd'),
            ':landed_2026_kg_usd'      => getNum($data, $idx, 'landed_2026_kg_usd'),
            ':landed_2026_total'       => getNum($data, $idx, 'landed_2026_total'),
            ':comentarios_supply'      => getVal($data, $idx, 'comentarios_supply'),
            ':preco_ajustado'          => getNum($data, $idx, 'preco_ajustado'),
        ];

        $stmt->execute($params);
        $inserted++;
    }

    $pdo->commit();
    fclose($handle);

    header("Location: atualizar_budget.php?sucesso=" . $inserted);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fclose($handle);
    header("Location: atualizar_budget.php?erro=" . urlencode("Erro na importação: " . $e->getMessage()));
    exit();
}
