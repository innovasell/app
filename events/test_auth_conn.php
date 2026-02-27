<?php
// Script de diagnóstico para rodar no navegador
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnóstico de Autenticação</h1>";

require_once 'conexao.php';
echo "<p>✅ Conexão importada com sucesso</p>";

$email = "hector.hansen@innovasell.com.br";
echo "<h3>Verificando: $email</h3>";

try {
    $stmt = $conn->prepare("SELECT id, nome, email, senha, admin FROM cot_representante WHERE email = ?");
    if (!$stmt) {
        die("<p style='color:red'>Erro no prepare: " . $conn->error . "</p>");
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        echo "<p style='color:green; font-weight:bold'>✅ Usuário ENCONTRADO!</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><td>ID</td><td>" . $user['id'] . "</td></tr>";
        echo "<tr><td>Nome</td><td>" . $user['nome'] . "</td></tr>";
        echo "<tr><td>Admin</td><td>" . $user['admin'] . "</td></tr>";

        $senha = $user['senha'];
        $len = strlen($senha);
        echo "<tr><td>Senha (len)</td><td>" . $len . "</td></tr>";

        $tipo = "Desconhecido";
        if ($len == 60 && substr($senha, 0, 1) === '$')
            $tipo = "HASH BCRYPT (Correto)";
        elseif ($len == 32)
            $tipo = "MD5 (Incompatível com password_verify padrão)";
        else
            $tipo = "Texto Plano / Outro (Incompatível)";

        echo "<tr><td>Formato Senha</td><td><strong>$tipo</strong></td></tr>";
        echo "</table>";

        // Teste de hash
        $testPass = "123456"; // Apenas para teste
        if (password_verify($testPass, $senha)) {
            echo "<p>Teste com senha '123456': VÁLIDA</p>";
        } else {
            echo "<p>Teste com senha '123456': INVÁLIDA</p>";
        }
    } else {
        echo "<p style='color:red; font-weight:bold'>❌ Usuário NÃO ENCONTRADO</p>";

        echo "<p>Listando primeiros 5 usuários para conferência:</p>";
        $res = $conn->query("SELECT email FROM cot_representante LIMIT 5");
        while ($row = $res->fetch_assoc()) {
            echo "<li>" . $row['email'] . "</li>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Exceção: " . $e->getMessage() . "</p>";
}
?>