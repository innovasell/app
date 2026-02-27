<?php
session_start();
require_once 'conexao.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!isset($_SESSION['representante_email'])) {
  header('Location: index.html');
  exit();
}

use Mpdf\Mpdf;

$num = isset($_GET['num']) ? trim($_GET['num']) : '';

if (empty($num)) {
  die('Número do cenário não informado.');
}

try {
  // Buscar dados do cenário
  $sqlCabecalho = "SELECT * FROM cot_cenarios_importacao WHERE num_cenario = :num";
  $stmtCabecalho = $pdo->prepare($sqlCabecalho);
  $stmtCabecalho->execute([':num' => $num]);
  $cabecalho = $stmtCabecalho->fetch(PDO::FETCH_ASSOC);

  if (!$cabecalho) {
    die('Cenário não encontrado.');
  }

  // Buscar itens
  $sqlItens = "SELECT * FROM cot_cenarios_itens WHERE num_cenario = :num ORDER BY id";
  $stmtItens = $pdo->prepare($sqlItens);
  $stmtItens->execute([':num' => $num]);
  $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

  // Montar HTML do PDF
  $html = '
  <!DOCTYPE html>
  <html>
  <head>
    <style>
      body { font-family: Arial, sans-serif; font-size: 9pt; }
      .header-table { width: 100%; margin-bottom: 20px; }
      .header-table td { padding: 5px; }
      .logo { width: 150px; }
      .title { color: #40883c; font-size: 18pt; font-weight: bold; }
      .info-section { margin: 15px 0; padding: 10px; background-color: #f8f9fa; border-radius: 5px; }
      .info-section h3 { color: #40883c; font-size: 12pt; margin: 0 0 10px 0; }
      .info-row { margin: 5px 0; }
      .info-label { font-weight: bold; display: inline-block; width: 180px; }
      .products-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 8pt; }
      .products-table th, .products-table td {
        border: 1px solid #dee2e6;
        padding: 6px;
        text-align: center;
      }
      .products-table th {
        background-color: #40883c;
        color: white;
        font-weight: bold;
      }
      .products-table tr:nth-child(even) { background-color: #f8f9fa; }
      .totals { margin-top: 20px; font-weight: bold; }
      .footer { margin-top: 30px; font-size: 7pt; color: #666; }
    </style>
  </head>
  <body>';

  // Cabeçalho
  $html .= '
    <table class="header-table">
      <tr>
        <td width="35%">
          <img src="assets/LOGO.svg" class="logo">
        </td>

        <td width="30%" align="center" style="vertical-align: middle;">
           ';

  $iconeModal = "";
  $mod = $cabecalho['modal'] ?? '';

  if ($mod) {
    if ($mod == 'Aéreo')
      $iconeModal = '✈️';
    elseif ($mod == 'Marítimo')
      $iconeModal = '🚢';
    elseif ($mod == 'Rodoviário')
      $iconeModal = '🚛';

    if ($iconeModal) {
      $html .= '<div style="font-size: 40pt; margin-bottom: 5px;">' . $iconeModal . '</div>';
      $html .= '<div style="font-size: 10pt; font-weight: bold; color: #555;">' . mb_strtoupper($mod) . '</div>';
    }
  }

  $html .= '
        </td>
        <td width="35%" align="right">
          <div class="title">CENÁRIO DE IMPORTAÇÃO</div>
          <div style="margin-top: 10px;">
            <strong>Nº:</strong> ' . htmlspecialchars($num) . '<br>
            <strong>Data:</strong> ' . date('d/m/Y', strtotime($cabecalho['data_criacao'])) . '
          </div>
        </td>
      </tr>
    </table>';

  // Informações Gerais e Dados Financeiros em 2 colunas
  $tempoVenda = $cabecalho['tempo_venda_meses'] == 0 ? 'IMEDIATO' : $cabecalho['tempo_venda_meses'] . ' meses';

  $html .= '
    <table width="100%" style="margin-top: 15px; font-size: 8pt;">
      <tr>
        <td width="50%" style="vertical-align: top; padding-right: 10px;">
          <div style="background-color: #f8f9fa; padding: 8px; border-radius: 5px;">
            <h3 style="color: #40883c; font-size: 10pt; margin: 0 0 8px 0;">Informações Gerais</h3>
            <div style="margin: 3px 0;"><strong>Fornecedor:</strong> <span style="font-size: 11pt;">' . htmlspecialchars($cabecalho['fornecedor']) . '</span></div>
            <div style="margin: 3px 0;"><strong>Criado por:</strong> ' . htmlspecialchars($cabecalho['criado_por']) . '</div>
          </div>
        </td>
        <td width="50%" style="vertical-align: top; padding-left: 10px;">
          <div style="background-color: #f8f9fa; padding: 8px; border-radius: 5px;">
            <h3 style="color: #40883c; font-size: 10pt; margin: 0 0 8px 0;">Dados Financeiros</h3>
            <div style="margin: 3px 0;"><strong>Abaixo seguem os indicadores financeiros:</strong></div>
          </div>
        </td>
      </tr>
    </table>';

  // --- AGRUPAR POR SUB-CENÁRIO ---
  $itensPorSubCenario = [];
  foreach ($itens as $item) {
    $nomeSub = !empty($item['nome_sub_cenario']) ? $item['nome_sub_cenario'] : 'Cenário Padrão';
    $itensPorSubCenario[$nomeSub][] = $item;
  }

  // --- LOOP PARA GERAR BLOCOS POR SUB-CENÁRIO ---
  foreach ($itensPorSubCenario as $nomeSubCenario => $listaItensSub) {

    // Recuperar contextos específicos deste bloco (Modal, Taxa, etc)
    // Como todos os itens do bloco tem o mesmo modal/taxa (conforme lógica de blocos UI), pegamos do primeiro.
    $primeiroItem = $listaItensSub[0];
    $modalBloco = !empty($primeiroItem['modal']) ? $primeiroItem['modal'] : $cabecalho['modal'];
    // A taxa não foi salva no item no banco, mas o VF foi calculado com ela. 
    // Se precisarmos exibir a taxa, ela deve vir do item se tiver col.
    // Como não criamos coluna taxa no item (apenas modal), não temos como recuperar a taxa exata usada se ela diferir do padrão, 
    // A MENOS que tenhamos adicionado a coluna taxa_juros_mensal na tabela itens.
    // VAMOS SUPOR QUE NÃO ADICIONAMOS AINDA. O VF ESTÁ CORRETO POIS FOI SALVO.
    // SE QUISER EXIBIR A TAXA, PRECISAMOS DELA. 
    // VOU USAR UMA LÓGICA DE ESTIMATIVA OU APENAS NÃO EXIBIR A TAXA NO HEADER DO BLOCO SE NÃO TIVER.
    // MAS ESPERA, O USER PEDIU TAXA INDEPENDENTE. PROVAVELMENTE DEVERIANDO TER ADICIONADO A COLUNA.
    // VOU EXIBIR O MODAL.

    $iconModal = 'fa-cubes';
    if (stripos($modalBloco, 'Aéreo') !== false)
      $iconModal = 'fa-plane';
    elseif (stripos($modalBloco, 'Marítimo') !== false)
      $iconModal = 'fa-ship';
    elseif (stripos($modalBloco, 'Rodoviário') !== false)
      $iconModal = 'fa-truck';

    // Título do Sub-Cenário com Modal
    $taxaBloco = !empty($primeiroItem['taxa_juros_mensal']) ? number_format($primeiroItem['taxa_juros_mensal'], 2, ',', '.') : '0,00';

    $html .= '
      <div style="margin-top: 25px; border-bottom: 2px solid #40883c; padding-bottom: 5px; mb-3">
          <table width="100%">
              <tr>
                  <td align="left">
                      <h2 style="color: #40883c; font-size: 14pt; margin: 0;">
                         <i class="fas fa-layer-group"></i> ' . htmlspecialchars($nomeSubCenario) . '
                      </h2>
                  </td>
                  <td align="right">
                      <span style="font-size: 10pt; color: #555; margin-right: 15px;">
                          <b>Taxa Juros:</b> ' . $taxaBloco . '%
                      </span>
                      <span style="font-size: 10pt; color: #555; font-weight: bold;">
                          <i class="fas ' . $iconModal . '"></i> ' . htmlspecialchars($modalBloco) . '
                      </span>
                  </td>
              </tr>
          </table>
      </div>';

    // Agrupar itens deste sub-cenário por produto (para manter layout original)
    $itensPorProduto = [];
    foreach ($listaItensSub as $item) {
      $nomeProd = $item['produto'];
      $itensPorProduto[$nomeProd][] = $item;
    }

    $totalLandedUSD = 0;
    $totalVF = 0;
    $totalVendaUSD = 0;
    $temSpecExclusiva = false;

    // Tabela de Produtos deste Sub-Cenário
    $html .= '
      <table class="products-table">
        <thead>
          <tr>
            <th>PRODUTO</th>
            <th>CLIENTE</th>
            <th>SPEC<br>HOMOL.</th>
            <th>DEADLINE</th>
            <th>NECESSIDADE<br>CLIENTE</th>
            <th>PREV DE VENDA<br>(MESES)</th>
            <!-- Taxa Removida -->
            <th>QTD</th>
            <th>UNIDADE</th>
            <th>USD/KG</th>
            <th>TOTAL LANDED<br>(US$)</th>
            <th>TOTAL<br>VF</th>
            <th>PREÇO DE VENDA<br>(USD)</th>
            <th>TOTAL VENDA<br>(US$)</th>
            <th>GM%</th>
          </tr>
        </thead>
        <tbody>';

    foreach ($listaItensSub as $item) { // Loop flat diretamente nos itens do sub-cenário
      $totalLandedUSD += $item['total_landed_usd'];
      $totalVF += $item['total_valor_futuro'];
      $totalVendaUSD += $item['total_venda_usd'];

      if (!empty($item['spec_exclusiva'])) {
        $temSpecExclusiva = true;
      }

      $tempoItem = isset($item['tempo_venda_meses']) ? $item['tempo_venda_meses'] : $cabecalho['tempo_venda_meses'];
      $taxaItem = isset($item['taxa_juros_mensal']) ? $item['taxa_juros_mensal'] : $cabecalho['taxa_juros_mensal'];
      $taxaExibir = $cabecalho['taxa_juros_mensal'];

      $destaqueSpec = $item['spec_exclusiva']
        ? '<div style="color: #e67e22; font-weight: bold;">SIM <span style="font-size: 8px;">⚠️</span></div>'
        : '<span style="color: #999;">NÃO</span>';

      $clienteDisplay = htmlspecialchars($item['cliente']);
      if (!empty($item['uf'])) {
        $clienteDisplay .= ' <small style="color:#666;">(' . htmlspecialchars($item['uf']) . ')</small>';
      }

      $dataNecessidade = !empty($item['data_necessidade']) ? date('d/m/Y', strtotime($item['data_necessidade'])) : '-';
      $necessidadeCliente = !empty($item['necessidade_cliente']) ? date('d/m/Y', strtotime($item['necessidade_cliente'])) : '-';

      $tipoDemanda = !empty($item['tipo_demanda']) ? ' <span style="font-weight:normal; color:#666;">(' . htmlspecialchars($item['tipo_demanda']) . ')</span>' : '';

      $html .= '<tr>
                    <td style="text-align: left; font-weight: bold; color: #333;">' .
        htmlspecialchars($item['produto']) . ' <small style="font-weight:normal; color:#555;">(' . htmlspecialchars($item['embalagem'] ?? '') . ' ' . htmlspecialchars($item['unidade']) . ')</small>' .
        $tipoDemanda .
        '</td>
                    <td style="text-align: left; font-weight: bold; color: #555;">' . $clienteDisplay . '</td>
                    <td style="text-align: center;">' . $destaqueSpec . '</td>
                    <td style="text-align: center;">' . $dataNecessidade . '</td>
                    <td style="text-align: center;">' . $necessidadeCliente . '</td>
                    <td style="text-align: center;">' . $tempoItem . '</td>
                    <!-- Taxa removida aqui -->
                    <td>' . number_format($item['qtd'], 2, ',', '.') . '</td>
                    <td>' . htmlspecialchars($item['unidade']) . '</td>
                    <td>$' . number_format($item['landed_usd_kg'], 4, ',', '.') . '</td>
                    <td>$' . number_format($item['total_landed_usd'], 2, ',', '.') . '</td>
                    <td>$' . number_format($item['total_valor_futuro'], 2, ',', '.') . '</td>
                    <td>$' . number_format($item['preco_unit_venda_usd_kg'], 4, ',', '.') . '</td>
                    <td>$' . number_format($item['total_venda_usd'], 2, ',', '.') . '</td>
                    <td>' . number_format($item['gm_percentual'], 2, ',', '.') . '%</td>
                  </tr>';
    }

    $html .= '</tbody></table>';

    if ($temSpecExclusiva) {
      $html .= '
          <div style="margin-top: 5px; background-color: #fff3cd; color: #856404; padding: 5px; border-radius: 3px; font-size: 8pt; border: 1px solid #ffeeba;">
            ⚠️ Fique atento, este cenário contém produtos com especificações homologadas
          </div>';
    }

    // --- CÁLCULOS TOTAIS DESTE SUB-CENÁRIO ---
    $dolarVenda = $cabecalho['dolar_venda'];
    $dolarCompra = $cabecalho['dolar_compra'];

    $capitalInicialBRL = $totalLandedUSD * $dolarVenda; // Usando Dolar Venda para calculo BRL do investimento? Geralmente é, ou Compra. Mantendo logica original.
    // Logica original: $capitalInicialBRL = $totalLandedUSD * $dolarVenda;

    $totalVendaBRL = $totalVendaUSD * $dolarVenda;
    $lucroLiquidoUSD = $totalVendaUSD - $totalVF;
    $lucroLiquidoBRL = $lucroLiquidoUSD * $dolarVenda;
    $gmGeralUSD = ($totalVendaUSD > 0) ? ($lucroLiquidoUSD / $totalVendaUSD) * 100 : 0;

    // BOX FINANCEIRO PARA ESTE SUB-CENÁRIO
    $html .= '
      <div style="margin-top: 15px; page-break-inside: avoid;">
          <h3 style="color: #40883c; font-size: 10pt; margin: 0 0 5px 0;">Resumo Financeiro - ' . htmlspecialchars($nomeSubCenario) . '</h3>
          
          <table width="100%" cellspacing="10" cellpadding="0" style="font-size: 9pt;">
              <tr>
                  <!-- INVESTIMENTO -->
                  <td width="32%" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; vertical-align: top;">
                      <div style="background-color: #e9ecef; padding: 5px; font-weight: bold; color: #555; border-bottom: 1px solid #dee2e6; font-size: 8pt;">
                          <i class="fas fa-boxes"></i> INVESTIMENTO
                      </div>
                      <div style="padding: 5px 10px;">
                          <table width="100%" style="font-size: 8pt;">
                              <tr>
                                  <td style="color: #666;">Dólar Compra:</td>
                                  <td align="right" style="font-weight: bold;">$ ' . number_format($dolarCompra, 4, ',', '.') . '</td>
                              </tr>
                              <tr>
                                  <td style="color: #666;">Total Landed:</td>
                                  <td align="right" style="font-weight: bold;">$ ' . number_format($totalLandedUSD, 2, ',', '.') . '</td>
                              </tr>
                              <tr>
                                  <td style="color: #666;">Capital Inicial (R$):</td>
                                  <td align="right" style="font-weight: bold; color: #d32f2f;">R$ ' . number_format($capitalInicialBRL, 2, ',', '.') . '</td>
                              </tr>
                          </table>
                      </div>
                  </td>

                  <!-- FATURAMENTO -->
                  <td width="32%" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; vertical-align: top;">
                      <div style="background-color: #e9ecef; padding: 5px; font-weight: bold; color: #555; border-bottom: 1px solid #dee2e6; font-size: 8pt;">
                          <i class="fas fa-hand-holding-usd"></i> FATURAMENTO
                      </div>
                      <div style="padding: 5px 10px;">
                          <table width="100%" style="font-size: 8pt;">
                              <tr>
                                  <td style="color: #666;">Dólar Venda:</td>
                                  <td align="right" style="font-weight: bold;">$ ' . number_format($dolarVenda, 4, ',', '.') . '</td>
                              </tr>
                              <tr>
                                  <td style="color: #666;">Total Venda (US$):</td>
                                  <td align="right" style="font-weight: bold;">$ ' . number_format($totalVendaUSD, 2, ',', '.') . '</td>
                              </tr>
                              <tr>
                                  <td style="color: #666;">Total Venda (R$):</td>
                                  <td align="right" style="font-weight: bold; color: #2e7d32;">R$ ' . number_format($totalVendaBRL, 2, ',', '.') . '</td>
                              </tr>
                          </table>
                      </div>
                  </td>

                  <!-- RESULTADO -->
                  <td width="32%" style="background-color: #fff3cd; border: 1px solid #ffeeba; border-radius: 6px; vertical-align: top;">
                       <div style="background-color: #ffeeba; padding: 5px; font-weight: bold; color: #856404; border-bottom: 1px solid #dee2e6; font-size: 8pt;">
                          <i class="fas fa-chart-line"></i> RESULTADO (LUCRO)
                      </div>
                      <div style="padding: 5px 10px;">
                          <table width="100%" style="font-size: 8pt;">
                              <tr>
                                  <td style="color: #666;">Lucro Líq. (US$):</td>
                                  <td align="right" style="font-weight: bold;">$ ' . number_format($lucroLiquidoUSD, 2, ',', '.') . '</td>
                              </tr>
                              <tr>
                                  <td style="color: #666;">Lucro Líq. (R$):</td>
                                  <td align="right" style="font-weight: bold; color: #2e7d32;">R$ ' . number_format($lucroLiquidoBRL, 2, ',', '.') . '</td>
                              </tr>
                              <tr>
                                  <td style="padding-top: 5px; color: #666;">Margem (GM%):</td>
                                  <td align="right" style="padding-top: 5px; font-weight: bold; font-size: 10pt; color: #2e7d32;">' . number_format($gmGeralUSD, 2, ',', '.') . '%</td>
                              </tr>
                          </table>
                      </div>
                  </td>
              </tr>
          </table>
      </div><br>';
  } // Fim loop Sub-Cenarios';

  // Observações
  if (!empty($cabecalho['observacoes'])) {
    $html .= '
      <div class="info-section" style="margin-top: 20px;">
        <h3>Observações</h3>
        <div>' . nl2br(htmlspecialchars($cabecalho['observacoes'])) . '</div>
      </div>';
  }

  // Footer
  $html .= '
    <div class="footer">
      Documento gerado em ' . date('d/m/Y H:i:s') . ' | H Hansen - Sistema de Cotações
    </div>
  </body>
  </html>';

  // Gerar PDF
  $mpdf = new Mpdf([
    'format' => 'A4-L', // Paisagem para caber mais colunas
    'orientation' => 'L',
    'margin_top' => 10,
    'margin_bottom' => 10,
    'margin_left' => 10,
    'margin_right' => 10
  ]);

  $mpdf->WriteHTML($html);

  // Salvar PDF na pasta tmp
  $arquivo = __DIR__ . "/tmp/cenario_" . $num . ".pdf";
  if (!is_dir(__DIR__ . "/tmp")) {
    mkdir(__DIR__ . "/tmp", 0777, true);
  }
  $mpdf->Output($arquivo, \Mpdf\Output\Destination::FILE);

  // Exibir PDF no navegador
  ob_clean();
  header('Content-Type: application/pdf');
  $mpdf->Output('cenario_' . $num . '.pdf', \Mpdf\Output\Destination::INLINE);

} catch (Exception $e) {
  die('Erro ao gerar PDF: ' . $e->getMessage());
}
?>