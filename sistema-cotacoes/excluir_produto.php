<?php
session_start();
require_once 'conexao.php';

// Segurança: Verificar permissão se necessário (ex: if !admin)

$codigo = $_GET['codigo'] ?? '';

if (empty($codigo)) {
    header("Location: gerenciar_produtos.php?erro=" . urlencode("Código inválido."));
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM cot_estoque WHERE codigo = :codigo");
    $stmt->execute([':codigo' => $codigo]);

    if ($stmt->rowCount() > 0) {
        header("Location: gerenciar_produtos.php?sucesso=1");
    } else {
        header("Location: gerenciar_produtos.php?erro=" . urlencode("Produto não encontrado ou já excluído."));
    }
} catch (PDOException $e) {
    // Tratamento para integridade referencial (se houver chaves estrangeiras)
    if ($e->errorInfo[1] == 1451) {
        $erro = "Não é possível excluir este produto pois ele está vinculado a outros registros.";
    } else {
        $erro = "Erro ao excluir: " . $e->getMessage();
    }
    header("Location: gerenciar_produtos.php?erro=" . urlencode($erro));
}
exit();
?>