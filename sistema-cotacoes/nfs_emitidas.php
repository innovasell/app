<?php
// nfs_emitidas.php
// Proteção e Proxy API
if (isset($_GET['action'])) {
    session_start();
    header('Content-Type: application/json');

    // Security Check
    // if (!isset($_SESSION['representante_email'])) {
    //     echo json_encode(['error' => 'Não autorizado']);
    //     exit;
    // }

    $apiKey = '77acff2977ab3aa96ddaf33add9a3cc6';
    $endpointNfs = 'https://api.maino.com.br/api/v2/notas_fiscais_emitidas';
    $endpointPedidos = 'https://api.maino.com.br/api/v2/pedidos';

    $action = $_GET['action'];
    $url = '';

    // Common Params
    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 50;

    // Date Params (Input YYYY-MM-DD -> API DD/MM/YYYY)
    $dateParams = "";
    if (!empty($_GET['data_inicio'])) {
        $dt = DateTime::createFromFormat('Y-m-d', $_GET['data_inicio']);
        // If coming from JS as DD/MM/YYYY already? No, JS sends YYYY-MM-DD usually or we handle it here.
        // Let's assume JS sends formatted DD/MM/YYYY or we handle it. 
        // Existing code expected YYYY-MM-DD and converted.
        if ($dt) {
            $dateParams .= "&data_inicio=" . $dt->format('d/m/Y');
        } else {
            // Maybe already formatted?
            $dateParams .= "&data_inicio=" . $_GET['data_inicio'];
        }
    }
    if (!empty($_GET['data_fim'])) {
        $dt = DateTime::createFromFormat('Y-m-d', $_GET['data_fim']);
        if ($dt) {
            $dateParams .= "&data_fim=" . $dt->format('d/m/Y');
        } else {
            $dateParams .= "&data_fim=" . $_GET['data_fim'];
        }
    }

    if ($action === 'fetch_nfs') {
        $url = "$endpointNfs?page=$page&per_page=$perPage" . $dateParams;
    } elseif ($action === 'fetch_pedidos') {
        $url = "$endpointPedidos?page=$page&per_page=100" . $dateParams; // Maximize per page for orders
    } else {
        echo json_encode(['error' => 'Invalid action']);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Api-Key: $apiKey",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        echo json_encode(['error' => curl_error($ch)]);
    } else {
        $data = json_decode($response, true);

        // Filter logic ONLY for NFs
        if ($action === 'fetch_nfs' && isset($data['notas_fiscais']) && is_array($data['notas_fiscais'])) {
            $cfopsVenda = [
                '5101',
                '6101',
                '5102',
                '6102',
                '5405',
                '6405',
                '5106',
                '6106',
                '5117',
                '6117',
                '5120',
                '6120'
            ];

            $notasFiltradas = [];
            foreach ($data['notas_fiscais'] as $nota) {
                // Tenta extrair o CFOP. Estrutura observada: "cfop": { "codigo": "XXXX" }
                $cfopCodigo = '';
                if (isset($nota['cfop']['codigo'])) {
                    $cfopCodigo = (string) $nota['cfop']['codigo'];
                }
                if (empty($cfopCodigo) && isset($nota['cfop'])) {
                    $cfopCodigo = (string) $nota['cfop'];
                }

                if (in_array($cfopCodigo, $cfopsVenda)) {
                    $notasFiltradas[] = $nota;
                }
            }
            $data['notas_fiscais'] = $notasFiltradas;
        }

        http_response_code($httpCode);
        echo json_encode($data);
    }
    curl_close($ch);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NFs Emitidas - Sistema de Comissões</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fa;
        }

        .table-hover tbody tr:hover {
            background-color: #e9ecef;
        }

        .badge-status {
            font-size: 0.8rem;
            padding: 0.4em 0.8em;
        }
    </style>
</head>

