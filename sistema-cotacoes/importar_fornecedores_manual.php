<?php
/**
 * Importador Manual de Fornecedores
 * Cole os dados do Excel diretamente no array abaixo
 */

require_once 'conexao.php';

// COLE OS DADOS DO EXCEL AQUI
// Formato: ['Nome', 'País', 'Contato', 'Email', 'Telefone', 'Observações']
$fornecedores = [
    // Exemplo:
    // ['Fornecedor China Ltd', 'China', 'Wang Li', 'wang@exemplo.com', '+86 21 1234 5678', ''],
    // ['European Chemicals GmbH', 'Alemanha', 'Hans Mueller', 'hans@exemplo.de', '+49 30 9876 5432', ''],

    // COLE SEUS DADOS ABAIXO (mantenha o formato):

];

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Importar Fornecedores - Manual</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
<div class='container py-5'>
    <h2 class='mb-4'>Importação Manual de Fornecedores</h2>
    <div class='card'>
        <div class='card-body'>";

if (empty($fornecedores)) {
    echo "<div class='alert alert-warning'>
            <h5>Nenhum fornecedor para importar</h5>
            <p>Edite o arquivo <code>importar_fornecedores_manual.php</code> e cole os dados do Excel no array <code>\$fornecedores</code>.</p>
            <hr>
            <h6>Formato:</h6>
            <pre>\$fornecedores = [
    ['Nome Fornecedor', 'País', 'Nome Contato', 'email@exemplo.com', 'Telefone', 'Observações'],
    ['Outro Fornecedor', 'Brasil', 'João Silva', 'joao@exemplo.com.br', '(11) 98765-4321', ''],
    // ... mais fornecedores
];</pre>
          </div>";
} else {
    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO cot_fornecedores (nome, pais, contato, email, telefone, observacoes, ativo) 
                VALUES (:nome, :pais, :contato, :email, :telefone, :observacoes, :ativo)
                ON DUPLICATE KEY UPDATE 
                pais = VALUES(pais),
                contato = VALUES(contato),
                email = VALUES(email),
                telefone = VALUES(telefone),
                observacoes = VALUES(observacoes)";

        $stmt = $pdo->prepare($sql);

        $sucesso = 0;
        $erros = 0;

        foreach ($fornecedores as $index => $forn) {
            try {
                $nome = trim($forn[0] ?? '');
                $pais = trim($forn[1] ?? '');
                $contato = trim($forn[2] ?? '');
                $email = trim($forn[3] ?? '');
                $telefone = trim($forn[4] ?? '');
                $observacoes = trim($forn[5] ?? '');

                if (empty($nome)) {
                    echo "<div class='alert alert-warning'>Linha " . ($index + 1) . ": Nome vazio, pulando...</div>";
                    continue;
                }

                $stmt->execute([
                    ':nome' => $nome,
                    ':pais' => $pais,
                    ':contato' => $contato,
                    ':email' => $email,
                    ':telefone' => $telefone,
                    ':observacoes' => $observacoes,
                    ':ativo' => 1
                ]);

                echo "<div class='alert alert-success'>✅ <strong>" . htmlspecialchars($nome) . "</strong> importado com sucesso!</div>";
                $sucesso++;

            } catch (PDOException $e) {
                echo "<div class='alert alert-danger'>❌ Erro ao importar " . htmlspecialchars($forn[0]) . ": " . htmlspecialchars($e->getMessage()) . "</div>";
                $erros++;
            }
        }

        $pdo->commit();

        echo "<hr>
              <h4>Resumo da Importação:</h4>
              <div class='alert alert-info'>
                <strong>Total processado:</strong> " . count($fornecedores) . "<br>
                <strong>Sucessos:</strong> $sucesso<br>
                <strong>Erros:</strong> $erros
              </div>
              <a href='gerenciar_fornecedores.php' class='btn btn-primary mt-3'>Ver Fornecedores</a>";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<div class='alert alert-danger'>Erro fatal: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

echo "    </div>
        </div>
    </div>
</body>
</html>";
?>