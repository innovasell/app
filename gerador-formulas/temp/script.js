document.addEventListener('DOMContentLoaded', () => {

    // =================================================================
    // LÓGICA DA PÁGINA DE PESQUISA (pesquisar_formulas.php)
    // =================================================================
    const formPesquisa = document.getElementById('form-pesquisa');
    if (formPesquisa) {
        const areaResultados = document.getElementById('area-resultados');
        const btnLimpar = document.getElementById('btn-limpar');
        const btnPesquisaAvancada = document.getElementById('btn_pesquisa_avancada');
        const pesquisaAvancadaDiv = document.getElementById('pesquisa_avancada');
        const confirmDeleteModalEl = document.getElementById('confirmDeleteModal');
        const confirmDeleteModal = bootstrap.Modal.getOrCreateInstance(confirmDeleteModalEl);
        const btnConfirmDelete = document.getElementById('btnConfirmDelete');
        const alertPlaceholder = document.getElementById('alert-placeholder');

        function showAlert(message, type = 'success') {
            if (!alertPlaceholder) return;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = [
                `<div class="alert alert-${type} alert-dismissible fade show" role="alert">`,
                `   <div>${message}</div>`,
                '   <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>',
                '</div>'
            ].join('');
            alertPlaceholder.innerHTML = ''; // Limpa alertas antigos
            alertPlaceholder.append(wrapper);
        }

        if (btnPesquisaAvancada && pesquisaAvancadaDiv) {
            btnPesquisaAvancada.addEventListener('click', () => {
                pesquisaAvancadaDiv.style.display = pesquisaAvancadaDiv.style.display === 'none' ? 'block' : 'none';
            });
        }

        async function buscarFormulas() {
            areaResultados.innerHTML = `<div class="d-flex justify-content-center align-items-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div><strong class="ms-3">Buscando...</strong></div>`;
            const formData = new FormData(formPesquisa);
            const params = new URLSearchParams(formData);
            params.append('_', new Date().getTime());

            try {
                const response = await fetch(`${formPesquisa.action}?${params.toString()}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const resultados = await response.json();
                renderizarResultados(resultados);
            } catch (error) {
                areaResultados.innerHTML = `<div class="alert alert-danger">Ocorreu um erro ao buscar os dados.</div>`;
                console.error('Erro na busca:', error);
            }
        }

        function renderizarResultados(resultados) {
            if (!resultados || resultados.length === 0) {
                areaResultados.innerHTML = `<div class="alert alert-warning">Nenhuma formulação encontrada.</div>`;
                return;
            }
            let tabelaHTML = `<div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>Código</th><th>Nome da Fórmula</th><th>Categoria</th><th>Data de Criação</th><th class="text-end">Ações</th></tr></thead><tbody>`;
            resultados.forEach(formula => {
                tabelaHTML += `<tr>
                    <td>${escapeHTML(formula.codigo_formula)}</td>
                    <td>${escapeHTML(formula.nome_formula)}</td>
                    <td>${escapeHTML(formula.categoria)}</td>
                    <td>${escapeHTML(formula.data_criacao_formatada)}</td>
                    <td class="text-end">
                        <a href="view_formula.php?id=${formula.id}" class="btn btn-info btn-sm" title="Visualizar"><i class="bi bi-eye"></i></a>
                        <a href="editar_formula.php?id=${formula.id}" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-danger btn-sm delete-btn" 
                                data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                data-formula-id="${formula.id}" data-formula-name="${escapeHTML(formula.nome_formula)}"
                                title="Excluir"><i class="bi bi-trash"></i></button>
                    </td></tr>`;
            });
            tabelaHTML += `</tbody></table></div>`;
            areaResultados.innerHTML = tabelaHTML;
        }

        formPesquisa.addEventListener('submit', (event) => {
            event.preventDefault();
            buscarFormulas();
        });

        if (btnLimpar) {
            btnLimpar.addEventListener('click', () => {
                formPesquisa.reset();
                if(pesquisaAvancadaDiv) pesquisaAvancadaDiv.style.display = 'none';
                areaResultados.innerHTML = `<div class="alert alert-info">Utilize os filtros acima para iniciar uma pesquisa.</div>`;
            });
        }

        if (confirmDeleteModalEl) {
          confirmDeleteModalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const formulaId = button.getAttribute('data-formula-id');
            const formulaName = button.getAttribute('data-formula-name');
            const modalBodyName = confirmDeleteModalEl.querySelector('#formulaNameToDelete');
            modalBodyName.textContent = formulaName;
            btnConfirmDelete.dataset.formulaId = formulaId;
          });
        }

        if (btnConfirmDelete) {
            btnConfirmDelete.addEventListener('click', async () => {
                const formulaId = btnConfirmDelete.dataset.formulaId;
                const originalButtonText = "Sim, Excluir";
                btnConfirmDelete.disabled = true;
                btnConfirmDelete.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Excluindo...`;
                try {
                    const response = await fetch(`deletar_formula.php?id=${formulaId}`);
                    const result = await response.json();
                    if (!response.ok || !result.success) { throw new Error(result.message || 'Erro desconhecido.'); }
                    confirmDeleteModal.hide();
                    showAlert('Formulação excluída com sucesso!', 'success');
                    buscarFormulas();
                } catch (error) {
                    showAlert(`Falha ao excluir: ${error.message}`, 'danger');
                } finally {
                    btnConfirmDelete.disabled = false;
                    btnConfirmDelete.innerHTML = originalButtonText;
                }
            });
        }
    }

    // =================================================================
    // LÓGICA DO FORMULÁRIO DE CRIAÇÃO/EDIÇÃO (criar_formula.php / editar_formula.php)
    // =================================================================
    const formulaForm = document.getElementById('formula-form');
    if (formulaForm) {
        const confirmSubmitModalEl = document.getElementById('confirmSubmitModal');
        const confirmSubmitModal = new bootstrap.Modal(confirmSubmitModalEl);

        document.getElementById('btn-submit-modal').addEventListener('click', () => {
            if (formulaForm.checkValidity()) {
                confirmSubmitModal.show();
            } else {
                formulaForm.reportValidity();
            }
        });
        document.getElementById('btn-confirm-submit').addEventListener('click', () => {
            formulaForm.submit();
        });

        document.getElementById('add-ativo').addEventListener('click', () => {
            const container = document.getElementById('ativos-container');
            const novoAtivo = document.createElement('div');
            novoAtivo.className = 'row g-2 mb-2 align-items-center dynamic-item';
            novoAtivo.innerHTML = `<div class="col-md-4"><input type="text" name="ativos_nome[]" class="form-control" placeholder="Nome do Ativo" required></div><div class="col-md-7"><input type="text" name="ativos_desc[]" class="form-control" placeholder="Descrição do Ativo"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm remove-item"><i class="bi bi-trash"></i></button></div>`;
            container.appendChild(novoAtivo);
        });

        document.getElementById('add-sub-formulacao').addEventListener('click', () => {
            const container = document.getElementById('sub-formulacoes-container');
            const subIndex = Date.now();
            const novaSub = document.createElement('div');
            novaSub.className = 'card shadow-sm mb-4 dynamic-item';
            novaSub.innerHTML = `<div class="card-header bg-light d-flex justify-content-between align-items-center"><input type="text" name="sub_formulacoes[${subIndex}][nome]" class="form-control fw-bold" placeholder="Nome da Parte (Ex: Papaya)" required><button type="button" class="btn btn-danger btn-sm remove-item ms-2"><i class="bi bi-x-lg"></i> Remover Parte</button></div><div class="card-body"><div class="mb-3"><label class="form-label">Modo de Preparo (desta parte)</label><textarea class="form-control" name="sub_formulacoes[${subIndex}][modo_preparo]" rows="4" required></textarea></div><h6 class="mt-4">Fases Internas</h6><div class="fases-container"></div><button type="button" class="btn btn-outline-success btn-sm mt-2 add-fase" data-sub-index="${subIndex}"><i class="bi bi-plus"></i> Adicionar Fase (A, B, C...)</button></div>`;
            container.appendChild(novaSub);
        });

        document.body.addEventListener('click', (event) => {
            if (event.target.closest('.remove-item')) {
                event.target.closest('.dynamic-item').remove();
            }
            if (event.target.closest('.add-fase')) {
                const button = event.target.closest('.add-fase');
                const subIndex = button.dataset.subIndex;
                const fasesContainer = button.closest('.card-body').querySelector('.fases-container');
                const faseIndex = Date.now();
                const novaFase = document.createElement('div');
                novaFase.className = 'card mb-3 dynamic-item';
                novaFase.innerHTML = `<div class="card-header card-header-sm d-flex justify-content-between align-items-center"><input type="text" name="sub_formulacoes[${subIndex}][fases][${faseIndex}][nome]" class="form-control form-control-sm" placeholder="Nome da Fase (Ex: Fase A)" required><button type="button" class="btn btn-danger btn-sm remove-item ms-2"><i class="bi bi-x-lg"></i></button></div><div class="card-body"><div class="ingredientes-container"></div><button type="button" class="btn btn-outline-primary btn-sm mt-2 add-ingrediente" data-sub-index="${subIndex}" data-fase-index="${faseIndex}"><i class="bi bi-plus"></i> Adicionar Ingrediente</button></div>`;
                fasesContainer.appendChild(novaFase);
            }
            if (event.target.closest('.add-ingrediente')) {
                const button = event.target.closest('.add-ingrediente');
                const subIndex = button.dataset.subIndex;
                const faseIndex = button.dataset.faseIndex;
                const ingredientesContainer = button.closest('.card-body').querySelector('.ingredientes-container');
                const ingredienteIndex = Date.now();
                const novoIngrediente = document.createElement('div');
                novoIngrediente.className = 'row g-2 mb-2 align-items-center dynamic-item';
                novoIngrediente.innerHTML = `
                    <div class="col-md-3"><input type="text" name="sub_formulacoes[${subIndex}][fases][${faseIndex}][ingredientes][${ingredienteIndex}][materia_prima]" placeholder="Matéria-Prima" class="form-control form-control-sm" required></div>
                    <div class="col-md-3"><input type="text" name="sub_formulacoes[${subIndex}][fases][${faseIndex}][ingredientes][${ingredienteIndex}][inci_name]" placeholder="INCI Name" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><input type="text" name="sub_formulacoes[${subIndex}][fases][${faseIndex}][ingredientes][${ingredienteIndex}][percentual]" placeholder="%" class="form-control form-control-sm" required></div>
                    <div class="col-md-3 d-flex justify-content-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="sub_formulacoes[${subIndex}][fases][${faseIndex}][ingredientes][${ingredienteIndex}][destaque]" value="1">
                            <label class="form-check-label small">Destacar</label>
                        </div>
                    </div>
                    <div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm remove-item"><i class="bi bi-trash"></i></button></div>
                `;
                ingredientesContainer.appendChild(novoIngrediente);
            }
        });
    }

    // Função de utilidade para escapar HTML
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return str.toString().replace(/[&<>"']/g, m => ({'&': '&amp;','<': '&lt;','>': '&gt;','"': '&quot;',"'": '&#039;'})[m]);
    }
});