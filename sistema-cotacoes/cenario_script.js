// Variáveis globais
let blockIdCounter = 0; // Contador para gerar IDs únicos de blocos de cenário
let itemIndex = 0;
let currentItemRow = null;
let currentBlockId = null; // Para saber em qual bloco adicionar o item ao retornar de modais (se necessário)

// Inicializa criando um primeiro bloco padrão
document.addEventListener('DOMContentLoaded', function () {
    adicionarBlocoCenario();

    // Data Atual
    const dataInput = document.getElementById('data');
    if (dataInput) {
        const hoje = new Date();
        dataInput.value = hoje.toISOString().split('T')[0];
    }

    // PTAX
    fetch("ptax.php")
        .then(response => response.json())
        .then(data => {
            if (data && data.value && data.value.length > 0) {
                const ptax = parseFloat(data.value[0].cotacaoVenda).toFixed(4).replace('.', ',');
                const compra = document.getElementById('dolar_compra');
                if (compra) compra.value = ptax;
                const venda = document.getElementById('dolar_venda');
                if (venda) venda.value = ptax;
            }
        })
        .catch(error => console.error("Erro ao buscar PTAX:", error));

    // Listener Busca Fornecedor
    const buscaFornecedorElement = document.getElementById('buscaFornecedor');
    if (buscaFornecedorElement) {
        buscaFornecedorElement.addEventListener('keyup', function () {
            carregarFornecedores(this.value);
        });
    }

    // Listener Busca Cliente
    const buscaClienteElement = document.getElementById('buscaCliente');
    if (buscaClienteElement) {
        buscaClienteElement.addEventListener('keyup', function () {
            const termo = this.value.trim();
            if (termo.length < 3) return;

            fetch(`buscar_clientes.php?q=${encodeURIComponent(termo)}`)
                .then(res => res.json())
                .then(clientes => {
                    const lista = document.getElementById('listaClientes');
                    if (lista) {
                        lista.innerHTML = '';

                        if (clientes.length === 0) {
                            lista.innerHTML = '<tr><td colspan="3" class="text-center">Nenhum cliente encontrado.</td></tr>';
                            return;
                        }

                        clientes.forEach(cli => {
                            const tr = document.createElement('tr');
                            const json = JSON.stringify(cli).replace(/'/g, "&apos;");
                            tr.innerHTML = `
                  <td>${cli.razao_social}</td>
                  <td>${cli.uf}</td>
                  <td>
                    <button type="button" class="btn btn-sm btn-primary" onclick='selecionarCliente(${json})'>
                      Selecionar
                    </button>
                  </td>
                `;
                            lista.appendChild(tr);
                        });
                    }
                })
                .catch(err => console.error("Erro ao buscar clientes:", err));
        });
    }

    // Listener Busca Produto (Enter)
    const buscaProdutoInput = document.getElementById('buscaProduto');
    if (buscaProdutoInput) {
        buscaProdutoInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarProdutos();
            }
        });
    }

    // Listener Submit do Formulário
    const form = document.getElementById('formCenario');
    if (form) {
        form.addEventListener('submit', function () {
            // Força atualização de todos os inputs ocultos antes de enviar
            recalcularTodos();
        });
    }
});

// --- Gestão de Blocos de Cenário ---

function adicionarBlocoCenario() {
    blockIdCounter++;
    const blockId = `cenario-block-${blockIdCounter}`;
    const container = document.getElementById('cenarios-container');

    const html = `
    <div id="${blockId}" class="card mb-4 shadow-sm cenario-block" style="border-left: 5px solid #0d6efd;">
        <div class="card-header bg-light">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <label class="form-label fw-bold mb-0">Nome do Cenário / Variação</label>
                    <input type="text" class="form-control form-control-sm block-nome" placeholder="Ex: Aéreo - Urgente" value="Cenário ${blockIdCounter}" required oninput="atualizarItensOcultos('${blockId}')">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold mb-0">Modal</label>
                    <select class="form-select form-select-sm block-modal" required onchange="atualizarItensOcultos('${blockId}')">
                        <option value="Aéreo">Aéreo</option>
                        <option value="Marítimo">Marítimo</option>
                        <option value="Rodoviário">Rodoviário</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold mb-0">Taxa Juros (%)</label>
                    <input type="number" step="0.01" class="form-control form-control-sm block-taxa" value="3.00" required oninput="recalcularBloco('${blockId}')">
                </div>
                 <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerBloco('${blockId}')" title="Remover este cenário inteiro">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-2">
            <div class="itens-lista-container">
                <!-- Itens deste bloco -->
            </div>
            
            <div class="mt-2 text-center border-top pt-2">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="adicionarItem('${blockId}', false)">
                    <i class="fas fa-plus"></i> Add Produto
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="adicionarItem('${blockId}', true)">
                    <i class="fas fa-copy"></i> Copiar Anterior
                </button>
            </div>
        </div>
    </div>
    `;

    container.insertAdjacentHTML('beforeend', html);

    // Adicionar um primeiro item automaticamente
    adicionarItem(blockId, false);
}

