<?php
require_once 'conexao.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['representante_email'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$codigo = $_GET['codigo'] ?? '';
$cliente = $_GET['cliente'] ?? '';

if (empty($codigo)) {
    echo json_encode(['error' => 'Código do produto não informado']);
    exit;
}

try {
    // 1. Histórico do Cliente Específico (Todas as datas, ou limitado se preferir)
    // Trazendo colunas: Quantidade (VOLUME?), Valor (PREÇO FULL?), Dolar, Data, Embalagem
    // Ajuste conforme suas colunas reais: VOLUME, PREÇO FULL USD/KG, DOLAR COTADO, DATA, EMBALAGEM_KG
    $sqlCliente = "SELECT DATA, VOLUME, `PREÇO FULL USD/KG` as PRECO_FULL, `PREÇO NET USD/KG` as PRECO_NET, `DOLAR COTADO` as DOLAR, EMBALAGEM_KG
                   FROM cot_cotacoes_importadas
                   WHERE `COD DO PRODUTO` = :codigo 
                   AND `RAZÃO SOCIAL` = :cliente
                   ORDER BY DATA DESC
                   LIMIT 10"; // Limitando para não poluir demais

    $stmt = $pdo->prepare($sqlCliente);
    $stmt->execute([':codigo' => $codigo, ':cliente' => $cliente]);
    $historicoCliente = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Histórico Geral (Últimos 12 meses)
    // Mesmas colunas + Razão Social
    $dataLimite = date('Y-m-d', strtotime('-12 months'));

    $sqlGeral = "SELECT `RAZÃO SOCIAL` as CLIENTE, DATA, VOLUME, `PREÇO FULL USD/KG` as PRECO_FULL, `PREÇO NET USD/KG` as PRECO_NET, `DOLAR COTADO` as DOLAR, EMBALAGEM_KG
                 FROM cot_cotacoes_importadas
                 WHERE `COD DO PRODUTO` = :codigo
                 AND DATA >= :dataLimite
                 ORDER BY DATA DESC
                 LIMIT 50";

    $stmt = $pdo->prepare($sqlGeral);
    $stmt->execute([':codigo' => $codigo, ':dataLimite' => $dataLimite]);
    $historicoGeral = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'cliente' => $historicoCliente,
        'geral' => $historicoGeral
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
