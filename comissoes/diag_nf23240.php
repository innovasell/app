<?php
// SCRIPT DE DIAGNÓSTICO TEMPORÁRIO — apagar após uso
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';
header('Content-Type: text/html; charset=utf-8');

$nf = '23240';

$stmt = $pdo->prepare("
    SELECT 
        i.id,
        i.batch_id,
        b.nome AS lote_nome,
        i.nfe,
        i.data_nf,
        i.codigo,
        i.descricao,
        i.embalagem,
        i.qtde,
        i.valor_bruto,
        i.icms,
        i.pis,
        i.cofins,
        i.venda_net,
        i.preco_net_un,
        i.preco_lista_brl,
        i.desconto_brl,
        i.desconto_pct,
        i.comissao_base_pct,
        i.pm_dias,
        i.ajuste_prazo_pct,
        i.comissao_final_pct,
        i.valor_comissao,
        i.flag_aprovacao,
        i.flag_teto,
        i.lista_nao_encontrada,
        p.cotacao_venda AS ptax_bd,
        pl.preco_net_usd AS price_list_usd
    FROM com_commission_items i
    JOIN com_commission_batches b ON b.id = i.batch_id
    LEFT JOIN fin_ptax_rates p ON p.data_cotacao = i.data_nf
    LEFT JOIN cot_price_list pl ON pl.codigo LIKE CONCAT(SUBSTR(i.codigo,1,9),'%') AND pl.embalagem = TRIM(REPLACE(i.embalagem,'(',''))
    WHERE i.nfe LIKE :nf
    ORDER BY i.id DESC
    LIMIT 10
");
$stmt->execute([':nf' => '%' . $nf . '%']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PTAX do dia da NF (23/01/2026)
$stmtPtax = $pdo->prepare("SELECT data_cotacao, cotacao_venda FROM fin_ptax_rates WHERE data_cotacao = '2026-01-23'");
$stmtPtax->execute();
$ptaxDia = $stmtPtax->fetch(PDO::FETCH_ASSOC);

// Valores esperados pelo XML
$venda_net_xml  = 14420.40 - 2595.67 - 76.86 - 354.74; // 11393.13
$preco_net_un_xml = $venda_net_xml / 20;
$ptax_xml = 5.3118;
$preco_lista_brl_xml = 135.74 * $ptax_xml;
$desconto_xml = max(0, ($preco_lista_brl_xml - $preco_net_un_xml) / $preco_lista_brl_xml);
$base_xml = $desconto_xml > 0.20 ? 0.0025 : ($desconto_xml > 0.15 ? 0.0040 : ($desconto_xml > 0.10 ? 0.0050 : ($desconto_xml > 0.05 ? 0.0070 : ($desconto_xml > 0 ? 0.0090 : 0.0100))));
$pm_xml = (28+35+42)/3; // 35d
$ajuste_xml = -(($pm_xml - 28)/7 * 0.0005);
$final_xml = max(0.0005, $base_xml + $ajuste_xml);
$comissao_xml = $venda_net_xml * $final_xml;

function fmtN($v, $dec=4) { return number_format((float)$v, $dec, ',', '.'); }
function fmtPct($v) { return number_format((float)$v * 100, 4, ',', '.') . '%'; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico NF <?= $nf ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { font-family: 'Segoe UI', sans-serif; background:#f0f4f8; } .diff { background:#fff3cd!important; font-weight:700; }</style>
</head>
<body class="p-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="text-primary">🔍 Diagnóstico — NF <?= $nf ?> (Ancla's)</h4>
    <a href="validacao.php" class="btn btn-sm btn-outline-secondary">← Voltar</a>
</div>

<!-- PTAX do dia -->
<div class="alert alert-info mb-3">
    <strong>PTAX 23/01/2026 no banco:</strong>
    <?= $ptaxDia ? 'R$ ' . fmtN($ptaxDia['cotacao_venda']) : '<span class="text-danger">NÃO ENCONTRADA</span>' ?>
    &nbsp;|&nbsp;
    <strong>PTAX do infCpl da NF (XML):</strong> R$ <?= fmtN($ptax_xml) ?>
    <?= ($ptaxDia && abs($ptaxDia['cotacao_venda'] - $ptax_xml) > 0.001) ? '<span class="badge bg-warning text-dark ms-2">PTAX DIFERENTE!</span>' : '' ?>
</div>

<!-- Valores esperados pelo XML -->
<div class="card mb-4 border-primary">
    <div class="card-header bg-primary text-white fw-bold">✅ Valores Calculados pelo XML (Referência Correta)</div>
    <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0" style="font-size:0.85rem">
            <tr><td class="fw-bold">Valor Bruto</td><td>R$ <?= fmtN(14420.40, 2) ?></td></tr>
            <tr><td class="fw-bold">ICMS + PIS + COFINS</td><td>R$ <?= fmtN(2595.67+76.86+354.74, 2) ?></td></tr>
            <tr><td class="fw-bold">Venda Net</td><td>R$ <?= fmtN($venda_net_xml, 2) ?></td></tr>
            <tr><td class="fw-bold">Preço Net Unitário (÷20 KG)</td><td>R$ <?= fmtN($preco_net_un_xml, 4) ?></td></tr>
            <tr><td class="fw-bold">Preço Lista USD</td><td>USD <?= fmtN(135.74, 2) ?></td></tr>
            <tr><td class="fw-bold">PTAX usada (infCpl)</td><td>R$ <?= fmtN($ptax_xml) ?></td></tr>
            <tr><td class="fw-bold">Preço Lista BRL</td><td>R$ <?= fmtN($preco_lista_brl_xml, 2) ?></td></tr>
            <tr><td class="fw-bold">Desconto %</td><td><?= fmtPct($desconto_xml) ?> → &gt;20% → base 0,25%</td></tr>
            <tr><td class="fw-bold">PM (XML: 3 dup 28/35/42d)</td><td><?= fmtN($pm_xml, 1) ?> dias</td></tr>
            <tr><td class="fw-bold">Ajuste PM</td><td><?= fmtPct($ajuste_xml) ?></td></tr>
            <tr><td class="fw-bold">% Final</td><td><?= fmtPct($final_xml) ?></td></tr>
            <tr class="table-success"><td class="fw-bold">Comissão Esperada</td><td><strong>R$ <?= fmtN($comissao_xml, 2) ?></strong></td></tr>
        </table>
    </div>
</div>

<!-- Dados do banco -->
<?php if (empty($rows)): ?>
    <div class="alert alert-danger">Nenhum item encontrado no banco para NF <?= $nf ?>.</div>
<?php else: ?>
    <h5>📦 Registros no Banco (com_commission_items) — <?= count($rows) ?> encontrado(s)</h5>
    <?php foreach ($rows as $i => $r): ?>
    <div class="card mb-3 border-secondary">
        <div class="card-header bg-secondary text-white">
            ID #<?= $r['id'] ?> — Lote: <?= htmlspecialchars($r['lote_nome']) ?> (batch_id=<?= $r['batch_id'] ?>)
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0" style="font-size:0.83rem">
                <tr><td class="fw-bold w-40">Venda Net (banco)</td>
                    <td class="<?= abs($r['venda_net'] - $venda_net_xml) > 0.5 ? 'diff' : '' ?>">
                        R$ <?= fmtN($r['venda_net'], 2) ?>
                        <?= abs($r['venda_net'] - $venda_net_xml) > 0.5 ? ' ⚠️ esperado: R$ '.fmtN($venda_net_xml,2) : '' ?>
                    </td></tr>
                <tr><td class="fw-bold">ICMS / PIS / COFINS (banco)</td>
                    <td>ICMS: R$ <?= fmtN($r['icms'],2) ?> | PIS: R$ <?= fmtN($r['pis'],2) ?> | COFINS: R$ <?= fmtN($r['cofins'],2) ?></td></tr>
                <tr><td class="fw-bold">Preço Net Unitário</td>
                    <td class="<?= abs($r['preco_net_un'] - $preco_net_un_xml) > 0.5 ? 'diff' : '' ?>">
                        R$ <?= fmtN($r['preco_net_un'], 4) ?>
                    </td></tr>
                <tr><td class="fw-bold">Preço Lista BRL (banco)</td>
                    <td class="<?= abs($r['preco_lista_brl'] - $preco_lista_brl_xml) > 1 ? 'diff' : '' ?>">
                        R$ <?= fmtN($r['preco_lista_brl'], 4) ?>
                        <?= abs($r['preco_lista_brl'] - $preco_lista_brl_xml) > 1 ? ' ⚠️ esperado: R$ '.fmtN($preco_lista_brl_xml,4) : '' ?>
                    </td></tr>
                <tr><td class="fw-bold">PTAX usada (banco)</td>
                    <td>R$ <?= fmtN($r['ptax_bd'] ?? 0) ?> 
                        <?= ($r['ptax_bd'] && abs($r['ptax_bd'] - $ptax_xml) > 0.001) ? '<span class="badge bg-warning text-dark">PTAX diferente do XML!</span>' : '' ?>
                    </td></tr>
                <tr><td class="fw-bold">Price List USD (banco)</td>
                    <td>USD <?= fmtN($r['price_list_usd'] ?? 0) ?></td></tr>
                <tr><td class="fw-bold">Desconto %</td>
                    <td class="<?= abs($r['desconto_pct'] - $desconto_xml) > 0.005 ? 'diff' : '' ?>">
                        <?= fmtPct($r['desconto_pct']) ?>
                        <?= abs($r['desconto_pct'] - $desconto_xml) > 0.005 ? ' ⚠️ esperado: '.fmtPct($desconto_xml) : '' ?>
                    </td></tr>
                <tr><td class="fw-bold">% Base</td><td><?= fmtPct($r['comissao_base_pct']) ?></td></tr>
                <tr><td class="fw-bold">PM (dias)</td>
                    <td class="<?= abs($r['pm_dias'] - $pm_xml) > 1 ? 'diff' : '' ?>">
                        <?= fmtN($r['pm_dias'], 1) ?> dias
                        <?= abs($r['pm_dias'] - $pm_xml) > 1 ? ' ⚠️ esperado: '.fmtN($pm_xml,1).'d (XML)' : '' ?>
                    </td></tr>
                <tr><td class="fw-bold">Ajuste Prazo</td><td><?= fmtPct($r['ajuste_prazo_pct']) ?></td></tr>
                <tr><td class="fw-bold">% Final</td>
                    <td class="<?= abs($r['comissao_final_pct'] - $final_xml) > 0.0001 ? 'diff' : '' ?>">
                        <?= fmtPct($r['comissao_final_pct']) ?>
                    </td></tr>
                <tr class="<?= abs($r['valor_comissao'] - $comissao_xml) > 0.5 ? 'table-warning' : 'table-success' ?>">
                    <td class="fw-bold">Comissão no Banco</td>
                    <td><strong>R$ <?= fmtN($r['valor_comissao'], 2) ?></strong>
                        <?= abs($r['valor_comissao'] - $comissao_xml) > 0.5 ? ' ⚠️ diferença: R$ '.fmtN($r['valor_comissao']-$comissao_xml,2) : ' ✅ OK' ?>
                    </td></tr>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="alert alert-warning mt-3">
    <strong>⚠️ Script temporário.</strong> Apague o arquivo <code>diag_nf23240.php</code> após o diagnóstico.
</div>
</body>
</html>
