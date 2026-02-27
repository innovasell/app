<?php
session_start();
$pagina_ativa = 'gerenciar_usuarios';

ob_start();
require_once 'header.php';
$headerContent = ob_get_clean();

// Proteção extra
if (!check_permission('gestao')) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Acesso negado.</div></div>";
    exit();
}

require_once 'conexao.php';

// Busca dados (apenas ativos)
try {
    $sql = "SELECT * FROM cot_representante WHERE ativo = 1 ORDER BY nome ASC";
    $stmt = $pdo->query($sql);
    if (!$stmt) {
        throw new Exception("Erro ao consultar usuários. Verifique se o script 'update_db_gestao.php' foi executado.");
    }
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback: tentar buscar sem filtro 'ativo' se a coluna não existir (apenas para não quebrar tudo)
    // Mas o ideal é avisar erro.
    $usuarios = [];
    $erroDb = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - H Hansen</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts: Montserrat -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f4f6f9;
        }
    </style>
</head>

<body>
    <?= $headerContent ?>

    <div class="container py-5">

        <?php if (isset($erroDb)): ?>
            <div class="alert alert-danger shadow-sm">
                <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Erro de Banco de Dados</h4>
                <p>Ocorreu um erro ao carregar os usuários: <strong><?= htmlspecialchars($erroDb) ?></strong></p>
                <hr>
                <p class="mb-0">Por favor, execute o script <a href="update_db_gestao.php" target="_blank"
                        class="fw-bold">update_db_gestao.php</a> para atualizar a estrutura do banco de dados.</p>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-primary"><i class="fas fa-users-cog me-2"></i>Gerenciar Usuários</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario"
                onclick="limparModal()">
                <i class="fas fa-plus me-2"></i>Novo Usuário
            </button>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Grupo</th>
                                <th class="text-center">Admin?</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($u['nome'] . ' ' . $u['sobrenome']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($u['email']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($u['telefone'] ?? '-') ?>
                                    </td>
                                    <td>
                                        <?php
                                        $bg = 'secondary';
                                        if ($u['grupo'] == 'admin')
                                            $bg = 'danger';
                                        if ($u['grupo'] == 'gestor')
                                            $bg = 'primary';
                                        if ($u['grupo'] == 'geral')
                                            $bg = 'info';
                                        ?>
                                        <span class="badge bg-<?= $bg ?>">
                                            <?= strtoupper($u['grupo']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?= $u['admin'] == 1 ? '<i class="fas fa-check-circle text-success"></i>' : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1"
                                            onclick="editarUsuario(<?= htmlspecialchars(json_encode($u)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($u['id'] != 1): // Evitar excluir admin principal (exemplo) ?>
                                            <button class="btn btn-sm btn-outline-danger"
                                                onclick="excluirUsuario(<?= $u['id'] ?>)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Usuário -->
    <div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formUsuario">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Novo Usuário</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="userId">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome *</label>
                                <input type="text" class="form-control" name="nome" id="nome" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sobrenome *</label>
                                <input type="text" class="form-control" name="sobrenome" id="sobrenome" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" id="email" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" name="telefone" id="telefone"
                                    placeholder="(11) 99999-9999">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Senha</label>
                                <input type="password" class="form-control" name="senha" id="senha"
                                    placeholder="Deixe em branco para manter a atual">
                                <div class="form-text text-muted" id="senhaHelp">Obrigatório para novos usuários.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Grupo de Acesso *</label>
                                <select class="form-select" name="grupo" id="grupo" required>
                                    <option value="geral">Geral</option>
                                    <option value="gestor">Gestor</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>

                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="admin" id="adminCheck">
                                    <label class="form-check-label" for="adminCheck">Flag Admin (Legado)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function limparModal() {
            document.getElementById('formUsuario').reset();
            document.getElementById('userId').value = '';
            document.getElementById('modalTitle').innerText = 'Novo Usuário';
            document.getElementById('senha').required = true;
            document.getElementById('senhaHelp').innerText = 'Obrigatório para novos usuários.';
        }

        function editarUsuario(user) {
            document.getElementById('userId').value = user.id;
            document.getElementById('nome').value = user.nome;
            document.getElementById('sobrenome').value = user.sobrenome;
            document.getElementById('email').value = user.email;
            document.getElementById('telefone').value = user.telefone || '';
            document.getElementById('grupo').value = user.grupo || 'geral';
            document.getElementById('adminCheck').checked = (user.admin == 1);

            document.getElementById('senha').value = '';
            document.getElementById('senha').required = false;
            document.getElementById('senhaHelp').innerText = 'Preencha apenas se quiser alterar a senha.';

            document.getElementById('modalTitle').innerText = 'Editar Usuário';

            var myModal = new bootstrap.Modal(document.getElementById('modalUsuario'));
            myModal.show();
        }

        document.getElementById('formUsuario').addEventListener('submit', function (e) {
            e.preventDefault();
            console.log("Formulário submetido via JS.");
            
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            btn.disabled = true;

            const formData = new FormData(this);

            fetch('salvar_usuario_admin.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log("Response status:", response.status);
                    return response.text().then(text => { // Get text first to debug if not JSON
                        console.log("Response text:", text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error("Resposta do servidor não é JSON válido: " + text);
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire('Sucesso!', 'Usuário salvo com sucesso.', 'success').then(() => location.reload());
                    } else {
                        btn.innerHTML = originalText; // Restore button
                        btn.disabled = false;
                        Swal.fire('Erro!', data.message || 'Erro ao salvar.', 'error');
                    }
                })
                .catch(err => {
                    console.error("Erro fetch:", err);
                    btn.innerHTML = originalText; // Restore button
         btn.disabled = false;
                    Swal.fire('Erro!', 'Erro na requisição: ' + err.message, 'error');
                });
        });

        function excluirUsuario(id) {
            Swal.fire({
                title: 'Tem certeza?',
                text: "Essa ação não pode ser desfeita!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, excluir!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('excluir_usuario.php?id=' + id)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Deletado!', 'Usuário removido.', 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Erro!', data.message, 'error');
                            }
                        });
                }
            })
        }
    </script>
</body>

</html>