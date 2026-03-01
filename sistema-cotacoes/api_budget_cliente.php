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
        // ── 1. Busca produtos do budget (query simples, sem JOIN) ──────────────
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
                    b.comentarios_supply
                FROM cot_budget_cliente b
                WHERE b.cnpj = :cnpj
                ORDER BY b.produto ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cnpj' => $cnpj]);
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── 2. Tenta enriquecer com price list (tolerante a falha) ────────────
        try {
            // Cria mapa produto → preco_net_usd a partir da cot_price_list
            $plStmt = $pdo->query("SELECT produto, preco_net_usd, embalagem FROM cot_price_list");
            $priceMap = [];
            while ($row = $plStmt->fetch(PDO::FETCH_ASSOC)) {
                $key = mb_strtolower(trim($row['produto']), 'UTF-8');
                $priceMap[$key] = [
                    'preco_net_usd' => $row['preco_net_usd'],
                    'embalagem'     => $row['embalagem'],
                ];
            }
            // Adiciona os dados de price list em cada produto
            foreach ($produtos as &$p) {
                $key = mb_strtolower(trim($p['produto'] ?? ''), 'UTF-8');
                $p['price_list_usd'] = $priceMap[$key]['preco_net_usd'] ?? null;
                $p['price_list_emb'] = $priceMap[$key]['embalagem']     ?? null;
            }
            unset($p);
        } catch (Throwable $plEx) {
            // cot_price_list não acessível — retorna produtos sem price list
            foreach ($produtos as &$p) {
                $p['price_list_usd'] = null;
                $p['price_list_emb'] = null;
            }
            unset($p);
        }

        // Força encoding UTF-8 em campos de texto
        $flags = JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR;
        echo json_encode($produtos, $flags);

    } catch (PDOException $e) {
        // Erro na query principal — retorna array vazio com info de debug
        $flags = JSON_UNESCAPED_UNICODE;
        echo json_encode(['__erro' => $e->getMessage()], $flags);
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

        // Se não encontrar o CNPJ em cot_clientes, retorna apenas os dados do budget
        if (!$cliente) {
            echo json_encode($extra ?? []);
        } else {
            echo json_encode(array_merge($cliente, $extra ?? []));
        }
    } catch (PDOException $e) {
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit();
}

echo json_encode(['erro' => 'Ação inválida']);
