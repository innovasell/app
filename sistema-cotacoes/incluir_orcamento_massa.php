<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
$pagina_ativa = 'incluir_orcamento_massa';

require_once 'header.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Incluir Orçamento em Massa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container py-5">
        <h2 class="mb-4">Incluir Orçamento em Massa</h2>

        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Upload de Itens</h5>
                <p class="card-text text-muted">Faça o upload de uma planilha CSV com os itens do orçamento para
                    preenchimento automático.</p>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Instruções:</strong>
                    <ol class="mb-0 mt-2">
                        <li>Baixe o <a href="template_orcamento_massa.csv" download class="fw-bold">modelo CSV aqui</a>.
                        </li>
                        <li>Preencha as colunas: <code>CODIGO_PRODUTO</code>, <code>QUANTIDADE</code>,
                            <code>EMBALAGEM</code>, <code>DISPONIBILIDADE</code>, <code>PRECO_NET_USD</code>.</li>
                        <li>Salve o arquivo e faça o upload abaixo.</li>
                    </ol>
                </div>

                <form action="processar_orcamento_massa.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="arquivo_csv" class="form-label">Arquivo CSV</label>
                        <input class="form-control" type="file" id="arquivo_csv" name="arquivo_csv" accept=".csv"
                            required>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i> Carregar Itens e Criar Orçamento
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="mt-3">
            <a href="incluir_orcamento.php" class="text-decoration-none text-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar para inclusão manual
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>