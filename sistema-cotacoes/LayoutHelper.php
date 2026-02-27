<?php

class LayoutHelper
{

    // Configurações de Cores e Estilo
    const COLOR_PRIMARY = '#0047fa'; // Azul vibrante Innovasell
    const COLOR_SECONDARY = '#0a1e42'; // Azul escuro
    const COLOR_ACCENT = '#f0f4f8'; // Fundo claro
    const FONT_FAMILY = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";

    public static function getPdfHtml($dadosOrcamento, $dadosCliente, $dadosRepresentante, $numOrcamento, $dataOrcamento, $incluirNet)
    {
        $logoPath = __DIR__ . '/assets/logo_nova.png';
        $logoSrc = 'assets/logo_nova.png';
        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoSrc = 'data:image/png;base64,' . $logoData;
        }

        // Dados Cliente
        $razao_social = htmlspecialchars($dadosCliente['razao_social'] ?? $dadosOrcamento[0]['RAZÃO SOCIAL']);
        $cnpj = htmlspecialchars($dadosCliente['cnpj'] ?? 'Não informado');
        $contato = htmlspecialchars($dadosCliente['contato'] ?? '');
        $email_cli = htmlspecialchars($dadosCliente['email'] ?? '');
        $tel_cli = htmlspecialchars($dadosCliente['telefone'] ?? '');
        $uf = htmlspecialchars($dadosCliente['uf'] ?? $dadosOrcamento[0]['UF']);

        // Dados Representante
        $rep_nome = htmlspecialchars(ucwords(strtolower($dadosRepresentante['nome'] . ' ' . $dadosRepresentante['sobrenome'])));
        $rep_email = htmlspecialchars($dadosRepresentante['email']);
        $rep_tel = htmlspecialchars($dadosRepresentante['telefone'] ?? '');

