<?php
session_start();
date_default_timezone_set('America/Sao_Paulo'); // Define fuso horário
// !!! DEFINA A PÁGINA ATIVA AQUI !!!
$pagina_ativa = 'consultar_amostras'; // Exemplo para consultar_amostras.php
// Use: 'incluir_amostra' para incluir_ped_amostras.php
// Use: 'gerenciar_cliente' para gerenciar_cliente.php
// Use: 'incluir_orcamento' para incluir_orcamento.php
// Use: 'filtrar' para filtrar.php
// Use: 'consultar_orcamentos' para consultar_orcamentos.php
// Use: 'previsao' para previsao.php

require_once 'header.php'; // Inclui o header
require_once 'conexao.php'; // Conexão PDO

// 1. Verificar Login
if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}



// --- CONFIGURAÇÕES ---
$itens_por_pagina = 25;

// --- OBTER PARÂMETROS ---
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$busca = trim(filter_input(INPUT_GET, 'busca', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

// --- CALCULAR OFFSET ---
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// --- CONSTRUIR QUERY BASE E FILTRO ---
$sql_base = "FROM pedidos_amostra pa INNER JOIN cot_clientes cc ON pa.id_cliente = cc.id";
$params = [];
$sql_where = "";
if (!empty($busca)) {
    // Ajuste a busca para incluir mais campos se desejar (ex: status, representante)
    $sql_where = " WHERE (pa.numero_referencia LIKE :busca OR cc.razao_social LIKE :busca)";
    $params[':busca'] = '%' . $busca . '%';
}

// --- QUERY PARA CONTAR TOTAL ---
$sql_count = "SELECT COUNT(pa.id) " . $sql_base . $sql_where;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();

// --- CALCULAR TOTAL DE PÁGINAS ---
$total_paginas = $total_registros > 0 ? ceil($total_registros / $itens_por_pagina) : 0;

// Ajusta página atual se for inválida após calcular o total
if ($pagina_atual > $total_paginas && $total_paginas > 0) {
    $pagina_atual = $total_paginas;
    $offset = ($pagina_atual - 1) * $itens_por_pagina; // Recalcula offset
} elseif ($pagina_atual > 1 && $total_paginas == 0) {
     $pagina_atual = 1;
     $offset = 0; // Recalcula offset
}

// --- QUERY PARA BUSCAR OS DADOS (ADICIONADO pa.status) ---
$sql_data = "SELECT pa.id, pa.numero_referencia, pa.responsavel_pedido, cc.razao_social, pa.status " // <<< ADICIONADO pa.status
          . $sql_base . $sql_where
          . " ORDER BY pa.data_pedido DESC "
          . " LIMIT :limit OFFSET :offset";

$stmt_data = $pdo->prepare($sql_data);

// Bind dos parâmetros
foreach ($params as $key => $value) {
    $stmt_data->bindValue($key, $value);
}
$stmt_data->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT); // Use bindValue aqui também por consistência
$stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);          // Use bindValue aqui também

$stmt_data->execute();
$pedidos = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Pedidos de Amostra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
     <style>
    .pagination { justify-content: center; margin-top: 2rem; }
    .badge { font-size: 0.85em; }

    td.action-buttons {
    white-space: nowrap;
}

.action-buttons {
    display: inline-flex;
    flex-direction: row; /* garante horizontal */
    gap: 4px;
    flex-wrap: nowrap;   /* impede quebrar linha */
}

.action-buttons a, .action-buttons button {
    display: inline-block;
    width: auto !important;
    font-size: 0.8rem;
    height: auto;              /* força altura automática */
    flex: 0 1 auto;

}





    </style>
</head>
<body>

