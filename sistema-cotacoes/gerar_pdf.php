<?php
require_once 'conexao.php';
require_once __DIR__ . '/vendor/autoload.php';

// Limpar buffer de saída anterior para evitar corrupção do PDF
if (ob_get_length())
    ob_clean();
ob_start();

use Mpdf\Mpdf;

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);


$campos = [
    'suframa' => 'SUFRAMA',
    'cliente' => 'RAZÃO SOCIAL',
    'uf' => 'UF',
    'data' => 'DATA',
    'codigo' => 'COD DO PRODUTO',
    'produto' => 'PRODUTO',
    'origem' => 'ORIGEM',
    'embalagem' => 'EMBALAGEM (KG)',
    'ncm' => 'NCM',
    'volume' => 'VOLUME',
    'ipi' => 'IPI %',
    'preco_net' => 'PREÇO NET USD/KG',
    'icms' => 'ICMS',
    'preco_full' => 'PREÇO FULL USD/KG',
    'disponibilidade' => 'DISPONIBILIDADE',
    'kg_disponivel' => 'Kg DISPONÍVEL',
    'cotado_por' => 'COTADO_POR',
    'dolar' => 'DOLAR COTADO',
    'gm' => 'GM%',
    'suspensao_ipi' => 'SUSPENSÃO DE IPI',
    'observacoes' => 'OBSERVAÇÕES'
];

$where = [];
$params = [];

foreach ($campos as $input => $coluna) {
    if (isset($_GET[$input]) && $_GET[$input] !== '') {
        $where[] = "`$coluna` LIKE ?";
        $params[] = '%' . $_GET[$input] . '%';
    }
}

$sql = "SELECT * FROM cot_cotacoes_importadas";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($dados) > 1000) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">

    <head>
        <meta charset="UTF-8">
        <title>Volume alto de dados</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </head>

    <body style="background-color: rgba(0, 128, 0, 0.4);">
        <div class="modal show fade" tabindex="-1" style="display: block;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title">Atenção</h5>
                        <button type="button" class="btn-close" onclick="window.history.back()"></button>
                    </div>
                    <div class="modal-body">
                        <p>O PDF não será gerado porque o volume de registros é muito alto
                            (<strong><?= count($dados) ?></strong>).<br>
                            Por favor, refine seus filtros de pesquisa.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="window.history.back()">Voltar</button>
                    </div>
                </div>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
}

