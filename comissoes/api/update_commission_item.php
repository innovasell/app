<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';

try {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) $body = $_POST;

    $id      = isset($body['id'])     ? (int)$body['id']     : 0;
    $action  = isset($body['action']) ? trim($body['action']) : 'update';

    if (!$id) throw new Exception("ID não fornecido.");

    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM com_commission_items WHERE id = ?");
        $stmt->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Item excluído.']);
        exit;
    }

    // UPDATE - campos permitidos
    $allowed = [
        'representante', 'cliente', 'cfop', 'codigo', 'descricao', 'embalagem',
        'qtde', 'valor_bruto', 'icms', 'pis', 'cofins', 'venda_net',
        'preco_net_un', 'preco_lista_brl', 'pm_dias', 'obs', 'flag_aprovacao'
    ];

    $fields = [];
    $values = [];

    foreach ($allowed as $col) {
        if (array_key_exists($col, $body)) {
            $fields[] = "$col = ?";
            $values[] = $body[$col];
        }
    }

    if (empty($fields)) throw new Exception("Nenhum campo para atualizar.");

    // Recalcular percentuais se valores financeiros foram alterados
    $recalc = array_intersect($allowed, array_keys($body));
    $shouldRecalc = !empty(array_intersect(['venda_net','preco_lista_brl','pm_dias'], array_keys($body)));

    if ($shouldRecalc) {
        // Busca os dados atuais
        $cur = $pdo->prepare("SELECT * FROM com_commission_items WHERE id=?");
        $cur->execute([$id]);
        $row = $cur->fetch(PDO::FETCH_ASSOC);

        $venda_net      = isset($body['venda_net'])      ? (float)$body['venda_net']      : (float)$row['venda_net'];
        $preco_lista_brl= isset($body['preco_lista_brl'])? (float)$body['preco_lista_brl']: (float)$row['preco_lista_brl'];
        $pm_dias        = isset($body['pm_dias'])        ? (float)$body['pm_dias']        : (float)$row['pm_dias'];
        $preco_net_un   = isset($body['preco_net_un'])   ? (float)$body['preco_net_un']   : (float)$row['preco_net_un'];

        $desconto_brl = max(0, $preco_lista_brl - $preco_net_un);
        $desconto_pct = $preco_lista_brl > 0 ? $desconto_brl / $preco_lista_brl : 0;

        $comissao_base_pct = 0.01;
        if ($desconto_pct <= 0)        $comissao_base_pct = 0.0100;
        elseif ($desconto_pct <= 0.05) $comissao_base_pct = 0.0090;
        elseif ($desconto_pct <= 0.10) $comissao_base_pct = 0.0070;
        elseif ($desconto_pct <= 0.15) $comissao_base_pct = 0.0050;
        elseif ($desconto_pct <= 0.20) $comissao_base_pct = 0.0040;
        else                           $comissao_base_pct = 0.0025;

        $pm_semanas = $pm_dias / 7;
        $ajuste_prazo_pct = -(($pm_dias - 28) / 7 * 0.0005);
        $comissao_final_pct = max(0.0005, $comissao_base_pct + $ajuste_prazo_pct);

        if ($preco_lista_brl == 0) $comissao_final_pct = 0;

        $valor_comissao = $venda_net * $comissao_final_pct;
        $flag_teto = $valor_comissao > 25000 ? 1 : 0;
        if ($flag_teto) {
            $valor_comissao = 25000 + ($valor_comissao - 25000) * 0.10;
        }
        $flag_aprovacao = ($desconto_pct > 0.20 || $pm_dias > 42) ? 1 : 0;

        $fields[] = "desconto_brl = ?";        $values[] = round($desconto_brl, 4);
        $fields[] = "desconto_pct = ?";        $values[] = round($desconto_pct, 4);
        $fields[] = "comissao_base_pct = ?";   $values[] = round($comissao_base_pct, 4);
        $fields[] = "pm_semanas = ?";          $values[] = round($pm_semanas, 4);
        $fields[] = "ajuste_prazo_pct = ?";    $values[] = round($ajuste_prazo_pct, 4);
        $fields[] = "comissao_final_pct = ?";  $values[] = round($comissao_final_pct, 4);
        $fields[] = "valor_comissao = ?";      $values[] = round($valor_comissao, 2);
        $fields[] = "flag_teto = ?";           $values[] = $flag_teto;
        $fields[] = "flag_aprovacao = ?";      $values[] = $flag_aprovacao;
        $fields[] = "lista_nao_encontrada = ?"; $values[] = ($preco_lista_brl == 0) ? 1 : 0;
    }

    $values[] = $id;
    $sql = "UPDATE com_commission_items SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    // Retorna o item atualizado
    $stmt2 = $pdo->prepare("SELECT * FROM com_commission_items WHERE id = ?");
    $stmt2->execute([$id]);
    $updated = $stmt2->fetch(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode(['success' => true, 'item' => $updated]);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
