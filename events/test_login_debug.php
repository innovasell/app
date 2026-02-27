<?php
// events/test_login_debug.php
// Script de diagnóstico interativo para problemas de login
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conexao.php'; // Usa a conexão do events (que conecta em u849249951_innovasell)

$emailAlvo = "hector.hansen@innovasell.com.br";
$senhaTeste = $_POST['senha_teste'] ?? '';

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Diagnóstico de Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 2rem;
            background: #f8f9fa
        }

        .card {
            margin-bottom: 1rem
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🛠️ Diagnóstico de Autenticação</h1>
        <p class="text-muted">Verificando banco de dados: <strong>
                <?= $banco ?>
            </strong></p>

        <!-- Verificação Tabela NOVA (cot_representante) -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                1. Tabela Nova (cot_representante) - <em>Usada pelo login atual</em>
            </div>
            <div class="card-body">
                <?php
                try {
                    $stmt = $conn->prepare("SELECT id, nome, email, senha, admin FROM cot_representante WHERE email = ?");
                    if ($stmt) {
                        $stmt->bind_param('s', $emailAlvo);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $userNova = $res->fetch_assoc();

                        if ($userNova) {
                            echo "<div class='alert alert-success'>✅ Usuário ENCONTRADO!</div>";
                            echo "<ul>";
                            echo "<li><strong>ID:</strong> " . $userNova['id'] . "</li>";
                            echo "<li><strong>Nome:</strong> " . $userNova['nome'] . "</li>";
                            echo "<li><strong>Admin:</strong> " . $userNova['admin'] . "</li>";
                            echo "<li><strong>Senha (Hash):</strong> " . substr($userNova['senha'], 0, 10) . "... (Len: " . strlen($userNova['senha']) . ")</li>";
                            echo "</ul>";
                        } else {
                            echo "<div class='alert alert-danger'>❌ Usuário NÃO ENCONTRADO nesta tabela.</div>";
                            echo "<p>Isso explica por que o login falha. O usuário precisa ser cadastrado no sistema de cotações.</p>";
                        }
                        $stmt->close();
                    } else {
                        echo "<div class='alert alert-warning'>⚠️ Tabela `cot_representante` não encontrada ou erro de SQL: " . $conn->error . "</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
                }
                ?>
            </div>
        </div>

        <!-- Verificação Tabela ANTIGA (users) -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                2. Tabela Antiga (users) - <em>Legada</em>
            </div>
            <div class="card-body">
                <?php
                try {
                    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ?");
                    if ($stmt) {
                        $stmt->bind_param('s', $emailAlvo);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $userAntiga = $res->fetch_assoc();

                        if ($userAntiga) {
                            echo "<div class='alert alert-info'>ℹ️ Usuário existe na tabela antiga (`users`).</div>";
                            echo "<ul>";
                            echo "<li><strong>ID:</strong> " . $userAntiga['id'] . "</li>";
                            echo "<li><strong>Nome:</strong> " . $userAntiga['name'] . "</li>";
                            echo "</ul>";
                        } else {
                            echo "<div class='alert alert-secondary'>Usuário também não existe na tabela antiga.</div>";
                        }
                        $stmt->close();
                    } else {
                        echo "<div class='alert alert-warning'>⚠️ Tabela `users` não encontrada.</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
                }
                ?>
            </div>
        </div>

        <!-- Teste de Senha -->
        <?php if (!empty($userNova)): ?>
            <div class="card border-primary">
                <div class="card-header bg-success text-white">
                    3. Teste de Senha (para `cot_representante`)
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-auto">
                            <input type="text" name="senha_teste" class="form-control"
                                placeholder="Digite a senha para testar" value="<?= htmlspecialchars($senhaTeste) ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary mb-3">Verificar</button>
                        </div>
                    </form>

                    <?php if ($senhaTeste): ?>
                        <hr>
                        <?php if (password_verify($senhaTeste, $userNova['senha'])): ?>
                            <div class="alert alert-success fw-bold">✅ A SENHA ESTÁ CORRETA! (Hash BCRYPT validado)</div>
                        <?php elseif (md5($senhaTeste) === $userNova['senha']): ?>
                            <div class="alert alert-warning fw-bold">⚠️ A SENHA ESTÁ NO FORMATO MD5 (LEGADO)!</div>
                            <p>O sistema novo espera BCRYPT. Precisamos atualizar o hash.</p>
                        <?php else: ?>
                            <div class="alert alert-danger fw-bold">❌ SENHA INCORRETA!</div>
                            <p>O hash no banco não bate com a senha digitada.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</body>

</html>