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
    header("Location: atualizar_budget.php?erro=" . urlencode("Erro no upload.")); exit();
}
if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
    header("Location: atualizar_budget.php?erro=" . urlencode("Apenas arquivos .csv são permitidos.")); exit();
}

ini_set('max_execution_time', 300);

// ─── Helper: remove acentos para comparação accent-insensitive ────────────────
function normalizeKey($str) {
    $str = mb_strtoupper(trim($str), 'UTF-8');
    $from = ['Á','À','Ã','Â','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï',
             'Ó','Ò','Õ','Ô','Ö','Ú','Ù','Û','Ü','Ç','Ñ'];
    $to   = ['A','A','A','A','A','E','E','E','E','I','I','I','I',
             'O','O','O','O','O','U','U','U','U','C','N'];
    return str_replace($from, $to, $str);
}

// ─── Mapa: fragmento normalizado (sem acentos) → campo DB ────────────────────
// Ordem importa: fragmentos mais específicos primeiro
$colMapRaw = [
    // Identificação
    'CNPJ'                               => 'cnpj',
    'RAZAO SOCIAL'                       => 'razao_social',
    'CLIENTE ORIGEM'                     => 'cliente_origem',
    'TERCEIRISTA'                        => 'terceirista',
    'FABRICANTE'                         => 'fabricante',
    'CONCAT'                             => 'concat',
    'TIPO'                               => 'tipo',
    'VENDEDOR AJUSTADO'                  => 'vendedor_ajustado',  // antes de VENDEDOR
    'EMBALAGEM'                          => 'embalagem',
    // KG — mais específicos primeiro
    'TOTAL KG'                           => 'kg_historico',
    'KG REALIZADO 2025'                  => 'kg_realizado_2025',
    'KG ORCADO 2026'                     => 'kg_orcado_2026',
    'KG REALIZADO 2026'                  => 'kg_realizado_2026',
    // Preços BRL — mais específicos primeiro
    'PRECO REALIZADO ENTRE'              => 'preco_hist_brl',
    'PRECO REALIZADO 2025'               => 'preco_2025_brl',
    'REAJUSTE SUGERIDO'                  => 'reajuste_sugerido',
    'PRECO SUGERIDO  USD'                => 'preco_sugerido_usd',  // USD antes do BRL
    'PRECO SUGERIDO'                     => 'preco_sugerido_brl',
    'PRECO ORCADO 2026  USD'             => 'preco_orcado_2026_usd',
    'PRECO ORCADO 2026'                  => 'preco_orcado_2026_brl',
    'PRECO REALIZADO 2026  USD'          => 'preco_realizado_2026_usd',
    'PRECO REALIZADO 2026'               => 'preco_realizado_2026_brl',
    // USD (em sequência, depois dos BRL)
    'PRECO REALIZADO ENTRE 17'           => 'preco_hist_usd',
    'PRECO REALIZADO 2025 (MEDIA) USD'   => 'preco_2025_usd',
    // Venda NET — atenção: ORCADO antes de genérico
    'VENDA NET REALIZADO 2025'           => 'venda_net_2025',
    'VENDA NET  ORCADO 2026'             => 'venda_net_orcado_2026',
    'VENDA NET  REALIZADO 2026'          => 'venda_net_realizado_2026',
    'VENDA NET REALIZADO 2026'           => 'venda_net_realizado_2026',
    'VENDA NET  ORCADO'                  => 'venda_net_orcado_2026',
    // Custo
    'CUSTO UNT REALIZADO 2025'           => 'custo_unt_realizado_2025',
    'CUSTO UNT ORCADO DANI'              => 'custo_unt_orcado_dani',
    'COMPARATIVO CUSTO'                  => 'comp_custo_dani',
    'CUSTO UNT  ORCADO 2026'             => 'custo_unt_orcado_2026',
    'CUSTO UNT  REALIZADO 2026'          => 'custo_unt_realizado_2026',
    'CUSTO TOTAL REALIZADO 2025'         => 'custo_total_2025',
    'CUSTO TOTAL  ORCADO 2026'           => 'custo_total_orcado_2026',
    'CUSTO TOTAL  REALIZADO 2026'        => 'custo_total_realizado_2026',
    // Lucro
    'LUCRO LIQUIDO REALIZADO 2025'       => 'lucro_liq_2025',
    'LUCRO LIQUIDO ORCADO 2026'          => 'lucro_liq_orcado_2026',
    'LUCRO LIQUIDO REALIZADO 2026'       => 'lucro_liq_realizado_2026',
    // GM
    'GM% REALIZADO 2025'                 => 'gm_2025',
    'GM% ORCADO 2026'                    => 'gm_orcado_2026',
    'GM% REALIZADO 2026'                 => 'gm_realizado_2026',
    // EXW / LANDED
    'LOTE ECONOMICO'                     => 'lote_economico_kg',
    'EXW 2026 (KG) USD'                  => 'exw_2026_kg_usd',
    'EXW 2026 (TOTAL) USD'               => 'exw_2026_total_usd',
    'LANDED 2026 (KG) USD'               => 'landed_2026_kg_usd',
    'LANDED 2026 (TOTAL)'                => 'landed_2026_total',
    // Outros
    'COME'                               => 'comentarios_supply',  // COMENTARIOS SUPPLY
    'PRECO AJUSTADO'                     => 'preco_ajustado',
];