        // Estilos CSS (Landscape Otimizado)
        $css = "
        <style>
            @page { margin: 10mm; }
            body { font-family: " . self::FONT_FAMILY . "; color: #333; font-size: 9pt; line-height: 1.3; }
            table { width: 100%; border-collapse: collapse; border-spacing: 0; }
            
            .header-bg { background-color: " . self::COLOR_ACCENT . "; padding: 20px; border-bottom: 3px solid " . self::COLOR_PRIMARY . "; }
            .logo { height: 50px; }
            .company-details { font-size: 8pt; color: #555; margin-top: 5px; }
            
            .quote-highlight { 
                color: " . self::COLOR_SECONDARY . "; 
                text-align: right;
                width: 100%;
                float: right;
            }
            .quote-num { font-size: 16pt; font-weight: bold; display: block; }
            .quote-date { font-size: 9pt; color: #666; }

            .section-title { 
                color: " . self::COLOR_SECONDARY . "; 
                font-size: 10pt; 
                font-weight: bold; 
                border-bottom: 1px solid #ddd; 
                margin: 15px 0 5px 0; 
                padding-bottom: 2px;
                text-transform: uppercase;
            }

            .info-grid { width: 100%; margin-bottom: 10px; }
            .info-grid td { vertical-align: top; padding: 5px; }
            .label { font-weight: bold; color: #666; font-size: 7pt; text-transform: uppercase; }
            .value { font-weight: 600; color: #000; font-size: 9pt; }

            /* Tabela de Produtos */
            .product-table { margin-top: 15px; width: 100%; }
            .product-table th { 
                background-color: " . self::COLOR_SECONDARY . "; 
                color: white; 
                padding: 8px 4px; 
                font-size: 8pt; 
                text-align: center; 
                border: 1px solid " . self::COLOR_SECONDARY . ";
            }
            .product-table td { 
                padding: 6px 4px; 
                border: 1px solid #e0e0e0; 
                font-size: 8pt; 
                text-align: center; 
            }
            .product-table tr:nth-child(even) { background-color: #f8f9fa; }
            .fw-bold { font-weight: bold; }
            .text-left { text-align: left !important; }

            /* Condições */
            .conditions-box { 
                background-color: #fcfcfc; 
                border: 1px solid #eee; 
                padding: 15px; 
                margin-top: 20px; 
                border-radius: 4px;
                font-size: 8pt;
            }
            .conditions-box h5 { margin: 0 0 10px 0; color: " . self::COLOR_PRIMARY . "; font-size: 9pt; }
            .conditions-list { margin: 0; padding-left: 20px; }
            .conditions-list li { margin-bottom: 2px; }

            .footer { 
                margin-top: 30px; 
                text-align: center; 
                font-size: 7pt; 
                color: #aaa; 
                border-top: 1px solid #eee; 
                padding-top: 10px; 
            }
        </style>";

        $html = "
        <html>
        <head>$css</head>
        <body>
            
            <!-- Header -->
            <table class='header-bg' style='width:100%'>
                <tr>
                    <td style='width: 60%; vertical-align: middle;'>
                        <img src='$logoSrc' class='logo'><br>
                        <div class='company-details'>
                            Rua Guaricanga, 169 - Lapa - São Paulo - SP - 05075-030<br>
                            www.innovasell.com.br
                        </div>
                    </td>
                    <td style='width: 40%; vertical-align: middle; text-align: right;'>
                        <div class='quote-highlight'>
                            <span class='quote-num'>Orçamento Nº $numOrcamento</span><br>
                            <span class='quote-date'>Data: $dataOrcamento | Validade: 30 dias</span>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Dados Cliente e Representante -->
            <table class='info-grid'>
                <tr>
                    <td width='50%' style='background-color: #fdfdfd; border-radius: 5px; border: 1px solid #eee;'>
                        <div class='section-title'>Dados do Cliente</div>
                        <table>
                            <tr>
                                <td><div class='label'>Razão Social</div><div class='value'>$razao_social</div></td>
                                <td><div class='label'>CNPJ</div><div class='value'>$cnpj</div></td>
                            </tr>
                            <tr>
                                <td><div class='label'>Contato</div><div class='value'>$contato</div></td>
                                <td><div class='label'>Email</div><div class='value'>$email_cli</div></td>
                            </tr>
                            <tr>
                                <td><div class='label'>Telefone</div><div class='value'>$tel_cli</div></td>
                                <td><div class='label'>UF</div><div class='value'>$uf</div></td>
                            </tr>
                        </table>
                    </td>
                    <td width='2%'></td>
                    <td width='48%' style='background-color: #f4faff; border-radius: 5px; border: 1px solid #d0e3f0;'>
                        <div class='section-title' style='color: #0047fa; border-color: #b3d7ff;'>Representante Comercial</div>
                        <table>
                            <tr>
                                <td><div class='label'>Nome</div><div class='value'>$rep_nome</div></td>
                            </tr>
                            <tr>
                                <td><div class='label'>Email</div><div class='value'>$rep_email</div></td>
                            </tr>
                            <tr>
                                <td><div class='label'>Telefone</div><div class='value'>$rep_tel</div></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- Tabela Produtos -->
            <table class='product-table'>
                <thead>
                    <tr>
                        <th width='8%'>CÓD</th>
                        <th width='25%'>PRODUTO</th>
                        <th width='10%'>EMB/KG</th>
                        <th width='8%'>NCM</th>
                        <th width='8%'>QTD</th>
                        <th width='6%'>IPI</th>
                        <th width='6%'>ICMS</th>
                        " . ($incluirNet ? "<th width='10%'>PREÇO NET<br>USD/KG</th>" : "") . "
                        <th width='10%'>PREÇO FULL<br>USD/KG</th>
                        <th width='12%'>TOTAL<br>USD</th>
                        <th width='10%'>DISPON.</th>
                    </tr>
                </thead>
                <tbody>";

        foreach ($dadosOrcamento as $d) {
            $v_num = floatval(str_replace(',', '.', $d['VOLUME']));
            $emb_num = floatval(str_replace(',', '.', $d['EMBALAGEM_KG']));
            $p_full_num = floatval(str_replace(',', '.', $d['PREÇO FULL USD/KG']));
            $total_item = $v_num * $p_full_num;

            // Formatação
            $vol_fmt = number_format($v_num, 3, ',', '.') . " KG";
            $emb_fmt = number_format($emb_num, 3, ',', '.') . " KG";
            $p_full_fmt = "USD " . number_format($d["PREÇO FULL USD/KG"], 2, ',', '.');
            $total_fmt = "USD " . number_format($total_item, 2, ',', '.');
            $ipi_fmt = number_format((float) str_replace("%", "", $d["IPI %"]), 2, ',', '.') . '%';
            $icms_fmt = number_format((float) str_replace("%", "", $d["ICMS"]), 2, ',', '.') . '%';

            $p_net_td = "";
            if ($incluirNet) {
                $p_net_fmt = "USD " . number_format($d["PREÇO NET USD/KG"], 2, ',', '.');
                $p_net_td = "<td>$p_net_fmt</td>";
            }

            $html .= "<tr>
                <td>{$d['COD DO PRODUTO']}</td>
                <td class='text-left fw-bold'>{$d['PRODUTO']}</td>
                <td>$emb_fmt</td>
                <td>{$d['NCM']}</td>
                <td>$vol_fmt</td>
                <td>$ipi_fmt</td>
                <td>$icms_fmt</td>
                $p_net_td
                <td class='fw-bold' style='color:#0047fa'>$p_full_fmt</td>
                <td class='fw-bold'>$total_fmt</td>
                <td style='font-size:7pt'>{$d['DISPONIBILIDADE']}</td>
            </tr>";
        }

        $html .= "
                </tbody>
            </table>

            <!-- Condições -->
            <div class='conditions-box'>
                <h5>CONDIÇÕES COMERCIAIS E OBSERVAÇÕES</h5>
                <ol class='conditions-list'>
                    <li><strong>Preço em Dólar:</strong> Convertido na data de Emissão da Nota Fiscal pela taxa do dólar Ptax do dia anterior ao Faturamento;</li>
                    <li><strong>Preço Full:</strong> Inclui PIS, COFINS e ICMS;</li>
                    <li><strong>IPI:</strong> Não Incluso;</li>
                    <li><strong>Condição de Pagamento:</strong> Primeira compra à vista;</li>
                    <li><strong>Frete FOB:</strong> Todos os pedidos abaixo de R$ 3.000,00 ou para destinatários fora da Grande São Paulo;</li>
                    <li><strong>Frete CIF:</strong> Somente para pedidos acima de R$ 3.000,00 para Grande São Paulo (SOB CONSULTA);</li>
                    <li><strong>Validade da Proposta:</strong> 30 dias.</li>
                </ol>
            </div>

            <div class='footer'>
                Innovasell Cloud | Sistema Integrado de Gestão | " . date('Y') . "
            </div>

        </body>
        </html>";

        return $html;
    }

    public static function getEmailHtml($dadosOrcamento, $dadosCliente, $dadosRepresentante, $numOrcamento, $dataOrcamento, $incluirNet)
    {
        // Versão simplificada mas bonita para email
        // CSS inline obrigatório

        $razao_social = htmlspecialchars($dadosCliente['razao_social'] ?? $dadosOrcamento[0]['RAZÃO SOCIAL']);
        $font = "font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;";

        $html = "
        <div style=\"background-color: #f4f4f4; padding: 20px; $font\">
            <div style=\"max-width: 800px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05);\">
                
                <div style=\"background-color: #0a1e42; color: white; padding: 20px; text-align: center;\">
                    <h2 style=\"margin: 0; font-size: 24px;\">Orçamento Nº $numOrcamento</h2>
                    <p style=\"margin: 5px 0 0 0; opacity: 0.8;\">Innovasell Cotações</p>
                </div>

                <div style=\"padding: 30px;\">
                    <p style=\"font-size: 16px; color: #333;\">Olá, <strong>$razao_social</strong>.</p>
                    <p style=\"font-size: 14px; color: #555;\">Segue abaixo o resumo da sua cotação gerada em <strong>$dataOrcamento</strong>.</p>

                    <table style=\"width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px;\">
                        <tr style=\"background-color: #0047fa; color: white;\">
                            <th style=\"padding: 10px; text-align: left;\">Produto</th>
                            <th style=\"padding: 10px;\">Qtd</th>
                            <th style=\"padding: 10px;\">Preço Full</th>
                            <th style=\"padding: 10px;\">Total</th>
                        </tr>";

        foreach ($dadosOrcamento as $d) {
            $v_num = floatval(str_replace(',', '.', $d['VOLUME']));
            $p_full_num = floatval(str_replace(',', '.', $d['PREÇO FULL USD/KG']));
            $total_item = $v_num * $p_full_num;

            $vol_fmt = number_format($v_num, 3, ',', '.') . " KG";
            $p_full_fmt = "USD " . number_format($d["PREÇO FULL USD/KG"], 2, ',', '.');
            $total_fmt = "USD " . number_format($total_item, 2, ',', '.');

            $html .= "
                        <tr style=\"border-bottom: 1px solid #eee;\">
                            <td style=\"padding: 10px; color: #333;\"><strong>{$d['PRODUTO']}</strong></td>
                            <td style=\"padding: 10px; text-align: center;\">$vol_fmt</td>
                            <td style=\"padding: 10px; text-align: center;\">$p_full_fmt</td>
                            <td style=\"padding: 10px; text-align: center; color: #0047fa; font-weight: bold;\">$total_fmt</td>
                        </tr>";
        }

        $html .= "
                    </table>

                    <div style=\"margin-top: 30px; background-color: #eef5ff; padding: 15px; border-radius: 5px; color: #004085; font-size: 13px;\">
                        <strong>📄 Anexo PDF Oficial:</strong> O detalhamento completo, incluindo impostos, NCM, condições comerciais e dados bancários encontra-se no arquivo PDF anexado.
                    </div>

                    <p style=\"margin-top: 30px; font-size: 12px; color: #999; text-align: center;\">
                        Innovasell - Rua Guaricanga, 169 - Lapa - SP<br>
                        Este é um e-mail automático.
                    </p>
                </div>
            </div>
        </div>";

        return $html;
    }
}
?>