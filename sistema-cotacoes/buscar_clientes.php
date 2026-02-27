<?php
require_once 'conexao.php';

$termo = $_GET['q'] ?? '';

$sql = "SELECT id, razao_social, uf, cnpj FROM cot_clientes 
        WHERE razao_social LIKE :termo 
           OR uf LIKE :termo 
           OR nome LIKE :termo
        ORDER BY razao_social ASC
        LIMIT 50";

try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':termo' => "%$termo%"]);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($clientes);
} catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