let blocoParaRemover = null;

function removerBloco(blockId) {
    if (document.querySelectorAll('.cenario-block').length <= 1) {
        alert("Você deve ter pelo menos um cenário.");
        return;
    }

    blocoParaRemover = blockId;
    const modalEl = document.getElementById('modalConfirmarExclusao');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    } else {
        // Fallback
        if (confirm('Tem certeza que deseja remover este cenário completo?')) {
            const el = document.getElementById(blockId);
            if (el) el.remove();
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const btnConfirm = document.getElementById('btnConfirmarExclusao');
    if (btnConfirm) {
        btnConfirm.addEventListener('click', function () {
            if (blocoParaRemover) {
                const el = document.getElementById(blocoParaRemover);
                if (el) el.remove();
                blocoParaRemover = null;
                const modalEl = document.getElementById('modalConfirmarExclusao');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
        });
    }
});

// --- Gestão de Itens ---

function adicionarItem(blockId, copiarAnterior = false) {
    const blockEl = document.getElementById(blockId);
    if (!blockEl) return;

    const containerItens = blockEl.querySelector('.itens-lista-container');
    const index = itemIndex++;

    // Dados padrão ou cópia
    let dadosCliente = { id: '', nome: '', uf: '' };
    let clienteFound = false;

    if (copiarAnterior) {
        // Tenta pegar do último item DESTE bloco
        const ultimoItem = containerItens.lastElementChild;
        if (ultimoItem) {
            dadosCliente = {
                id: ultimoItem.querySelector('.item-id-cliente')?.value || '',
                nome: ultimoItem.querySelector('.item-cliente')?.value || '',
                uf: ultimoItem.querySelector('.item-uf')?.value || ''
            };
            clienteFound = true;
        } else {
            // Tenta pegar de qualquer bloco anterior para facilitar
            const qualquerUltimoItem = document.querySelector('.item-row:last-child');
            if (qualquerUltimoItem) {
                dadosCliente = {
                    id: qualquerUltimoItem.querySelector('.item-id-cliente')?.value || '',
                    nome: qualquerUltimoItem.querySelector('.item-cliente')?.value || '',
                    uf: qualquerUltimoItem.querySelector('.item-uf')?.value || ''
                };
            }
        }
    }

    // HTML do item
    // Inputs Ocultos de Contexto do Bloco (Sincronizados via JS)

    const html = `
    <div class="item-row border rounded mb-3 shadow-sm bg-white" data-index="${index}" style="padding: 15px; border-left: 4px solid #40883c !important;">
        <!-- Hidden Fields -->
        <input type="hidden" name="itens[${index}][nome_sub_cenario]" class="item-sub-cenario-hidden">
        <input type="hidden" name="itens[${index}][modal]" class="item-modal-hidden">
        <input type="hidden" name="itens[${index}][taxa_juros_mensal]" class="item-taxa-hidden">
        <input type="hidden" name="itens[${index}][tempo_venda_meses]" class="item-tempo" value="0">


        <!-- Top Bar: Index & Delete -->
        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
            <div>
                <span class="badge bg-secondary rounded-pill">Item #${index + 1}</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('.item-row').remove();" title="Remover Item">
                <i class="fas fa-trash-alt"></i> Remover
            </button>
        </div>

        <!-- Section 1: Core Data (Client & Product) -->
        <div class="row g-2 mb-3">
             <div class="col-md-3">
                <label class="form-label small text-muted fw-bold">CLIENTE</label>
                <div class="input-group">
                    <input type="hidden" name="itens[${index}][id_cliente]" class="item-id-cliente" value="${dadosCliente.id}">
                    <input type="text" name="itens[${index}][cliente]" class="form-control form-control-sm item-cliente" value="${dadosCliente.nome}" readonly required placeholder="Selecione..." onclick="abrirModalClienteItem(this)" style="cursor: pointer; background-color: #f8f9fa;">
                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="abrirModalClienteItem(this)"><i class="fas fa-search"></i></button>
                </div>
                <input type="hidden" name="itens[${index}][uf]" class="item-uf" value="${dadosCliente.uf}">
            </div>

            <div class="col-md-4">
                <label class="form-label small text-muted fw-bold">PRODUTO</label>
                <div class="input-group">
                    <input type="hidden" name="itens[${index}][codigo]" class="item-codigo" required>
                    <input type="text" name="itens[${index}][produto]" class="form-control form-control-sm item-produto" readonly required placeholder="Selecione..." onclick="abrirModalProduto(this)" style="cursor: pointer; background-color: #f8f9fa;">
                     <button class="btn btn-sm btn-outline-secondary" type="button" onclick="abrirModalProduto(this)"><i class="fas fa-search"></i></button>
                </div>
            </div>

            <div class="col-md-1">
                 <label class="form-label small text-muted fw-bold">QTD</label>
                 <input type="number" name="itens[${index}][qtd]" class="form-control form-control-sm item-qtd fw-bold text-center" step="0.01" required oninput="calcularItem(this)"> 
            </div>
            
            <div class="col-md-2">
                 <label class="form-label small text-muted fw-bold">EMBALAGEM</label>
                 <input type="text" name="itens[${index}][embalagem]" class="form-control form-control-sm item-embalagem bg-white text-center" placeholder="Ex: 25kg" required>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted fw-bold">UNIDADE</label>
                <input type="text" name="itens[${index}][unidade]" class="form-control form-control-sm item-unidade bg-light text-center" readonly tabindex="-1">
            </div>
        </div>

        <!-- Section 2: Logistics & Planning (Light Blue Block) -->
                <div class="p-2 mb-3 rounded" style="background-color: #e3f2fd;">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <label class="form-label small text-primary fw-bold mb-1"><i class="fas fa-tag me-1"></i>TIPO</label>
                            <select name="itens[${index}][tipo_demanda]" class="form-select form-select-sm item-tipo-demanda border-primary">
                                <option value="Pedido">Pedido</option>
                                <option value="Forecast">Forecast</option>
                                <option value="Est Segurança">Est Segurança</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-primary fw-bold mb-1"><i class="fas fa-calendar me-1"></i>DEADLINE</label>
                            <input type="date" name="itens[${index}][data_necessidade]" class="form-control form-control-sm item-deadline border-primary">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-primary fw-bold mb-1"><i class="far fa-calendar-alt me-1"></i>NEC. CLIENTE</label>
                            <input type="date" name="itens[${index}][necessidade_cliente]" class="form-control form-control-sm item-necessidade border-primary">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-primary fw-bold mb-1" title="Meses para venda"><i class="fas fa-hourglass-half me-1"></i>PREVISÃO VENDA</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control form-control-sm border-primary text-center fw-bold" value="0" min="0" oninput="this.closest('.item-row').querySelector('.item-tempo').value = this.value; calcularItem(this);">
                                    <span class="input-group-text bg-white text-primary border-primary small" style="font-size: 0.7rem;">Meses</span>
                            </div>
                            <div class="form-text text-end text-primary" style="font-size: 0.65rem; margin-top: 2px;">0 = Imediato</div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Financials (Light Green Block) -->
                <div class="p-2 rounded border" style="background-color: #f1f8e9; border-color: #8bc34a !important;">
                    <div class="row g-2">
                        <!-- Inputs -->
                        <div class="col-md-2">
                            <label class="form-label small text-success fw-bold mb-1">LANDED ($/UN)</label>
                            <input type="number" name="itens[${index}][landed_usd_kg]" class="form-control form-control-sm item-landed border-success" step="0.0001" required oninput="calcularItem(this)">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-success fw-bold mb-1">VENDA ($/UN)</label>
                            <input type="number" name="itens[${index}][preco_unit_venda_usd_kg]" class="form-control form-control-sm item-preco-venda border-success" step="0.0001" required oninput="calcularItem(this)">
                        </div>

                        <!-- Calculated Totals -->
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Total Landed</label>
                            <div class="fw-bold text-dark item-total-landed-view" style="font-size: 0.9rem;">$0.00</div>
                            <input type="hidden" name="itens[${index}][total_landed_usd]" class="item-total-landed">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Total VF (Futuro)</label>
                            <div class="fw-bold text-warning item-vf-view" style="font-size: 0.9rem;">$0.00</div>
                            <input type="hidden" name="itens[${index}][valor_futuro]" class="item-vf">
                                <input type="hidden" name="itens[${index}][total_valor_futuro]" class="item-total-vf">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted mb-1">Total Venda</label>
                                    <div class="fw-bold text-success item-total-venda-view" style="font-size: 0.9rem;">$0.00</div>
                                    <input type="hidden" name="itens[${index}][total_venda_usd]" class="item-total-venda">
                                </div>
                                <div class="col-md-2 text-end">
                                    <label class="form-label small text-muted mb-1">GM% (Margem)</label>
                                    <div class="fw-bold text-primary item-gm-view" style="font-size: 1.1rem;">0.00%</div>
                                    <input type="hidden" name="itens[${index}][gm_percentual]" class="item-gm">
                                </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="itens[${index}][spec_exclusiva]" value="1" id="spec-${index}">
                                        <label class="form-check-label small text-secondary" for="spec-${index}">Este item possui especificação exclusiva (Homologação)?</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                `;

    containerItens.insertAdjacentHTML('beforeend', html);

    // Atualiza os campos ocultos do item recém-criado com os dados do bloco
    atualizarItensOcultos(blockId);
}

// Atualiza o nome e modal de todos os itens dentro de um bloco
function atualizarItensOcultos(blockId) {
    const blockEl = document.getElementById(blockId);
    if (!blockEl) return;

    const nome = blockEl.querySelector('.block-nome').value;
    const modal = blockEl.querySelector('.block-modal').value;
    const taxa = blockEl.querySelector('.block-taxa').value || 0;

    // Atualizar inputs ocultos de cada item
    const itens = blockEl.querySelectorAll('.item-row');
    itens.forEach(row => {
        const subInput = row.querySelector('.item-sub-cenario-hidden');
        if (subInput) subInput.value = nome;

        const modalInput = row.querySelector('.item-modal-hidden');
        if (modalInput) modalInput.value = modal;

        const taxaInput = row.querySelector('.item-taxa-hidden');
        if (taxaInput) taxaInput.value = taxa;
    });
}

// Recalcula tudo de um bloco (usado quando a taxa do bloco muda)
// Recalcula tudo de um bloco (usado quando a taxa do bloco muda)
function recalcularBloco(blockId) {
    const blockEl = document.getElementById(blockId);
    if (!blockEl) return;

    // Sincronizar dados ocultos (taxa, etc)
    atualizarItensOcultos(blockId);

    const itens = blockEl.querySelectorAll('.item-row');
    itens.forEach(row => {
        calcularItem(row.querySelector('.item-qtd'));
    });
}

function calcularItem(element) {
    const row = element.closest('.item-row');
    if (!row) return;

    // Achar o bloco pai para pegar a taxa
    const blockEl = row.closest('.cenario-block');
    let taxa = 3.00; // default
    if (blockEl) {
        const taxaInput = blockEl.querySelector('.block-taxa');
        if (taxaInput) taxa = parseFloat(taxaInput.value) || 0;
    }

    const qtd = parseFloat(row.querySelector('.item-qtd').value) || 0;
    const landed = parseFloat(row.querySelector('.item-landed').value) || 0;
    const precoVenda = parseFloat(row.querySelector('.item-preco-venda').value) || 0;
    const tempoRaw = row.querySelector('.item-tempo').value;
    const tempo = tempoRaw === "" ? 12 : parseInt(tempoRaw);

    // Total Landed
    const totalLanded = qtd * landed;
    const totalLandedView = row.querySelector('.item-total-landed-view');
    if (totalLandedView) totalLandedView.innerText = '$' + totalLanded.toFixed(2);

    const totalLandedInput = row.querySelector('.item-total-landed');
    if (totalLandedInput) totalLandedInput.value = totalLanded.toFixed(2);

    // Valor Futuro (VF)
    let vf;
    if (tempo === 0) {
        vf = totalLanded;
    } else {
        const taxaDecimal = taxa / 100;
        vf = totalLanded * Math.pow(1 + taxaDecimal, tempo);
    }

    const vfView = row.querySelector('.item-vf-view');
    if (vfView) vfView.innerText = '$' + vf.toFixed(2);

    const vfInput = row.querySelector('.item-vf');
    if (vfInput) vfInput.value = vf.toFixed(2);

    const totalVfInput = row.querySelector('.item-total-vf');
    if (totalVfInput) totalVfInput.value = vf.toFixed(2);

    // Total Venda
    const totalVenda = qtd * precoVenda;

    const totalVendaView = row.querySelector('.item-total-venda-view');
    if (totalVendaView) totalVendaView.innerText = '$' + totalVenda.toFixed(2);

    const totalVendaInput = row.querySelector('.item-total-venda');
    if (totalVendaInput) totalVendaInput.value = totalVenda.toFixed(2);

    // GM%
    let gm = 0;
    if (totalVenda > 0) {
        gm = ((totalVenda - vf) / totalVenda) * 100;
    }

    const gmView = row.querySelector('.item-gm-view');
    if (gmView) gmView.innerText = gm.toFixed(2) + '%';

    const gmInput = row.querySelector('.item-gm');
    if (gmInput) gmInput.value = gm.toFixed(2);
}

function recalcularTodos() {
    document.querySelectorAll('.cenario-block').forEach(block => {
        recalcularBloco(block.id);
    });
}

// --- Funções dos Modais ---

// Fornecedor
function abrirModalFornecedor() {
    const modalEl = document.getElementById('modalFornecedores');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        carregarFornecedores();
    }
}

