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

    // GET: listar ou buscar um evento
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($action === 'get' && $id) {
            $stmt = $pdo->prepare("SELECT * FROM evt_events WHERE id = ?");
            $stmt->execute([$id]);
            $ev = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ev) throw new Exception("Evento não encontrado.");
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $ev]);
            exit;
        }

        // list — inclui valor realizado agregado
        $sql = "SELECT
                    e.*,
                    COALESCE(SUM(ex.valor), 0) AS realizado
                FROM evt_events e
                LEFT JOIN evt_expenses ex ON ex.event_id = e.id AND ex.status_pagamento != 'cancelado'
                GROUP BY e.id
                ORDER BY e.created_at DESC";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // Incluir também despesas de viagem_express_expenses vinculadas ao evento
        foreach ($rows as &$row) {
            $stmtVe = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS vt FROM viagem_express_expenses WHERE event_id = ?");
            $stmtVe->execute([$row['id']]);
            $vt = (float)$stmtVe->fetchColumn();
            $row['realizado'] = round((float)$row['realizado'] + $vt, 2);
        }
        unset($row);

        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // POST: criar, atualizar ou excluir
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) $body = $_POST;

    $action = $body['action'] ?? 'create';
    $id     = isset($body['id']) ? (int)$body['id'] : 0;

    if ($action === 'delete') {
        if (!$id) throw new Exception("ID não fornecido.");
        $pdo->prepare("DELETE FROM evt_events WHERE id = ?")->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Evento excluído.']);
        exit;
    }

    // Validação básica
    $nome = trim($body['nome'] ?? '');
    if (!$nome) throw new Exception("O nome do evento é obrigatório.");

    $fields = [
        'nome'            => $nome,
        'descricao'       => $body['descricao'] ?? null,
        'data_inicio'     => $body['data_inicio'] ?: null,
        'data_fim'        => $body['data_fim'] ?: null,
        'local_evento'    => $body['local_evento'] ?? null,
        'objetivo'        => $body['objetivo'] ?? null,
        'responsavel'     => $body['responsavel'] ?? null,
        'status'          => in_array($body['status'] ?? '', ['planejamento','em_execucao','encerrado']) ? $body['status'] : 'planejamento',
        'orcamento_total' => max(0, (float)($body['orcamento_total'] ?? 0)),
        'contingencia_pct'=> min(100, max(0, (float)($body['contingencia_pct'] ?? 5))),
    ];

    if ($action === 'create') {
        $fields['created_by'] = user()['name'] ?? user()['email'] ?? 'sistema';
        $cols = implode(', ', array_keys($fields));
        $phs  = implode(', ', array_fill(0, count($fields), '?'));
        $stmt = $pdo->prepare("INSERT INTO evt_events ($cols) VALUES ($phs)");
        $stmt->execute(array_values($fields));
        $newId = $pdo->lastInsertId();

        $stmt2 = $pdo->prepare("SELECT * FROM evt_events WHERE id = ?");
        $stmt2->execute([$newId]);
        $created = $stmt2->fetch(PDO::FETCH_ASSOC);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Evento criado.', 'data' => $created]);
        exit;
    }

    if ($action === 'update') {
        if (!$id) throw new Exception("ID não fornecido.");
        $set = implode(' = ?, ', array_keys($fields)) . ' = ?';
        $vals = array_values($fields);
        $vals[] = $id;
        $pdo->prepare("UPDATE evt_events SET $set WHERE id = ?")->execute($vals);

        $stmt2 = $pdo->prepare("SELECT * FROM evt_events WHERE id = ?");
        $stmt2->execute([$id]);
        $updated = $stmt2->fetch(PDO::FETCH_ASSOC);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Evento atualizado.', 'data' => $updated]);
        exit;
    }

    throw new Exception("Ação desconhecida: $action");

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
