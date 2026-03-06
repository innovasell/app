<?php
session_start();
require_once 'conexao.php';

// Opcional: checar se está logado
if (!isset($_SESSION['representante_email'])) {
    header("Location: index.html");
    exit();
}

try {
    // Busca todos os dados da Price List
    $stmt = $pdo->query("SELECT fabricante, classificacao, codigo, produto, fracionado, embalagem, lead_time, preco_net_usd FROM cot_price_list ORDER BY fabricante, codigo");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Configura os headers para download do arquivo CSV
    $filename = "Price_List_Export_" . date('Ymd_His') . ".csv";
    
    // Força o download e define o tipo de conteúdo como CSV UTF-8
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Abre o ponteiro para o output do PHP
    $output = fopen('php://output', 'w');

    // Adiciona o BOM do UTF-8 para o Excel abrir com a acentuação correta automaticamente
    fputs($output, "\xEF\xBB\xBF");

    // Escreve o cabeçalho do CSV
    fputcsv($output, ['FABRICANTE', 'CLASSIFICAÇÃO', 'CODIGO', 'PRODUTO', 'FRACIONADO', 'EMBALAGEM', 'LEAD TIME', 'PREÇO NET USD'], ';');

    // Escreve os dados linha a linha no CSV
    foreach ($data as $row) {
        // Formata os valores numéricos com vírgula para manter compatibilidade no Excel no Brasil
        $preco_formatado = number_format((float)$row['preco_net_usd'], 4, ',', '');
        $embalagem_formatada = str_replace('.', ',', $row['embalagem']);

        fputcsv($output, [
            $row['fabricante'],
            $row['classificacao'],
            $row['codigo'],
            $row['produto'],
            $row['fracionado'],
            $embalagem_formatada,
            $row['lead_time'],
            $preco_formatado
        ], ';');
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    die("Erro ao gerar exportação da Price List: " . $e->getMessage());
}
?>
