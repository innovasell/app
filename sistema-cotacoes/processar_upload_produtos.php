<?php
session_start();
require_once 'conexao.php';

// Verificação de segurança (apenas admin ou permissão específica)
// Ajuste conforme sua lógica de permissão
if (!isset($_SESSION['representante_email'])) {
    die("Acesso negado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo_csv'])) {
    $file = $_FILES['arquivo_csv'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Erro no upload do arquivo.");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        die("Por favor, envie um arquivo CSV.");
    }

    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        die("Erro ao abrir o arquivo.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Inativar todos os produtos
        $pdo->exec("UPDATE cot_estoque SET ativo = 0");

        // Pular cabeçalho se existir
        $header = fgetcsv($handle, 0, ';'); // Assumindo separador ;

        // Preparar statements
        $stmtCheck = $pdo->prepare("SELECT id FROM cot_estoque WHERE codigo = ?");
        $stmtUpdate = $pdo->prepare("UPDATE cot_estoque SET produto=?, unidade=?, ncm=?, ipi=?, origem=?, ativo=1 WHERE id=?");
        $stmtInsert = $pdo->prepare("INSERT INTO cot_estoque (codigo, produto, unidade, ncm, ipi, origem, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)");

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            // Mapeamento das colunas (ajuste conforme seu template)
            // 0: codigo, 1: produto, 2: unidade, 3: ncm, 4: ipi, 5: origem
            $codigo = trim($row[0] ?? '');
            $produto = trim($row[1] ?? '');
            $unidade = trim($row[2] ?? '');
            $ncm = trim($row[3] ?? '');
            $ipi = str_replace(',', '.', str_replace('.', '', trim($row[4] ?? '0'))); // Trata formato PT-BR 1.000,00 -> 1000.00
            $origem = (int) trim($row[5] ?? 0);

            if (empty($codigo) || empty($produto))
                continue; // Pular linhas vazias

            // Verifica se já existe
            $stmtCheck->execute([$codigo]);
            $exists = $stmtCheck->fetchColumn();

            if ($exists) {
                // Atualiza e Reativa
                $stmtUpdate->execute([$produto, $unidade, $ncm, $ipi, $origem, $exists]);
            } else {
                // Insere Novo Ativo
                $stmtInsert->execute([$codigo, $produto, $unidade, $ncm, $ipi, $origem]);
            }
        }

        $pdo->commit();
        header("Location: gerenciar_produtos.php?sucesso=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao processar: " . $e->getMessage());
    } finally {
        fclose($handle);
    }
} else {
    header("Location: gerenciar_produtos.php");
    exit;
}
?>