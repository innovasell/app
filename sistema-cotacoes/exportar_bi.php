<?php
require_once 'conexao.php';
session_start();

// Verifica se é administrador
if (!isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
    echo "Acesso negado.";
    exit;
}

$tipo = $_GET['tipo'] ?? '';
$data_inicial = $_GET['data_inicial'] ?? '';
$data_final = $_GET['data_final'] ?? '';
$representante = $_GET['representante'] ?? '';
$uf_filtro = $_GET['uf'] ?? '';

// Filtros SQL
$filtros = [];
$params = [];

if ($data_inicial) {
    $filtros[] = "`DATA` >= :data_inicial";
    $params[':data_inicial'] = $data_inicial;
}
if ($data_final) {
    $filtros[] = "`DATA` <= :data_final";
    $params[':data_final'] = $data_final;
}
if ($representante) {
    $filtros[] = "`COTADO_POR` = :representante";
    $params[':representante'] = $representante;
}
if ($uf_filtro) {
    $filtros[] = "`UF` = :uf";
    $params[':uf'] = $uf_filtro;
}

$where = $filtros ? "WHERE " . implode(" AND ", $filtros) : "";

// Definir Query Baseado no Tipo
$sql = "";
$filename = "bi_export_" . $tipo . "_" . date('Y-m-d') . ".csv";
$headers = [];

switch ($tipo) {
    case 'prod_qtd':
        $sql = "SELECT PRODUTO, COUNT(*) as qtd FROM cot_cotacoes_importadas $where GROUP BY PRODUTO ORDER BY qtd DESC";
        $headers = ['Produto', 'Quantidade de Cotações'];
        break;
    case 'cli_qtd':
        $sql = "SELECT `RAZÃO SOCIAL` as cliente, COUNT(DISTINCT NUM_ORCAMENTO) as qtd FROM cot_cotacoes_importadas $where GROUP BY `RAZÃO SOCIAL` ORDER BY qtd DESC";
        $headers = ['Cliente', 'Quantidade de Propostas'];
        break;
    case 'rep_qtd':
        $sql = "SELECT COTADO_POR, COUNT(*) as qtd FROM cot_cotacoes_importadas $where GROUP BY COTADO_POR ORDER BY qtd DESC";
        $headers = ['Representante', 'Itens Orçados'];
        break;
    case 'uf_qtd':
        $sql = "SELECT UF, COUNT(DISTINCT NUM_ORCAMENTO) as qtd FROM cot_cotacoes_importadas $where GROUP BY UF ORDER BY qtd DESC";
        $headers = ['UF', 'Quantidade de Propostas'];
        break;
    case 'rep_val':
        $sql = "SELECT COTADO_POR, SUM(VOLUME * `PREÇO FULL USD/KG`) as total FROM cot_cotacoes_importadas $where GROUP BY COTADO_POR ORDER BY total DESC";
        $headers = ['Representante', 'Valor Total (USD)'];
        break;
    case 'cli_val':
        $sql = "SELECT `RAZÃO SOCIAL` as cliente, SUM(VOLUME * `PREÇO FULL USD/KG`) as total FROM cot_cotacoes_importadas $where GROUP BY `RAZÃO SOCIAL` ORDER BY total DESC";
        $headers = ['Cliente', 'Valor Total (USD)'];
        break;
    case 'prod_val':
        $sql = "SELECT PRODUTO, SUM(VOLUME * `PREÇO FULL USD/KG`) as total FROM cot_cotacoes_importadas $where GROUP BY PRODUTO ORDER BY total DESC";
        $headers = ['Produto', 'Valor Total (USD)'];
        break;
    default:
        die("Tipo de exportação inválido.");
}

// Executar
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();

// Output CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF"); // BOM
fputcsv($output, $headers, ';');

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Formatar valores se necessário (se tiver coluna 'total')
    if (isset($row['total'])) {
        $row['total'] = number_format($row['total'], 2, ',', '.');
    }
    fputcsv($output, $row, ';');
}
fclose($output);
exit;
