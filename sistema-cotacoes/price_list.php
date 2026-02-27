<?php
session_start();
$pagina_ativa = 'pricelist';
require_once 'header.php';
require_once 'conexao.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Consultar Price List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h2 class="mb-4">Price List</h2>

        <div class="card mb-4 mt-3">
            <div class="card-header bg-success text-white">
                <i class="fas fa-search me-2"></i> Consultar Produtos
            </div>
            <div class="card-body">
                <?php
// Busca Todos os Produtos para Renderização Server-Side (Melhor Performance na Busca)
$stmt = $pdo->query("SELECT * FROM cot_price_list ORDER BY produto ASC");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extrair Fabricantes Únicos da lista carregada
$fabricantes = array_filter(array_unique(array_column($produtos, 'fabricante')));
sort($fabricantes);
?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle w-100" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-filter me-1"></i> Filtrar Fabricantes
                            </button>
                            <ul class="dropdown-menu p-2" aria-labelledby="dropdownMenuButton" style="max-height: 300px; overflow-y: auto; width: 300px;">
                                <li>
                                    <div class="form-check">
                                        <input class="form-check-input filtro-fabricante-todos" type="checkbox" id="fab_all" checked onchange="toggleTodosFabricantes(this)">
                                        <label class="form-check-label fw-bold" for="fab_all">Todos</label>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php foreach ($fabricantes as $fab): ?>
                                    <li>
                                        <div class="form-check">
                                            <input class="form-check-input filtro-fabricante" type="checkbox" value="<?= htmlspecialchars($fab) ?>" id="fab_<?= md5($fab) ?>">
                                            <label class="form-check-label" for="fab_<?= md5($fab) ?>">
                                                <?= htmlspecialchars($fab) ?>
                                            </label>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchTerm" class="form-control"
                                placeholder="Digite Código, Nome ou Fabricante..." onkeyup="filterTable()">
                        </div>
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-striped table-hover small">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Produto</th>
                                <th>Fabricante</th>
                                <th>Classificação</th>
                                <th>Frac.</th>
                                <th>Emb.</th>
                                <th>Lead Time</th>
                                <th>Preço Net (USD)</th>
                            </tr>
                        </thead>
                        <tbody id="priceListBody">
                            <?php foreach ($produtos as $p): 
                                // Formatação
                                $classRaw = mb_strtoupper(trim($p['classificacao'] ?? ''), 'UTF-8');
                                $rowClass = ($classRaw === 'DESCONTINUADO') ? 'table-danger' : '';
                                $classStyle = ($classRaw === 'SOB DEMANDA') ? 'fw-bold text-warning' : '';
                                
                                $codeRaw = mb_strtoupper(trim($p['codigo'] ?? ''), 'UTF-8');
                                $isCodeValid = ($codeRaw !== 'N/A' && $codeRaw !== '');
                                
                                $price = '$ ' . number_format((float)$p['preco_net_usd'], 2, '.', ',');

                                $emb = number_format((float)$p['embalagem'], 3, ',', '.') . ' KG';

                                // Data attributes for filtering (create a search string)
                                $searchString = mb_strtolower($p['codigo'] . ' ' . $p['produto'] . ' ' . $p['fabricante'], 'UTF-8');
                            ?>
                            <tr class="<?= $rowClass ?>" data-search="<?= htmlspecialchars($searchString) ?>" data-fab="<?= htmlspecialchars($p['fabricante']) ?>">
                                <td>
                                    <?php if ($isCodeValid): ?>
                                        <a href="https://app.maino.com.br/produto_estoques?utf8=%E2%9C%93&filtro=true&codigo=<?= urlencode($p['codigo']) ?>&status=R&ordem=codigo&commit=Filtrar" target="_blank">
                                            <?= htmlspecialchars($p['codigo']) ?> <i class="fas fa-external-link-alt small text-muted"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">NÃO CADASTRADO</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold"><?= htmlspecialchars($p['produto']) ?></td>
                                <td><?= htmlspecialchars($p['fabricante']) ?></td>
                                <td class="<?= $classStyle ?>"><?= htmlspecialchars($p['classificacao']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($p['fracionado'] ?? '') ?></td>
                                <td><?= $emb ?></td>
                                <td class="text-center text-muted small"><?= htmlspecialchars($p['lead_time'] ?? '-') ?></td>
                                <td class="fw-bold"><?= $price ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load initial state (everything valid)
        document.addEventListener('DOMContentLoaded', () => {
             // Optional: Initial focus
             // document.getElementById('searchTerm').focus();
        });

        function toggleTodosFabricantes(source) {
            const checkboxes = document.querySelectorAll('.filtro-fabricante');
            checkboxes.forEach(cb => cb.checked = source.checked);
            filterTable();
        }

        // Desmarcar "Todos" se um individual for desmarcado
        document.querySelectorAll('.filtro-fabricante').forEach(cb => {
            cb.addEventListener('change', function () {
                if (!this.checked) {
                    document.getElementById('fab_all').checked = false;
                }
                filterTable();
            });
        });

        function filterTable() {
            const term = document.getElementById('searchTerm').value.toLowerCase().trim();
            const allChecked = document.getElementById('fab_all').checked;
            
            // Coletar fabricantes selecionados (Set para busca O(1))
            let selectedFabs = new Set();
            if (!allChecked) {
                document.querySelectorAll('.filtro-fabricante:checked').forEach(cb => {
                    selectedFabs.add(cb.value);
                });
            }

            const rows = document.querySelectorAll('#priceListBody tr');
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                const itemFab = row.getAttribute('data-fab') || '';
                
                // Match Text
                const matchesText = (term === '') || searchData.includes(term);
                
                // Match Fabricante
                const matchesFab = allChecked || selectedFabs.has(itemFab);

                if (matchesText && matchesFab) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>

</html>