<?php
/**
 * api_budget_cliente.php
 * Endpoint AJAX para busca de clientes e produtos do pricelist por cliente.
 *
 * GET ?action=buscar_clientes&q=termo  → retorna JSON com clientes que têm budget
 * GET ?action=buscar_produtos&cnpj=XX  → retorna JSON com produtos do cliente + price list atual
 */
session_start();
require_once 'conexao.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['representante_email'])) {
    echo json_encode(['erro' => 'Não autorizado']); exit();
}

$action = $_GET['action'] ?? '';

// ─── Buscar clientes ──────────────────────────────────────────────────────────
if ($action === 'buscar_clientes') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) { echo json_encode([]); exit(); }

    try {
        // Busca em cot_clientes linkando com cot_budget_cliente pelo CNPJ
        $sql = "SELECT DISTINCT c.id, c.nome, c.razao_social, c.cnpj
                FROM cot_clientes c
                INNER JOIN cot_budget_cliente b ON b.cnpj = c.cnpj
                WHERE CONCAT_WS(' ', c.nome, c.razao_social, c.cnpj) LIKE :q
                ORDER BY c.razao_social ASC
                LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q' => '%' . $q . '%']);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($clientes);
    } catch (PDOException $e) {
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit();
}

// ─── Buscar produtos de um cliente ───────────────────────────────────────────
if ($action === 'buscar_produtos') {
    $cnpj = trim($_GET['cnpj'] ?? '');
    if (empty($cnpj)) { echo json_encode([]); exit(); }

    try {
        // Busca os produtos do budget + JOIN com price list pelo nome do produto
        $sql = "SELECT
                    b.produto,
                    b.fabricante,
                    b.embalagem,
                    b.kg_historico,
                    b.kg_realizado_2025,
                    b.kg_orcado_2026,
                    b.kg_realizado_2026,
                    b.preco_hist_brl,
                    b.preco_2025_brl,
                    b.preco_sugerido_brl,
                    b.preco_orcado_2026_brl,
                    b.preco_realizado_2026_brl,
                    b.preco_ajustado,
                    b.reajuste_sugerido,
                    b.vendedor_ajustado,
                    b.comentarios_supply,
                    pl.preco_net_usd AS price_list_usd,
                    pl.embalagem     AS price_list_emb
                FROM cot_budget_cliente b
                LEFT JOIN cot_price_list pl
                    ON LOWER(TRIM(b.produto)) = LOWER(TRIM(pl.produto))
                WHERE b.cnpj = :cnpj
                ORDER BY b.produto ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cnpj' => $cnpj]);
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($produtos);
    } catch (PDOException $e) {
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit();
}

// ─── Resumo do cliente ────────────────────────────────────────────────────────
if ($action === 'resumo_cliente') {
    $cnpj = trim($_GET['cnpj'] ?? '');
    if (empty($cnpj)) { echo json_encode(null); exit(); }

    try {
        // Dados do cot_clientes
        $stmt = $pdo->prepare("SELECT nome, razao_social, cnpj FROM cot_clientes WHERE cnpj = :cnpj LIMIT 1");
        $stmt->execute([':cnpj' => $cnpj]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        // Dados extras do budget
        $stmt2 = $pdo->prepare("SELECT vendedor_ajustado, cliente_origem, COUNT(*) as total_produtos
                                 FROM cot_budget_cliente WHERE cnpj = :cnpj
                                 GROUP BY vendedor_ajustado, cliente_origem LIMIT 1");
        $stmt2->execute([':cnpj' => $cnpj]);
        $extra = $stmt2->fetch(PDO::FETCH_ASSOC);

        echo json_encode(array_merge($cliente ?? [], $extra ?? []));
    } catch (PDOException $e) {
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit();
}

echo json_encode(['erro' => 'Ação inválida']);
