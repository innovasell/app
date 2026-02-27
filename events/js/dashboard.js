// Dashboard JavaScript
let categoryChart, clientsChart;
let editModal, editEventoModal;

// Inicializa quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function () {
    editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    editEventoModal = new bootstrap.Modal(document.getElementById('editEventoModal'));
    loadDashboardStats();
    loadExpenses();
});

// Carrega estatísticas do dashboard
async function loadDashboardStats() {
    try {
        const response = await fetch('api/get_dashboard_stats.php');
        const result = await response.json();

        if (result.success) {
            const stats = result.data;

            // Atualiza cards de resumo
            document.getElementById('totalGeral').textContent = formatCurrency(stats.total_geral);

            // Atualiza totais por categoria
            const categorias = stats.por_categoria;
            let totalPassagens = 0, totalHoteis = 0, totalTransporte = 0;

            categorias.forEach(cat => {
                if (cat.categoria === 'Passagem Aérea') totalPassagens = cat.total;
                if (cat.categoria === 'Hotel') totalHoteis = cat.total;
                if (cat.categoria === 'Transporte') totalTransporte = cat.total;
            });

            document.getElementById('totalPassagens').textContent = formatCurrency(totalPassagens);
            document.getElementById('totalHoteis').textContent = formatCurrency(totalHoteis);
            document.getElementById('totalTransporte').textContent = formatCurrency(totalTransporte);

            // Cria gráfico de pizza por categoria
            createCategoryChart(categorias);

            // Cria gráfico de barras dos top passageiros.
            createPassengersChart(stats.top_passageiros.slice(0, 5));
        }
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
    }
}

// Cria gráfico de pizza das categorias
function createCategoryChart(data) {
    const ctx = document.getElementById('categoryChart').getContext('2d');

    if (categoryChart) {
        categoryChart.destroy();
    }

    const labels = data.map(d => d.categoria);
    const values = data.map(d => d.total);
    const colors = [
        'rgba(59, 130, 246, 0.8)',   // Blue - Passagem Aérea
        'rgba(139, 92, 246, 0.8)',   // Purple - Hotel
        'rgba(34, 197, 94, 0.8)',    // Green - Transporte (was Outros)
        'rgba(251, 146, 60, 0.8)',   // Orange - Seguro
        'rgba(107, 114, 128, 0.8)',  // Gray - Outros
        'rgba(156, 163, 175, 0.8)'   // Light Gray - Não Categorizado
    ];

    categoryChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const label = context.label || '';
                            const value = formatCurrency(context.parsed);
                            return label + ': ' + value;
                        }
                    }
                }
            }
        }
    });
}

// Cria gráfico de barras dos top passageiros
function createPassengersChart(data) {
    const ctx = document.getElementById('clientsChart').getContext('2d');

    if (clientsChart) {
        clientsChart.destroy();
    }

    const labels = data.map(d => (d.passageiro || 'Sem nome').substring(0, 30));
    const values = data.map(d => d.total);

    clientsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Gasto',
                data: values,
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return formatCurrency(context.parsed.y);
                        }
                    }
                }
            }
        }
    });
}

