<?php
// CORREÇÃO: A ligação à base de dados DEVE vir primeiro.
session_start();
require_once 'config.php'; 
require_once 'header.php'; 

// Agora que $conn existe, este código pode ser executado sem erro.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('<div class="container mt-5"><div class="alert alert-danger">ID da formulação não fornecido.</div></div>');
}
$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM formulacoes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$formula = $stmt->get_result()->fetch_assoc();

if (!$formula) {
    die('<div class="container mt-5"><div class="alert alert-danger">Formulação não encontrada.</div></div>');
}

// Lógica para buscar as novas sub-formulações
$stmt_sub = $conn->prepare("SELECT * FROM sub_formulacoes WHERE formulacao_id = ? ORDER BY id ASC");
$stmt_sub->bind_param("i", $id);
$stmt_sub->execute();
$sub_formulacoes_result = $stmt_sub->get_result();
$sub_formulacoes = [];
while ($sub = $sub_formulacoes_result->fetch_assoc()) {
    $stmt_fases = $conn->prepare("SELECT * FROM fases WHERE sub_formulacao_id = ? ORDER BY id ASC");
    $stmt_fases->bind_param("i", $sub['id']);
    $stmt_fases->execute();
    $fases_result = $stmt_fases->get_result();
    $fases = [];
    while ($fase = $fases_result->fetch_assoc()) {
        $stmt_ing = $conn->prepare("SELECT * FROM ingredientes WHERE fase_id = ? ORDER BY id ASC");
        $stmt_ing->bind_param("i", $fase['id']);
        $stmt_ing->execute();
        $fase['ingredientes'] = $stmt_ing->get_result()->fetch_all(MYSQLI_ASSOC);
        $fases[] = $fase;
    }
    $sub['fases'] = $fases;
    $sub_formulacoes[] = $sub;
}

// Lógica para buscar os ativos
$stmt_ativos = $conn->prepare("SELECT * FROM ativos_destaque WHERE formulacao_id = ?");
$stmt_ativos->bind_param("i", $id);
$stmt_ativos->execute();
$ativos = $stmt_ativos->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center p-3">
            <div>
                <h1 class="h3 mb-1"><?php echo htmlspecialchars($formula['nome_formula']); ?></h1>
                <span class="badge text-bg-secondary fw-normal"><?php echo htmlspecialchars($formula['codigo_formula']); ?></span>
                <div class="mt-3">
                    <small class="text-muted d-block">Desenvolvido para: <strong><?php echo htmlspecialchars($formula['desenvolvida_para'] ?? 'N/A'); ?></strong></small>
                    <small class="text-muted d-block">Solicitado por: <strong><?php echo htmlspecialchars($formula['solicitada_por'] ?? 'N/A'); ?></strong></small>
                </div>
            </div>
            <div>
                <a href="pesquisar_formulas.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
                <a href="javascript:window.print()" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer"></i> Imprimir</a>
            </div>
        </div>

        <div class="card-body p-4">
            <h2 class="h5 text-primary border-bottom pb-2 mb-3 mt-2"><i class="bi bi-stars"></i> Ativos em Destaque</h2>
            <?php if (!empty($ativos)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($ativos as $ativo): ?>
                        <li class="list-group-item">
                            <strong><?php echo htmlspecialchars($ativo['nome_ativo']); ?>:</strong>
                            <?php echo htmlspecialchars($ativo['descricao']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted">Nenhum ativo em destaque cadastrado.</p>
            <?php endif; ?>

            <?php foreach ($sub_formulacoes as $sub): ?>
                <div class="mt-5">
                    <h2 class="h4 bg-light p-3 rounded"><?php echo htmlspecialchars($sub['nome_sub_formula']); ?></h2>
                    
                    <h3 class="h5 text-primary border-bottom pb-2 mb-3 mt-4"><i class="bi bi-beaker"></i> Fases e Ingredientes</h3>
                    <?php foreach ($sub['fases'] as $fase): ?>
                        <h4 class="h6 fw-bold mt-4"><?php echo htmlspecialchars($fase['nome_fase']); ?></h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 40%;">Matéria-prima</th>
                                        <th style="width: 40%;">INCI Name</th>
                                        <th class="text-end" style="width: 20%;">Percentual (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fase['ingredientes'] as $ingrediente): ?>
                                        <tr class="<?php echo ($ingrediente['destaque'] == 1) ? 'table-primary fw-bold' : ''; ?>">
                                            <td><?php echo htmlspecialchars($ingrediente['materia_prima']); ?></td>
                                            <td><?php echo htmlspecialchars($ingrediente['inci_name']); ?></td>
                                            <td class="text-end">
                                                <?php 
                                                if (is_numeric($ingrediente['percentual'])) {
                                                    echo number_format($ingrediente['percentual'], 2, ',', '.');
                                                } else {
                                                    echo htmlspecialchars($ingrediente['percentual']);
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>

                    <h3 class="h5 text-primary border-bottom pb-2 mb-3 mt-4"><i class="bi bi-journal-text"></i> Modo de Preparo (<?php echo htmlspecialchars($sub['nome_sub_formula']); ?>)</h3>
                    <div class="p-3 bg-light rounded text-break">
                        <?php echo nl2br(htmlspecialchars($sub['modo_preparo'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
$timestamp_versao = date("H:i d/m/Y", filemtime(basename(__FILE__)));
require_once 'footer.php';
?>