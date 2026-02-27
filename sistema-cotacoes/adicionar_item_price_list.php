<?php
session_start();
require_once 'conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fabricante = $_POST['fabricante'] ?? '';
    $classificacao = $_POST['classificacao'] ?? '';
    $codigo = $_POST['codigo'] ?? '';
    $produto = $_POST['produto'] ?? '';
    $fracionado = $_POST['fracionado'] ?? 'Não';
    $embalagem = $_POST['embalagem'] ?? 0;
    $preco_net_usd = $_POST['preco_net_usd'] ?? 0;
    $lead_time = $_POST['lead_time'] ?? '';

    // Validação básica
    if (empty($produto) || empty($preco_net_usd)) {
        header("Location: gerenciar_price_list.php?erro=" . urlencode("Produto e Preço são obrigatórios!"));
        exit;
    }

    try {
        $sql = "INSERT INTO cot_price_list (fabricante, classificacao, codigo, produto, fracionado, embalagem, preco_net_usd, lead_time) 
                VALUES (:fabricante, :classificacao, :codigo, :produto, :fracionado, :embalagem, :preco_net_usd, :lead_time)";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':fabricante', $fabricante);
        $stmt->bindParam(':classificacao', $classificacao);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':produto', $produto);
        $stmt->bindParam(':fracionado', $fracionado);
        $stmt->bindParam(':embalagem', $embalagem);
        $stmt->bindParam(':preco_net_usd', $preco_net_usd);
        $stmt->bindParam(':lead_time', $lead_time);

        if ($stmt->execute()) {
            header("Location: gerenciar_price_list.php?sucesso_add=1");
        } else {
            header("Location: gerenciar_price_list.php?erro=" . urlencode("Erro ao inserir item no banco de dados."));
        }
    } catch (PDOException $e) {
        header("Location: gerenciar_price_list.php?erro=" . urlencode("Erro SQL: " . $e->getMessage()));
    }
} else {
    header("Location: gerenciar_price_list.php");
}
?>