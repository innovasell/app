<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';

try {
    $batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
    
    if (!$batch_id) {
        throw new Exception("Batch ID não fornecido.");
    }

    $stmt = $pdo->prepare("SELECT * FROM com_commission_items WHERE batch_id = ? ORDER BY representante ASC, cliente ASC");
    $stmt->execute([$batch_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatar números para visualização
    foreach ($items as &$item) {
        $item['data_nf'] = !empty($item['data_nf']) ? date('d/m/Y', strtotime($item['data_nf'])) : null;
        $item['valor_bruto'] = (float)$item['valor_bruto'];
        $item['venda_net'] = (float)$item['venda_net'];
        $item['preco_net_un'] = (float)$item['preco_net_un'];
        $item['preco_lista_brl'] = (float)$item['preco_lista_brl'];
        $item['desconto_pct_fmt'] = number_format($item['desconto_pct'] * 100, 2, ',', '.') . '%';
        $item['comissao_base_pct'] = (float)$item['comissao_base_pct'];
        $item['comissao_base_fmt'] = number_format($item['comissao_base_pct'] * 100, 2, ',', '.') . '%';
        $item['ajuste_prazo_pct'] = (float)$item['ajuste_prazo_pct'];
        $item['ajuste_prazo_fmt'] = number_format($item['ajuste_prazo_pct'] * 100, 2, ',', '.') . '%';
        $item['comissao_final_pct'] = (float)$item['comissao_final_pct'];
        $item['comissao_final_fmt'] = number_format($item['comissao_final_pct'] * 100, 2, ',', '.') . '%';
        $item['valor_comissao'] = (float)$item['valor_comissao'];
    }

    echo json_encode(['success' => true, 'data' => $items]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
