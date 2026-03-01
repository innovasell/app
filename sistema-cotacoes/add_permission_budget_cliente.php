<?php
/**
 * add_permission_budget_cliente.php
 * Script ONE-TIME: insere a permissão 'budget_cliente' na tabela cot_menu_permissoes.
 * Acesse uma vez no servidor e apague depois.
 */
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    die('Acesso negado.');
}

try {
    // Verifica se já existe
    $check = $pdo->prepare("SELECT id FROM cot_menu_permissoes WHERE menu_key = 'budget_cliente' LIMIT 1");
    $check->execute();

    if ($check->rowCount() === 0) {
        $pdo->prepare("INSERT INTO cot_menu_permissoes (menu_key, grupos_permitidos) VALUES ('budget_cliente', '[\"admin\",\"geral\"]')")->execute();
        echo "<div style='font-family:sans-serif;padding:20px;color:green'><strong>✅ Permissão 'budget_cliente' inserida com sucesso!</strong><br>Grupos permitidos: admin, geral.<br><br><a href='atualizar_budget.php'>→ Ir para Atualizar BUDGET</a></div>";
    } else {
        echo "<div style='font-family:sans-serif;padding:20px;color:orange'><strong>⚠️ A permissão 'budget_cliente' já existe.</strong><br><a href='atualizar_budget.php'>→ Ir para Atualizar BUDGET</a></div>";
    }
} catch (PDOException $e) {
    echo "<div style='font-family:sans-serif;padding:20px;color:red'><strong>❌ Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
