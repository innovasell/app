<?php 
session_start(); // <- inicia a sessão antes de checagens no header.php
require_once 'config.php';
require_once 'header.php'; 
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-0">Nova Formulação</h1>
            <p class="text-muted">Preencha os campos abaixo para gerar um novo documento.</p>
        </div>
        <a href="pesquisar_formulas.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <form id="formula-form" action="processar_formula.php" method="post">
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Informações Gerais</h5></div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <input type="hidden" name="acao" id="acao" value="">
                        <label for="formulaName" class="form-label">Nome da Formulação Principal</label>
                        <input type="text" class="form-control" id="formulaName" name="formulaName" required>
                    </div>

                    <div class="col-md-4">
                        <label for="antigo_codigo" class="form-label">Antigo Código (opcional)</label>
                        <input type="text" class="form-control" id="antigo_codigo" name="antigo_codigo">
                    </div>

                    <div class="col-md-6">
                        <label for="desenvolvida_para" class="form-label">Desenvolvida Para</label>
                        <input type="text" class="form-control" id="desenvolvida_para" name="desenvolvida_para">
                    </div>

                    <div class="col-md-6">
                        <label for="solicitada_por" class="form-label">Solicitada Por</label>
                        <input type="text" class="form-control" id="solicitada_por" name="solicitada_por">
                    </div>

                    <div class="col-md-6">
                        <label for="category" class="form-label">Categoria</label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="HC" selected>Hair Care</option>
                            <option value="SKC">Skin Care</option>
                            <option value="BC">Body Care</option>
                            <option value="SUC">Sun Care</option>
                            <option value="MK">Make-Up</option>
                            <option value="OC">Oral Care</option>
                            <option value="PC">Pet Care</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-stars"></i> Ativos em Destaque</h5>
                <button type="button" id="add-ativo" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Adicionar Ativo
                </button>
            </div>
            <div class="card-body p-4">
                <div id="ativos-container"></div>
            </div>
        </div>

        <h3 class="h4 mt-5">Partes da Formulação</h3>
        <div id="sub-formulacoes-container"></div>
        <button type="button" id="add-sub-formulacao" class="btn btn-success w-100 mt-2">
            <i class="bi bi-plus-lg"></i> Adicionar Parte da Formulação
        </button>

        <div class="d-grid mt-4">
            <button type="button" id="btn-submit-modal" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle-fill"></i> Gerar Formulação e PDF
            </button>
        </div>
    </form>
</div>

<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar Ação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        Como você deseja proceder com esta formulação?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="btn-salvar-sair" class="btn btn-secondary">SALVAR E SAIR</button>
        <button type="button" id="btn-gerar-pdf" class="btn btn-primary">GERAR PDF</button>
      </div>
  </div></div>
</div>



<?php
$timestamp_versao = date("H:i d/m/Y", filemtime(basename(__FILE__)));
require_once 'footer.php';
?>