// Carrega despesas com filtros
async function loadExpenses() {
    const tbody = document.getElementById('expensesTableBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="spinner-border text-primary"></div></td></tr>';

    const categoria = document.getElementById('filterCategoria').value;
    const dataInicio = document.getElementById('filterDataInicio').value;
    const dataFim = document.getElementById('filterDataFim').value;

    const params = new URLSearchParams();
    if (categoria) params.append('categoria', categoria);
    if (dataInicio) params.append('data_inicio', dataInicio);
    if (dataFim) params.append('data_fim', dataFim);
    params.append('limit', 100);

    try {
        const response = await fetch('api/get_expenses.php?' + params.toString());
        const result = await response.json();

        if (result.success && result.data.length > 0) {
            tbody.innerHTML = '';
            result.data.forEach(expense => {
                const row = document.createElement('tr');
                const eventoVisitaText = expense.evento_visita || '<span class="text-muted">-</span>';
                const faturaText = expense.num_fatura || '<span class="text-muted">-</span>';

                row.innerHTML = `
                    <td>${formatDate(expense.dt_emissao)}</td>
                    <td>${expense.passageiro || '-'}</td>
                    <td>${eventoVisitaText}</td>
                    <td>${faturaText}</td>
                    <td title="${expense.produto || ''}">
                        ${(expense.produto && expense.produto.length > 50) ? expense.produto.substring(0, 47) + '...' : (expense.produto || '-')}
                    </td>
                    <td>
                        <span class="badge ${getCategoryBadgeClass(expense.categoria_despesa)}">
                            ${expense.categoria_despesa}
                        </span>
                        ${expense.categoria_auto == 0 ? '<i class="bi bi-pencil-fill text-warning" title="Editado manualmente"></i>' : ''}
                    </td>
                    <td><strong>${formatCurrency(expense.total)}</strong></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-info" onclick="openEditEventoModal(${expense.id}, '${(expense.produto || '').replace(/'/g, "\\\'")}', '${(expense.evento_visita || '').replace(/'/g, "\\\'")}', '${(expense.num_fatura || '').replace(/'/g, "\\\'")}', '${(expense.passageiro || '').replace(/'/g, "\\\'")}', '${expense.dt_emissao}', ${expense.total})" title="Editar detalhes">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(${expense.id}, '${expense.produto.replace(/'/g, "\\'")}', '${expense.categoria_despesa}')" title="Editar categoria">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteExpense(${expense.id}, '${expense.produto.replace(/'/g, "\\'")}')" title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Nenhuma despesa encontrada.</td></tr>';
        }
    } catch (error) {
        console.error('Erro ao carregar despesas:', error);
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Erro ao carregar dados.</td></tr>';
    }
}

// Abre modal de edição de categoria
function openEditModal(id, produto, categoriaAtual) {
    document.getElementById('editExpenseId').value = id;
    document.getElementById('editProductName').textContent = produto;
    document.getElementById('editCategory').value = categoriaAtual;
    editModal.show();
}

// Salva a categoria editada
async function saveCategory() {
    const id = document.getElementById('editExpenseId').value;
    const categoria = document.getElementById('editCategory').value;

    try {
        const response = await fetch('api/update_category.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id), categoria: categoria })
        });

        const result = await response.json();

        if (result.success) {
            editModal.hide();
            // Recarrega os dados
            loadDashboardStats();
            loadExpenses();

            // Mostra mensagem de sucesso
            showToast('Categoria atualizada com sucesso!', 'success');
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (error) {
        console.error('Erro ao salvar categoria:', error);
        alert('Erro ao salvar categoria.');
    }
}

// Helpers
function formatCurrency(value) {
    return 'R$ ' + parseFloat(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('pt-BR');
}

function getCategoryBadgeClass(categoria) {
    const classes = {
        'Passagem Aérea': 'bg-primary',
        'Hotel': 'bg-purple',
        'Seguro': 'bg-warning',
        'Transporte': 'bg-info',
        'Outros': 'bg-success',
        'Não Categorizado': 'bg-secondary'
    };
    return classes[categoria] || 'bg-secondary';
}

function showToast(message, type) {
    // Simples alert por enquanto, pode melhorar com toasts do Bootstrap
    alert(message);
}

// Função para excluir uma despesa
async function deleteExpense(id, produto) {
    if (!confirm(`Tem certeza que deseja excluir esta despesa?\n\nProduto: ${produto}`)) {
        return;
    }

    try {
        const response = await fetch('api/delete_expense.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id) })
        });

        const result = await response.json();

        if (result.success) {
            // Recarrega os dados
            loadDashboardStats();
            loadExpenses();
            showToast('Despesa excluída com sucesso!', 'success');
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (error) {
        console.error('Erro ao excluir despesa:', error);
        alert('Erro ao excluir despesa.');
    }
}

// Abre modal de edição de evento/visita
function openEditEventoModal(id, produto, eventoAtual, faturaAtual, passageiro, data, valor) {
    document.getElementById('editEventoExpenseId').value = id;
    document.getElementById('editEventoProduto').value = produto;
    document.getElementById('editEventoVisita').value = eventoAtual || '';
    document.getElementById('editFatura').value = faturaAtual || '';
    document.getElementById('editEventoPassageiro').value = passageiro || '';
    document.getElementById('editEventoData').value = data || '';
    document.getElementById('editEventoValor').value = valor || '';
    editEventoModal.show();
}

// Salva o evento/visita editado
async function saveEvento() {
    const id = document.getElementById('editEventoExpenseId').value;
    const eventoVisita = document.getElementById('editEventoVisita').value.trim();
    const numFatura = document.getElementById('editFatura').value.trim();
    const produto = document.getElementById('editEventoProduto').value.trim();
    const passageiro = document.getElementById('editEventoPassageiro').value.trim();
    const data = document.getElementById('editEventoData').value;
    const valor = document.getElementById('editEventoValor').value;

    try {
        const response = await fetch('api/update_evento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: parseInt(id),
                evento_visita: eventoVisita,
                num_fatura: numFatura,
                produto: produto,
                passageiro: passageiro,
                dt_emissao: data,
                total: parseFloat(valor)
            })
        });

        const result = await response.json();

        if (result.success) {
            editEventoModal.hide();
            // Recarrega os dados
            loadDashboardStats();
            loadExpenses();

            // Mostra mensagem de sucesso
            showToast('Detalhes atualizados com sucesso!', 'success');
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (error) {
        console.error('Erro ao salvar detalhes:', error);
        alert('Erro ao salvar detalhes.');
    }
}
