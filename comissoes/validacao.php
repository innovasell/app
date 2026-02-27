<?php
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação de Importação</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .editable {
            cursor: pointer;
            border-bottom: 1px dashed #0d6efd;
        }

        .editable:hover {
            background-color: #f1f8ff;
        }

        .status-pending {
            background-color: #fff3cd;
            /* Amarelo alerta */
        }

        .status-validated {}
    </style>
</head>

<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="bi bi-percent"></i> Sistema de Comissões</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="upload.php">Upload NFs</a></li>
                    <li class="nav-item"><a class="nav-link active" href="validacao.php">Validação</a></li>
                    <li class="nav-item"><a class="nav-link" href="config_cfop.php">Configurar CFOPs</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4">

        <!-- Batch Selector -->
        <div class="card shadow-sm mb-4" id="batchSelectorCard">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary">Selecione um Lote para Validar</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Lote (ID)</th>
                                <th>Data Importação</th>
                                <th>Total Itens</th>
                                <th>Pendentes</th>
                                <th>Sem Vendedor</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody id="batchListBody">
                            <tr>
                                <td colspan="6" class="text-center">Carregando lotes...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Validation Area (Hidden first) -->
        <div class="card shadow-sm mb-4 d-none" id="validationCard">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-secondary btn-sm" onclick="showBatchList()">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </button>
                    <h5 class="mb-0 text-primary" id="batchTitle">Detalhes do Lote</h5>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadSellersModal">
                        <i class="bi bi-person-lines-fill"></i> Vincular Vendedores (CSV)
                    </button>
                    <button class="btn btn-success" onclick="confirmBatch()">
                        <i class="bi bi-check-circle"></i> Confirmar e Calcular
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th>NF</th>
                                <th>Vendedor</th>
                                <th>Data</th>
                                <th>Produto (Descrição / Código)</th>
                                <th>Validação de Emb.</th>
                                <th>Qtd</th>
                                <th>Vl. Unit (USD)</th>
                                <th>PTAX</th>
                                <th>Price List (USD)</th>
                                <th>PM (Dias)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <!-- Items -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editId">
                    <div class="mb-3">
                        <label class="form-label">Embalagem Validada</label>
                        <input type="text" class="form-control" id="editPackaging">
                        <div class="form-text">Ex: 25, 200... (Apenas números se for KG/L)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price List (USD)</label>
                        <input type="number" step="0.01" class="form-control" id="editPrice">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prazo Médio (Dias)</label>
                        <input type="number" step="1" class="form-control" id="editTerm">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="saveEdit()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Sellers Modal -->
    <div class="modal fade" id="uploadSellersModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="uploadSellersForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Vincular Vendedores (CSV)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Envie um arquivo CSV com as colunas:
                            <code>Numero_NF;Nome_Vendedor</code>
                        </p>
                        <input type="file" name="sellers_csv" class="form-control mb-3" required accept=".csv">
                        <input type="hidden" id="uploadSellersBatchId" name="batch_id">
                        <a href="api/download_template_sellers.php" class="btn btn-sm btn-outline-secondary">Baixar
                            Modelo</a>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Processar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Product Link Modal -->
    <div class="modal fade" id="linkProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vincular Produto da Lista de Preços</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="linkItemId">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="productSearchTerm"
                            placeholder="Digite código ou nome do produto...">
                        <button class="btn btn-outline-primary" type="button" onclick="searchProducts()">Buscar</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover small">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Produto</th>
                                    <th>Emb.</th>
                                    <th>Preço (USD)</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody id="productSearchResults">
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Digite algo para buscar...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentItems = [];
        let currentBatchId = null;

        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        const uploadSellersModal = new bootstrap.Modal(document.getElementById('uploadSellersModal'));
        const linkProductModal = new bootstrap.Modal(document.getElementById('linkProductModal'));

        const batchSelectorCard = document.getElementById('batchSelectorCard');
        const validationCard = document.getElementById('validationCard');
        const batchListBody = document.getElementById('batchListBody');

        document.addEventListener('DOMContentLoaded', loadBatches);

        async function loadBatches() {
            try {
                const response = await fetch('api/get_batches.php');
                const result = await response.json();

                if (result.success) {
                    batchListBody.innerHTML = '';
                    if (result.data.length === 0) {
                        batchListBody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhum lote encontrado.</td></tr>';
                        return;
                    }

                    result.data.forEach(batch => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${batch.batch_id}</td>
                            <td>${batch.formatted_date}</td>
                            <td><span class="badge bg-secondary">${batch.item_count}</span></td>
                            <td><span class="badge ${batch.pending_count > 0 ? 'bg-warning text-dark' : 'bg-success'}">${batch.pending_count}</span></td>
                             <td><span class="badge ${batch.missing_sellers > 0 ? 'bg-danger' : 'bg-success'}">${batch.missing_sellers}</span></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="openBatch('${batch.batch_id}')">
                                    Abrir <i class="bi bi-folder2-open"></i>
                                </button>
                            </td>
                        `;
                        batchListBody.appendChild(tr);
                    });
                }
            } catch (e) {
                console.error(e);
            }
        }

        function showBatchList() {
            validationCard.classList.add('d-none');
            batchSelectorCard.classList.remove('d-none');
            loadBatches(); // Refresh
        }

        async function openBatch(batchId) {
            currentBatchId = batchId;
            document.getElementById('uploadSellersBatchId').value = batchId;
            document.getElementById('batchTitle').textContent = "Lote: " + batchId;

            batchSelectorCard.classList.add('d-none');
            validationCard.classList.remove('d-none');

            loadItems(batchId);
        }

        async function loadItems(batchId) {
            try {
                const response = await fetch(`api/get_batch_items.php?batch_id=${batchId}`);
                const result = await response.json();

                if (result.success) {
                    renderTable(result.data);
                } else {
                    alert('Erro ao carregar: ' + result.error);
                }
            } catch (e) {
                console.error(e);
                alert('Erro na comunicação com o servidor.');
            }
        }

        function renderTable(items) {
            currentItems = items;
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '';

            items.forEach(item => {
                const row = document.createElement('tr');
                if (item.status === 'pending') {
                    row.classList.add('status-pending');
                }

                const unitUsd = item.unit_price_usd ? parseFloat(item.unit_price_usd).toLocaleString('en-US', { style: 'currency', currency: 'USD' }) : '<span class="text-danger">Erro PTAX</span>';
                const cost = item.cost_price ? parseFloat(item.cost_price).toLocaleString('en-US', { style: 'currency', currency: 'USD' }) : '<span class="text-danger fw-bold">--</span>';
                const seller = item.seller_name ? item.seller_name : '<span class="text-danger">Não Informado</span>';

                // Format formatting
                const qty = parseFloat(item.quantity).toLocaleString('pt-BR', { minimumFractionDigits: 3 }) + ' KG';
                const ptax = item.ptax_rate ? parseFloat(item.ptax_rate).toLocaleString('pt-BR', { minimumFractionDigits: 4 }) : '-';

                // Allow clicking on price if missing or to edit
                const costDisplay = item.cost_price
                    ? parseFloat(item.cost_price).toLocaleString('en-US', { style: 'currency', currency: 'USD' })
                    : '<span class="text-secondary" style="cursor:pointer; text-decoration: underline;" onclick="openLinkProduct(' + item.id + ')">--</span>';

                const costCell = item.cost_price
                    ? `<span class="editable" onclick="openEdit(${item.id})">${costDisplay}</span>`
                    : `<span class="text-primary fw-bold" style="cursor:pointer;" onclick="openLinkProduct(${item.id})">-- (Vincular)</span>`;

                row.innerHTML = `
                    <td>${item.nfe_number}</td>
                    <td>${seller}</td>
                    <td>${formatDate(item.nfe_date)}</td>
                    <td>
                        <div class="fw-bold">${item.product_name}</div>
                        <small class="text-muted">${item.product_code_original}</small>
                    </td>
                    <td><span class="editable" onclick="openEdit(${item.id})">${item.packaging_validated || '?'}</span></td>
                    <td>${qty}</td>
                    <td>${unitUsd}</td>
                    <td>${ptax}</td>
                    <td>${costCell}</td>
                    <td><span class="editable" onclick="openEdit(${item.id})">${item.average_term ? Math.round(item.average_term) : '0'}</span></td>
                    <td>${getStatusBadge(item.status)}</td>
                `;
                tbody.appendChild(row);
            });
        }

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const [y, m, d] = dateStr.split('-');
            return `${d}/${m}/${y}`;
        }

        function getStatusBadge(status) {
            if (status === 'validated') {
                return '<span class="badge bg-success">Validado</span>';
            } else {
                return '<span class="badge bg-warning text-dark">Pendente</span>';
            }
        }

        function openEdit(id) {
            const item = currentItems.find(i => i.id == id);
            if (!item) return;

            document.getElementById('editId').value = id;
            document.getElementById('editPackaging').value = item.packaging_validated;
            document.getElementById('editPrice').value = item.cost_price;
            document.getElementById('editTerm').value = item.average_term;

            editModal.show();
        }

        async function saveEdit() {
            const id = document.getElementById('editId').value;
            const pkg = document.getElementById('editPackaging').value;
            const price = document.getElementById('editPrice').value;
            const term = document.getElementById('editTerm').value;

            await updateField(id, 'packaging_validated', pkg);
            await updateField(id, 'cost_price', price);
            await updateField(id, 'average_term', term);

            editModal.hide();
            loadItems(currentBatchId);
        }

        async function updateField(id, field, value) {
            await fetch('api/update_item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, field, value })
            });
        }

        // Upload Sellers Form
        document.getElementById('uploadSellersForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);

            try {
                const response = await fetch('api/upload_sellers_batch.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    if (result.debug) console.log('Upload Debug:', result.debug);
                    alert(result.message);
                    uploadSellersModal.hide();
                    loadItems(currentBatchId); // Reload items
                } else {
                    console.error('Upload Error Debug:', result.debug);
                    alert('Erro: ' + result.error);
                }
            } catch (err) {
                alert('Erro na comunicação.');
            }
        });

        function confirmBatch() {
            const pending = currentItems.filter(i => i.status === 'pending');
            if (pending.length > 0) {
                if (!confirm(`Existem ${pending.length} itens pendentes. Continuar?`)) {
                    return;
                }
            }
            alert('Cálculo em desenvolvimento!');
        }

        // --- Link Product Functions ---

        function openLinkProduct(itemId) {
            document.getElementById('linkItemId').value = itemId;
            document.getElementById('productSearchTerm').value = '';
            document.getElementById('productSearchResults').innerHTML = '<tr><td colspan="5" class="text-center text-muted">Digite algo para buscar...</td></tr>';
            linkProductModal.show();
            setTimeout(() => document.getElementById('productSearchTerm').focus(), 500); // Focus
        }

        // Trigger search on Enter
        document.getElementById('productSearchTerm').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                searchProducts();
            }
        });

        async function searchProducts() {
            const term = document.getElementById('productSearchTerm').value;
            if (term.length < 3) {
                alert('Digite pelo menos 3 caracteres.');
                return;
            }

            const tbody = document.getElementById('productSearchResults');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">Buscando...</td></tr>';

            try {
                const res = await fetch(`api/search_products.php?term=${encodeURIComponent(term)}`);
                const data = await res.json();

                tbody.innerHTML = '';
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Nenhum produto encontrado.</td></tr>';
                    return;
                }

                data.forEach(p => {
                    const price = parseFloat(p.preco_net_usd).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
                    // Provide button to select
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${p.codigo}</td>
                        <td>${p.produto}</td>
                        <td>${p.embalagem}</td>
                        <td>${price}</td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="selectProduct('${p.preco_net_usd}', '${p.embalagem}')">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

            } catch (e) {
                console.error(e);
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Erro na busca.</td></tr>';
            }
        }

        async function selectProduct(price, pkg) {
            const itemId = document.getElementById('linkItemId').value;

            // Update item with new price and packaging (if we want to sync packaging too? usually yes if we link to a specific packaging price)
            // User just said "trazendo o valor da tabela". 
            // Ideally we should also update status to 'validated' if we manually link it.

            try {
                // Update Price
                await updateField(itemId, 'cost_price', price);
                // Update Packaging (Validated) to match the selected one? 
                // It helps consistency. Let's do it.
                await updateField(itemId, 'packaging_validated', pkg);

                // We might want to set status to 'validated' too? 
                // The current backend doesn't have a direct 'status' update via update_item.php? 
                // Let's check update_item.php logic. It usually just updates the field.

                linkProductModal.hide();
                loadItems(currentBatchId);

            } catch (e) {
                alert('Erro ao vincular.');
            }
        }
    </script>
</body>

</html>