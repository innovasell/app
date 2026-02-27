<?php
require_once 'conexao.php';

header('Content-Type: application/json');

$num = isset($_GET['num']) ? trim($_GET['num']) : '';

if (empty($num)) {
    echo json_encode(['erro' => 'Número do cenário não informado.']);
    exit();
}

try {
    // Buscar cabeçalho
    $sqlCabecalho = "SELECT * FROM cot_cenarios_importacao WHERE num_cenario = :num";
    $stmtCabecalho = $pdo->prepare($sqlCabecalho);
    $stmtCabecalho->execute([':num' => $num]);
    $cabecalho = $stmtCabecalho->fetch(PDO::FETCH_ASSOC);

    if (!$cabecalho) {
        echo json_encode(['erro' => 'Cenário não encontrado.']);
        exit();
    }

    // Buscar itens
    $sqlItens = "SELECT * FROM cot_cenarios_itens WHERE num_cenario = :num ORDER BY id";
    $stmtItens = $pdo->prepare($sqlItens);
    $stmtItens->execute([':num' => $num]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'cabecalho' => $cabecalho,
        'itens' => $itens
    ]);

} catch (PDOException $e) {
    echo json_encode(['erro' => 'Erro ao buscar detalhes: ' . $e->getMessage()]);
}
?>