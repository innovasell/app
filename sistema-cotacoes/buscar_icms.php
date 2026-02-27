<?php
require_once 'conexao.php';

header('Content-Type: application/json');

$uf = $_GET['uf'] ?? '';

if (!$uf) {
    echo json_encode(['erro' => 'UF não informada']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT aliquota FROM cot_icms WHERE uf = :uf");
    $stmt->execute([':uf' => strtoupper($uf)]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resultado) {
        echo json_encode(['aliquota' => $resultado['aliquota']]);
    } else {
        echo json_encode(['erro' => 'UF não encontrada']);
    }
} catch (PDOException $e) {
    echo json_encode(['erro' => 'Erro ao buscar alíquota']);
}