<div class="container mt-4">
    <h2>Consultar Pedidos de Amostra</h2>
    <hr>

    <!-- Formulário de Busca -->
    <div class="row mb-3">
        <div class="col-md-8 col-lg-6"> <!-- Ajustado tamanho para melhor responsividade -->
            <form action="consultar_amostras.php" method="GET" class="d-flex gap-2">
                <input type="text" name="busca" class="form-control me-2" placeholder="Buscar por Nº Pedido ou Cliente..." value="<?php echo htmlspecialchars($busca); ?>">
                <button type="submit" class="btn btn-primary">Buscar</button>
                <?php if (!empty($busca)): ?>
                    <a href="consultar_amostras.php" class="btn btn-secondary ms-2" title="Limpar Busca">Limpar</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Tabela de Resultados -->
    <div class="table-responsive"> <!-- Adicionado para melhor rolagem em telas pequenas -->
        <table class="table table-striped table-hover table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Número</th>
                    <th>Cliente</th>
                    <th>Representante</th>
                    <th class="text-center">Status</th> <!-- <<< CABEÇALHO ADICIONADO -->
                    <th class="text-center">Ações</th>
 
                </tr>
            </thead>
            <tbody>
                <?php if (count($pedidos) > 0): ?>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pedido['numero_referencia']); ?></td>
                            <td><?php echo htmlspecialchars($pedido['razao_social']); ?></td>
                            <td><?php echo htmlspecialchars($pedido['responsavel_pedido']); ?></td>
                            <!-- <<< CÉLULA DE STATUS ADICIONADA >>> -->
                            <td class="text-center">
                                <?php
                                $status = $pedido['status'] ?? 'Desconhecido';
                                $badge_class = 'bg-secondary'; // Cor padrão
                                switch (strtolower($status)) {
                                    case 'pendente': $badge_class = 'bg-warning text-dark'; break;
                                    case 'enviado': $badge_class = 'bg-success'; break;
                                    case 'cancelado': $badge_class = 'bg-danger'; break;
                                    // Adicione outros status aqui
                                }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                            </td>
                            <!-- <<< FIM CÉLULA DE STATUS >>> -->

                            <td class="text-center action-buttons">
    <a href="alterar_amostra.php?id=<?php echo $pedido['id']; ?>" class="btn btn-sm btn-warning">Alterar</a>
    <a href="excluir_amostra.php?id=<?php echo $pedido['id']; ?>" class="btn btn-sm btn-danger">Excluir</a>
    <a href="gerar_pdf_amostra.php?id=<?php echo $pedido['id']; ?>" class="btn btn-sm btn-info">PDF</a>
</td>

                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                         <!-- <<< COLSPAN AJUSTADO PARA 5 >>> -->
                        <td colspan="5" class="text-center">Nenhum pedido de amostra encontrado<?php echo !empty($busca) ? ' para "' . htmlspecialchars($busca) . '"' : ''; ?>.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div> <!-- Fim .table-responsive -->

    <!-- Paginação (sem alterações lógicas, apenas a verificação inicial $total_paginas) -->
    <?php if ($total_paginas > 1): ?>
        <nav aria-label="Navegação de páginas">
            <ul class="pagination">
                <!-- ... (código da paginação como antes) ... -->
                <?php if ($pagina_atual > 1): ?>
                    <li class="page-item"><a class="page-link" href="?pagina=<?php echo $pagina_atual - 1; ?>&busca=<?php echo urlencode($busca); ?>">«</a></li>
                <?php else: ?>
                    <li class="page-item disabled"><span class="page-link">«</span></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina_atual) ? 'active' : ''; ?>"><a class="page-link" href="?pagina=<?php echo $i; ?>&busca=<?php echo urlencode($busca); ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                 <?php if ($pagina_atual < $total_paginas): ?>
                    <li class="page-item"><a class="page-link" href="?pagina=<?php echo $pagina_atual + 1; ?>&busca=<?php echo urlencode($busca); ?>">»</a></li>
                <?php else: ?>
                     <li class="page-item disabled"><span class="page-link">»</span></li>
                <?php endif; ?>
            </ul>
        </nav>
         <p class="text-center text-muted">Página <?= $pagina_atual ?> de <?= $total_paginas ?> (<?= $total_registros ?> pedidos encontrados)</p>
    <?php elseif($total_registros > 0): ?>
         <p class="text-center text-muted"><?= $total_registros ?> pedido(s) encontrado(s).</p>
    <?php endif; ?>


</div> <!-- Fim container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>