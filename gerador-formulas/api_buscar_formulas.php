<?php
header('Content-Type: application/json');
require_once 'config.php';
$resultados = [];
$sql = "SELECT DISTINCT f.* FROM formulacoes f";
$joins = []; $where = []; $params = []; $types = '';
if (!empty($_GET['nome_formula'])) { $where[] = "f.nome_formula LIKE ?"; $params[] = "%{$_GET['nome_formula']}%"; $types .= 's'; }
if (!empty($_GET['categoria'])) { $where[] = "f.categoria = ?"; $params[] = $_GET['categoria']; $types .= 's'; }
if (!empty($_GET['ativo'])) { $joins['ad'] = " LEFT JOIN ativos_destaque ad ON f.id = ad.formulacao_id"; $where[] = "ad.nome_ativo LIKE ?"; $params[] = "%{$_GET['ativo']}%"; $types .= 's'; }
if (isset($_GET['termo_avancado']) && !empty($_GET['termo_avancado'])) {
    $filtro_avancado = $_GET['filtro_avancado']; $termo_avancado = $_GET['termo_avancado'];
    $joins['fa'] = " LEFT JOIN fases fa ON f.id = fa.formulacao_id"; $joins['i'] = " LEFT JOIN ingredientes i ON fa.id = i.fase_id";
    if ($filtro_avancado == 'inci_name') { $where[] = "i.inci_name LIKE ?"; $params[] = "%{$termo_avancado}%"; $types .= 's'; }
    elseif ($filtro_avancado == 'materia_prima') { $where[] = "i.materia_prima LIKE ?"; $params[] = "%{$termo_avancado}%"; $types .= 's'; }
}
if (isset($_GET['data']) && !empty($_GET['data'])) { $data = $_GET['data']; $where[] = "DATE(f.data_criacao) = ?"; $params[] = $data; $types .= 's'; }
$sql .= implode('', $joins);
if (!empty($where)) { $sql .= " WHERE " . implode(' AND ', $where); }
$sql .= " ORDER BY f.data_criacao DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $bind_args = []; $bind_args[] = $types;
    foreach ($params as $key => $value) { $bind_args[] = &$params[$key]; }
    call_user_func_array(array($stmt, 'bind_param'), $bind_args);
}
if (!empty($_GET['codigo'])) {
    $where[] = "(f.codigo_formula LIKE ? OR f.antigo_codigo LIKE ?)";
    $params[] = "%{$_GET['codigo']}%";
    $params[] = "%{$_GET['codigo']}%";
    $types .= 'ss';
}

$sql .= implode('', $joins);
if (!empty($where)) { $sql .= " WHERE " . implode(' AND ', $where); }
$sql .= " ORDER BY f.data_criacao DESC";
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Formata a data antes de enviar
    $row['data_criacao_formatada'] = date('d/m/Y', strtotime($row['data_criacao']));
    $resultados[] = $row;
}

// Imprime os resultados como JSON e encerra o script
echo json_encode($resultados);
exit();
?>