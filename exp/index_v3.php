<?php 
// Versão Hardcoded para visualização
$versao_visual = "3.0 (Renomeação de Arquivos)";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Conferência de Coleta</title>
    
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="style_v3.css">
</head>
<body class="d-flex flex-column min-vh-100">

<div class="container text-center mt-4 flex-grow-1">
    <img src="logo.png" alt="Logo Empresa" class="img-fluid mb-4" style="max-height: 80px;">

    <div id="tela-inicio">
        <button class="btn btn-primary btn-lg btn-circulo" onclick="mostrarTela('tela-colaborador')">
            INICIAR CONFERÊNCIA<br>DE COLETA
        </button>
    </div>

    <div id="tela-colaborador" class="d-none">
        <h3>Quem está conferindo?</h3>
        <div class="d-grid gap-3 mt-4 col-md-6 mx-auto">
            <button class="btn btn-outline-primary btn-lg" onclick="selecionarColaborador('Diogo')">Diogo</button>
            <button class="btn btn-outline-primary btn-lg" onclick="selecionarColaborador('Leandro')">Leandro</button>
            <button class="btn btn-outline-primary btn-lg" onclick="selecionarColaborador('Célio')">Célio</button>
            <button class="btn btn-outline-primary btn-lg" onclick="selecionarColaborador('Felipe')">Felipe</button>
        </div>
        <button class="btn btn-secondary mt-4" onclick="mostrarTela('tela-inicio')">Voltar</button>
    </div>

    <div id="tela-nf" class="d-none">
        <h3>Informe a Nota Fiscal</h3>
        <div class="col-md-6 mx-auto mt-4">
            <input type="number" id="input-nf" class="form-control form-control-lg text-center" placeholder="Ex: 12345">
            <button class="btn btn-success btn-lg mt-3 w-100" onclick="confirmarNF()">Avançar</button>
        </div>
        <button class="btn btn-secondary mt-4" onclick="mostrarTela('tela-colaborador')">Voltar</button>
    </div>

    <div id="tela-acoes" class="d-none">
        <h4 class="mb-3">NF: <span id="display-nf" class="text-primary fw-bold"></span></h4>
        <p>Colaborador: <span id="display-colaborador"></span></p>
        
        <div class="row g-3 mt-2">
            <div class="col-12">
                <input type="file" id="input-mercadoria" name="fotos_mercadoria[]" accept="image/*" capture="environment" multiple class="d-none" onchange="adicionarFotos('mercadoria')">
                <label for="input-mercadoria" id="btn-mercadoria" class="btn btn-outline-dark btn-lg w-100 py-4">
                    📸 FOTOS DA MERCADORIA
                    <br><small id="contador-mercadoria" class="fw-light" style="font-size: 0.9rem;">(Nenhuma foto)</small>
                </label>
            </div>

            <div class="col-12">
                <input type="file" id="input-coleta" name="fotos_coleta[]" accept="image/*" capture="environment" multiple class="d-none" onchange="adicionarFotos('coleta')">
                <label for="input-coleta" id="btn-coleta" class="btn btn-outline-dark btn-lg w-100 py-4">
                    🚚 FOTOS DA COLETA
                    <br><small id="contador-coleta" class="fw-light" style="font-size: 0.9rem;">(Nenhuma foto)</small>
                </label>
            </div>

            <div class="col-12">
                <button id="btn-motorista" class="btn btn-outline-dark btn-lg w-100 py-4" data-bs-toggle="modal" data-bs-target="#modalMotorista">
                    📝 DADOS DO MOTORISTA
                </button>
            </div>
        </div>

        <hr>
        <button id="btn-finalizar" class="btn btn-success btn-lg w-100 py-3 mb-5" onclick="enviarDados()">
            ✅ FINALIZAR E ENVIAR
        </button>
    </div>
    
    <div id="tela-loading" class="d-none mt-5">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
        <h4 class="mt-3">Enviando dados...</h4>
        <p>Aguarde, enviando fotos para o servidor.</p>
    </div>
</div>

<footer class="bg-light text-center py-3 mt-auto border-top">
    <small class="text-muted">
        Versão Forçada: <strong><?php echo $versao_visual; ?></strong>
    </small>
</footer>

<div class="modal fade" id="modalMotorista" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Dados do Motorista</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-start">
        <div class="mb-3">
            <label class="form-label">Nome Completo</label>
            <input type="text" id="mot-nome" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">CPF</label>
            <input type="tel" id="mot-cpf" class="form-control" placeholder="000.000.000-00">
        </div>
        <div class="mb-3">
            <label class="form-label">Placa do Veículo</label>
            <input type="text" id="mot-placa" class="form-control" placeholder="ABC-1234">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal" onclick="verificarMotorista()">SALVAR DADOS</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="script_v3.js"></script>

</body>
</html>