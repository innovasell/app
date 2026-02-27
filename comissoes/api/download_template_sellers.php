<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="modelo_vendedores.csv"');

$output = fopen('php://output', 'w');
// Add BOM or set charset if needed, but simple csv usually fine.
// Header
fputcsv($output, ['Numero_NF', 'Nome_Vendedor'], ';');

// Example data
fputcsv($output, ['12345', 'Joao Silva'], ';');
fputcsv($output, ['12346', 'Maria Souza'], ';');

fclose($output);
