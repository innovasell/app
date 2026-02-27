<?php
// teste_mpdf.php

require_once __DIR__ . '/vendor/autoload.php';

echo "Iniciando teste do mPDF...<br>";

try {
    // 1. Cria uma nova instância do mPDF sem configurações extras
    $mpdf = new \Mpdf\Mpdf([
        'tempDir' => __DIR__ . '/temp' // Garante que o mPDF tem uma pasta para escrever
    ]);

    // 2. Define um cabeçalho e rodapé de teste MUITO simples
    $mpdf->SetHTMLHeader('<div style="text-align: center; font-weight: bold; border-bottom: 1px solid #000;">CABEÇALHO DE TESTE</div>');
    $mpdf->SetHTMLFooter('<div style="text-align: center; border-top: 1px solid #000;">RODAPÉ DE TESTE - Página {PAGENO}</div>');

    // 3. Define o HTML principal, incluindo a regra @page que reserva as margens
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <style>
        @page {
            /* Reserva 30mm no topo e 20mm embaixo para o conteúdo acima */
            margin-top: 30mm;
            margin-bottom: 20mm;
            margin-left: 20mm;
            margin-right: 20mm;
        }
    </style>
    </head>
    <body>
        <h1>Página de Teste do mPDF</h1>
        <p>Este é o conteúdo principal da página.</p>
        <p>Se você consegue ver o cabeçalho e o rodapé nesta página,
           significa que a biblioteca mPDF está instalada e funcionando corretamente.
           O problema, então, está na lógica do seu script `processar_formula.php`.</p>
    </body>
    </html>
    ';

    // 4. Escreve o HTML para o buffer do PDF
    $mpdf->WriteHTML($html);

    // 5. Gera o PDF e o envia diretamente para o navegador para visualização
    echo "Gerando PDF para o navegador...";
    $mpdf->Output('teste_mpdf.pdf', 'I'); // 'I' significa "inline" (mostrar no navegador)
    exit;

} catch (\Mpdf\MpdfException $e) {
    die("O mPDF encontrou um erro: " . $e->getMessage());
}