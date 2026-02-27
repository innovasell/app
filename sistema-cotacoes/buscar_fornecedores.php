<?php
require_once 'conexao.php';

header('Content-Type: application/json');

$termo = isset($_GET['q']) ? trim($_GET['q']) : '';

try {
    if (empty($termo)) {
        // Retornar todos os fornecedores ativos
        $sql = "SELECT id, nome, pais, contato, email, telefone 
            FROM cot_fornecedores 
            WHERE ativo = 1 
            ORDER BY nome ASC 
            LIMIT 50";
        $stmt = $pdo->query($sql);
    } else {
        // Buscar por termo
        $sql = "SELECT id, nome, pais, contato, email, telefone 
            FROM cot_fornecedores 
            WHERE ativo = 1 
            AND (nome LIKE :termo OR pais LIKE :termo)
            ORDER BY nome ASC 
            LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':termo' => "%$termo%"]);
    }

    $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($fornecedores);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao buscar fornecedores: ' . $e->getMessage()]);
}
?>