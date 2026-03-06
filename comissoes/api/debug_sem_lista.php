<?php
/**
 * api/debug_sem_lista.php
 * Diagnóstico: para cada item S/Lista de um lote, mostra o que o sistema tentou
 * buscar na price list vs. o que realmente existe cadastrado para aquele código.
 */
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $err['message'] . ' in ' . basename($err['file']) . ':' . $err['line']]);
    }
});

require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';

$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$limit    = min((int)($_GET['limit'] ?? 100), 500);

if (!$batch_id) {
    echo json_encode(['success' => false, 'message' => 'batch_id obrigatório']);
    exit;
}

try {
    // Busca itens S/Lista do lote
    $stmt = $pdo->prepare("
        SELECT id, codigo, embalagem, representante, cliente, nfe
        FROM com_commission_items
        WHERE batch_id = ? AND lista_nao_encontrada = 1
        ORDER BY codigo
        LIMIT {$limit}
    ");
    $stmt->execute([$batch_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    foreach ($items as $item) {
        $codigo    = trim($item['codigo']);
        $embalagem = trim($item['embalagem']); // ex: "(1 KG)" ou "1 KG"
        $codigo9   = substr($codigo, 0, 9);

        // Remove parênteses para obter o valor limpo
        $embLimpa = preg_replace('/[()]/', '', $embalagem);
        $embLimpa = trim($embLimpa); // ex: "1 KG"

        // Gera candidatos de busca (mesmo algoritmo do process_commission.php)
        $candidatos = [$embLimpa];
        if (preg_match('/^([\d.,]+)\s*(.*)$/', $embLimpa, $m)) {
            $numRaw   = $m[1];
            $unidade  = trim($m[2]);
            $numNorm  = str_replace(',', '.', $numRaw);
            $numFloat = (float)$numNorm;
            $candidatos = array_values(array_unique(array_merge($candidatos, [
                $numNorm . ($unidade ? ' '.$unidade : ''),
                number_format($numFloat, 3, '.', '') . ($unidade ? ' '.$unidade : ''),
                number_format($numFloat, 0, '.', '') . ($unidade ? ' '.$unidade : ''),
                number_format($numFloat, 2, '.', '') . ($unidade ? ' '.$unidade : ''),
                trim(rtrim(number_format($numFloat, 3, '.', ''), '0'), '.') . ($unidade ? ' '.$unidade : ''),
            ])));
        }

        // Busca TODAS as embalagens disponíveis na price list para este código
        $stmtPl = $pdo->prepare("
            SELECT embalagem, preco_net_usd
            FROM cot_price_list
            WHERE codigo LIKE ?
            ORDER BY embalagem
        ");
        $stmtPl->execute(["{$codigo9}%"]);
        $opcoesPriceList = $stmtPl->fetchAll(PDO::FETCH_ASSOC);

        // Verifica se algum candidato bate com as opções disponíveis
        $embsDispon  = array_column($opcoesPriceList, 'embalagem');
        $encontrados = array_values(array_intersect($candidatos, $embsDispon));

        $result[] = [
            'id'                  => (int)$item['id'],
            'codigo'              => $codigo,
            'codigo9'             => $codigo9,
            'embalagem_db'        => $embalagem,
            'emb_limpa'           => $embLimpa,
            'candidatos_tentados' => $candidatos,
            'opcoes_price_list'   => $opcoesPriceList,
            'encontraria_agora'   => !empty($encontrados),
            'match'               => $encontrados,
            'nfe'                 => $item['nfe'] ?? '',
            'cliente'             => $item['cliente'] ?? '',
        ];
    }

    $totais = [
        'total_sem_lista'     => count($result),
        'sem_codigo_na_lista' => count(array_filter($result, fn($r) => empty($r['opcoes_price_list']))),
        'cod_existe_emb_diff' => count(array_filter($result, fn($r) => !empty($r['opcoes_price_list']) && empty($r['match']))),
        'encontraria_agora'   => count(array_filter($result, fn($r) => $r['encontraria_agora'])),
    ];

    ob_clean();
    echo json_encode([
        'success'  => true,
        'batch_id' => $batch_id,
        'totais'   => $totais,
        'itens'    => $result,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
