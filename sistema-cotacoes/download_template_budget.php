<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html'); exit();
}

$headers = [
    'CNPJ',
    'RAZÃO SOCIAL',
    'CLIENTE ORIGEM',
    'TERCEIRISTA',
    'PRODUTO',
    'Fabricante',
    'Concat',
    'Tipo',
    'Vendedor',
    'Vendedor ajustado',
    'Embalagem',
    'TOTAL KG ENTRE 2017 a 2024',
    'KG Realizado 2025',
    'KG Orçado 2026',
    'KG Realizado 2026',
    'Preço Realizado entre 17 e 23 (Média)',
    'Preço Realizado 2025 (Média)',
    'Reajuste Sugerido',
    'Preço Sugerido ',
    'Preço Orçado 2026',
    'Preço Realizado 2026',
    'Preço Realizado entre 17 e 23 (Média) USD',
    'Preço Realizado 2025 (Média) USD',
    'Preço Sugerido  USD',
    'Preço Orçado 2026  USD',
    'Preço Realizado 2026  USD',
    'Venda NET Realizado 2025',
    'Venda NET  Orçado 2026',
    'Venda NET  Realizado 2026',
    'Custo Unt Realizado 2025',
    'Custo Unt orçado dani 2026',
    'comparativo custo dani x cuso orçado 2026',
    'Custo Unt  Orçado 2026',
    'Custo Unt  Realizado 2026',
    'Custo Total Realizado 2025',
    'Custo Total  Orçado 2026',
    'Custo Total  Realizado 2026',
    'Lucro Liquido Realizado 2025',
    'Lucro Liquido Orçado 2026',
    'Lucro Liquido Realizado 2026',
    'GM% Realizado 2025',
    'GM% Orçado 2026',
    'GM% Realizado 2026',
    'LOTE ECONÔMICO (KG)',
    'EXW 2026 (KG) USD',
    'EXW 2026 (TOTAL) USD',
    'LANDED 2026 (KG) USD',
    'LANDED 2026 (TOTAL)',
    'COME TARIOS SUPPLY',
    'PREÇO AJUSTADO',
];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_budget_cliente.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM para compatibilidade com Excel BR
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
fputcsv($output, $headers, ';');
fclose($output);
exit();
