<?php
session_start();
require_once 'conexao.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}

use Mpdf\Mpdf;

$numCenario = isset($_GET['num_cenario']) ? trim($_GET['num_cenario']) : '';

if (empty($numCenario)) {
    die('Número do cenário não informado.');
}

try {
    // Buscar Cabeçalho da OC usando o número do cenário de origem (Pega a mais recente)
    $sqlHeader = "SELECT * FROM cot_pedidos_compra WHERE num_cenario_origem = :num ORDER BY id DESC LIMIT 1";
    $stmtHeader = $pdo->prepare($sqlHeader);
    $stmtHeader->execute([':num' => $numCenario]);
    $pedido = $stmtHeader->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        die('Ordem de Compra não encontrada para este cenário.');
    }

    // Buscar Itens da OC
    $sqlItens = "SELECT * FROM cot_pedidos_compra_itens WHERE id_pedido = :id_pedido";
    $stmtItens = $pdo->prepare($sqlItens);
    $stmtItens->execute([':id_pedido' => $pedido['id']]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    // Buscar dados do Fornecedor (para ter mais detalhes se necessário, mas já temos nome na OC)
    // No futuro, se precisar de endereço, busca na tabela de fornecedores usando id_fornecedor

    // Montar HTML do PDF
    $html = '
  <!DOCTYPE html>
  <html>
  <head>
    <style>
      body { font-family: Arial, sans-serif; font-size: 10pt; }
      .header-table { width: 100%; margin-bottom: 20px; border-bottom: 2px solid #ccc; padding-bottom: 10px; }
      .logo { width: 150px; }
      .title { font-size: 16pt; font-weight: bold; text-align: right; }
      .info-box { background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
      .info-box h3 { margin-top: 0; font-size: 11pt; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
      .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
      .items-table th { background-color: #eee; border: 1px solid #ddd; padding: 8px; text-align: center; font-weight: bold; font-size: 9pt; }
      .items-table td { border: 1px solid #ddd; padding: 8px; text-align: center; font-size: 9pt; }
      .items-table td.left { text-align: left; }
      .items-table td.right { text-align: right; }
      .footer { margin-top: 30px; font-size: 8pt; color: #666; border-top: 1px solid #ccc; padding-top: 10px; }
    </style>
  </head>
  <body>';

    $html .= '
    <table class="header-table">
        <tr>
            <td width="50%">
                <img src="assets/LOGO.svg" class="logo"><br>
            </td>
            <td width="50%" align="right" valign="top">
                <div class="title">ORDEM DE COMPRA</div>
                <div style="font-size: 12pt; margin-top: 5px;">OC #: ' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</div>
                <div style="font-size: 10pt;">Data: ' . date('d/m/Y', strtotime($pedido['data_criacao'])) . '</div>
            </td>
        </tr>
    </table>';

    // Se houver modal, exibir abaixo do header ou integrado
    if (!empty($pedido['modal'])) {
        $iconeModal = '';
        if ($pedido['modal'] == 'Aéreo')
            $iconeModal = '✈️';
        elseif ($pedido['modal'] == 'Marítimo')
            $iconeModal = '🚢';
        elseif ($pedido['modal'] == 'Rodoviário')
            $iconeModal = '🚛';

        if ($iconeModal) {
            $html .= '
            <div style="text-align: center; margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; background-color: #fff; border-radius: 5px;">
                <div style="font-size: 30pt;">' . $iconeModal . '</div>
                <div style="font-size: 12pt; font-weight: bold; color: #555;">MODAL ' . mb_strtoupper($pedido['modal']) . '</div>
            </div>';
        }
    }

    $html .= '
    <div class="info-box">
        <h3>Fornecedor</h3>
        <div><strong>Nome:</strong> ' . htmlspecialchars($pedido['fornecedor']) . '</div>
        <!-- Poderíamos adicionar mais dados do fornecedor aqui se buscássemos na tabela -->
    </div>';

    $html .= '
    <h3>Itens do Pedido</h3>
    <table class="items-table">
        <thead>
            <tr>
                <th>PRODUTO</th>
                <th>CÓDIGO</th>
                <th>DEADLINE</th>
                <th>UNIDADE</th>
                <th>QTD</th>
                <th>CUSTO (US$)</th>
                <th>TOTAL (US$)</th>
            </tr>
        </thead>
        <tbody>';

    $totalGeral = 0;

    foreach ($itens as $item) {
        $totalItem = $item['qtd'] * $item['landed_usd'];
        $totalGeral += $totalItem;

        $deadline = !empty($item['data_necessidade']) ? date('d/m/Y', strtotime($item['data_necessidade'])) : '-';

        $html .= '
        <tr>
            <td class="left">' .
            htmlspecialchars($item['produto']) . ' <small>(' . htmlspecialchars($item['embalagem'] ?? '') . ' ' . htmlspecialchars($item['unidade']) . ')</small>' .
            '</td>
            <td>' . htmlspecialchars($item['codigo_produto']) . '</td>
            <td>' . $deadline . '</td>
            <td>' . htmlspecialchars($item['unidade']) . '</td>
            <td>' . number_format($item['qtd'], 2, ',', '.') . '</td>
            <td class="right">$' . number_format($item['landed_usd'], 4, ',', '.') . '</td>
            <td class="right">$' . number_format($totalItem, 2, ',', '.') . '</td>
        </tr>';
    }

    $html .= '
        <tr style="background-color: #f0f0f0; font-weight: bold;">
            <td colspan="6" class="right">TOTAL GERAL</td>
            <td class="right">$' . number_format($totalGeral, 2, ',', '.') . '</td>
        </tr>
    </tbody>
  </table>';

    if (!empty($pedido['obs'])) {
        $html .= '
      <div class="info-box" style="margin-top: 20px;">
          <h3>Observações</h3>
          <div>' . nl2br(htmlspecialchars($pedido['obs'])) . '</div>
      </div>';
    }

    $html .= '
    <div class="footer">
        Gerado por: ' . htmlspecialchars($pedido['criado_por']) . ' | Origem: Cenário ' . htmlspecialchars($pedido['num_cenario_origem']) . '
    </div>
  </body>
  </html>';

    // Gerar PDF
    $mpdf = new Mpdf([
        'format' => 'A4',
        'orientation' => 'P',
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_left' => 15,
        'margin_right' => 15
    ]);

    $mpdf->WriteHTML($html);
    $mpdf->Output('OC_' . $pedido['id'] . '_' . $numCenario . '.pdf', \Mpdf\Output\Destination::INLINE);

} catch (Exception $e) {
    die('Erro ao gerar PDF da OC: ' . $e->getMessage());
}
?>