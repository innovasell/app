<?php
session_start();
date_default_timezone_set('America/Sao_Paulo'); // Define fuso horário
// !!! DEFINA A PÁGINA ATIVA AQUI !!!
$pagina_ativa = 'gerenciar_cliente'; // Exemplo para consultar_amostras.php
// Use: 'incluir_amostra' para incluir_ped_amostras.php
// Use: 'gerenciar_cliente' para gerenciar_cliente.php
// Use: 'incluir_orcamento' para incluir_orcamento.php
// Use: 'filtrar' para filtrar.php
// Use: 'consultar_orcamentos' para consultar_orcamentos.php
// Use: 'previsao' para previsao.php

require_once 'header.php'; // Inclui o header
require_once 'conexao.php'; // Conexão PDO

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}


// --- Configuração da Paginação ---
$itemsPerPage = 15; // Quantos clientes por página

// --- Obter Página Atual ---
$currentPage = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);
if (!$currentPage || $currentPage < 1) {
    $currentPage = 1; // Página padrão é 1
}

// --- Obter Termo de Busca ---
$termo_busca = filter_input(INPUT_GET, 'termo_busca', FILTER_SANITIZE_SPECIAL_CHARS);

$clientes = [];
$totalItems = 0;
$totalPages = 0;
$alert_message = null;
$alert_type = 'info';

