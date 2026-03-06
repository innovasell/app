<?php
/**
 * api/debug_sem_lista.php
 * Diagnóstico: para cada item S/Lista de um lote, mostra o que o sistema tentou
 * buscar na price list vs. o que realmente existe cadastrado para aquele código.
 * Uso: ?batch_id=X  (opcional: &limit=50)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';

$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$limit    = min((int)($_GET['limit'] ?? 100), 500);

if (!$batch_id) {
    echo json_encode(['success' => false, 'message' => 'batch_id obrigatório']);
    exit;
}

// Busca itens S/Lista do lote
$stmt = $pdo->prepare("
    SELECT id, codigo, embalagem, descricao, representante, cliente, nfe
    FROM com_commission_items
    WHERE batch_id = ? AND lista_nao_encontrada = 1
    ORDER BY codigo
    LIMIT ?
");
$stmt->execute([$batch_id, $limit]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];

foreach ($items as $item) {
    $codigo    = trim($item['codigo']);
    $embalagem = trim($item['embalagem']); // ex: "(1 KG)" ou "1 KG"
    $codigo9   = substr($codigo, 0, 9);

    // Remove parênteses para obter o valor limpo
    $embLimpa = preg_replace('/[()]/','', $embalagem);
    $embLimpa = trim($embLimpa); // ex: "1 KG"

    // Gera candidatos de busca (mesmo algoritmo do process_commission.php)
    $candidatos = [$embLimpa];
    if (preg_match('/^([\d.,]+)\s*(.*)$/', $embLimpa, $m)) {
        $numRaw   = $m[1];
        $unidade  = trim($m[2]);
        $numNorm  = str_replace(',', '.', $numRaw);
        $numFloat = (float)$numNorm;
        $candidatos = array_unique(array_merge($candidatos, [
            $numNorm . ($unidade ? ' '.$unidade : ''),
            number_format($numFloat, 3, '.', '') . ($unidade ? ' '.$unidade : ''),
            number_format($numFloat, 0, '.', '') . ($unidade ? ' '.$unidade : ''),
            number_format($numFloat, 2, '.', '') . ($unidade ? ' '.$unidade : ''),
            trim(rtrim(number_format($numFloat, 3, '.', ''), '0'), '.') . ($unidade ? ' '.$unidade : ''),
        ]));
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
        'id'           => $item['id'],
        'codigo'       => $codigo,
        'codigo9'      => $codigo9,
        'embalagem_db' => $embalagem,        // Como foi salvo no batch
        'emb_limpa'    => $embLimpa,          // Embalagem sem parênteses
        'candidatos_tentados' => $candidatos, // O que o sistema tentou procurar
        'opcoes_price_list'   => $opcoesPriceList, // O que existe na price list
        'encontraria_agora'   => !empty($encontrados), // Com o algoritmo atual, acharia?
        'match'        => $encontrados,
        'nfe'          => $item['nfe'],
        'cliente'      => $item['cliente'],
        'descricao'    => $item['descricao'] ?? '',
    ];
}

$totais = [
    'total_sem_lista'      => count($result),
    'sem_codigo_na_lista'  => count(array_filter($result, fn($r) => empty($r['opcoes_price_list']))),
    'cod_existe_emb_diff'  => count(array_filter($result, fn($r) => !empty($r['opcoes_price_list']) && empty($r['match']))),
    'encontraria_agora'    => count(array_filter($result, fn($r) => $r['encontraria_agora'])),
];

echo json_encode([
    'success' => true,
    'batch_id' => $batch_id,
    'totais'  => $totais,
    'itens'   => $result,
]);
