<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../sistema-cotacoes/conexao.php';

// Busca os lotes recentes com total de vendas e comissões e total de itens
try {
    $stmt = $pdo->query("
        SELECT 
            b.id, 
            b.periodo, 
            b.created_at as data_upload, 
            COUNT(i.id) as total_itens,
            COALESCE(SUM(i.venda_net), 0) as total_venda_net,
            COALESCE(SUM(i.valor_comissao), 0) as total_comissoes,
            COALESCE(SUM(i.flag_aprovacao), 0) as itens_aprovacao,
            COALESCE(SUM(i.flag_teto), 0) as itens_teto
        FROM com_commission_batches b
        LEFT JOIN com_commission_items i ON b.id = i.batch_id
        GROUP BY b.id
        ORDER BY b.id DESC
        LIMIT 20
    ");
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro no banco de dados: " . $e->getMessage());
}

$pagina_ativa = 'dashboard';
require_once __DIR__ . '/header.php';
?>

    <div class="container px-4 py-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-primary"><i class="bi bi-collection"></i> Histórico de Comissões (Lotes)</h3>
            <a href="comissoes.php" class="btn btn-success"><i class="bi bi-plus-circle"></i> Novo Lote de Planilhas</a>
        </div>

        <div class="row">
            <?php if (empty($lotes)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        Nenhum lote calculado recentemente. Importe planilhas de movimentação e pedidos em <b>Cálculo de Comissões</b>.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($lotes as $lote): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <a href="comissoes.php?view_batch=<?= $lote['id'] ?>" class="card h-100 shadow-sm batch-card">
                            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between">
                                <h5 class="text-primary m-0">Lote #<?= $lote['id'] ?></h5>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($lote['data_upload'])) ?></small>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Itens Processados:</strong> <?= $lote['total_itens'] ?></p>
                                <p class="mb-1 text-success"><strong>Venda Líquida:</strong> R$ <?= number_format($lote['total_venda_net'], 2, ',', '.') ?></p>
                                <p class="mb-3 text-primary"><strong>Total Comissões:</strong> R$ <?= number_format($lote['total_comissoes'], 2, ',', '.') ?></p>
                                
                                <div>
                                    <?php if ($lote['itens_aprovacao'] > 0): ?>
                                        <span class="badge bg-danger mb-1" title="Itens com PM > 42 ou Desconto > 20%"><i class="bi bi-exclamation-triangle"></i> <?= $lote['itens_aprovacao'] ?> nec. aprovação</span>
                                    <?php endif; ?>
                                    <?php if ($lote['itens_teto'] > 0): ?>
                                        <span class="badge bg-warning text-dark mb-1" title="Itens que bateram teto de 25k"><i class="bi bi-star-fill"></i> <?= $lote['itens_teto'] ?> c/ prêmio</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer bg-light text-center text-primary fw-bold" style="border-top: 1px dashed #ccc;">
                                Abrir e Ver Detalhes <i class="bi bi-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
