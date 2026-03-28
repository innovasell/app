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

        // ── LIST: rubricas com realizado por categoria ──────────────────────
        if ($action === 'list') {
            $sql = "SELECT
                        b.*,
                        b.valor_orcado AS orcado,
                        COALESCE(SUM(CASE WHEN ex.status_pagamento != 'cancelado' THEN ex.valor ELSE 0 END), 0) AS realizado
                    FROM evt_budget b
                    LEFT JOIN evt_expenses ex ON ex.event_id = b.event_id AND ex.categoria = b.categoria
                    WHERE b.event_id = ?
                    GROUP BY b.id
                    ORDER BY b.categoria, b.sub_rubrica";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$event_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        }

        // ── SUMMARY: resumo total do evento + por categoria ─────────────────
        if ($action === 'summary') {
            // Total orçado nas rubricas
            $stmtO = $pdo->prepare("SELECT COALESCE(SUM(valor_orcado),0) FROM evt_budget WHERE event_id = ?");
            $stmtO->execute([$event_id]);
            $orcado_rubricas = (float)$stmtO->fetchColumn();

            // Total realizado (evt_expenses, exceto canceladas)
            $stmtR = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM evt_expenses WHERE event_id = ? AND status_pagamento != 'cancelado'");
            $stmtR->execute([$event_id]);
            $realizado = (float)$stmtR->fetchColumn();

            // Soma de viagem_express vinculadas
            $stmtVe = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM viagem_express_expenses WHERE event_id = ?");
            $stmtVe->execute([$event_id]);
            $realizado += (float)$stmtVe->fetchColumn();

            // Pendente (despesas pendentes)
            $stmtP = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM evt_expenses WHERE event_id = ? AND status_pagamento = 'pendente'");
            $stmtP->execute([$event_id]);
            $pendente = (float)$stmtP->fetchColumn();

            // Orçamento total do evento
            $stmtEv = $pdo->prepare("SELECT orcamento_total FROM evt_events WHERE id = ?");
            $stmtEv->execute([$event_id]);
            $orcado_total = (float)($stmtEv->fetchColumn() ?: $orcado_rubricas);

            // Por categoria (para progress bars)
            $sqlCat = "SELECT
                        b.categoria,
                        SUM(b.valor_orcado) AS orcado,
                        COALESCE(SUM(CASE WHEN ex.status_pagamento != 'cancelado' THEN ex.valor ELSE 0 END), 0) AS realizado
                       FROM evt_budget b
                       LEFT JOIN evt_expenses ex ON ex.event_id = b.event_id AND ex.categoria = b.categoria
                       WHERE b.event_id = ?
                       GROUP BY b.categoria
                       ORDER BY b.categoria";
            $stmtCat = $pdo->prepare($sqlCat);
            $stmtCat->execute([$event_id]);
            $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

            // Agregar viagem_express por budget_category
            $stmtVeCat = $pdo->prepare("SELECT COALESCE(budget_category, categoria_despesa, 'Transporte') AS categoria,
                                                COALESCE(SUM(total),0) AS total
                                         FROM viagem_express_expenses
                                         WHERE event_id = ?
                                         GROUP BY 1");
            $stmtVeCat->execute([$event_id]);
            $veCats = $stmtVeCat->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($categories as &$cat) {
                $cat['realizado'] = round((float)$cat['realizado'] + (float)($veCats[$cat['categoria']] ?? 0), 2);
            }
            unset($cat);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'summary' => [
                    'orcado_total'    => round($orcado_total, 2),
                    'orcado_rubricas' => round($orcado_rubricas, 2),
                    'realizado_total' => round($realizado, 2),
                    'pendente_total'  => round($pendente, 2),
                ],
                'categories' => $categories,
            ]);
            exit;
        }

        throw new Exception("action inválida.");
    }

    // POST: criar, atualizar, excluir
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) $body = $_POST;

    $action = $body['action'] ?? 'create';
    $id     = isset($body['id']) ? (int)$body['id'] : 0;

    if ($action === 'delete') {
        if (!$id) throw new Exception("ID não fornecido.");
        $pdo->prepare("DELETE FROM evt_budget WHERE id = ?")->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Rubrica excluída.']);
        exit;
    }

    $event_id   = isset($body['event_id']) ? (int)$body['event_id'] : 0;
    $categoria  = trim($body['categoria'] ?? '');
    $valor      = max(0, (float)($body['valor_orcado'] ?? 0));
    if (!$event_id) throw new Exception("event_id obrigatório.");
    if (!$categoria) throw new Exception("Categoria obrigatória.");

    $fields = [
        'event_id'    => $event_id,
        'categoria'   => $categoria,
        'sub_rubrica' => $body['sub_rubrica'] ? trim($body['sub_rubrica']) : null,
        'valor_orcado'=> $valor,
        'obs'         => $body['obs'] ? trim($body['obs']) : null,
    ];

    if ($action === 'create') {
        $cols = implode(', ', array_keys($fields));
        $phs  = implode(', ', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT INTO evt_budget ($cols) VALUES ($phs)")->execute(array_values($fields));
        $newId = $pdo->lastInsertId();
        $stmt2 = $pdo->prepare("SELECT * FROM evt_budget WHERE id = ?");
        $stmt2->execute([$newId]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Rubrica criada.', 'data' => $stmt2->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'update') {
        if (!$id) throw new Exception("ID não fornecido.");
        unset($fields['event_id']); // não alterar o evento
        $set  = implode(' = ?, ', array_keys($fields)) . ' = ?';
        $vals = array_values($fields);
        $vals[] = $id;
        $pdo->prepare("UPDATE evt_budget SET $set WHERE id = ?")->execute($vals);
        $stmt2 = $pdo->prepare("SELECT * FROM evt_budget WHERE id = ?");
        $stmt2->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Rubrica atualizada.', 'data' => $stmt2->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    throw new Exception("Ação desconhecida.");

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