try {
    // --- Contar Total de Itens (com filtro, se houver) ---
    $sqlCount = "SELECT COUNT(*) FROM cot_clientes";
    $countParams = [];
    if (!empty($termo_busca)) {
        $sqlCount .= " WHERE CONCAT_WS(' ', nome, razao_social, cnpj) LIKE :termo";
        $countParams[':termo'] = '%' . $termo_busca . '%';
    }
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($countParams);
    $totalItems = (int) $stmtCount->fetchColumn();

    // --- Calcular Total de Páginas ---
    if ($totalItems > 0) {
        $totalPages = ceil($totalItems / $itemsPerPage);
    } else {
        $totalPages = 0; // Ou 1 se preferir mostrar página 1 mesmo vazia
    }


    // --- Validar/Ajustar Página Atual (não pode ser maior que total de páginas) ---
     if ($currentPage > $totalPages && $totalPages > 0) {
         $currentPage = $totalPages; // Vai para a última página se pedir uma página inexistente
     } elseif ($currentPage > 1 && $totalPages == 0) {
          $currentPage = 1; // Vai para a página 1 se não há resultados mas pediu pág > 1
     }


    // --- Calcular Offset para a consulta principal ---
    $offset = ($currentPage - 1) * $itemsPerPage;

    // --- Buscar Clientes da Página Atual (com filtro, se houver) ---
    if ($totalItems > 0 || empty($termo_busca)) { // Só busca se houver itens ou se não estiver buscando
        $sql = "SELECT id, nome, razao_social, cnpj FROM cot_clientes";
        $params = []; // Reinicia params para a query principal
        if (!empty($termo_busca)) {
            $sql .= " WHERE CONCAT_WS(' ', nome, razao_social, cnpj) LIKE :termo";
            $params[':termo'] = '%' . $termo_busca . '%';
        }
        $sql .= " ORDER BY nome ASC, razao_social ASC LIMIT :limit OFFSET :offset"; // Adiciona LIMIT e OFFSET

        $stmt = $pdo->prepare($sql);

        // Bind dos parâmetros (termo, limit, offset)
        if (!empty($termo_busca)) {
             $stmt->bindParam(':termo', $params[':termo'], PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute(); // Executa sem passar $params aqui, pois foram bindados individualmente
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $clientes = []; // Garante array vazio se busca não retornou resultados
    }


} catch (PDOException $e) {
    // error_log("Erro ao buscar/contar clientes: " . $e->getMessage());
    $alert_message = "Erro ao consultar clientes: " . $e->getMessage();
    $alert_type = 'danger';
    $clientes = [];
    $totalItems = 0;
    $totalPages = 0;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .action-buttons a, .action-buttons button { margin-right: 5px; margin-bottom: 5px; /* Espaçamento p/ mobile */ }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Gerenciar Clientes</h1>

        <?php if ($alert_message): ?>
        <div class="alert alert-<?= htmlspecialchars($alert_type) ?>" role="alert">
            <?= htmlspecialchars($alert_message) ?>
        </div>
        <?php endif; ?>

        <!-- Barra de Busca e Botão Adicionar -->
        <form method="GET" action="gerenciar_cliente.php" class="mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md">
                    <label for="termo_busca" class="form-label">Buscar por Nome Fantasia, Razão Social ou CNPJ:</label>
                    <input type="text" class="form-control" id="termo_busca" name="termo_busca" value="<?= htmlspecialchars($termo_busca ?? '') ?>">
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                     <a href="gerenciar_cliente.php" class="btn btn-secondary" title="Limpar Busca">
                        <i class="bi bi-eraser"></i> Limpar
                    </a>
                </div>
                <div class="col-md-auto">
                     <a href="adicionar_cliente.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Adicionar Novo Cliente
                    </a>
                </div>
            </div>
        </form>

        <!-- Tabela de Clientes -->
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Razão Social</th>
                        <th>Nome Fantasia</th>
                        <th>CNPJ</th>
                        <th style="width: 150px;">Ação</th> <!-- Ajuste largura se necessário -->
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($clientes)): ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?= htmlspecialchars($cliente['razao_social'] ?? '') ?></td>
                                <td><?= htmlspecialchars($cliente['nome'] ?? '') ?></td>
                                <td><?= htmlspecialchars($cliente['cnpj'] ?? '') ?></td>
                                <td class="action-buttons">
                                    <a href="editar_cliente.php?id=<?= htmlspecialchars($cliente['id']) ?>" class="btn btn-sm btn-warning" title="Alterar Cliente">
                                        <i class="bi bi-pencil-square"></i> Alterar
                                    </a>
                                    <!-- Botão de Excluir Comentado Corretamente -->
                                    <!--
                                    <a href="excluir_cliente.php?id=<?= htmlspecialchars($cliente['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir este cliente?');" title="Excluir Cliente">
                                        <i class="bi bi-trash"></i> Excluir
                                    </a>
                                    -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">
                                <?php if (!empty($termo_busca)): ?>
                                    Nenhum cliente encontrado para o termo "<?= htmlspecialchars($termo_busca) ?>".
                                <?php else: ?>
                                    Nenhum cliente cadastrado ainda. <a href="adicionar_cliente.php">Adicionar um novo cliente</a>.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div> <!-- Fim .table-responsive -->
        </div> <!-- Fim .table-responsive -->

        </div> <!-- Fim .table-responsive -->

<!-- Controles de Paginação -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Paginação de Clientes" class="mt-4">
        <ul class="pagination justify-content-center flex-wrap"> <!-- Classes Bootstrap para layout -->

            <?php
            // Constrói a query string base para manter o filtro de busca
            $queryString = '';
            if (!empty($termo_busca)) {
                $queryString = '&termo_busca=' . urlencode($termo_busca);
            }

            // Botão "Anterior"
            $prevPage = $currentPage - 1;
            $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
            echo "<li class='page-item {$prevDisabled}'><a class='page-link' href='?pagina={$prevPage}{$queryString}'>Anterior</a></li>";

            // Lógica dos Links de Página (com elipses)
            $range = 2; // Quantos links mostrar antes/depois da página atual

            // Sempre mostrar página 1
            $active = ($currentPage == 1) ? 'active' : '';
            echo "<li class='page-item {$active}'><a class='page-link' href='?pagina=1{$queryString}'>1</a></li>";

            // Elipses iniciais (...)
            if ($currentPage > $range + 2) {
                 echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }

            // Links numéricos no meio
            $start = max(2, $currentPage - $range);
            $end = min($totalPages - 1, $currentPage + $range);

            for ($i = $start; $i <= $end; $i++) {
                $active = ($i == $currentPage) ? 'active' : '';
                echo "<li class='page-item {$active}'><a class='page-link' href='?pagina={$i}{$queryString}'>{$i}</a></li>";
            }

            // Elipses finais (...)
            if ($currentPage < $totalPages - $range - 1) {
                 echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }

            // Sempre mostrar a última página (se for diferente da 1)
            if ($totalPages > 1) {
                $active = ($currentPage == $totalPages) ? 'active' : '';
                echo "<li class='page-item {$active}'><a class='page-link' href='?pagina={$totalPages}{$queryString}'>{$totalPages}</a></li>";
            }

            // Botão "Próxima"
            $nextPage = $currentPage + 1;
            $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
            echo "<li class='page-item {$nextDisabled}'><a class='page-link' href='?pagina={$nextPage}{$queryString}'>Próxima</a></li>";

            ?>
        </ul>
    </nav>
     <p class="text-center text-muted">Página <?= $currentPage ?> de <?= $totalPages ?> (<?= $totalItems ?> clientes encontrados)</p>
<?php elseif ($totalItems > 0 && $totalPages <= 1): ?>
     <p class="text-center text-muted"><?= $totalItems ?> cliente(s) encontrado(s).</p>
<?php endif; ?>
<!-- Fim Controles de Paginação -->

</div> <!-- Fim .container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
</body>
</html>