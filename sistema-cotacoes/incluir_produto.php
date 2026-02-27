<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

// Define a página ativa para o menu
$pagina_ativa = 'incluir_produto';

require_once 'header.php';
require_once 'conexao.php';


?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Incluir Novo Produto no Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Incluir Novo Produto no Estoque</h2>

        <div class="alert alert-warning" role="alert">
          <strong>ATENÇÃO:</strong> TODOS OS DADOS DEVEM SER PREENCHIDOS EXATAMENTE IGUAIS AO CADASTRO DO SISTEMA MAINÔ.
        </div>
        <?php if (isset($_GET['sucesso'])) : ?>
            <div class="alert alert-success">
                ✅ Produto "<strong><?= htmlspecialchars($_GET['produto_nome'] ?? '') ?></strong>" cadastrado com sucesso!
            </div>
        <?php elseif (isset($_GET['erro'])) : ?>
            <div class="alert alert-danger">
                ❌ Erro ao cadastrar produto: <?= htmlspecialchars($_GET['erro']) ?>
            </div>
        <?php endif; ?>

        <form action="salvar_produto.php" method="POST" class="border p-4 rounded bg-light">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="codigo" class="form-label">Código do Produto <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="codigo" name="codigo" required maxlength="50">
                </div>
                <div class="col-md-8">
                    <label for="produto" class="form-label">Nome do Produto <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="produto" name="produto" required maxlength="255">
                </div>
                <div class="col-md-3">
                    <label for="unidade" class="form-label">Unidade <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="unidade" name="unidade" required placeholder="Ex: KG, L, UN">
                </div>
                <div class="col-md-3">
                    <label for="ncm" class="form-label">NCM <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="ncm" name="ncm" required maxlength="20">
                </div>
                <div class="col-md-3">
                    <label for="ipi" class="form-label">IPI (%) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="ipi" name="ipi" required step="0.01" placeholder="Ex: 5.00">
                </div>
                <div class="col-md-3">
                    <label for="origem" class="form-label">Origem <span class="text-danger">*</span></label>
                    <select class="form-select" id="origem" name="origem" required>
                        <option value="" disabled selected>Selecione...</option>
                        <option value="0">0 - Nacional</option>
                        <option value="1">1 - Importado</option>
                        <option value="6">6 - Importado (CAMEX)</option>
                    </select>
                </div>
                <div class="col-12 text-end mt-4">
                    <button type="submit" class="btn btn-primary">Salvar Produto</button>
                </div>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>