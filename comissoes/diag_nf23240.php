<?php
// SCRIPT DE DIAGNÓSTICO TEMPORÁRIO — apagar após uso
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';
header('Content-Type: text/html; charset=utf-8');

$nf = '23240';

// Query simples sem JOINs complexos
$stmt = $pdo->prepare("SELECT i.*, b.nome AS lote_nome FROM com_commission_items i JOIN com_commission_batches b ON b.id = i.batch_id WHERE i.nfe LIKE :nf ORDER BY i.id DESC LIMIT 10");
$stmt->execute([':nf' => '%' . $nf . '%']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PTAX do dia 23/01/2026
$ptaxDia = $pdo->query("SELECT data_cotacao, cotacao_venda FROM fin_ptax_rates WHERE data_cotacao = '2026-01-23'")->fetch(PDO::FETCH_ASSOC);

// Price list separada (codigo 004003001, embalagem "10 KG")
$stmtPL = $pdo->prepare("SELECT preco_net_usd, embalagem FROM cot_price_list WHERE codigo LIKE '004003001%' ORDER BY id DESC LIMIT 5");
$stmtPL->execute();
$plRows = $stmtPL->fetchAll(PDO::FETCH_ASSOC);

// Referência calculada pelo XML
$v_bruto   = 14420.40;
$v_icms    = 2595.67;
$v_pis     = 76.86;
$v_cofins  = 354.74;
$v_net     = $v_bruto - $v_icms - $v_pis - $v_cofins; // 11393.13
$qtde      = 20;
$pnu       = $v_net / $qtde;
$ptax_xml  = 5.3118;
$pl_usd    = 135.74;
$pl_brl    = $pl_usd * $ptax_xml;
$dsc_brl   = $pl_brl - $pnu;
$dsc_pct   = max(0, $dsc_brl / $pl_brl);
$base_pct  = $dsc_pct > 0.20 ? 0.0025 : ($dsc_pct > 0.15 ? 0.0040 : ($dsc_pct > 0.10 ? 0.0050 : ($dsc_pct > 0.05 ? 0.0070 : ($dsc_pct > 0 ? 0.0090 : 0.0100))));
$pm        = (28+35+42)/3;
$ajuste    = -(($pm-28)/7*0.0005);
$final_pct = max(0.0005, $base_pct + $ajuste);
$comissao  = $v_net * $final_pct;

function f($v, $d=2){ return number_format((float)$v,$d,',','.'); }
function fp($v){ return number_format((float)$v*100,4,',','.').'%'; }
function diff($a,$b,$tol=0.5){ return abs($a-$b)>$tol; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="UTF-8"><title>Diag NF <?=$nf?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f0f4f8;font-family:'Segoe UI',sans-serif}.hi{background:#fff3cd!important;font-weight:700}</style>
</head>
<body class="p-4">
<h4 class="text-primary mb-3">🔍 Diagnóstico — NF <?=$nf?> (Ancla's) | INNOVA BN-5 PREMIUM (10 KG) | 20 KG</h4>

<!-- PTAX -->
<div class="alert <?= $ptaxDia ? (abs($ptaxDia['cotacao_venda']-$ptax_xml)>0.001?'alert-warning':'alert-success') : 'alert-danger' ?> mb-3">
    <strong>PTAX 23/01/2026 no banco:</strong>
    <?= $ptaxDia ? 'R$ '.f($ptaxDia['cotacao_venda'],4) : '❌ NÃO ENCONTRADA' ?>
    &nbsp;|&nbsp;
    <strong>PTAX do infCpl (XML):</strong> R$ <?=f($ptax_xml,4)?>
    <?= ($ptaxDia && abs($ptaxDia['cotacao_venda']-$ptax_xml)>0.001) ? '<strong> ← PTAX DIFERENTE! Esta é a causa.</strong>' : ' ✅ Igual' ?>
</div>

<!-- Price List -->
<div class="card mb-3">
    <div class="card-header bg-info text-white fw-bold">Price List — código 004003001</div>
    <div class="card-body p-0">
    <?php if(empty($plRows)): ?>
        <div class="p-3 text-danger fw-bold">❌ Produto NÃO encontrado na Price List!</div>
    <?php else: ?>
        <table class="table table-sm table-bordered mb-0">
            <thead><tr><th>ID</th><th>Embalagem</th><th>USD</th><th>BRL (×PTAX XML)</th><th>BRL (×PTAX banco)</th></tr></thead>
            <tbody>
            <?php foreach($plRows as $pl): ?>
                <tr>
                    <td><?=$pl['preco_net_usd']?'—':''?></td>
                    <td><strong><?=htmlspecialchars($pl['embalagem'])?></strong></td>
                    <td>USD <?=f($pl['preco_net_usd'],4)?></td>
                    <td>R$ <?=f($pl['preco_net_usd']*$ptax_xml,2)?></td>
                    <td>R$ <?=$ptaxDia?f($pl['preco_net_usd']*$ptaxDia['cotacao_venda'],2):'—'?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<!-- Referência XML -->
<div class="card mb-3 border-primary">
    <div class="card-header bg-primary text-white fw-bold">✅ Referência — Cálculo pelo XML (correto)</div>
    <div class="card-body p-0">
    <table class="table table-sm table-bordered mb-0" style="font-size:.85rem">
        <tr><td>Venda Net</td><td>R$ <?=f($v_net,2)?></td></tr>
        <tr><td>Preço Net Unitário</td><td>R$ <?=f($pnu,4)?></td></tr>
        <tr><td>PL USD × PTAX 5,3118</td><td>R$ <?=f($pl_brl,4)?></td></tr>
        <tr><td>Desconto %</td><td><?=fp($dsc_pct)?> → base <?=fp($base_pct)?></td></tr>
        <tr><td>PM (28+35+42÷3)</td><td><?=f($pm,1)?> dias | ajuste <?=fp($ajuste)?></td></tr>
        <tr><td>% Final</td><td><?=fp($final_pct)?></td></tr>
        <tr class="table-success"><td><strong>Comissão Esperada</strong></td><td><strong>R$ <?=f($comissao,2)?></strong></td></tr>
    </table>
    </div>
</div>

<!-- Dados do banco -->
<?php if(empty($rows)): ?>
    <div class="alert alert-danger">❌ Nenhum item encontrado no banco para NF <?=$nf?>.</div>
<?php else: ?>
    <h5>📦 Registros no banco — <?=count($rows)?> encontrado(s)</h5>
    <?php foreach($rows as $r): ?>
    <div class="card mb-3">
        <div class="card-header bg-secondary text-white">ID #<?=$r['id']?> — Lote: <?=htmlspecialchars($r['lote_nome'])?> (batch=<?=$r['batch_id']?>)</div>
        <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0" style="font-size:.83rem">
            <tr><td>Venda Net</td>
                <td class="<?=diff($r['venda_net'],$v_net)?'hi':''?>">R$ <?=f($r['venda_net'],2)?> <?=diff($r['venda_net'],$v_net)?'⚠️ esp: R$ '.f($v_net,2):''?></td></tr>
            <tr><td>ICMS / PIS / COFINS</td>
                <td>R$ <?=f($r['icms'],2)?> / R$ <?=f($r['pis'],2)?> / R$ <?=f($r['cofins'],2)?></td></tr>
            <tr><td>Preço Net Unitário</td>
                <td class="<?=diff($r['preco_net_un'],$pnu,0.5)?'hi':''?>">R$ <?=f($r['preco_net_un'],4)?></td></tr>
            <tr><td>Preço Lista BRL</td>
                <td class="<?=diff($r['preco_lista_brl'],$pl_brl,1)?'hi':''?>">R$ <?=f($r['preco_lista_brl'],4)?> <?=diff($r['preco_lista_brl'],$pl_brl,1)?'⚠️ esp: R$ '.f($pl_brl,4):''?></td></tr>
            <tr><td>Desconto %</td>
                <td class="<?=diff($r['desconto_pct'],$dsc_pct,0.005)?'hi':''?>"><?=fp($r['desconto_pct'])?> <?=diff($r['desconto_pct'],$dsc_pct,0.005)?'⚠️ esp: '.fp($dsc_pct):''?></td></tr>
            <tr><td>% Base</td><td><?=fp($r['comissao_base_pct'])?></td></tr>
            <tr><td>PM (dias)</td>
                <td class="<?=diff($r['pm_dias'],$pm,1)?'hi':''?>"><?=f($r['pm_dias'],1)?> <?=diff($r['pm_dias'],$pm,1)?'⚠️ esp: '.f($pm,1).'d':''?></td></tr>
            <tr><td>Ajuste Prazo</td><td><?=fp($r['ajuste_prazo_pct'])?></td></tr>
            <tr><td>% Final</td>
                <td class="<?=diff($r['comissao_final_pct'],$final_pct,0.0001)?'hi':''?>"><?=fp($r['comissao_final_pct'])?></td></tr>
            <tr class="<?=diff($r['valor_comissao'],$comissao,0.5)?'table-warning':'table-success'?>">
                <td><strong>Comissão Banco</strong></td>
                <td><strong>R$ <?=f($r['valor_comissao'],2)?></strong> <?=diff($r['valor_comissao'],$comissao,0.5)?'⚠️ diff: R$ '.f($r['valor_comissao']-$comissao,2):' ✅'?></td></tr>
        </table>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="alert alert-danger mt-3">⚠️ <strong>Apague <code>diag_nf23240.php</code> após usar!</strong></div>
</body></html>
