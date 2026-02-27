<?php
// admin_users.php - Gestão de Usuários e Permissões
session_start();
require_once 'site_conexao.php';

// Verificação de segurança: Apenas Admin
if (!isset($_SESSION['sso_user']) || $_SESSION['sso_user']['admin'] != 1) {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['sso_user'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Innovasell</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }

        .table img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }

        .perm-check {
            transform: scale(1.2);
            cursor: pointer;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Innovasell Cloud</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Voltar ao Dashboard</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Gestão de Acessos</h2>
            <button class="btn btn-primary" onclick="abrirModalNovoUsuario()">
                <i class="bi bi-person-plus-fill"></i> Novo Usuário
            </button>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Usuário</th>
                                <th class="text-center">Admin</th>
                                <th class="text-center" title="Portal de Cotações">Cot.</th>
                                <th class="text-center" title="Portal de Expedição">Exp.</th>
                                <th class="text-center" title="InnovaWiki (FAQ)">Wiki</th>
                                <th class="text-center" title="Portal de Comissões">Com.</th>
                                <th class="text-center" title="Formularium">Form.</th>
                                <th class="text-center" title="InnovaEvents">Event.</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="listaUsuarios">
                            <tr>
                                <td colspan="9" class="text-center py-4">Carregando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar/Novo -->
    <div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Editar Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formUsuario">
                        <input type="hidden" name="id" id="userId">
                        <input type="hidden" name="action" id="action" value="save">

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome</label>
                                <input type="text" name="nome" id="nome" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sobrenome</label>
                                <input type="text" name="sobrenome" id="sobrenome" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">E-mail</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>

                        <!-- Senha Field (Only for new users or optional reset) -->
                        <div class="mb-3" id="divSenha">
                            <label class="form-label">Senha Inicial</label>
                            <input type="text" name="senha" id="senha" class="form-control"
                                placeholder="Deixe em branco para manter a atual">
                            <div class="form-text">Para novos usuários, defina uma senha temporária.</div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="force_changepass"
                                id="force_changepass" value="1">
                            <label class="form-check-label" for="force_changepass">
                                Obrigar troca de senha no próximo login
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="admin" id="admin" value="1">
                            <label class="form-check-label fw-bold" for="admin">
                                Usuário Administrador (Acesso Total)
                            </label>
                        </div>

                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarUsuario()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', carregarUsuarios);

        function carregarUsuarios() {
            fetch('admin_users_api.php?action=list')
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('listaUsuarios');
                    tbody.innerHTML = '';

                    if (data.error) {
                        tbody.innerHTML = `<tr><td colspan="9" class="text-danger text-center">${data.error}</td></tr>`;
                        return;
                    }

                    data.forEach(u => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="ps-4">
                                <div class="fw-bold text-dark">${u.nome} ${u.sobrenome}</div>
                                <div class="small text-muted">${u.email}</div>
                            </td>
                            <td class="text-center">${u.admin == 1 ? '<span class="badge bg-dark">Admin</span>' : '-'}</td>
                            ${renderCheck(u.id, 'acesso_cotacoes', u.acesso_cotacoes)}
                            ${renderCheck(u.id, 'acesso_expedicao', u.acesso_expedicao)}
                            ${renderCheck(u.id, 'acesso_faq', u.acesso_faq)}
                            ${renderCheck(u.id, 'acesso_comissoes', u.acesso_comissoes)}
                            ${renderCheck(u.id, 'acesso_formulas', u.acesso_formulas)}
                            ${renderCheck(u.id, 'acesso_viagens', u.acesso_viagens)}
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-secondary" onclick='editarUsuario(${JSON.stringify(u)})'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="excluirUsuario(${u.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                })
                .catch(err => console.error(err));
        }

        function renderCheck(uid, perm, val) {
            const checked = val == 1 ? 'checked' : '';
            // Se for admin, o checkbox fica marcado e desabilitado visualmente (ou não, para flexibilidade)
            // Aqui vamos deixar editável, mas o backend ignora se for admin.
            return `
                <td class="text-center">
                    <input type="checkbox" class="form-check-input perm-check" 
                           ${checked} onchange="togglePerm(${uid}, '${perm}', this.checked)">
                </td>
            `;
        }

        function togglePerm(uid, perm, status) {
            fetch('admin_users_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle&id=${uid}&perm=${perm}&val=${status ? 1 : 0}`
            }).then(res => res.json()).then(res => {
                if (!res.success) alert('Erro ao salvar: ' + res.error);
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('modalUsuario'));

        function abrirModalNovoUsuario() {
            document.getElementById('formUsuario').reset();
            document.getElementById('userId').value = '';
            document.getElementById('modalTitulo').innerText = 'Novo Usuário';
            document.getElementById('divSenha').style.display = 'block';
            modal.show();
        }

        function editarUsuario(u) {
            document.getElementById('userId').value = u.id;
            document.getElementById('nome').value = u.nome;
            document.getElementById('sobrenome').value = u.sobrenome;
            document.getElementById('email').value = u.email;
            document.getElementById('admin').checked = (u.admin == 1);
            document.getElementById('force_changepass').checked = (u.force_changepass == 1);

            document.getElementById('senha').value = ''; // Senha em branco na edição
            document.getElementById('modalTitulo').innerText = 'Editar Usuário';

            modal.show();
        }

        function salvarUsuario() {
            const form = document.getElementById('formUsuario');
            const formData = new FormData(form);

            fetch('admin_users_api.php', {
                method: 'POST',
                body: formData
            }).then(res => res.json()).then(res => {
                if (res.success) {
                    modal.hide();
                    carregarUsuarios();
                } else {
                    alert('Erro: ' + res.error);
                }
            });
        }

        function excluirUsuario(id) {
            if (confirm('Tem certeza que deseja excluir este usuário?')) {
                fetch('admin_users_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&id=${id}`
                }).then(res => res.json()).then(res => {
                    if (res.success) carregarUsuarios();
                    else alert('Erro: ' + res.error);
                });
            }
        }
    </script>
</body>

</html>