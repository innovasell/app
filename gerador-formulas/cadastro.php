<?php
session_start();
// Proteção: Apenas administradores podem acessar esta página
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    die('Acesso negado.');
}

require_once 'config.php';
require_once 'header.php';
?>

<div class="container my-4">
    <h1 class="h2 mb-4">Cadastro de Novos Usuários</h1>

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success">Usuário cadastrado com sucesso!</div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['erro']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form action="cadastro_processar.php" method="post">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="col-md-6">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" required>
                    </div>
                    <div class="col-md-6">
                        <label for="is_admin" class="form-label">Tipo de Acesso</label>
                        <select id="is_admin" name="is_admin" class="form-select">
                            <option value="0" selected>Usuário Padrão</option>
                            <option value="1">Administrador</option>
                        </select>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Cadastrar Usuário</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$timestamp_versao = date("H:i d/m/Y", filemtime(basename(__FILE__)));
require_once 'footer.php';
?>