// Normaliza as chaves do mapa (sem acentos)
$colMap = [];
foreach ($colMapRaw as $k => $v) {
    $colMap[normalizeKey($k)] = $v;
}

$handle = fopen($file['tmp_name'], 'r');

// Remove BOM se existir
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($handle);

try {
    // Detecta separador
    $firstLine = fgets($handle);
    rewind($handle);
    $bom2 = fread($handle, 3);
    if ($bom2 !== "\xEF\xBB\xBF") rewind($handle);
    $sep = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

    // Lê header
    $rawHeader = fgetcsv($handle, 0, $sep);
    if (!$rawHeader) throw new Exception("Arquivo CSV vazio ou inválido.");

    // Normaliza headers (sem acentos, uppercase, trimado)
    // Só converte encoding se o header NÃO for UTF-8 válido
    $headerNorm = [];
    foreach ($rawHeader as $h) {
        $h = trim($h);
        if (!mb_detect_encoding($h, 'UTF-8', /* strict */ true)) {
            // ISO-8859-1 / Windows-1252 → converte para UTF-8
            $h = mb_convert_encoding($h, 'UTF-8', 'ISO-8859-1');
        }
        $headerNorm[] = normalizeKey($h);
    }

    // Monta índice de colunas
    $idx = [];

    // PRODUTO: header normalizado = exatamente 'PRODUTO'
    foreach ($headerNorm as $i => $h) {
        if ($h === 'PRODUTO') { $idx['produto'] = $i; break; }
    }
    // Fallback produto: contém 'PRODUTO' mas não é 'COD PRODUTO' etc.
    if (!isset($idx['produto'])) {
        foreach ($headerNorm as $i => $h) {
            if (strpos($h, 'PRODUTO') !== false && strpos($h, 'COD') === false) {
                $idx['produto'] = $i; break;
            }
        }
    }

    // VENDEDOR AJUSTADO antes de VENDEDOR genérico
    foreach ($headerNorm as $i => $h) {
        if (strpos($h, 'VENDEDOR AJUSTADO') !== false) { $idx['vendedor_ajustado'] = $i; break; }
    }
    foreach ($headerNorm as $i => $h) {
        if ($h === 'VENDEDOR') { $idx['vendedor'] = $i; break; }
    }
    // Fallback: qualquer header que seja só VENDEDOR (sem "AJUSTADO")
    if (!isset($idx['vendedor'])) {
        foreach ($headerNorm as $i => $h) {
            if ($h === 'VENDEDOR' || ($h === 'VENDEDOR' && !isset($idx['vendedor']))) {
                $idx['vendedor'] = $i; break;
            }
        }
    }

    // Demais colunas: por fragmento (ordem do colMap)
    foreach ($colMap as $fragment => $campo) {
        if (isset($idx[$campo])) continue;
        foreach ($headerNorm as $i => $h) {
            if (strpos($h, $fragment) !== false) {
                $idx[$campo] = $i;
                break;
            }
        }
    }

    // ─── Fallbacks via regex (para colunas com acentos que podem corromper) ───
    // kg_orcado_2026: qualquer header "KG ... 2026" que NÃO seja realizado/total
    if (!isset($idx['kg_orcado_2026'])) {
        foreach ($headerNorm as $i => $h) {
            if (preg_match('/^KG\s.{2,20}2026$/i', $h)
                && strpos($h, 'REALIZ') === false
                && strpos($h, 'TOTAL')  === false) {
                $idx['kg_orcado_2026'] = $i;
                break;
            }
        }
    }
    // kg_orcado_2026: fallback ainda mais amplo — posição entre KG Realizado 2025 e KG Realizado 2026
    if (!isset($idx['kg_orcado_2026'])
        && isset($idx['kg_realizado_2025'])
        && isset($idx['kg_realizado_2026'])) {
        $posA = $idx['kg_realizado_2025'];
        $posB = $idx['kg_realizado_2026'];
        if ($posB - $posA === 2) {
            // A coluna exatamente no meio provavelmente é KG Orçado 2026
            $idx['kg_orcado_2026'] = $posA + 1;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    function getVal($data, $idx, $campo) {
        if (!isset($idx[$campo])) return null;
        $v = isset($data[$idx[$campo]]) ? trim($data[$idx[$campo]]) : '';
        if ($v === '' || $v === '-') return null;
        $v = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1, UTF-8');
        return $v;
    }
    function getNum($data, $idx, $campo) {
        $v = getVal($data, $idx, $campo);
        if ($v === null) return null;
        // Remove separador de milhar (ponto) e troca vírgula decimal por ponto
        $v = preg_replace('/\.(?=\d{3})/', '', $v); // remove ponto-milhar
        $v = str_replace(',', '.', $v);
        $v = preg_replace('/[^0-9.\-]/', '', $v);
        return is_numeric($v) ? $v : null;
    }

    // ─── Prepare INSERT ───────────────────────────────────────────────────────
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
    $sqlInsert = "INSERT INTO `cot_budget_cliente` (`" . implode('`, `', $campos) . "`) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sqlInsert);

    $pdo->beginTransaction();

    // ── Apaga todos os registros antes de importar (DELETE respeita transação) ─
    $pdo->exec("DELETE FROM cot_budget_cliente");

    $inserted = 0;
    $skipped  = 0;

    while (($data = fgetcsv($handle, 0, $sep)) !== false) {
        if (empty(array_filter($data))) continue;

        $concat = getVal($data, $idx, 'concat');
        if (empty($concat)) { $skipped++; continue; }

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

    // Grava diagnóstico de colunas detectadas para debug
    $detected = [];
    foreach ($idx as $campo => $pos) {
        $detected[] = $campo . '=' . ($rawHeader[$pos] ?? '?');
    }
    $notDetected = [];
    $criticals = ['kg_orcado_2026', 'kg_realizado_2025', 'produto', 'cnpj', 'concat', 'embalagem'];
    foreach ($criticals as $c) {
        if (!isset($idx[$c])) $notDetected[] = $c;
    }

    $msg = $inserted . '&not_found=' . urlencode(implode(',', $notDetected));
    header("Location: atualizar_budget.php?sucesso=" . $msg);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (isset($handle) && is_resource($handle)) fclose($handle);
    header("Location: atualizar_budget.php?erro=" . urlencode("Erro: " . $e->getMessage()));
    exit();
}
