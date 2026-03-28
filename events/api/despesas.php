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
            $where  = ['e.event_id = ?'];
            $params = [$event_id];

            if (!empty($_GET['categoria'])) {
                $where[] = 'e.categoria = ?';
                $params[] = $_GET['categoria'];
            }
            if (!empty($_GET['status'])) {
                $where[] = 'e.status_pagamento = ?';
                $params[] = $_GET['status'];
            }
            if (!empty($_GET['data_inicio'])) {
                $where[] = 'e.data_despesa >= ?';
                $params[] = $_GET['data_inicio'];
            }
            if (!empty($_GET['data_fim'])) {
                $where[] = 'e.data_despesa <= ?';
                $params[] = $_GET['data_fim'];
            }
            if (!empty($_GET['origem'])) {
                $where[] = 'e.origem = ?';
                $params[] = $_GET['origem'];
            }

            $limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;
            $whereStr = implode(' AND ', $where);

            // Inclui contagem de parcelas e parcelas pagas
            $sql = "SELECT e.*,
                        COUNT(p.id)                                      AS parcelas_total,
                        SUM(p.status_pagamento = 'pago')                 AS parcelas_pagas,
                        SUM(p.status_pagamento = 'pendente')             AS parcelas_pendentes,
                        MIN(CASE WHEN p.status_pagamento = 'pendente' THEN p.vencimento END) AS prox_vencimento
                    FROM evt_expenses e
                    LEFT JOIN evt_expense_parcelas p ON p.expense_id = e.id
                    WHERE $whereStr
                    GROUP BY e.id
                    ORDER BY e.data_despesa DESC, e.created_at DESC
                    LIMIT $limit";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Stats (usando mesma lógica sem JOIN para evitar duplicação)
            $sqlStats = "SELECT
                COALESCE(SUM(CASE WHEN status_pagamento != 'cancelado' THEN valor ELSE 0 END), 0) AS total,
                COALESCE(SUM(CASE WHEN status_pagamento = 'pendente'  THEN valor ELSE 0 END), 0) AS pendente,
                COALESCE(SUM(CASE WHEN status_pagamento = 'aprovado'  THEN valor ELSE 0 END), 0) AS aprovado,
                COALESCE(SUM(CASE WHEN status_pagamento = 'pago'      THEN valor ELSE 0 END), 0) AS pago
                FROM evt_expenses WHERE event_id = ?";
            $paramsStats = [$event_id];
            // Aplicar mesmos filtros (sem prefixo 'e.')
            $whereStatsArr = ['event_id = ?'];
            if (!empty($_GET['categoria']))   { $whereStatsArr[] = 'categoria = ?';        $paramsStats[] = $_GET['categoria']; }
            if (!empty($_GET['status']))       { $whereStatsArr[] = 'status_pagamento = ?'; $paramsStats[] = $_GET['status']; }
            if (!empty($_GET['data_inicio']))  { $whereStatsArr[] = 'data_despesa >= ?';    $paramsStats[] = $_GET['data_inicio']; }
            if (!empty($_GET['data_fim']))     { $whereStatsArr[] = 'data_despesa <= ?';    $paramsStats[] = $_GET['data_fim']; }
            if (!empty($_GET['origem']))       { $whereStatsArr[] = 'origem = ?';           $paramsStats[] = $_GET['origem']; }
            $sqlStats = "SELECT
                COALESCE(SUM(CASE WHEN status_pagamento != 'cancelado' THEN valor ELSE 0 END), 0) AS total,
                COALESCE(SUM(CASE WHEN status_pagamento = 'pendente'  THEN valor ELSE 0 END), 0) AS pendente,
                COALESCE(SUM(CASE WHEN status_pagamento = 'aprovado'  THEN valor ELSE 0 END), 0) AS aprovado,
                COALESCE(SUM(CASE WHEN status_pagamento = 'pago'      THEN valor ELSE 0 END), 0) AS pago
                FROM evt_expenses WHERE " . implode(' AND ', $whereStatsArr);
            $stmtS = $pdo->prepare($sqlStats);
            $stmtS->execute($paramsStats);
            $stats = $stmtS->fetch(PDO::FETCH_ASSOC);

            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $rows, 'stats' => $stats]);
            exit;
        }

        throw new Exception("action inválida.");
    }

    // ── POST ──────────────────────────────────────────────────────────────────
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) $body = $_POST;

    $action = $body['action'] ?? 'create';
    $id     = isset($body['id']) ? (int)$body['id'] : 0;

    if ($action === 'delete') {
        if (!$id) throw new Exception("ID não fornecido.");
        // Parcelas são excluídas em cascata pelo FK
        $pdo->prepare("DELETE FROM evt_expenses WHERE id = ?")->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Despesa excluída.']);
        exit;
    }

    // ── Validação comum ───────────────────────────────────────────────────────
    $event_id  = isset($body['event_id']) ? (int)$body['event_id'] : 0;
    $descricao = trim($body['descricao'] ?? '');
    $categoria = trim($body['categoria'] ?? '');
    $parcelado = !empty($body['parcelas']) && is_array($body['parcelas']) && count($body['parcelas']) > 0;

    if (!$event_id)  throw new Exception("event_id obrigatório.");
    if (!$descricao) throw new Exception("Descrição obrigatória.");
    if (!$categoria) throw new Exception("Categoria obrigatória.");

    // Se parcelado, valor = soma das parcelas; senão usa valor direto
    $parcelas = $parcelado ? $body['parcelas'] : [];
    if ($parcelado) {
        $valor = 0;
        foreach ($parcelas as $p) {
            $pv = (float)($p['valor'] ?? 0);
            if ($pv <= 0) throw new Exception("Todas as parcelas precisam ter valor maior que zero.");
            if (empty($p['vencimento'])) throw new Exception("Todas as parcelas precisam ter vencimento.");
            $valor += $pv;
        }
    } else {
        $valor = (float)($body['valor'] ?? 0);
        if ($valor <= 0) throw new Exception("Valor deve ser maior que zero.");
    }

    $status = $parcelado ? 'pendente' : ($body['status_pagamento'] ?? 'pendente');
    if (!in_array($status, ['pendente','aprovado','pago','cancelado'])) $status = 'pendente';

    $fields = [
        'event_id'        => $event_id,
        'categoria'       => $categoria,
        'sub_rubrica'     => ($body['sub_rubrica'] ?? '') !== '' ? trim($body['sub_rubrica']) : null,
        'descricao'       => $descricao,
        'fornecedor'      => ($body['fornecedor'] ?? '') !== '' ? trim($body['fornecedor']) : null,
        'valor'           => round($valor, 2),
        'data_despesa'    => ($body['data_despesa'] ?? '') ?: null,
        'data_vencimento' => ($body['data_vencimento'] ?? '') ?: null,
        'status_pagamento'=> $status,
        'parcelado'       => $parcelado ? 1 : 0,
        'observacao'      => ($body['observacao'] ?? '') !== '' ? trim($body['observacao']) : null,
        'origem'          => trim($body['origem'] ?? 'manual'),
        'criado_por'      => user()['name'] ?? user()['email'] ?? 'sistema',
    ];

    // ── CREATE ────────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $cols = implode(', ', array_keys($fields));
        $phs  = implode(', ', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT INTO evt_expenses ($cols) VALUES ($phs)")->execute(array_values($fields));
        $newId = (int)$pdo->lastInsertId();

        // Inserir parcelas
        if ($parcelado) {
            _insertParcelas($pdo, $newId, $event_id, $parcelas);
        }

        $stmt2 = $pdo->prepare("SELECT * FROM evt_expenses WHERE id = ?");
        $stmt2->execute([$newId]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Despesa registrada.', 'data' => $stmt2->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    if ($action === 'update') {
        if (!$id) throw new Exception("ID não fornecido.");
        unset($fields['event_id'], $fields['criado_por']);
        $set  = implode(' = ?, ', array_keys($fields)) . ' = ?';
        $vals = array_values($fields);
        $vals[] = $id;
        $pdo->prepare("UPDATE evt_expenses SET $set WHERE id = ?")->execute($vals);

        // Substituir parcelas se enviadas
        if ($parcelado && !empty($parcelas)) {
            $pdo->prepare("DELETE FROM evt_expense_parcelas WHERE expense_id = ?")->execute([$id]);
            _insertParcelas($pdo, $id, $event_id ?: _getEventIdFromExpense($pdo, $id), $parcelas);
        }

        $stmt2 = $pdo->prepare("SELECT * FROM evt_expenses WHERE id = ?");
        $stmt2->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Despesa atualizada.', 'data' => $stmt2->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── UPDATE STATUS ─────────────────────────────────────────────────────────
    if ($action === 'update_status') {
        if (!$id) throw new Exception("ID não fornecido.");
        $newStatus = $body['status_pagamento'] ?? '';
        if (!in_array($newStatus, ['pendente','aprovado','pago','cancelado'])) throw new Exception("Status inválido.");

        $extra = [];
        $vals  = [$newStatus];
        if ($newStatus === 'aprovado') {
            $extra[] = "aprovado_por = ?";
            $extra[] = "data_aprovacao = NOW()";
            $vals[]  = user()['name'] ?? 'sistema';
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

// ── Helpers ───────────────────────────────────────────────────────────────────

function _insertParcelas(PDO $pdo, int $expenseId, int $eventId, array $parcelas): void
{
    $stmt = $pdo->prepare("INSERT INTO evt_expense_parcelas (expense_id, event_id, numero, vencimento, valor, observacao) VALUES (?,?,?,?,?,?)");
    foreach ($parcelas as $i => $p) {
        $stmt->execute([
            $expenseId,
            $eventId,
            $i + 1,
            $p['vencimento'],
            round((float)$p['valor'], 2),
            ($p['observacao'] ?? '') !== '' ? trim($p['observacao']) : null,
        ]);
    }
}

function _getEventIdFromExpense(PDO $pdo, int $expenseId): int
{
    $s = $pdo->prepare("SELECT event_id FROM evt_expenses WHERE id = ?");
    $s->execute([$expenseId]);
    return (int)$s->fetchColumn();
}
