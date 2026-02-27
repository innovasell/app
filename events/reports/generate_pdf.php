<?php
/**
 * Gerador de Relatórios em PDF
 * Suporta relatórios por Evento/Visita, Fatura, e Colaborador
 */

session_start();
require_once '../auth.php';
require_login();

require_once '../config.php';
require_once '../GraphMailer.php';
$graphConfig = require '../config_graph.php';
require_once __DIR__ . '/../../sistema-cotacoes/vendor/autoload.php'; // mPDF

// Parâmetros
$tipo = $_GET['tipo'] ?? ''; // evento, fatura, colaborador
$valor = $_GET['valor'] ?? '';
$acao = $_GET['acao'] ?? 'download'; // download ou email

$response = ['success' => false, 'message' => ''];

try {
    if (empty($tipo) || empty($valor)) {
        throw new Exception('Parâmetros inválidos.');
    }

    // Determina o filtro SQL
    $filtroField = '';
    $tituloRelatorio = '';

    if ($tipo === 'evento') {
        $filtroField = 'evento_visita';
        $tituloRelatorio = 'Relatório de Despesas por Evento/Visita';
    } elseif ($tipo === 'fatura') {
        $filtroField = 'num_fatura';
        $tituloRelatorio = 'Relatório de Despesas por Fatura';
    } elseif ($tipo === 'colaborador') {
        $filtroField = 'passageiro';
        $tituloRelatorio = 'Relatório de Despesas por Colaborador';
    } else {
        throw new Exception('Tipo de relatório inválido.');
    }

    // Busca dados do banco
    $sql = "SELECT * FROM viagem_express_expenses WHERE $filtroField = ? ORDER BY dt_emissao DESC, id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $valor);
    $stmt->execute();
    $result = $stmt->get_result();

    $despesas = [];
    $totalGeral = 0;
    while ($row = $result->fetch_assoc()) {
        $despesas[] = $row;
        $totalGeral += floatval($row['total']);
    }

    if (count($despesas) === 0) {
        throw new Exception('Nenhuma despesa encontrada para este filtro.');
    }

    // Caminho da logo
    $logoPath = __DIR__ . '/../../sistema-cotacoes/assets/LOGO.png';

    // Se a logo existir, converte para base64
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
    }

    // Monta HTML do PDF
    $dataGeracao = date('d/m/Y H:i');
    $filtroInfo = htmlspecialchars($valor);

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            margin: 20px;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .header-title {
            flex: 1;
        }
        .header-logo {
            text-align: right;
        }
        .header-logo img {
            max-width: 120px;
            max-height: 60px;
        }
        h1 {
            font-size: 18pt;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .info-box {
            background-color: #f0f0f0;
            padding: 10px;
            margin: 15px 0;
            border-left: 4px solid #007bff;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 10pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 9pt;
        }
        thead {
            background-color: #333;
            color: white;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .total-row {
            background-color: #e8f4f8 !important;
            font-weight: bold;
            font-size: 11pt;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="border: none; width: 100%;">
            <tr>
                <td style="width: 70%; border: none; vertical-align: top;">
                    <h1>' . $tituloRelatorio . '</h1>
                </td>
                <td style="width: 30%; border: none; text-align: right; vertical-align: top;">';

    if ($logoBase64) {
        $html .= '<img src="' . $logoBase64 . '" style="max-width: 120px; max-height: 60px;">';
    }

    $html .= '          </td>
            </tr>
        </table>
    </div>

    <div class="info-box">
        <p><strong>Filtro Aplicado:</strong> ' . $filtroInfo . '</p>
        <p><strong>Data de Geração:</strong> ' . $dataGeracao . '</p>
        <p><strong>Total de Itens:</strong> ' . count($despesas) . '</p>
        <p><strong>Valor Total:</strong> R$ ' . number_format($totalGeral, 2, ',', '.') . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Passageiro</th>
                <th>Evento/Visita</th>
                <th>Produto</th>
                <th>Categoria</th>
                <th class="text-right">Valor</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($despesas as $despesa) {
        $data = $despesa['dt_emissao'] ? date('d/m/Y', strtotime($despesa['dt_emissao'])) : '-';
        $cliente = htmlspecialchars($despesa['cliente'] ?? '-');
        $passageiro = htmlspecialchars($despesa['passageiro'] ?? '-');
        $eventoVisita = htmlspecialchars($despesa['evento_visita'] ?? '-');
        $produto = htmlspecialchars($despesa['produto'] ?? '-');
        $categoria = htmlspecialchars($despesa['categoria_despesa'] ?? '-');
        $valor = 'R$ ' . number_format(floatval($despesa['total']), 2, ',', '.');

        $html .= '<tr>
                <td>' . $data . '</td>
                <td>' . $passageiro . '</td>
                <td>' . $eventoVisita . '</td>
                <td>' . $produto . '</td>
                <td>' . $categoria . '</td>
                <td class="text-right">' . $valor . '</td>
            </tr>';
    }

    $html .= '
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL GERAL:</td>
                <td class="text-right">R$ ' . number_format($totalGeral, 2, ',', '.') . '</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Relatório gerado em ' . $dataGeracao . ' | InnovaEvents - Sistema de Gestão de Despesas de Viagens</p>
    </div>
</body>
</html>';

    // Gera o PDF
    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4',
        'orientation' => 'L', // Landscape
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_left' => 10,
        'margin_right' => 10,
    ]);

    $mpdf->WriteHTML($html);

    // Nome do arquivo
    $fileName = 'relatorio_' . $tipo . '_' . date('YmdHis') . '.pdf';
    $pdfDir = __DIR__ . '/../tmp';

    // Cria diretório tmp se não existir
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0755, true);
    }

    $pdfPath = $pdfDir . '/' . $fileName;
    $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

    // Se for download, envia o PDF
    if ($acao === 'download') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($pdfPath);
        exit;
    }

    // Se for email, envia por email
    if ($acao === 'email') {
        $userEmail = user()['email'];

        if (empty($userEmail)) {
            throw new Exception('Email do usuário não encontrado.');
        }

        $mailer = new GraphMailer($graphConfig);
        $subject = "$tituloRelatorio - $filtroInfo";
        $emailBody = "
            <h2>$tituloRelatorio</h2>
            <p>Olá,</p>
            <p>Segue em anexo o relatório solicitado:</p>
            <ul>
                <li><strong>Tipo:</strong> $tituloRelatorio</li>
                <li><strong>Filtro:</strong> $filtroInfo</li>
                <li><strong>Total de itens:</strong> " . count($despesas) . "</li>
                <li><strong>Valor total:</strong> R$ " . number_format($totalGeral, 2, ',', '.') . "</li>
            </ul>
            <p>Atenciosamente,<br>InnovaEvents - Sistema de Gestão de Despesas</p>
        ";

        $result = $mailer->sendEmail($userEmail, $subject, $emailBody, $pdfPath);

        if ($result['success']) {
            $response['success'] = true;
            $response['message'] = 'Email enviado com sucesso!';
            $response['email'] = $userEmail;
        } else {
            throw new Exception('Erro ao enviar email: ' . ($result['error'] ?? 'Desconhecido'));
        }
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("ERRO GENERATE PDF: " . $e->getMessage());
    $response['message'] = $e->getMessage();

    // Se for download e houver erro, mostra mensagem HTML
    if ($acao === 'download') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<h2>Erro ao gerar relatório</h2>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><a href="../relatorios.php">Voltar para Relatórios</a></p>';
        exit;
    }
}

// Para ação de email, sempre retorna JSON
if ($acao === 'email') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>