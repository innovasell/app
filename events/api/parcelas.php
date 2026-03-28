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

    // ── GET: listar parcelas de uma despesa ───────────────────────────────────
    if ($method === 'GET') {
        $expense_id = isset($_GET['expense_id']) ? (int)$_GET['expense_id'] : 0;
        $event_id   = isset($_GET['event_id'])   ? (int)$_GET['event_id']   : 0;

        if ($expense_id) {
            $stmt = $pdo->prepare("SELECT * FROM evt_expense_parcelas WHERE expense_id = ? ORDER BY numero");
            $stmt->execute([$expense_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Info da despesa pai
            $stmtD = $pdo->prepare("SELECT id, descricao, categoria, valor, parcelado FROM evt_expenses WHERE id = ?");
            $stmtD->execute([$expense_id]);
            $despesa = $stmtD->fetch(PDO::FETCH_ASSOC);

            ob_end_clean();
            echo json_encode(['success' => true, 'despesa' => $despesa, 'parcelas' => $rows]);
            exit;
        }

        // Listar todas as parcelas de um evento (para calendário de vencimentos)
        if ($event_id) {
            $stmt = $pdo->prepare("
                SELECT p.*, e.descricao AS despesa_desc, e.categoria
                FROM evt_expense_parcelas p
                JOIN evt_expenses e ON e.id = p.expense_id
                WHERE p.event_id = ?
                ORDER BY p.vencimento, p.expense_id, p.numero
            ");
            $stmt->execute([$event_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ob_end_clean();
            echo json_encode(['success' => true, 'parcelas' => $rows]);
            exit;
        }

        throw new Exception("expense_id ou event_id obrigatório.");
    }

    // ── POST: atualizar status, excluir, ou recalcular ───────────────────────
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) $body = $_POST;

    $action = $body['action'] ?? '';

    // ── Marcar parcela como paga / cancelada / pendente ───────────────────────
    if ($action === 'update_status') {
        $id     = (int)($body['id'] ?? 0);
        $status = $body['status'] ?? '';
        if (!$id) throw new Exception("ID da parcela não fornecido.");
        if (!in_array($status, ['pendente','pago','cancelado'])) throw new Exception("Status inválido.");

        $dataPag = ($status === 'pago') ? ($body['data_pagamento'] ?? date('Y-m-d')) : null;
        $pdo->prepare("UPDATE evt_expense_parcelas SET status_pagamento = ?, data_pagamento = ? WHERE id = ?")
            ->execute([$status, $dataPag, $id]);

        // Recalcular status da despesa pai
        _recalcExpenseStatus($pdo, _getExpenseIdFromParcela($pdo, $id));

        $stmtP = $pdo->prepare("SELECT * FROM evt_expense_parcelas WHERE id = ?");
        $stmtP->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Status atualizado.', 'parcela' => $stmtP->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── Editar valor/vencimento de uma parcela ────────────────────────────────
    if ($action === 'update') {
        $id        = (int)($body['id'] ?? 0);
        $valor     = (float)($body['valor'] ?? 0);
        $vencimento = $body['vencimento'] ?? '';
        $obs       = $body['observacao'] ?? null;
        if (!$id) throw new Exception("ID da parcela não fornecido.");
        if ($valor <= 0) throw new Exception("Valor deve ser maior que zero.");
        if (!$vencimento) throw new Exception("Vencimento obrigatório.");

        $pdo->prepare("UPDATE evt_expense_parcelas SET valor = ?, vencimento = ?, observacao = ? WHERE id = ?")
            ->execute([$valor, $vencimento, $obs, $id]);

        // Recalcular total da despesa pai
        $expense_id = _getExpenseIdFromParcela($pdo, $id);
        _recalcExpenseTotal($pdo, $expense_id);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Parcela atualizada.']);
        exit;
    }

    // ── Excluir uma parcela específica ────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) throw new Exception("ID não fornecido.");
        $expense_id = _getExpenseIdFromParcela($pdo, $id);
        $pdo->prepare("DELETE FROM evt_expense_parcelas WHERE id = ?")->execute([$id]);
        // Renumerar
        _renumberParcelas($pdo, $expense_id);
        _recalcExpenseTotal($pdo, $expense_id);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Parcela excluída.']);
        exit;
    }

    // ── Adicionar parcela avulsa ──────────────────────────────────────────────
    if ($action === 'add') {
        $expense_id = (int)($body['expense_id'] ?? 0);
        $valor      = (float)($body['valor'] ?? 0);
        $vencimento = $body['vencimento'] ?? '';
        if (!$expense_id) throw new Exception("expense_id obrigatório.");
        if ($valor <= 0) throw new Exception("Valor deve ser maior que zero.");
        if (!$vencimento) throw new Exception("Vencimento obrigatório.");

        // Buscar event_id e próximo número
        $stmtE = $pdo->prepare("SELECT event_id FROM evt_expenses WHERE id = ?");
        $stmtE->execute([$expense_id]);
        $ev = $stmtE->fetch(PDO::FETCH_ASSOC);
        if (!$ev) throw new Exception("Despesa não encontrada.");

        $stmtN = $pdo->prepare("SELECT COALESCE(MAX(numero),0)+1 FROM evt_expense_parcelas WHERE expense_id = ?");
        $stmtN->execute([$expense_id]);
        $nextNum = (int)$stmtN->fetchColumn();

        $pdo->prepare("INSERT INTO evt_expense_parcelas (expense_id, event_id, numero, vencimento, valor, observacao) VALUES (?,?,?,?,?,?)")
            ->execute([$expense_id, $ev['event_id'], $nextNum, $vencimento, $valor, $body['observacao'] ?? null]);

        _recalcExpenseTotal($pdo, $expense_id);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Parcela adicionada.']);
        exit;
    }

    throw new Exception("Ação desconhecida: $action");

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function _getExpenseIdFromParcela(PDO $pdo, int $parcelaId): int
{
    $s = $pdo->prepare("SELECT expense_id FROM evt_expense_parcelas WHERE id = ?");
    $s->execute([$parcelaId]);
    return (int)$s->fetchColumn();
}

function _recalcExpenseTotal(PDO $pdo, int $expenseId): void
{
    if (!$expenseId) return;
    $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM evt_expense_parcelas WHERE expense_id = ?");
    $s->execute([$expenseId]);
    $total = (float)$s->fetchColumn();
    $pdo->prepare("UPDATE evt_expenses SET valor = ? WHERE id = ?")->execute([$total, $expenseId]);
    _recalcExpenseStatus($pdo, $expenseId);
}

function _recalcExpenseStatus(PDO $pdo, int $expenseId): void
{
    if (!$expenseId) return;
    $s = $pdo->prepare("SELECT
        COUNT(*) AS total,
        SUM(status_pagamento = 'pago') AS pagas,
        SUM(status_pagamento = 'cancelado') AS canceladas
        FROM evt_expense_parcelas WHERE expense_id = ?");
    $s->execute([$expenseId]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    $total     = (int)$r['total'];
    $pagas     = (int)$r['pagas'];
    $canceladas = (int)$r['canceladas'];
    $ativas    = $total - $canceladas;

    if ($ativas === 0)        $status = 'cancelado';
    elseif ($pagas === $ativas) $status = 'pago';
    elseif ($pagas > 0)       $status = 'aprovado'; // reutilizamos 'aprovado' como "parcialmente pago"
    else                       $status = 'pendente';

    $pdo->prepare("UPDATE evt_expenses SET status_pagamento = ? WHERE id = ?")->execute([$status, $expenseId]);
}

function _renumberParcelas(PDO $pdo, int $expenseId): void
{
    $rows = $pdo->prepare("SELECT id FROM evt_expense_parcelas WHERE expense_id = ? ORDER BY vencimento, id");
    $rows->execute([$expenseId]);
    $ids = $rows->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $i => $pid) {
        $pdo->prepare("UPDATE evt_expense_parcelas SET numero = ? WHERE id = ?")->execute([$i + 1, $pid]);
    }
}