$css = '<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #444; padding: 4px; text-align: center; }
  th { background-color: #eee; }
  footer { text-align: center; font-size: 8pt; margin-top: 20px; }
</style>';

$html = $css;
$html .= '<htmlpageheader name="header">
            <div style="text-align: right;">
              <img src="assets/LOGO.svg" style="height: 40px;">
            </div>
          </htmlpageheader>
          <sethtmlpageheader name="header" value="on" show-this-page="1" />
          <sethtmlpageheader name="header" value="on" page="2" />';

$html .= '<h1 style="text-align: center;">Relatório de Cotações</h1>';
$html .= '<div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; text-align: center; margin: 10px 0;">
  <strong>⚠ PROIBIDO O COMPARTILHAMENTO DESTE DOCUMENTO COM PESSOAS EXTERNAS</strong>
</div>';


// Filtros
$filtrosAtivos = [];
foreach ($campos as $input => $coluna) {
    if (isset($_GET[$input]) && $_GET[$input] !== '') {
        $filtrosAtivos[] = '<strong>' . $coluna . '</strong>: ' . htmlspecialchars($_GET[$input]);
    }
}
$html .= empty($filtrosAtivos)
    ? '<p style="text-align: center; margin-bottom: 20px;"><em>Nenhum filtro foi utilizado.</em></p>'
    : '<p style="text-align: center; margin-bottom: 20px;">Filtros utilizados: ' . implode(' | ', $filtrosAtivos) . '</p>';

$html .= '<table><thead><tr>';
foreach ($campos as $coluna) {
    $html .= '<th>' . htmlspecialchars($coluna) . '</th>';
}
$html .= '</tr></thead><tbody>';

foreach ($dados as $linha) {
    $html .= '<tr>';
    foreach ($campos as $coluna) {
        $html .= '<td>' . (isset($linha[$coluna]) ? htmlspecialchars($linha[$coluna]) : '') . '</td>';
    }
    $html .= '</tr>';
}
$html .= '</tbody></table>';
$html .= '<footer>Gerado em ' . date('d/m/Y H:i:s') . '</footer>';

try {
    $mpdf = new Mpdf(['tempDir' => __DIR__ . '/tmp', 'margin_top' => 0, 'setAutoTopMargin' => 'stretch']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('cotacoes.pdf', 'I');
} catch (\Mpdf\MpdfException $e) {
    echo "<script>alert('Erro ao gerar PDF. Por favor, tente novamente com menos registros.'); window.history.back();</script>";
}

if (!empty($dados)) {
    $html = '
    <html>
    <head>
        <style>
            body { font-family: sans-serif; font-size: 10pt; }
            .logo { width: 200px; }
            .header-table td { padding: 4px; }
            .product-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .product-table th, .product-table td {
                border: 1px solid #ccc;
                padding: 5px;
                text-align: center;
            }
            .product-table th {
                background-color: #6c3483;
                color: white;
            }
            .observacoes {
                margin-top: 20px;
                font-size: 9pt;
            }
        </style>
    </head>
    <body>
        <table width="100%">
            <tr>
                <td><img src="https://i.imgur.com/TKPBuVv.png" class="logo"></td>
                <td align="right">
                    <h2 style="color: #6c3483;">PROPOSTA COMERCIAL</h2>
                    <p>' . date("d/m/Y") . '<br>Orçado por<br><strong>' . ($dados[0]["COTADO POR"] ?? "") . '</strong><br>' . strtolower($dados[0]["COTADO POR"] ?? "") . '@innovasell.com.br</p>
                </td>
            </tr>
        </table>

        <table class="header-table" width="100%">
            <tr><td><strong>CLIENTE</strong> ' . ($dados[0]["RAZÃO SOCIAL"] ?? "") . '</td></tr>
            <tr><td><strong>UF</strong> ' . ($dados[0]["UF"] ?? "") . '</td></tr>
        </table>

        <table class="product-table">
            <tr>
                <th>COD DO PRODUTO</th>
                <th>PRODUTO</th>
                <th>EMBALAGEM/KG</th>
                <th>NCM</th>
                <th>VOLUME</th>
                <th>IPI %</th>
                <th>ICMS</th>
                <th>PREÇO FULL USD/KG</th>
                <th>DISPONIBILIDADE</th>
            </tr>';

    foreach ($dados as $d) {
        $html .= '<tr>
            <td>' . $d["COD DO PRODUTO"] . '</td>
            <td>' . $d["PRODUTO"] . '</td>
            <td>' . $d["EMBALAGEM (KG)"] . '</td>
            <td>' . $d["NCM"] . '</td>
            <td>' . $d["VOLUME"] . '</td>
            <td>' . $d["IPI %"] . '</td>
            <td>' . $d["ICMS"] . '</td>
            <td>$' . number_format($d["PREÇO FULL USD/KG"], 2) . '</td>
            <td>' . $d["DISPONIBILIDADE"] . '</td>
        </tr>';
    }

    $html .= '</table>

        <div class="observacoes">
            <p>1. Preço em Dólar (Convertido na data de Emissão da nota Fiscal pela taxa do dólar Ptax do dia anterior ao Faturamento);</p>
            <p>2. Preço Full: Inclui PIS, COFINS e ICMS;</p>
            <p>3. IPI: Não Incluso</p>
            <p>4. Condição de pagamento: primeira compra à vista;</p>
            <p>5. Frete: FOB - Todos os pedidos abaixo de R$ 3.000,00 ou para destinatários fora da Grande São Paulo</p>
            <p>6. Frete: CIF - Somente para pedidos acima de R$ 3.000,00 para grande SÃO PAULO ( SOB CONSULTA ).</p>
            <p>7. Validade da proposta: 30 dias</p>
        </div>
    </body>
    </html>';

    $mpdf = new Mpdf();
    $mpdf->WriteHTML($html);
    $mpdf->Output('proposta_comercial.pdf', 'I');
    exit;
}
