<?php
// events/test_report_launcher.php
// Script auxiliar para gerar links de teste de relatórios com dados reais
require_once 'conexao.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

function getOneResult($conn, $field)
{
    $sql = "SELECT DISTINCT $field FROM viagem_express_expenses WHERE $field IS NOT NULL AND $field != '' LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) {
        return $row[$field];
    }
    return null;
}

$evento = getOneResult($conn, 'evento_visita');
$fatura = getOneResult($conn, 'num_fatura');
$passageiro = getOneResult($conn, 'passageiro');

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Teste de Relatórios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container py-5">
    <h1>🚀 Links de Teste Rápido (Relatórios)</h1>
    <p class="mb-4">Use os links abaixo para verificar se a geração de PDF está funcionando com dados reais do seu
        banco.</p>

    <div class="row">
        <!-- Evento -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">Por Evento/Visita</div>
                <div class="card-body">
                    <?php if ($evento): ?>
                        <p><strong>Dado encontrado:</strong>
                            <?= htmlspecialchars($evento) ?>
                        </p>
                        <a href="reports/generate_pdf.php?tipo=evento&valor=<?= urlencode($evento) ?>&acao=download"
                            target="_blank" class="btn btn-outline-primary w-100">Gerar PDF</a>
                        <br><br>
                        <a href="reports/generate_pdf.php?tipo=evento&valor=<?= urlencode($evento) ?>&acao=email"
                            target="_blank" class="btn btn-outline-secondary w-100">Testar Envio Email</a>
                    <?php else: ?>
                        <div class="alert alert-warning">Nenhum evento encontrado no banco.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Fatura -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">Por Fatura</div>
                <div class="card-body">
                    <?php if ($fatura): ?>
                        <p><strong>Dado encontrado:</strong>
                            <?= htmlspecialchars($fatura) ?>
                        </p>
                        <a href="reports/generate_pdf.php?tipo=fatura&valor=<?= urlencode($fatura) ?>&acao=download"
                            target="_blank" class="btn btn-outline-success w-100">Gerar PDF</a>
                        <br><br>
                        <a href="reports/generate_pdf.php?tipo=fatura&valor=<?= urlencode($fatura) ?>&acao=email"
                            target="_blank" class="btn btn-outline-secondary w-100">Testar Envio Email</a>
                    <?php else: ?>
                        <div class="alert alert-warning">Nenhuma fatura encontrada. Importe um CSV com faturas.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colaborador -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">Por Colaborador</div>
                <div class="card-body">
                    <?php if ($passageiro): ?>
                        <p><strong>Dado encontrado:</strong>
                            <?= htmlspecialchars($passageiro) ?>
                        </p>
                        <a href="reports/generate_pdf.php?tipo=colaborador&valor=<?= urlencode($passageiro) ?>&acao=download"
                            target="_blank" class="btn btn-outline-info w-100">Gerar PDF</a>
                        <br><br>
                        <a href="reports/generate_pdf.php?tipo=colaborador&valor=<?= urlencode($passageiro) ?>&acao=email"
                            target="_blank" class="btn btn-outline-secondary w-100">Testar Envio Email</a>
                    <?php else: ?>
                        <div class="alert alert-warning">Nenhum passageiro encontrado.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 text-center">
        <a href="relatorios.php" class="btn btn-link">Voltar para a página oficial de relatórios</a>
    </div>
</body>

</html>