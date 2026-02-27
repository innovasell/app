<?php
require_once 'db.php';

$message = '';

// Handle Add/Delete Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $cfop = trim($_POST['cfop']);
            $desc = trim($_POST['description']);
            if ($cfop) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO com_cfop_rules (cfop, description, is_active) VALUES (?, ?, 1)");
                    $stmt->execute([$cfop, $desc]);
                    $message = '<div class="alert alert-success">CFOP adicionado com sucesso!</div>';
                } catch (PDOException $e) {
                    $message = '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            $pdo->prepare("DELETE FROM com_cfop_rules WHERE id = ?")->execute([$id]);
            $message = '<div class="alert alert-success">CFOP removido!</div>';
        }
    }
}

// Fetch existing rules
$rules = $pdo->query("SELECT * FROM com_cfop_rules ORDER BY cfop ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Configurar CFOPs de Venda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="bi bi-percent"></i> Sistema de Comissões</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="upload.php">Upload NFs</a></li>
                    <li class="nav-item"><a class="nav-link" href="validacao.php">Validação</a></li>
                    <li class="nav-item"><a class="nav-link active" href="config_cfop.php">Configurar CFOPs</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Adicionar CFOP de Venda</h5>
                    </div>
                    <div class="card-body">
                        <?= $message ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="mb-3">
                                <label class="form-label">Número CFOP</label>
                                <input type="text" name="cfop" class="form-control" placeholder="Ex: 5102" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <input type="text" name="description" class="form-control"
                                    placeholder="Ex: Venda de mercadoria">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i>
                                Adicionar</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">CFOPs Configurados</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>CFOP</th>
                                    <th>Descrição</th>
                                    <th style="width: 50px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($rules) > 0): ?>
                                    <?php foreach ($rules as $rule): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <?= htmlspecialchars($rule['cfop']) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($rule['description']) ?>
                                            </td>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('Excluir este CFOP?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i
                                                            class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Nenhum CFOP configurado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>