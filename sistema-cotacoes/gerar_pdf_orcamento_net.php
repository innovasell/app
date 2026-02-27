<?php
file_put_contents(
    __DIR__ . "/tmp/log_gerar_pdf.txt",
    "🔥 TOPO DO SCRIPT ACESSADO em: " . date("Y-m-d H:i:s") . "\n",
    FILE_APPEND
);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once 'conexao.php';
require_once __DIR__ . '/vendor/autoload.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


file_put_contents(
    __DIR__ . "/tmp/log_gerar_pdf.txt",
    "🚀 gerar_pdf_orcamento.php ACESSADO em: " . date("Y-m-d H:i:s") . "\n",
    FILE_APPEND
);
$num = $_GET['num'] ?? '';
if (!$num) {
    die("❌ Parâmetro 'num' não fornecido.");
}

// Verifica se o usuário está logado
if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}
use Mpdf\Mpdf;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$num_orcamento = $_GET['num'] ?? '';


require_once 'conexao.php';

$num = trim($_GET['num'] ?? '');

if (strlen($num) < 10 || !ctype_digit($num)) {
    die("Número do orçamento inválido.");
}


$stmt = $pdo->prepare("SELECT * FROM cot_cotacoes_importadas WHERE NUM_ORCAMENTO = ?");
$stmt->execute([$num]);

$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($dados)) {
    echo "Nenhum dado encontrado.";
    exit;
}


// Montar o PDF
$html = '
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 8pt; }
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
            <td><img src="assets/LOGO.svg" class="logo"></td>
            <td align="right">
                <h2 style="color: #6c3483;">PROPOSTA COMERCIAL</h2>
<p>' . date("d/m/Y") . '<br>Orçado por<br><strong>'
    . ucwords($_SESSION["representante_nome"] ?? "") . ' ' . ucwords($_SESSION["representante_sobrenome"] ?? "") .
    '</strong><br>' . ($_SESSION["representante_email"] ?? "email@desconhecido.com") . '</p>


            </td>
        </tr>
    </table>

    <table class="header-table" width="100%">
        <tr><td><strong>CLIENTE</strong> ' . ($dados[0]["RAZÃO SOCIAL"] ?? "") . '</td></tr>
        <tr><td><strong>UF</strong> ' . ($dados[0]["UF"] ?? "") . '</td></tr>
        <tr><td><strong>ORÇAMENTO Nº</strong> ' . $num . '</td></tr>
    </table>

    <table class="product-table">
        <tr>
            <th>CÓDIGO</th>
            <th>PRODUTO</th>
            <th>EMB/KG</th>
            <th>NCM</th>
            <th>VOLUME</th>
            <th>IPI %</th>
            <th>ICMS</th>
            <th>PREÇO NET USD/KG</th>
            <th>PREÇO FULL USD/KG</th>
            <th>DISPONIBILIDADE</th>
        </tr>';

foreach ($dados as $d) {
    $html .= '<tr>
        <td>' . $d["COD DO PRODUTO"] . '</td>
        <td>' . $d["PRODUTO"] . '</td>
        <td>' . $d["EMBALAGEM_KG"] . '</td>
        <td>' . $d["NCM"] . '</td>
        <td>' . $d["VOLUME"] . '</td>
<td>' . number_format((float) str_replace("%", "", $d["IPI %"]), 2, ',', '.') . '%</td>
<td>' . number_format((float) str_replace("%", "", $d["ICMS"]), 2, ',', '.') . '%</td>

        <td>$' . number_format($d["PREÇO NET USD/KG"], 2) . '</td>
        <td>$' . number_format($d["PREÇO FULL USD/KG"], 2) . '</td>

        <td>' . $d["DISPONIBILIDADE"] . '</td>
    </tr>';
}

$html .= '</table>
    <table width="100%" style="margin-top: 20px; font-size: 9pt; border-collapse: collapse;">
    <tr>
        <td style="padding: 3px;">1. Preço em Dólar (Convertido na data de Emissão da nota Fiscal pela taxa do dólar Ptax do dia anterior ao Faturamento);</td>
    </tr>
    <tr>
        <td style="padding: 3px;">2. Preço Full: Inclui PIS, COFINS e ICMS;</td>
    </tr>
    <tr>
        <td style="padding: 3px;">3. IPI: Não Incluso</td>
    </tr>
    <tr>
        <td style="padding: 3px;">4. Condição de pagamento: primeira compra à vista;</td>
    </tr>
    <tr>
        <td style="padding: 3px;">5. Frete: FOB - Todos os pedidos abaixo de R$ 3.000,00 ou para destinatários fora da Grande São Paulo</td>
    </tr>
    <tr>
        <td style="padding: 3px;">6. Frete: CIF - Somente para pedidos acima de R$ 3.000,00 para grande SÃO PAULO ( SOB CONSULTA ).</td>
    </tr>
    <tr>
        <td style="padding: 3px;">7. Validade da proposta: 30 dias</td>
    </tr>
</table>

</body>
</html>';

$mpdf = new Mpdf([
    'format' => 'A4',
    'orientation' => 'P',
    'margin_top' => 10,
    'margin_bottom' => 15,
    'margin_left' => 10,
    'margin_right' => 10
]);
$mpdf->SetDisplayMode('fullpage'); // mostra o PDF ocupando a página inteira
$mpdf->SetDisplayMode('real');     // mostra com zoom 100%
$mpdf->SetDisplayMode('default');  // comportamento padrão
$mpdf->WriteHTML($html);
$arquivo = __DIR__ . "/tmp/orcamento_" . $num . ".pdf";
if (!is_writable(dirname($arquivo))) {
    die("❌ A pasta tmp/ não tem permissão de escrita.");
}
$mpdf->Output($arquivo, \Mpdf\Output\Destination::FILE);
http_response_code(200);
exit;


