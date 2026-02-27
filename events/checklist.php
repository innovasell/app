<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checklist - Sistema de Eventos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4"><i class="bi bi-clipboard-check"></i> Checklist do Sistema</h1>

        <div id="results"></div>

        <div class="mt-4">
            <button class="btn btn-primary" onclick="runTests()">
                <i class="bi bi-play-fill"></i> Executar Testes
            </button>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <script>
        async function runTests() {
            const results = document.getElementById('results');
            results.innerHTML = '<div class="alert alert-info">Executando testes...</div>';

            let html = '';

            // Teste 1: Conexão com banco
            html += '<div class="card mb-3">';
            html += '<div class="card-header"><i class="bi bi-database"></i> Teste 1: Conexão com Banco de Dados</div>';
            html += '<div class="card-body">';
            try {
                const response = await fetch('api/test.php');
                const data = await response.json();
                if (data.success) {
                    html += '<div class="alert alert-success"><i class="bi bi-check-circle"></i> ' + data.message + '</div>';
                } else {
                    html += '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ' + data.message + '</div>';
                }
            } catch (e) {
                html += '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Erro: ' + e.message + '</div>';
            }
            html += '</div></div>';

            results.innerHTML = html;

            // Teste 2: Criação de tabela
            html += '<div class="card mb-3">';
            html += '<div class="card-header"><i class="bi bi-table"></i> Teste 2: Criação/Verificação da Tabela</div>';
            html += '<div class="card-body">';
            try {
                const response = await fetch('setup_db.php?api=1');
                const data = await response.json();
                if (data.success) {
                    html += '<div class="alert alert-success"><i class="bi bi-check-circle"></i> ' + data.message + '</div>';
                } else {
                    html += '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> ' + data.message + '</div>';
                }
            } catch (e) {
                html += '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Erro: ' + e.message + '</div>';
            }
            html += '</div></div>';

            results.innerHTML = html;

            // Teste 3: Upload de arquivo
            html += '<div class="card mb-3">';
            html += '<div class="card-header"><i class="bi bi-cloud-upload"></i> Teste 3: Sistema de Upload</div>';
            html += '<div class="card-body">';
            html += '<p>Para testar o upload, use a página de importação:</p>';
            html += '<a href="importar.php" class="btn btn-primary"><i class="bi bi-upload"></i> Ir para Importação</a>';
            html += '</div></div>';

            results.innerHTML = html;

            // Resumo final
            html += '<div class="alert alert-info mt-4">';
            html += '<h5><i class="bi bi-info-circle"></i> Próximos Passos:</h5>';
            html += '<ol>';
            html += '<li>Se todos os testes passarem, vá para <a href="importar.php">Importar Dados</a></li>';
            html += '<li>Faça upload de um arquivo CSV do VIAGEM EXPRESS</li>';
            html += '<li>Verifique o dashboard em <a href="index.php">Dashboard</a></li>';
            html += '</ol>';
            html += '</div>';

            results.innerHTML = html;
        }
    </script>
</body>

</html>