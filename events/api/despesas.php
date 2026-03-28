<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../auth.php';
require_login();

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $action   = $_GET['action'] ?? 'list';
        $event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
        if (!$event_id) throw new Exception("event_id obrigatório.");

        if ($action === 'list') {
            $where  = ['event_id = ?'];
            $params = [$event_id];

            if (!empty($_GET['categoria'])) {
                $where[] = 'categoria = ?';
                $params[] = $_GET['categoria'];
            }
            if (!empty($_GET['status'])) {
                $where[] = 'status_pagamento = ?';
                $params[] = $_GET['status'];
            }
            if (!empty($_GET['data_inicio'])) {
                $where[] = 'data_despesa >= ?';
                $params[] = $_GET['data_inicio'];
            }
            if (!empty($_GET['data_fim'])) {
                $where[] = 'data_despesa <= ?';
                $params[] = $_GET['data_fim'];
            }
            if (!empty($_GET['origem'])) {
                $where[] = 'origem = ?';
                $params[] = $_GET['origem'];
            }

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;
            $whereStr = implode(' AND ', $where);

            $sql = "SELECT * FROM evt_expenses WHERE $whereStr ORDER BY data_despesa DESC, created_at DESC LIMIT $limit";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Stats
            $sqlStats = "SELECT
                COALESCE(SUM(CASE WHEN status_pagamento != 'cancelado' THEN valor ELSE 0 END), 0) AS total,
                COALESCE(SUM(CASE WHEN status_pagamento = 'pendente'  THEN valor ELSE 0 END), 0) AS pendente,
                COALESCE(SUM(CASE WHEN status_pagamento = 'aprovado'  THEN valor ELSE 0 END), 0) AS aprovado,
                COALESCE(SUM(CASE WHEN status_pagamento = 'pago'      THEN valor ELSE 0 END), 0) AS pago
                FROM evt_expenses WHERE $whereStr";
            $stmtS = $pdo->prepare($sqlStats);
            $stmtS->execute($params);
            $stats = $stmtS->fetch(PDO::FETCH_ASSOC);

            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $rows, 'stats' => $stats]);
            exit;
        }

        throw new Exception("action inválida.");
    }

    // POST
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) $body = $_POST;

    $action = $body['action'] ?? 'create';
    $id     = isset($body['id']) ? (int)$body['id'] : 0;

    if ($action === 'delete') {
        if (!$id) throw new Exception("ID não fornecido.");
        $pdo->prepare("DELETE FROM evt_expenses WHERE id = ?")->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Despesa excluída.']);
        exit;
    }

    // Validação
    $event_id = isset($body['event_id']) ? (int)$body['event_id'] : 0;
    $descricao = trim($body['descricao'] ?? '');
    $categoria = trim($body['categoria'] ?? '');
    $valor     = (float)($body['valor'] ?? 0);

    if (!$event_id)  throw new Exception("event_id obrigatório.");
    if (!$descricao) throw new Exception("Descrição obrigatória.");
    if (!$categoria) throw new Exception("Categoria obrigatória.");
    if ($valor <= 0) throw new Exception("Valor deve ser maior que zero.");

    $status = $body['status_pagamento'] ?? 'pendente';
    if (!in_array($status, ['pendente','aprovado','pago','cancelado'])) $status = 'pendente';

    $fields = [
        'event_id'        => $event_id,
        'categoria'       => $categoria,
        'sub_rubrica'     => $body['sub_rubrica'] ? trim($body['sub_rubrica']) : null,
        'descricao'       => $descricao,
        'fornecedor'      => $body['fornecedor'] ? trim($body['fornecedor']) : null,
        'valor'           => $valor,
        'data_despesa'    => $body['data_despesa'] ?: null,
        'data_vencimento' => $body['data_vencimento'] ?: null,
        'status_pagamento'=> $status,
        'observacao'      => $body['observacao'] ? trim($body['observacao']) : null,
        'origem'          => trim($body['origem'] ?? 'manual'),
        'criado_por'      => user()['name'] ?? user()['email'] ?? 'sistema',
    ];

    if ($action === 'create') {
        $cols = implode(', ', array_keys($fields));
        $phs  = implode(', ', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT INTO evt_expenses ($cols) VALUES ($phs)")->execute(array_values($fields));
        $newId = $pdo->lastInsertId();
        $stmt2 = $pdo->prepare("SELECT * FROM evt_expenses WHERE id = ?");
        $stmt2->execute([$newId]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Despesa registrada.', 'data' => $stmt2->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'update') {
        if (!$id) throw new Exception("ID não fornecido.");
        unset($fields['event_id'], $fields['criado_por']);
        $set  = implode(' = ?, ', array_keys($fields)) . ' = ?';
        $vals = array_values($fields);
        $vals[] = $id;
        $pdo->prepare("UPDATE evt_expenses SET $set WHERE id = ?")->execute($vals);
        $stmt2 = $pdo->prepare("SELECT * FROM evt_expenses WHERE id = ?");
        $stmt2->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Despesa atualizada.', 'data' => $stmt2->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    // update status only
    if ($action === 'update_status') {
        if (!$id) throw new Exception("ID não fornecido.");
        $newStatus = $body['status_pagamento'] ?? '';
        if (!in_array($newStatus, ['pendente','aprovado','pago','cancelado'])) throw new Exception("Status inválido.");

        $extra = [];
        $vals  = [$newStatus];
        if ($newStatus === 'aprovado') {
            $extra[] = "aprovado_por = ?";
            $extra[] = "data_aprovacao = NOW()";
            $vals[] = user()['name'] ?? 'sistema';
        }
        $extraStr = $extra ? ', ' . implode(', ', $extra) : '';
        $vals[] = $id;
        $pdo->prepare("UPDATE evt_expenses SET status_pagamento = ?$extraStr WHERE id = ?")->execute($vals);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Status atualizado.']);
        exit;
    }

    throw new Exception("Ação desconhecida.");

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
