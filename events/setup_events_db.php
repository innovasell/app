<?php
/**
 * setup_events_db.php — Migração de banco para o portal InnovaEvents
 * Cria as tabelas evt_events, evt_budget, evt_expenses
 * Altera viagem_express_expenses com event_id e budget_category
 */
require_once 'conexao.php';
require_once 'auth.php';
require_login();
require_admin();

$results = [];

function runSql(PDO $pdo, string $sql, string $desc): void
{
    global $results;
    try {
        $pdo->exec($sql);
        $results[] = ['ok' => true, 'msg' => $desc];
    } catch (PDOException $e) {
        $results[] = ['ok' => false, 'msg' => "$desc: " . $e->getMessage()];
    }
}

// ── TABELA: evt_events ────────────────────────────────────────────────────────
runSql($pdo, "CREATE TABLE IF NOT EXISTS evt_events (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nome             VARCHAR(255) NOT NULL,
    descricao        TEXT,
    data_inicio      DATE,
    data_fim         DATE,
    local_evento     VARCHAR(255),
    objetivo         TEXT,
    responsavel      VARCHAR(255),
    status           ENUM('planejamento','em_execucao','encerrado') NOT NULL DEFAULT 'planejamento',
    orcamento_total  DECIMAL(15,2) NOT NULL DEFAULT 0,
    contingencia_pct DECIMAL(5,2)  NOT NULL DEFAULT 5.00,
    created_by       VARCHAR(100),
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Tabela evt_events");

// ── TABELA: evt_budget ────────────────────────────────────────────────────────
runSql($pdo, "CREATE TABLE IF NOT EXISTS evt_budget (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    event_id      INT NOT NULL,
    categoria     VARCHAR(100) NOT NULL,
    sub_rubrica   VARCHAR(150),
    valor_orcado  DECIMAL(15,2) NOT NULL DEFAULT 0,
    obs           TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES evt_events(id) ON DELETE CASCADE,
    INDEX idx_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Tabela evt_budget");

// ── TABELA: evt_expenses ──────────────────────────────────────────────────────
runSql($pdo, "CREATE TABLE IF NOT EXISTS evt_expenses (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    event_id          INT NOT NULL,
    budget_id         INT NULL,
    categoria         VARCHAR(100) NOT NULL,
    sub_rubrica       VARCHAR(150),
    descricao         TEXT NOT NULL,
    fornecedor        VARCHAR(255),
    valor             DECIMAL(15,2) NOT NULL DEFAULT 0,
    data_despesa      DATE,
    data_vencimento   DATE,
    status_pagamento  ENUM('pendente','aprovado','pago','cancelado') NOT NULL DEFAULT 'pendente',
    comprovante_url   VARCHAR(500),
    observacao        TEXT,
    origem            VARCHAR(50) NOT NULL DEFAULT 'manual',
    ref_external_id   INT NULL,
    criado_por        VARCHAR(100),
    aprovado_por      VARCHAR(100),
    data_aprovacao    DATETIME NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES evt_events(id) ON DELETE CASCADE,
    INDEX idx_event    (event_id),
    INDEX idx_cat      (categoria),
    INDEX idx_status   (status_pagamento),
    INDEX idx_data     (data_despesa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Tabela evt_expenses");

// ── TABELA: evt_expense_parcelas ──────────────────────────────────────────────
runSql($pdo, "CREATE TABLE IF NOT EXISTS evt_expense_parcelas (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    expense_id       INT NOT NULL,
    event_id         INT NOT NULL,
    numero           SMALLINT NOT NULL DEFAULT 1,
    vencimento       DATE NOT NULL,
    valor            DECIMAL(15,2) NOT NULL,
    status_pagamento ENUM('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
    data_pagamento   DATE NULL,
    observacao       TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (expense_id) REFERENCES evt_expenses(id) ON DELETE CASCADE,
    INDEX idx_expense  (expense_id),
    INDEX idx_event    (event_id),
    INDEX idx_venc     (vencimento),
    INDEX idx_status   (status_pagamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Tabela evt_expense_parcelas");

// ── ALTER: evt_expenses — adicionar flag parcelado ────────────────────────────
runSql($pdo, "ALTER TABLE evt_expenses ADD COLUMN IF NOT EXISTS parcelado TINYINT(1) NOT NULL DEFAULT 0 AFTER status_pagamento",
    "Coluna parcelado em evt_expenses");

// ── ALTER: viagem_express_expenses ────────────────────────────────────────────
runSql($pdo, "ALTER TABLE viagem_express_expenses
    ADD COLUMN IF NOT EXISTS event_id       INT NULL AFTER id,
    ADD COLUMN IF NOT EXISTS budget_category VARCHAR(100) NULL AFTER event_id",
    "Colunas event_id, budget_category em viagem_express_expenses");

runSql($pdo, "ALTER TABLE viagem_express_expenses
    ADD INDEX IF NOT EXISTS idx_event_id (event_id)",
    "Índice event_id em viagem_express_expenses");

// ─────────────────────────────────────────────────────────────────────────────

$allOk = !in_array(false, array_column($results, 'ok'));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup DB — InnovaEvents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-5" style="max-width:640px">
    <h4 class="mb-4"><i class="bi bi-database-gear"></i> Setup do Banco — InnovaEvents</h4>
    <?php foreach ($results as $r): ?>
        <div class="alert <?= $r['ok'] ? 'alert-success' : 'alert-danger' ?> py-2 mb-2 small">
            <?= $r['ok'] ? '✓' : '✗' ?> <?= htmlspecialchars($r['msg']) ?>
        </div>
    <?php endforeach; ?>
    <div class="mt-4 d-flex gap-2">
        <a href="index.php" class="btn btn-<?= $allOk ? 'primary' : 'secondary' ?>">← Ir para o Portal</a>
        <?php if (!$allOk): ?>
            <a href="setup_events_db.php" class="btn btn-warning">↺ Tentar novamente</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
