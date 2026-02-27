<?php
/**
 * Importador de Fornecedores do CSV do Bling
 * Lê o arquivo relatório_contatos e importa apenas os fornecedores
 */

require_once 'conexao.php';

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Importar Fornecedores do Bling</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
<div class='container py-5'>
    <h2 class='mb-4'>Importação de Fornecedores (CSV Bling)</h2>
    <div class='card'>
        <div class='card-body'>";

// Buscar arquivo CSV mais recente
$arquivos = glob(__DIR__ . '/relatório_contatos*.csv');
if (empty($arquivos)) {
    echo "<div class='alert alert-danger'>Nenhum arquivo de relatório de contatos encontrado!</div>";
    echo "</div></div></div></body></html>";
    exit;
}

// Usar o arquivo mais recente
$arquivo = $arquivos[0];
echo "<div class='alert alert-info'>Usando arquivo: <strong>" . basename($arquivo) . "</strong></div>";

try {
    $handle = fopen($arquivo, 'r');

    if (!$handle) {
        throw new Exception("Não foi possível abrir o arquivo CSV");
    }

    // Ler cabeçalho
    $cabecalho = fgetcsv($handle, 10000, ';');
    echo "<div class='alert alert-success'>CSV lido! Total de colunas: " . count($cabecalho) . "</div>";

    // Mapear índices das colunas importantes
    $indices = [];
    foreach ($cabecalho as $i => $col) {
        $col = trim($col);
        if ($col == 'Razão Social')
            $indices['razao'] = $i;
        if ($col == 'Nome Fantasia')
            $indices['fantasia'] = $i;
        if ($col == 'País')
            $indices['pais'] = $i;
        if ($col == 'Nome')
            $indices['nome_contato'] = $i;
        if ($col == 'E-mail')
            $indices['email'] = $i;
        if ($col == 'Celular')
            $indices['celular'] = $i;
        if ($col == 'Telefone Comercial')
            $indices['telefone'] = $i;
        if ($col == 'Tipo de Situação')
            $indices['situacao'] = $i;
        if ($col == 'Tipo')
            $indices['tipo'] = $i; // Para identificar se é fornecedor
    }

    echo "<div class='alert alert-info'><strong>Campos mapeados:</strong> " . implode(', ', array_keys($indices)) . "</div>";

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
    $pulados = 0;
    $linha = 2;

    while (($row = fgetcsv($handle, 10000, ';')) !== FALSE) {
        // Pular linhas vazias
        if (empty($row[0]) || count($row) < 5) {
            continue;
        }

        try {
            // Verificar se é fornecedor
            $tipo = isset($indices['tipo']) ? strtolower(trim($row[$indices['tipo']] ?? '')) : '';
            $situacao = isset($indices['situacao']) ? trim($row[$indices['situacao']] ?? '') : '';

            // Apenas linhas que contêm "fornecedor" no campo Tipo
            if (strpos($tipo, 'fornecedor') === false) {
                $pulados++;
                $linha++;
                continue;
            }

            // Apenas fornecedores ativos
            if ($situacao && strtolower($situacao) != 'ativo') {
                $pulados++;
                $linha++;
                continue;
            }

            // Extrair dados
            $razao = isset($indices['razao']) ? trim($row[$indices['razao']] ?? '') : '';
            $fantasia = isset($indices['fantasia']) ? trim($row[$indices['fantasia']] ?? '') : '';
            $pais = isset($indices['pais']) ? trim($row[$indices['pais']] ?? '') : 'Brasil';
            $nomeContato = isset($indices['nome_contato']) ? trim($row[$indices['nome_contato']] ?? '') : '';
            $email = isset($indices['email']) ? trim($row[$indices['email']] ?? '') : '';
            $celular = isset($indices['celular']) ? trim($row[$indices['celular']] ?? '') : '';
            $telefone = isset($indices['telefone']) ? trim($row[$indices['telefone']] ?? '') : '';

            // Nome do fornecedor (priorizar nome fantasia, depois razão social)
            $nomeFornecedor = !empty($fantasia) ? $fantasia : $razao;

            if (empty($nomeFornecedor)) {
                echo "<div class='alert alert-warning'>Linha $linha: Nome vazio, pulando...</div>";
                $pulados++;
                $linha++;
                continue;
            }

            // Telefone principal (priorizar comercial, depois celular)
            $telefonePrincipal = !empty($telefone) ? $telefone : $celular;

            // Observações (se tiver razão social diferente do nome fantasia)
            $obs = '';
            if (!empty($razao) && !empty($fantasia) && $razao != $fantasia) {
                $obs = "Razão Social: $razao";
            }

            $stmt->execute([
                ':nome' => $nomeFornecedor,
                ':pais' => $pais,
                ':contato' => $nomeContato,
                ':email' => $email,
                ':telefone' => $telefonePrincipal,
                ':observacoes' => $obs,
                ':ativo' => 1
            ]);

            echo "<div class='alert alert-success'>✅ Linha $linha: <strong>" . htmlspecialchars($nomeFornecedor) . "</strong> (" . htmlspecialchars($pais) . ") importado!</div>";
            $sucesso++;

        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>❌ Linha $linha: " . htmlspecialchars($e->getMessage()) . "</div>";
            $erros++;
        }

        $linha++;
    }

    fclose($handle);
    $pdo->commit();

    echo "<hr><h4>Resumo da Importação:</h4>
          <div class='alert alert-info'>
            <strong>Total de linhas processadas:</strong> $linha<br>
            <strong>Fornecedores importados:</strong> $sucesso<br>
            <strong>Pulados (não-fornecedores ou inativos):</strong> $pulados<br>
            <strong>Erros:</strong> $erros
          </div>";

    if ($sucesso > 0) {
        echo "<a href='gerenciar_fornecedores.php' class='btn btn-primary btn-lg mt-3'>
                <i class='fas fa-building me-2'></i>Ver Fornecedores Importados
              </a>";
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<div class='alert alert-danger'>Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "    </div>
        </div>
    </div>
</body>
</html>";
?>