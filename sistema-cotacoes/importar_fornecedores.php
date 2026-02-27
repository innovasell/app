<?php
/**
 * Script Simplificado de Importação de Fornecedores
 * 
 * INSTRUÇÕES:
 * 1. Exporte o arquivo "BASE FORNECEDORES.xlsx" para "BASE FORNECEDORES.csv" (CSV UTF-8)
 * 2. Execute este script no navegador
 * 
 * OU use o importador_fornecedores_excel.php se tiver PhpSpreadsheet instalado
 */

require_once 'conexao.php';

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Importar Fornecedores</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
<div class='container py-5'>
    <h2 class='mb-4'>Importação de Fornecedores</h2>";

// Verificar se existe arquivo CSV
$arquivoCSV = __DIR__ . '/BASE FORNECEDORES.csv';
$arquivoExcel = __DIR__ . '/BASE FORNECEDORES.xlsx';

if (file_exists($arquivoCSV)) {
    echo "<div class='alert alert-info'>Usando arquivo CSV...</div>";
    importarCSV($arquivoCSV, $pdo);
} else if (file_exists($arquivoExcel)) {
    echo "<div class='alert alert-warning'>
            <h5>Excel encontrado, mas precisa ser convertido para CSV</h5>
            <p>Por favor, siga estes passos:</p>
            <ol>
                <li>Abra o arquivo <strong>BASE FORNECEDORES.xlsx</strong> no Excel</li>
                <li>Clique em <strong>Arquivo → Salvar como</strong></li>
                <li>Escolha o tipo <strong>CSV UTF-8 (delimitado por vírgula) (*.csv)</strong></li>
                <li>Salve com o nome <strong>BASE FORNECEDORES.csv</strong></li>
                <li>Execute este script novamente</li>
            </ol>
            <p><strong>OU</strong> use o importador_fornecedores_manual.php para inserção manual</p>
          </div>";
} else {
    echo "<div class='alert alert-danger'>Nenhum arquivo de fornecedores encontrado!</div>";
}

echo "</div></body></html>";

function importarCSV($arquivo, $pdo)
{
    try {
        $handle = fopen($arquivo, 'r');

        if (!$handle) {
            throw new Exception("Não foi possível abrir o arquivo CSV");
        }

        // Ler cabeçalho
        $cabecalho = fgetcsv($handle, 1000, ',');
        echo "<div class='alert alert-success'>Arquivo CSV lido! Cabeçalho: " . implode(' | ', $cabecalho) . "</div>";

        $pdo->beginTransaction();

        $sql = "INSERT INTO cot_fornecedores (nome, pais, contato, email, telefone, observacoes, ativo) 
                VALUES (:nome, :pais, :contato, :email, :telefone, :observacoes, :ativo)
                ON DUPLICATE KEY UPDATE 
                pais = VALUES(pais),
                contato = VALUES(contato),
                email = VALUES(email),
                telefone = VALUES(telefone)";

        $stmt = $pdo->prepare($sql);

        $sucesso = 0;
        $erros = 0;
        $linha = 2; // Começando após cabeçalho

        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            // Pular linhas vazias
            if (empty($row[0])) {
                continue;
            }

            try {
                $nome = trim($row[0] ?? '');
                $pais = trim($row[1] ?? '');
                $contato = trim($row[2] ?? '');
                $email = trim($row[3] ?? '');
                $telefone = trim($row[4] ?? '');
                $observacoes = trim($row[5] ?? '');

                if (empty($nome)) {
                    echo "<div class='alert alert-warning'>Linha $linha: Nome vazio, pulando...</div>";
                    $linha++;
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

                echo "<div class='alert alert-success'>✅ Linha $linha: <strong>" . htmlspecialchars($nome) . "</strong> importado!</div>";
                $sucesso++;

            } catch (PDOException $e) {
                echo "<div class='alert alert-danger'>❌ Linha $linha: " . htmlspecialchars($e->getMessage()) . "</div>";
                $erros++;
            }

            $linha++;
        }

        fclose($handle);
        $pdo->commit();

        echo "<hr><h4>Resumo:</h4>
              <div class='alert alert-info'>
                <strong>Sucessos:</strong> $sucesso<br>
                <strong>Erros:</strong> $erros
              </div>
              <a href='gerenciar_fornecedores.php' class='btn btn-primary'>Ver Fornecedores</a>";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<div class='alert alert-danger'>Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>