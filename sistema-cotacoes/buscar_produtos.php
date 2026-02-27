<?php
require_once 'conexao.php';

header('Content-Type: application/json');

$termo = $_GET['q'] ?? '';

try {
    $sql = "SELECT * FROM cot_estoque 
            WHERE (codigo LIKE :termo 
               OR produto LIKE :termo 
               OR ncm LIKE :termo)
               AND ativo = 1
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':termo' => "%$termo%"]);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($produtos);
} catch (Exception $e) {
    echo json_encode(['erro' => $e->getMessage()]);
}
