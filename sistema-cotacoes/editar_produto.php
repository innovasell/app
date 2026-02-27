<?php
session_start();
$pagina_ativa = 'incluir_produto';
require_once 'header.php';
require_once 'conexao.php';

$codigo = $_GET['codigo'] ?? '';

if (empty($codigo)) {
    header("Location: gerenciar_produtos.php?erro=" . urlencode("Código do produto não informado."));
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM cot_estoque WHERE codigo = :codigo");
    $stmt->execute([':codigo' => $codigo]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        header("Location: gerenciar_produtos.php?erro=" . urlencode("Produto não encontrado."));
        exit();
    }
} catch (PDOException $e) {
    die("Erro ao buscar produto: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Editar Produto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h2 class="mb-4">Editar Produto</h2>

        <form action="atualizar_produto.php" method="POST" class="border p-4 rounded bg-light">
            <!-- Hidden field for original code if needed logic, but here code is PK and we might not want to change it easily -->

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="codigo" class="form-label">Código do Produto</label>
                    <input type="text" class="form-control" id="codigo" name="codigo"
                        value="<?= htmlspecialchars($produto['codigo']) ?>" readonly style="background-color: #e9ecef;">
                    <small class="text-muted">O código não pode ser alterado.</small>
                </div>
                <div class="col-md-8">
                    <label for="produto" class="form-label">Nome do Produto <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="produto" name="produto"
                        value="<?= htmlspecialchars($produto['produto']) ?>" required maxlength="255">
                </div>
                <div class="col-md-3">
                    <label for="unidade" class="form-label">Unidade <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="unidade" name="unidade"
                        value="<?= htmlspecialchars($produto['unidade']) ?>" required placeholder="Ex: KG, L, UN">
                </div>
                <div class="col-md-3">
                    <label for="ncm" class="form-label">NCM <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="ncm" name="ncm"
                        value="<?= htmlspecialchars($produto['ncm']) ?>" required maxlength="20">
                </div>
                <div class="col-md-3">
                    <label for="ipi" class="form-label">IPI (%) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="ipi" name="ipi"
                        value="<?= htmlspecialchars($produto['ipi']) ?>" required step="0.01" placeholder="Ex: 5.00">
                </div>
                <div class="col-md-3">
                    <label for="origem" class="form-label">Origem <span class="text-danger">*</span></label>
                    <select class="form-select" id="origem" name="origem" required>
                        <option value="0" <?= $produto['origem'] == 0 ? 'selected' : '' ?>>0 - Nacional</option>
                        <option value="1" <?= $produto['origem'] == 1 ? 'selected' : '' ?>>1 - Importado</option>
                        <option value="6" <?= $produto['origem'] == 6 ? 'selected' : '' ?>>6 - Importado (CAMEX)</option>
                    </select>
                </div>
                <div class="col-12 text-end mt-4">
                    <a href="gerenciar_produtos.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>