<body>

    <?php
    $pagina_ativa = 'nfs_emitidas';
    include 'header.php';
    ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-file-invoice-dollar me-2" style="color: #40883c;"></i> Notas Fiscais Emitidas</h2>
            <small class="text-muted">Integração API Mainô</small>
        </div>

        <!-- Filtros -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-body bg-light rounded">
                <h5 class="card-title fs-6 text-uppercase text-muted mb-3">Filtros de Pesquisa</h5>
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="dataInicio" class="form-label fw-bold">Data Inicial</label>
                        <input type="date" class="form-control" id="dataInicio" value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="dataFim" class="form-label fw-bold">Data Final</label>
                        <input type="date" class="form-control" id="dataFim" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-success w-100 fw-bold" onclick="carregarDados()"
                            style="background-color: #40883c; border-color: #40883c;">
                            <i class="fas fa-filter me-2"></i> Filtrar
                        </button>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100 fw-bold" onclick="exportarCSV()" id="btnCsv" disabled>
                            <i class="fas fa-file-csv me-2"></i> CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="text-white" style="background-color: #40883c;">
                            <tr>
                                <th class="py-3 ps-3">Data</th>
                                <th class="py-3">Nº NFe</th>
                                <th class="py-3">Cliente</th>
                                <th class="py-3 text-start">Cidade/UF</th>
                                <th class="py-3 text-end">Valor Total</th>
                                <th class="py-3 text-center">Vendedor</th>
                                <!-- <th class="py-3 pe-3 text-end">Ações</th> -->
                            </tr>
                        </thead>
                        <tbody id="tabelaNFs">
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-search fa-2x mb-3 d-block opacity-25"></i>
                                    Clique em "Filtrar" para carregar os dados.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="3" class="text-end pe-3">TOTAIS DA PÁGINA:</td>
                                <td class="text-end" id="totalValor">R$ 0,00</td>
                                <td colspan="2"></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loadingOverlay"
            class="position-fixed top-0 start-0 w-100 h-100 d-none justify-content-center align-items-center"
            style="background: rgba(255,255,255,0.8); z-index: 1050;">
            <div class="text-center">
                <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: #40883c;"></div>
                <p class="mt-2 fw-bold" style="color: #40883c;">Carregando dados da API...</p>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let allNfs = [];
        let allPedidos = []; // Store fetched orders
        let clientRepMap = {}; // Map ClientName -> RepresentativeName
        let cepCache = {}; // Cache CEP -> { localidade: '...', uf: '...' }
        const LIMIT_PAGES = 30; // Safety limit to prevent infinite loops

        // Utility: Format currency
        function formatMoney(value) {
            return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        async function carregarDados() {
            const dataInicio = document.getElementById('dataInicio').value;
            const dataFim = document.getElementById('dataFim').value;
            const tbody = document.getElementById('tabelaNFs');
            const loading = document.getElementById('loadingOverlay');
            const loadingText = loading.querySelector('p');
            const btnCsv = document.getElementById('btnCsv');

            if (!dataInicio || !dataFim) {
                alert('Por favor, selecione o período.');
                return;
            }

            loading.classList.remove('d-none');
            loading.classList.add('d-flex');
            tbody.innerHTML = '';
            loadingText.innerText = 'Iniciando busca...';
            btnCsv.disabled = true;

            allNfs = [];
            allPedidos = [];
            clientRepMap = {};

            // Clear Totals
            document.getElementById('totalValor').innerText = 'R$ 0,00';
            document.getElementById('totalImpostos').innerText = 'R$ 0,00';
            document.getElementById('totalFrete').innerText = 'R$ 0,00';

            try {
                // Fetch NFs and Pedidos
                const p1 = fetchNfsRecursive(dataInicio, dataFim, 1, loadingText);
                const p2 = fetchPedidosRecursive(dataInicio, dataFim, 1, loadingText);

                await Promise.all([p1, p2]);

                loadingText.innerText = 'Correlacionando Vendedores...';
                buildClientRepMap();
                
                loadingText.innerText = 'Resolvendo Cidades (ViaCEP)...';
                await resolveCities(loadingText);
                
                renderTable();

                if (allNfs.length > 0) {
                    btnCsv.disabled = false;
                }

            } catch (error) {
                console.error(error);
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">Erro ao carregar dados: ${error.message}</td></tr>`;
            } finally {
                loading.classList.remove('d-flex');
                loading.classList.add('d-none');
                loadingText.innerText = 'Carregando dados da API...';
            }
        }

        async function fetchNfsRecursive(dataInicio, dataFim, page, statusEl) {
            if (page > LIMIT_PAGES) return; // Safety Break

            // Update status only if it's the primary task (optional visuals)
            // statusEl.innerText = `Carregando Notas (Página ${page})...`; 

            const url = `nfs_emitidas.php?action=fetch_nfs&data_inicio=${dataInicio}&data_fim=${dataFim}&page=${page}&per_page=50`;

            const response = await fetch(url);
            if (!response.ok) throw new Error('Erro ao buscar NFs');

            const data = await response.json();
            const notas = data.notas_fiscais || [];

            if (notas.length === 0) return; // Stop if empty

            allNfs = allNfs.concat(notas);
            statusEl.innerText = `Notas carregadas: ${allNfs.length}...`;

            if (data.pagination && data.pagination.next_page) {
                await new Promise(r => setTimeout(r, 200)); // Gentle throttling
                await fetchNfsRecursive(dataInicio, dataFim, page + 1, statusEl);
            }
        }

        async function fetchPedidosRecursive(dataInicio, dataFim, page, statusEl) {
            if (page > LIMIT_PAGES) return;

            const url = `nfs_emitidas.php?action=fetch_pedidos&data_inicio=${dataInicio}&data_fim=${dataFim}&page=${page}&per_page=100`;

            const response = await fetch(url);
            if (!response.ok) throw new Error('Erro ao buscar Pedidos');

            const data = await response.json();
            const pedidos = data.pedidos || [];

            if (pedidos.length === 0) return;

            allPedidos = allPedidos.concat(pedidos);

            if (data.pagination && data.pagination.next_page) {
                await new Promise(r => setTimeout(r, 200));
                await fetchPedidosRecursive(dataInicio, dataFim, page + 1, statusEl);
            }
        }

        async function resolveCities(statusEl) {
            // Collect unique unknown CEPs
            const uniqueCeps = new Set();
            allNfs.forEach(item => {
                let cep = null;
                if (item.destinatario && item.destinatario.cep) cep = item.destinatario.cep.replace(/\D/g, '');
                else if (item.cliente && item.cliente.cep) cep = item.cliente.cep.replace(/\D/g, '');

                if (cep && cep.length === 8 && !cepCache[cep]) {
                    uniqueCeps.add(cep);
                }
            });

            const cepsToFetch = Array.from(uniqueCeps);
            if (cepsToFetch.length === 0) return;

            // Fetch in batches to avoid rate limits
            let processed = 0;
            const total = cepsToFetch.length;
            const BATCH_SIZE = 5;

            for (let i = 0; i < total; i += BATCH_SIZE) {
                const batch = cepsToFetch.slice(i, i + BATCH_SIZE);
                statusEl.innerText = `Resolvendo Cidades: ${processed}/${total}...`;

                await Promise.all(batch.map(async (cep) => {
                    try {
                        const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                        if (res.ok) {
                            const addr = await res.json();
                            if (!addr.erro) {
                                cepCache[cep] = { localidade: addr.localidade, uf: addr.uf };
                            } else {
                                cepCache[cep] = { localidade: 'N/Encontrado', uf: '-' };
                            }
                        }
                    } catch (e) {
                        console.warn(`Erro CEP ${cep}`, e);
                        cepCache[cep] = { localidade: 'Erro', uf: '-' };
                    }
                }));
                processed += batch.length;
                await new Promise(r => setTimeout(r, 250)); // Gentle delay
            }
        }

        function buildClientRepMap() {
            allPedidos.forEach(pedido => {
                if (pedido.cliente && pedido.cliente.razao_social) {
                    const clientName = pedido.cliente.razao_social.trim().toUpperCase();
                    const repName = (pedido.representante && pedido.representante.nome) ? pedido.representante.nome : 'N/D';

                    if (!clientRepMap[clientName] || clientRepMap[clientName] === 'N/D') {
                        clientRepMap[clientName] = repName;
                    }
                }
            });
        }

        function renderTable() {
            const tbody = document.getElementById('tabelaNFs');
            let sumValor = 0;

            if (allNfs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4">Nenhum registro encontrado no período.</td></tr>`;
                return;
            }

            // Create fragment for better performance
            const fragment = document.createDocumentFragment();

            allNfs.forEach(item => {
                let dataEmissao = item.dthr_emissao || item.data_emissao || 'N/D';
                if (dataEmissao !== 'N/D') {
                    try {
                        const d = new Date(dataEmissao);
                        dataEmissao = d.toLocaleDateString('pt-BR');
                    } catch (e) { }
                }

                const numeroNfe = item.numero_nfe || '-';

                // Client Matching
                let cliente = 'N/D';
                if (item.destinatario && item.destinatario.razao_social) {
                    cliente = item.destinatario.razao_social;
                } else if (item.cliente && item.cliente.razao_social) {
                    cliente = item.cliente.razao_social;
                }

                // Lookup Vendor
                const clientKey = cliente.trim().toUpperCase();
                const vendedor = clientRepMap[clientKey] || '-';

                const valor = parseFloat(item.valor_nota_nfe || item.valor_total || 0);
                sumValor += valor;

                // Resolve City from Cache
                let cidadeUf = '...';
                if (cep && cepCache[cep]) {
                    cidadeUf = `${cepCache[cep].localidade}/${cepCache[cep].uf}`;
                } else if (!cep) {
                    cidadeUf = 'S/ CEP';
                }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-3">${dataEmissao}</td>
                    <td>${numeroNfe}</td>
                    <td class="fw-bold text-secondary text-truncate" style="max-width: 250px;" title="${cliente}">${cliente}</td>
                    <td class="text-start text-muted">${cidadeUf}</td>
                    <td class="text-end">${formatMoney(valor)}</td>
                    <td class="text-center text-primary fw-bold">${vendedor}</td> 
                `;
                fragment.appendChild(tr);
            });

            tbody.innerHTML = '';
            tbody.appendChild(fragment);

            document.getElementById('totalValor').innerText = formatMoney(sumValor);
        }

        function exportarCSV() {
            if (allNfs.length === 0) {
                alert("Não há dados para exportar. Faça uma pesquisa primeiro.");
                return;
            }

            const header = ["Data", "Nº NFe", "Cliente", "Cidade", "UF", "Valor Total", "Vendedor", "Peso Bruto (kg)", "Peso Líquido (kg)"];
            const rows = [];

            // Add Header
            rows.push(header.join(";"));

            allNfs.forEach(item => {
                // Format Data same as Table
                let dataEmissao = item.dthr_emissao || item.data_emissao || 'N/D';
                if (dataEmissao !== 'N/D') {
                    try {
                        const d = new Date(dataEmissao);
                        dataEmissao = d.toLocaleDateString('pt-BR');
                    } catch (e) { }
                }

                const numeroNfe = item.numero_nfe || '-';

                let cliente = 'N/D';
                let cep = null;
                if (item.destinatario && item.destinatario.razao_social) {
                    cliente = item.destinatario.razao_social;
                    if (item.destinatario.cep) cep = item.destinatario.cep.replace(/\D/g, '');
                } else if (item.cliente && item.cliente.razao_social) {
                    cliente = item.cliente.razao_social;
                    if (item.cliente.cep) cep = item.cliente.cep.replace(/\D/g, '');
                }

                const clientKey = cliente.trim().toUpperCase();
                const vendedor = clientRepMap[clientKey] || '-';

                const valor = parseFloat(item.valor_nota_nfe || item.valor_total || 0).toFixed(2).replace('.', ',');

                const pesoBruto = parseFloat(item.peso_bruto || 0).toFixed(3).replace('.', ',');
                const pesoLiq = parseFloat(item.peso_liquido || 0).toFixed(3).replace('.', ',');

                let cidade = '';
                let uf = '';
                if (cep && cepCache[cep]) {
                    cidade = cepCache[cep].localidade;
                    uf = cepCache[cep].uf;
                }

                // CSV Row
                const row = [
                    dataEmissao,
                    numeroNfe,
                    `"${cliente}"`,
                    `"${cidade}"`,
                    `"${uf}"`,
                    valor,
                    `"${vendedor}"`,
                    pesoBruto,
                    pesoLiq
                ];
                rows.push(row.join(";"));
            });

            const csvContent = "data:text/csv;charset=utf-8,\uFEFF" + rows.join("\n");
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `notas_fiscais_${new Date().toISOString().slice(0, 10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

</body>

</html>
```