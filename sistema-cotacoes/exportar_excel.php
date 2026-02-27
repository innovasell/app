<?php
require_once 'conexao.php';
session_start();

// Verifica se é administrador
if (!isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
    echo "Acesso negado.";
    exit;
}

function formatarOrigem($codigoOrigem)
{
    $rotulos = [
        '0' => 'NACIONAL',
        '1' => 'IMPORTADO',
        '6' => 'LISTA CAMEX'
    ];
    return $rotulos[trim($codigoOrigem)] ?? $codigoOrigem;
}

$campos = [
    'suframa' => 'SUFRAMA',
    'razao_social' => 'RAZÃO SOCIAL',
    'uf' => 'UF',
    'data_inicial' => 'DATA',
    'data_final' => 'DATA',
    'codigo' => 'COD DO PRODUTO',
    'produto' => 'PRODUTO',
    'origem' => 'ORIGEM',
    'embalagem' => 'EMBALAGEM_KG',
    'ncm' => 'NCM',
    'volume' => 'VOLUME',
    'ipi' => 'IPI %',
    'preco_net' => 'PREÇO NET USD/KG',
    'icms' => 'ICMS',
    'preco_full' => 'PREÇO FULL USD/KG',
    'disponibilidade' => 'DISPONIBilidade',
    'cotado_por' => 'COTADO_POR',
    'dolar' => 'DOLAR COTADO',
    'suspensao_ipi' => 'SUSPENCAO_IPI',
    'observacoes' => 'OBSERVAÇÕES'
];

$filtros = [];
$parametros = [];

foreach ($campos as $campo => $coluna) {
    if (!empty($_GET[$campo])) {
        if ($campo === 'data_inicial') {
            $filtros[] = "`$coluna` >= :data_inicial";
            $parametros[':data_inicial'] = $_GET[$campo];
        } elseif ($campo === 'data_final') {
            $filtros[] = "`$coluna` <= :data_final";
            $parametros[':data_final'] = $_GET[$campo];
        } else {
            $filtros[] = "`$coluna` LIKE :$campo";
            $parametros[":{$campo}"] = "%" . $_GET[$campo] . "%";
        }
    }
}

$where = $filtros ? "WHERE " . implode(" AND ", $filtros) : "";
$orderBy = " ORDER BY `NUM_ORCAMENTO` DESC ";

// SQL sem limite (exportar tudo)
$sql = "SELECT * FROM cot_cotacoes_importadas $where $orderBy";
$stmt = $pdo->prepare($sql);
foreach ($parametros as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Configurações do cabeçalho para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=cotacoes_export_' . date('Y-m-d_H-i') . '.csv');

// Abre saída php
$output = fopen('php://output', 'w');

// BOM para Excel reconhecer UTF-8
fputs($output, "\xEF\xBB\xBF");

// Cabeçalho do CSV
$headers = [
    'DATA',
    'RAZÃO SOCIAL',
    'UF',
    'CÓDIGO',
    'PRODUTO',
    'ORIGEM',
    'EMBALAGEM',
    'NCM',
    'VOLUME',
    'IPI %',
    'PREÇO NET USD/KG',
    'ICMS',
    'PREÇO FULL USD/KG',
    'DISPONIBILIDADE',
    'COTADO POR',
    'DÓLAR COTADO',
    'SUSPENSÃO IPI',
    'SUFRAMA',
    'OBSERVAÇÕES'
];
fputcsv($output, $headers, ';'); // Ponto e vírgula é padrão Excel BR

// Dados
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $linha = [
        date('d/m/Y', strtotime($row['DATA'])),
        $row['RAZÃO SOCIAL'],
        $row['UF'],
        $row['COD DO PRODUTO'],
        $row['PRODUTO'],
        formatarOrigem($row['origem'] ?? $row['ORIGEM'] ?? ''),
        $row['EMBALAGEM_KG'],
        $row['NCM'],
        $row['VOLUME'],
        number_format((float) $row['IPI %'], 2, ',', '.') . '%',
        number_format((float) $row['PREÇO NET USD/KG'], 2, ',', '.'),
        str_replace('.', ',', $row['ICMS']), // Tenta manter formato numérico padrão BR se possível
        number_format((float) $row['PREÇO FULL USD/KG'], 2, ',', '.'),
        $row['DISPONIBILIDADE'],
        $row['COTADO_POR'],
        $row['DOLAR COTADO'],
        $row['SUSPENCAO_IPI'],
        $row['SUFRAMA'],
        $row['OBSERVAÇÕES']
    ];
    fputcsv($output, $linha, ';');
}

fclose($output);
exit;