function carregarFornecedores(termo = '') {
    fetch(`buscar_fornecedores.php?q=${encodeURIComponent(termo)}`)
        .then(res => res.json())
        .then(fornecedores => {
            const lista = document.getElementById('listaFornecedores');
            if (!lista) return;

            lista.innerHTML = '';

            if (fornecedores.length === 0) {
                lista.innerHTML = '<tr><td colspan="3" class="text-center">Nenhum fornecedor encontrado.</td></tr>';
                return;
            }

            fornecedores.forEach(f => {
                const tr = document.createElement('tr');
                const json = JSON.stringify(f).replace(/'/g, "&apos;");
                tr.innerHTML = `
      <td>${f.nome}</td>
      <td>${f.pais || '-'}</td>
      <td>
        <button type="button" class="btn btn-sm btn-primary" onclick='selecionarFornecedor(${json})'>
          Selecionar
        </button>
      </td>
    `;
                lista.appendChild(tr);
            });
        })
        .catch(err => console.error('Erro ao buscar fornecedores:', err));
}

function selecionarFornecedor(fornecedor) {
    document.getElementById('id_fornecedor').value = fornecedor.id;
    document.getElementById('fornecedor').value = fornecedor.nome;

    const modalEl = document.getElementById('modalFornecedores');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

// Cliente
function abrirModalClienteItem(button) {
    // Armazena qual linha chamou o modal
    currentItemRow = button.closest('.item-row');
    abrirModalCliente();
}

function abrirModalCliente() {
    const modalEl = document.getElementById('modalClientes');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        const busca = document.getElementById('buscaCliente');
        if (busca) {
            busca.value = '';
            busca.focus();
        }
    }
}

function selecionarCliente(cliente) {
    if (currentItemRow) {
        currentItemRow.querySelector('.item-id-cliente').value = cliente.id || '';
        currentItemRow.querySelector('.item-cliente').value = cliente.razao_social;
        const ufInput = currentItemRow.querySelector('.item-uf');
        if (ufInput) ufInput.value = cliente.uf;

        currentItemRow = null;
    } else {
        // Fallback se necessário
    }

    const modalEl = document.getElementById('modalClientes');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

// Produto
function abrirModalProduto(button) {
    currentItemRow = button.closest('.item-row');
    const modalEl = document.getElementById('modalProdutos');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        setTimeout(() => {
            const busca = document.getElementById('buscaProduto');
            if (busca) busca.focus();
        }, 500);
    }
}

function buscarProdutos() {
    const buscaInput = document.getElementById('buscaProduto');
    if (!buscaInput) return;

    const termo = buscaInput.value;
    const tbody = document.getElementById('listaProdutos');
    tbody.innerHTML = '<tr><td colspan="3" class="text-center">Buscando...</td></tr>';

    fetch(`buscar_produtos.php?q=${encodeURIComponent(termo)}`)
        .then(res => res.json())
        .then(produtos => {
            tbody.innerHTML = '';

            if (produtos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center">Nenhum produto encontrado.</td></tr>';
                return;
            }

            produtos.forEach(p => {
                const tr = document.createElement('tr');
                const json = JSON.stringify(p).replace(/'/g, "&apos;");
                tr.innerHTML = `
                <td>${p.codigo}</td>
                <td>${p.produto}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-primary" onclick='selecionarProduto(${json})'>
                        Selecionar
                    </button>
                </td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(err => {
            console.error('Erro:', err);
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Erro ao buscar produtos.</td></tr>';
        });
}

function selecionarProduto(produto) {
    if (!currentItemRow) return;

    currentItemRow.querySelector('.item-codigo').value = produto.codigo;
    currentItemRow.querySelector('.item-produto').value = produto.produto;
    currentItemRow.querySelector('.item-unidade').value = produto.unidade || 'KG';

    const modalEl = document.getElementById('modalProdutos');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}
