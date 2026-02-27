<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$email = $_GET['email'] ?? $_SESSION['representante_email'] ?? null;

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("❌ Email inválido no enviar_orcamento.php: " . print_r($email, true));
    if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
        die("E-mail inválido.");
    }
    return; // Retorna ao script pai
}

require_once 'conexao.php';
require_once 'GraphMailer.php';
$graphConfig = require 'config_graph.php';
require_once __DIR__ . '/vendor/autoload.php';

// Habilita exibição apenas se chamado diretamente
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Captura o número do orçamento
$num = $_GET['num'] ?? null;
if (!$num) {
    error_log("❌ Num orçamento ausente.");
    if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']))
        die("Número ausente.");
    return;
}
$incluir_net = $_POST['incluir_net'] ?? $_GET['incluir_net'] ?? 'false';
$incluir_net_bool = ($incluir_net === 'true');

// Buscar dados do orçamento
$stmt = $pdo->prepare("SELECT * FROM cot_cotacoes_importadas WHERE NUM_ORCAMENTO = ?");
$stmt->execute([$num]);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$dados) {
    error_log("❌ Orçamento $num não encontrado no BD.");
    if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']))
        die("Orçamento não encontrado.");
    return;
}

// Dados Gerais
$cliente = htmlspecialchars($dados[0]['RAZÃO SOCIAL'] ?? '');
$uf = htmlspecialchars($dados[0]['UF'] ?? '');
$data_orcamento = date('d/m/Y', strtotime($dados[0]['DATA'] ?? date('Y-m-d')));
// Buscar Email do Representante
$stmt_rep = $pdo->prepare("SELECT * FROM cot_representante WHERE UPPER(TRIM(CONCAT(TRIM(nome), ' ', TRIM(sobrenome)))) = ? LIMIT 1");
$stmt_rep->execute([strtoupper(trim($dados[0]['COTADO_POR']))]);
$dados_rep = $stmt_rep->fetch(PDO::FETCH_ASSOC);

if (!$dados_rep) {
    $dados_rep = ['nome' => $dados[0]['COTADO_POR'], 'sobrenome' => '', 'email' => '', 'telefone' => ''];
}

// Buscar Dados Completos do Cliente
$stmt_cli = $pdo->prepare("SELECT * FROM cot_clientes WHERE razao_social = ? LIMIT 1");
$stmt_cli->execute([$dados[0]['RAZÃO SOCIAL']]);
$dados_cliente = $stmt_cli->fetch(PDO::FETCH_ASSOC);

if (!$dados_cliente) {
    $dados_cliente = [
        'razao_social' => $dados[0]['RAZÃO SOCIAL'],
        'uf' => $dados[0]['UF']
    ];
}

require_once 'LayoutHelper.php';

// ==========================================
// 1. CONSTRUÇÃO DO HTML PARA O E-MAIL
// ==========================================
$htmlEmail = LayoutHelper::getEmailHtml($dados, $dados_cliente, $dados_rep, $num, $data_orcamento, $incluir_net_bool);

// ==========================================
// 2. CONSTRUÇÃO DO HTML PARA O PDF
// ==========================================
$htmlPDF = LayoutHelper::getPdfHtml($dados, $dados_cliente, $dados_rep, $num, $data_orcamento, $incluir_net_bool);

// ==========================================
// 3. GERAÇÃO DO PDF ESPECIAL (LANDSCAPE)
// ==========================================
$pdfPath = __DIR__ . "/tmp/orcamento_" . $num . ".pdf";
try {
    // Limpar buffers para evitar corrupção
    while (ob_get_level())
        ob_end_clean();

    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4-L', // Landscape
        'orientation' => 'L',
        'margin_top' => 0,
        'margin_bottom' => 0,
        'margin_left' => 0,
        'margin_right' => 0,
        'tempDir' => __DIR__ . '/tmp'
    ]);

    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($htmlPDF);
    $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

    file_put_contents(__DIR__ . "/tmp/log_enviar_orcamento.txt", "📄 PDF Landscape gerado: $pdfPath\n", FILE_APPEND);

} catch (\Mpdf\MpdfException $e) {
    error_log("❌ Erro MPDF: " . $e->getMessage());
    file_put_contents(__DIR__ . "/tmp/log_enviar_orcamento.txt", "❌ ERRO MPDF: " . $e->getMessage() . "\n", FILE_APPEND);
}

// ==========================================
// 4. ENVIO DO E-MAIL
// ==========================================
$subject = "Orçamento Nº {$num} | {$cliente}";
$mailer = new GraphMailer($graphConfig);

$result = $mailer->sendEmail($email, $subject, $htmlEmail, $pdfPath);

if ($result['success']) {
    file_put_contents(__DIR__ . "/tmp/log_enviar_orcamento.txt", "✅ [GRAPH] Enviado para {$email} em " . date("Y-m-d H:i:s") . "\n", FILE_APPEND);
    if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
        echo "✅ Sucesso.";
    }
} else {
    file_put_contents(__DIR__ . "/tmp/log_enviar_orcamento.txt", "❌ [GRAPH] ERRO: " . $result['error'] . "\n", FILE_APPEND);
    if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
        http_response_code(500);
        echo "❌ Erro: " . $result['error'];
    }
}