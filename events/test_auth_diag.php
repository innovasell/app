<?php
// Script de diagnóstico de autenticação (não expõe a senha completa)
require_once 'conexao.php';

$email = "hector.hansen@innovasell.com.br";
echo "<h2>Diagnóstico de Usuário: $email</h2>";

try {
    // 1. Verifica se usuário existe
    $stmt = $conn->prepare("SELECT id, nome, email, senha FROM cot_representante WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        echo "<p style='color:green'>✅ Usuário encontrado!</p>";
        echo "<ul>";
        echo "<li>ID: " . $user['id'] . "</li>";
        echo "<li>Nome: " . $user['nome'] . "</li>";

        $senha = $user['senha'];
        $len = strlen($senha);
        echo "<li>Tamanho da senha armazenada: " . $len . " caracteres</li>";

        // Verifica formato da senha
        if ($len == 60 && substr($senha, 0, 1) === '$') {
            echo "<li>Formato: Parece ser um hash BCRYPT (padrão password_hash)</li>";
        } elseif ($len == 32) {
            echo "<li>Formato: Parece ser MD5 (Legado)</li>";
        } else {
            echo "<li>Formato: Texto plano ou desconhecido</li>";
        }

        // Mostra primeiros/últimos caracteres para confirmar se não está vazio ou corrompido
        if ($len > 5) {
            echo "<li>Snippet: " . substr($senha, 0, 3) . "..." . substr($senha, -3) . "</li>";
        }
        echo "</ul>";

    } else {
        echo "<p style='color:red'>❌ Usuário NÃO encontrado na tabela cot_representante!</p>";

        // Lista alguns usuários para ver se estamos no banco certo
        echo "<h4>Usuários na tabela (Limit 5):</h4>";
        $res = $conn->query("SELECT email FROM cot_representante LIMIT 5");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                echo $row['email'] . "<br>";
            }
        }
    }

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>