<?php
require_once 'conexao.php';

header('Content-Type: application/json; charset=utf-8');

$termo = $_GET['q'] ?? '';
$fabricantesParam = $_GET['fab'] ?? ''; // Ex: "BASF|DOW"

try {
    // Search by Code, Name or Manufacturer in cot_price_list
    // Limit to 50 results (unless filtering by manufacturer, then maybe more? limit still good)

    $where = [];
    $params = [];

    // Filtro de Texto (Busca Geral)
    if (!empty($termo)) {
        $where[] = "(UPPER(TRIM(codigo)) LIKE UPPER(TRIM(:termo)) OR UPPER(TRIM(produto)) LIKE UPPER(TRIM(:termo)) OR UPPER(TRIM(fabricante)) LIKE UPPER(TRIM(:termo)))";
        $params[':termo'] = "%" . strtoupper(trim($termo)) . "%";
    }

    // Filtro de Fabricantes (Multi-Select)
    if (!empty($fabricantesParam)) {
        $fabs = explode('|', $fabricantesParam);
        $inQuery = [];
        foreach ($fabs as $k => $f) {
            $key = ":fab$k";
            $inQuery[] = $key;
            $params[$key] = strtoupper(trim($f)); // Ensure trim and uppercase
        }
        // Use TRIM(fabricante) and UPPER(fabricante) to be safe against dirty data and case sensitivity
        if (!empty($inQuery)) {
            $where[] = "UPPER(TRIM(fabricante)) IN (" . implode(',', $inQuery) . ")";
        }
    }

    $sql = "SELECT fabricante, classificacao, codigo, produto, fracionado, embalagem, lead_time, preco_net_usd 
            FROM cot_price_list";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY produto ASC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($produtos);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
