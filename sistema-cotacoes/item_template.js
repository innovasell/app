// Template melhorado para produtos do cenário
function getItemHTML(index, clienteAnterior) {
  return `
        <div class="item-row" data-index="${index}" style="border: 1px solid #dee2e6; padding: 15px; border-radius: 8px; margin-bottom: 15px; background-color: #f8f9fa;">
          <div class="row g-2">
            <!-- Header -->
            <div class="col-12 d-flex justify-content-between align-items-center mb-3 bg-primary bg-opacity-10 p-2 rounded">
              <strong class="text-primary"><i class="fas fa-box me-2"></i>Produto #${index + 1}</strong>
              <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.item-row').remove()">
                <i class="fas fa-trash"></i> Remover
              </button>
            </div>

            <!-- DADOS DO PRODUTO -->
            <div class="col-12 mb-1"><small class="fw-bold text-secondary"><i class="fas fa-info-circle me-1"></i>DADOS DO PRODUTO</small><hr class="my-1"></div>
            
            <div class="col-md-3">
              <label class="form-label small">Cliente <span class="text-danger">*</span></label>
              <div class="input-group input-group-sm">
                <input type="hidden" name="itens[${index}][id_cliente]" class="item-id-cliente" value="${clienteAnterior.id}">
                <input type="text" name="itens[${index}][cliente]" class="form-control item-cliente" value="${clienteAnterior.nome}" readonly required>
                <button class="btn btn-outline-secondary" type="button" onclick="abrirModalClienteItem(this)">
                  <i class="fas fa-search"></i>
                </button>
              </div>
            </div>

            <div class="col-md-1">
              <label class="form-label small">UF</label>
              <input type="text" name="itens[${index}][uf]" class="form-control form-control-sm item-uf" value="${clienteAnterior.uf}" readonly>
            </div>

            <div class="col-md-4">
              <label class="form-label small">Produto <span class="text-danger">*</span></label>
              <div class="input-group input-group-sm">
                <input type="hidden" name="itens[${index}][codigo]" class="item-codigo" required>
                <input type="text" name="itens[${index}][produto]" class="form-control item-produto" readonly required>
                <button class="btn btn-outline-secondary" type="button" onclick="abrirModalProduto(this)">
                  <i class="fas fa-search"></i>
                </button>
                <button class="btn btn-outline-info" type="button" onclick="verPrecoLista(this)" title="Ver Preço de Lista">
                  <i class="fas fa-dollar-sign"></i>
                </button>
              </div>
            </div>

            <div class="col-md-1">
              <label class="form-label small">QTD <span class="text-danger">*</span></label>
              <input type="number" name="itens[${index}][qtd]" class="form-control form-control-sm item-qtd" step="0.01" required oninput="calcularItem(this)">
            </div>
            
            <div class="col-md-2">
              <label class="form-label small">Embalagem <span class="text-danger">*</span></label>
              <input type="text" name="itens[${index}][embalagem]" class="form-control form-control-sm item-embalagem" placeholder="Ex: 25kg" required>
            </div>

            <div class="col-md-1">
              <label class="form-label small">UN</label>
              <input type="text" name="itens[${index}][unidade]" class="form-control form-control-sm item-unidade" readonly style="background-color: #e9ecef;">
            </div>

            <div class="col-md-1">
              <label class="form-label small">Spec</label>
              <select name="itens[${index}][spec_exclusiva]" class="form-select form-select-sm">
                <option value="0">Não</option>
                <option value="1">Sim</option>
              </select>
            </div>

            <div class="col-md-2">
              <label class="form-label small">Variação / Cenário</label>
              <input type="text" name="itens[${index}][nome_sub_cenario]" class="form-control form-control-sm" placeholder="Ex: Opção 1">
            </div>

            <div class="col-md-2">
              <label class="form-label small">Deadline</label>
              <input type="date" name="itens[${index}][data_necessidade]" class="form-control form-control-sm">
            </div>

            <div class="col-md-2">
              <label class="form-label small">Tipo</label>
              <select name="itens[${index}][tipo_demanda]" class="form-select form-select-sm">
                <option value="Pedido">Pedido</option>
                <option value="Forecast">Forecast</option>
                <option value="Est Segurança">Est Segurança</option>
              </select>
            </div>

            <div class="col-md-1">
              <label class="form-label small">Tempo</label>
              <input type="number" name="itens[${index}][tempo_venda_meses]" class="form-control form-control-sm item-tempo" value="12" min="0" required oninput="calcularItem(this)" title="Meses p/ venda">
            </div>

            <!-- CUSTOS -->
            <div class="col-12 mb-1 mt-3"><small class="fw-bold text-secondary"><i class="fas fa-dollar-sign me-1"></i>CUSTOS E PRECIFICAÇÃO</small><hr class="my-1"></div>

            <div class="col-md-2">
              <label class="form-label small">Landed (US$/UN) <span class="text-danger">*</span></label>
              <input type="number" name="itens[${index}][landed_usd_kg]" class="form-control form-control-sm item-landed" step="0.0001" required oninput="calcularItem(this)">
            </div>

            <div class="col-md-2">
              <label class="form-label small">Total Landed</label>
              <input type="number" name="itens[${index}][total_landed_usd]" class="form-control form-control-sm calculated-field item-total-landed" readonly>
            </div>

            <div class="col-md-2">
              <label class="form-label small">Valor Futuro</label>
              <input type="number" name="itens[${index}][valor_futuro]" class="form-control form-control-sm calculated-field item-vf" readonly>
            </div>

            <div class="col-md-2">
              <label class="form-label small">Total VF</label>
              <input type="number" name="itens[${index}][total_valor_futuro]" class="form-control form-control-sm calculated-field item-total-vf" readonly>
            </div>

            <div class="col-md-2">
              <label class="form-label small">Preço Venda (US$/UN) <span class="text-danger">*</span></label>
              <input type="number" name="itens[${index}][preco_unit_venda_usd_kg]" class="form-control form-control-sm item-preco-venda" step="0.0001" required oninput="calcularItem(this)">
            </div>

            <div class="col-md-2">
              <label class="form-label small">Total Venda</label>
              <input type="number" name="itens[${index}][total_venda_usd]" class="form-control form-control-sm calculated-field item-total-venda" readonly>
            </div>

            <!-- MARGEM -->
            <div class="col-12 mb-1 mt-3"><small class="fw-bold text-success"><i class="fas fa-chart-line me-1"></i>MARGEM</small><hr class="my-1"></div>
            
            <div class="col-md-2">
              <label class="form-label small fw-bold">GM%</label>
              <input type="number" name="itens[${index}][gm_percentual]" class="form-control form-control-sm calculated-field item-gm fw-bold text-success" readonly>
            </div>
          </div>
        </div>
      `;
}
