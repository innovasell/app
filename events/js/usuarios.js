// usuarios.js - Gerenciamento de usuários

// Criar novo usuário
document.getElementById('newUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);

    try {
        const response = await fetch('api/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', ...data })
        });

        const result = await response.json();

        if (result.success) {
            alert('Usuário criado com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (error) {
        alert('Erro ao criar usuário: ' + error.message);
    }
});

// Editar usuário
function editUser(id, name, email, role) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUserName').value = name;
    document.getElementById('editUserEmail').value = email;
    document.getElementById('editUserRole').value = role;
    document.getElementById('editUserPassword').value = '';

    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

document.getElementById('editUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);

    try {
        const response = await fetch('api/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update', ...data })
        });

        const result = await response.json();

        if (result.success) {
            alert('Usuário atualizado com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (error) {
        alert('Erro ao atualizar usuário: ' + error.message);
    }
});

// Excluir usuário
async function deleteUser(id, name) {
    if (!confirm(`Tem certeza que deseja excluir o usuário "${name}"?`)) {
        return;
    }

    try {
        const response = await fetch('api/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id })
        });

        const result = await response.json();

        if (result.success) {
            alert('Usuário excluído com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (error) {
        alert('Erro ao excluir usuário: ' + error.message);
    }
}
