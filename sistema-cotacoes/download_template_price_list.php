<?php
// download_template_price_list.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=modelo_price_list.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
// Matches the logic in importer: FABRICANTE;CLASSIFICAÇÃO;COD PRODUTO;PRODUTO;FRACIONADO;EMBALAGEM;LEAD TIME;NET USD
fputcsv($output, array('FABRICANTE', 'CLASSIFICAÇÃO', 'COD PRODUTO', 'PRODUTO', 'FRACIONADO', 'EMBALAGEM', 'LEAD TIME', 'NET USD'), ';');

// Add some sample data
fputcsv($output, array('FABRICANTE EXEMPLO', 'CLASSIFICACAO A', '12345', 'PRODUTO TESTE A', 'N', '10,00', 'IMEDIATO', '150,50'), ';');
fputcsv($output, array('OUTRO FABRICANTE', 'CLASSIFICACAO B', '67890', 'PRODUTO TESTE B', 'S', '5,000', '15 DIAS', '25,00'), ';');

fclose($output);
exit();
?>