<?php
session_start();
require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';

try {
    $batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
    
    if (!$batch_id) {
        die("Batch ID não fornecido.");
    }

    $stmt = $pdo->prepare("SELECT * FROM com_commission_items WHERE batch_id = ? ORDER BY representante ASC, cliente ASC");
    $stmt->execute([$batch_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        die("Nenhum item encontrado para este lote.");
    }

    // Header para download CSV
    $filename = "comissoes_lote_{$batch_id}_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // Adiciona BOM UTF-8 para Excel
    fputs($output, "\xEF\xBB\xBF");

    // Cabeçalhos
    $headers = [
        'Data NF', 'Série/Nota', 'Pedido', 'Representante', 'Cliente', 
        'Código', 'Descrição', 'Embalagem', 'Qtde', 'Valor Bruto (R$)', 
        'Venda Net (R$)', 'Preço Net/Un (R$)', 'Preço Lista BRL', 
        'Desconto (%)', 'Comissão Base (%)', 'PM (Dias)', 'Semanas', 
        'Ajuste PM (%)', 'Comissão Final (%)', 'Valor Comissão', 
        'Aprovação', 'Teto Atingido'
    ];
    fputcsv($output, $headers, ';');

    // Linhas
    foreach ($items as $item) {
        $row = [
            $item['data_nf'] ? date('d/m/Y', strtotime($item['data_nf'])) : '',
            $item['nfe'],
            $item['pedido'],
            $item['representante'],
            $item['cliente'],
            $item['codigo'],
            $item['descricao'],
            $item['embalagem'],
            number_format($item['qtde'], 2, ',', ''),
            number_format($item['valor_bruto'], 2, ',', ''),
            number_format($item['venda_net'], 2, ',', ''),
            number_format($item['preco_net_un'], 4, ',', ''),
            number_format($item['preco_lista_brl'], 4, ',', ''),
            number_format($item['desconto_pct'] * 100, 2, ',', '') . '%',
            number_format($item['comissao_base_pct'] * 100, 2, ',', '') . '%',
            number_format($item['pm_dias'], 1, ',', ''),
            number_format($item['pm_semanas'], 1, ',', ''),
            number_format($item['ajuste_prazo_pct'] * 100, 2, ',', '') . '%',
            number_format($item['comissao_final_pct'] * 100, 2, ',', '') . '%',
            number_format($item['valor_comissao'], 2, ',', ''),
            $item['flag_aprovacao'] ? 'Sim' : 'Não',
            $item['flag_teto'] ? 'Sim' : 'Não'
        ];
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}
