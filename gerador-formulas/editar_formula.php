<?php
// editar_formula.php — RENDERIZA O FORMULÁRIO (GET) e envia para atualizar_formula.php (POST)
session_start();
require_once 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ----- Somente GET renderiza; POST é tratado no atualizar_formula.php -----
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Location: pesquisar_formulas.php');
    exit;
}

$formulacaoId = (int)($_GET['id'] ?? 0);
if ($formulacaoId <= 0) {
    die('ID da formulação inválido.');
}

// Cabeçalho
$stmt = $conn->prepare("SELECT id, nome_formula, codigo_formula, antigo_codigo, categoria, desenvolvida_para, solicitada_por FROM formulacoes WHERE id=?");
$stmt->bind_param("i", $formulacaoId);
$stmt->execute();
$dados = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$dados) die('Formulação não encontrada.');

// Ativos
$ativos = [];
$stmt = $conn->prepare("SELECT nome_ativo, descricao FROM ativos_destaque WHERE formulacao_id=? ORDER BY id ASC");
$stmt->bind_param("i", $formulacaoId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $ativos[] = $r;
$stmt->close();

// Partes → Fases → Ingredientes
$partes = [];
$stmt = $conn->prepare("SELECT id, nome_sub_formula, modo_preparo FROM sub_formulacoes WHERE formulacao_id=? ORDER BY id ASC");
$stmt->bind_param("i", $formulacaoId);
$stmt->execute();
$resSub = $stmt->get_result();
while ($sub = $resSub->fetch_assoc()) {
    $subId = (int)$sub['id'];

    $stmt2 = $conn->prepare("SELECT id, nome_fase FROM fases WHERE sub_formulacao_id=? ORDER BY id ASC");
    $stmt2->bind_param("i", $subId);
    $stmt2->execute();
    $resF = $stmt2->get_result();
    $fases = [];
    while ($f = $resF->fetch_assoc()) {
        $faseId = (int)$f['id'];

        $stmt3 = $conn->prepare("SELECT materia_prima, inci_name, percentual, destaque FROM ingredientes WHERE fase_id=? ORDER BY id ASC");
        $stmt3->bind_param("i", $faseId);
        $stmt3->execute();
        $resI = $stmt3->get_result();
        $ings = [];
        while ($ing = $resI->fetch_assoc()) $ings[] = $ing;
        $stmt3->close();

        $fases[] = ['id'=>$faseId,'nome'=>$f['nome_fase'],'ingredientes'=>$ings];
    }
    $stmt2->close();

    $partes[] = [
        'id' => $subId,
        'nome' => $sub['nome_sub_formula'],
        'modo_preparo' => $sub['modo_preparo'],
        'fases' => $fases
    ];
}
$stmt->close();

require_once 'header.php';
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h2 mb-0">Editar Formulação</h1>
      <p class="text-muted">Atualize os campos e salve as alterações.</p>
    </div>
    <a href="pesquisar_formulas.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>

  <!-- IMPORTANTE: envia para o processador simples -->
  <form id="formula-form" action="atualizar_formula.php" method="post">
    <input type="hidden" name="id" value="<?= (int)$dados['id'] ?>">

    <div class="card shadow-sm mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Informações Gerais</h5>
      </div>
      <div class="card-body p-4">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label" for="formulaName">Nome da Formulação Principal</label>
            <input type="text" class="form-control" id="formulaName" name="formulaName" value="<?= h($dados['nome_formula']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="antigo_codigo">Antigo Código (opcional)</label>
            <input type="text" class="form-control" id="antigo_codigo" name="antigo_codigo" value="<?= h($dados['antigo_codigo']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="desenvolvida_para">Desenvolvida Para</label>
            <input type="text" class="form-control" id="desenvolvida_para" name="desenvolvida_para" value="<?= h($dados['desenvolvida_para']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="solicitada_por">Solicitada Por</label>
            <input type="text" class="form-control" id="solicitada_por" name="solicitada_por" value="<?= h($dados['solicitada_por']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="category">Categoria</label>
            <select class="form-select" id="category" name="category" required>
              <?php
                $cats = ['HC'=>'Hair Care','SKC'=>'Skin Care','BC'=>'Body Care','SUC'=>'Sun Care','MK'=>'Make-Up','OC'=>'Oral Care','PC'=>'Pet Care'];
                foreach ($cats as $k=>$v) {
                    $sel = ($dados['categoria']===$k)?'selected':'';
                    echo "<option value=\"".h($k)."\" $sel>".h($v)."</option>";
                }
              ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Ativos em Destaque -->
    <div class="card shadow-sm mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-stars"></i> Ativos em Destaque</h5>
        <button type="button" id="add-ativo" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-circle"></i> Adicionar Ativo
        </button>
      </div>
      <div class="card-body p-4">
        <div id="ativos-container">
          <?php if (empty($ativos)): ?>
            <div class="row g-3 align-items-end mb-2 ativo-row">
              <div class="col-md-5">
                <label class="form-label">Nome do Ativo</label>
                <input type="text" class="form-control" name="ativos_nome[]" value="">
              </div>
              <div class="col-md-6">
                <label class="form-label">Descrição</label>
                <input type="text" class="form-control" name="ativos_desc[]" value="">
              </div>
              <div class="col-md-1 d-grid">
                <button type="button" class="btn btn-outline-danger btn-remove-ativo">Remover</button>
              </div>
            </div>
          <?php else: foreach ($ativos as $a): ?>
            <div class="row g-3 align-items-end mb-2 ativo-row">
              <div class="col-md-5">
                <label class="form-label">Nome do Ativo</label>
                <input type="text" class="form-control" name="ativos_nome[]" value="<?= h($a['nome_ativo']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Descrição</label>
                <input type="text" class="form-control" name="ativos_desc[]" value="<?= h($a['descricao']) ?>">
              </div>
              <div class="col-md-1 d-grid">
                <button type="button" class="btn btn-outline-danger btn-remove-ativo">Remover</button>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <h3 class="h4 mt-5">Partes da Formulação</h3>
    <div id="sub-formulacoes-container">
      <?php
      $pIdx = 0;
      foreach ($partes as $parte):
        $fases = $parte['fases'] ?? [];
      ?>
      <div class="card shadow-sm mb-3 part-card" data-p-idx="<?= $pIdx ?>">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-diagram-3"></i>
            <strong>Parte da Formulação</strong>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-part">Remover Parte</button>
        </div>
        <div class="card-body p-3">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Nome da Parte</label>
              <input type="text" class="form-control" name="sub_formulacoes[<?= $pIdx ?>][nome]" value="<?= h($parte['nome']) ?>" placeholder="Ex.: Base, Ativos, etc.">
            </div>
            <div class="col-12">
              <label class="form-label">Modo de Preparo (opcional)</label>
              <textarea rows="3" class="form-control" name="sub_formulacoes[<?= $pIdx ?>][modo_preparo]" placeholder="Descreva o modo de preparo desta parte..."><?= h($parte['modo_preparo']) ?></textarea>
            </div>
          </div>

          <div class="phases-container" data-next-index="<?= count($fases) ?>">
            <?php
            $fIdx = 0;
            if (empty($fases)) $fases = [['nome'=>'','ingredientes'=>[]]];
            foreach ($fases as $fase):
                $ings = $fase['ingredientes'] ?? [];
                $total = 0.0;
                foreach ($ings as $ing) $total += (float)$ing['percentual'];
            ?>
            <div class="card border-0 mb-3 shadow-sm phase-card" data-f-idx="<?= $fIdx ?>">
              <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-diagram-3"></i>
                  <strong>Fase</strong>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="small text-muted total-percent">Total: <?= number_format($total,2,',','.') ?>%</span>
                  <button type="button" class="btn btn-sm btn-outline-danger btn-remove-phase">Remover Fase</button>
                </div>
              </div>
              <div class="card-body p-3">
                <div class="row g-3 mb-2">
                  <div class="col-md-6">
                    <label class="form-label">Nome da Fase</label>
                    <input type="text" class="form-control" name="sub_formulacoes[<?= $pIdx ?>][fases][<?= $fIdx ?>][nome]" value="<?= h($fase['nome']) ?>" placeholder="Ex.: Fase A, Fase B...">
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-2">
                    <thead>
                      <tr>
                        <th>Matéria-prima</th>
                        <th>INCI Name</th>
                        <th class="text-end">%</th>
                        <th class="text-center" style="width: 90px;">Ações</th>
                      </tr>
                    </thead>
                    <tbody data-phase="<?= $fIdx ?>">
                    <?php
                    if (empty($ings)) $ings = [['materia_prima'=>'','inci_name'=>'','percentual'=>'']];
                    foreach ($ings as $ing):
                    ?>
                      <tr>
                        <td><input type="text" class="form-control" name="sub_formulacoes[<?= $pIdx ?>][fases][<?= $fIdx ?>][ingredientes][materia_prima][]" value="<?= h($ing['materia_prima'] ?? '') ?>" placeholder="Matéria-prima"></td>
                        <td><input type="text" class="form-control" name="sub_formulacoes[<?= $pIdx ?>][fases][<?= $fIdx ?>][ingredientes][inci_name][]" value="<?= h($ing['inci_name'] ?? '') ?>" placeholder="INCI Name"></td>
                        <td>
                          <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control text-end" name="sub_formulacoes[<?= $pIdx ?>][fases][<?= $fIdx ?>][ingredientes][percentual][]" value="<?= h($ing['percentual'] ?? '') ?>" placeholder="0.00" inputmode="decimal">
                            <span class="input-group-text">%</span>
                          </div>
                        </td>
                        <td class="text-center">
                          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ingrediente">Remover</button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div class="d-flex justify-content-end">
                  <button type="button" class="btn btn-outline-primary btn-add-ingrediente" data-p-idx="<?= $pIdx ?>" data-f-idx="<?= $fIdx ?>">Adicionar Ingrediente</button>
                </div>
              </div>
            </div>
            <?php $fIdx++; endforeach; ?>
          </div>

          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-outline-success btn-add-phase" data-p-idx="<?= $pIdx ?>">Adicionar Fase</button>
          </div>
        </div>
      </div>
      <?php $pIdx++; endforeach; ?>
    </div>

    <button type="button" id="add-sub-formulacao" class="btn btn-success w-100 mt-2">
      <i class="bi bi-plus-lg"></i> Adicionar Parte da Formulação
    </button>

    <div class="d-grid mt-4">
      <button type="button" id="btn-submit-modal" class="btn btn-primary btn-lg">
        <i class="bi bi-check-circle-fill"></i> Salvar alterações
      </button>
    </div>
  </form>
</div>

<!-- Modal simples só para confirmar o envio -->
<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Confirmar</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
    </div>
    <div class="modal-body">Deseja salvar as alterações?</div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button type="button" id="btn-salvar-sair" class="btn btn-primary">Salvar e sair</button>
    </div>
  </div></div>
</div>

<?php
$timestamp_versao = date("H:i d/m/Y", filemtime(basename(__FILE__)));
require_once 'footer.php';
