<?php
// Versão Hardcoded para visualização
$versao_visual = "4.0 (Progress Bar)";
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
                <button class="btn btn-outline-primary btn-lg"
                    onclick="selecionarColaborador('Leandro')">Leandro</button>
                <button class="btn btn-outline-primary btn-lg" onclick="selecionarColaborador('Célio')">Célio</button>
                <button class="btn btn-outline-primary btn-lg" onclick="selecionarColaborador('Felipe')">Felipe</button>
            </div>
            <button class="btn btn-secondary mt-4" onclick="mostrarTela('tela-inicio')">Voltar</button>
        </div>

        <div id="tela-nf" class="d-none">
            <h3>Informe as Notas Fiscais</h3>
            <div class="col-md-6 mx-auto mt-4">
                <div class="input-group mb-3">
                    <input type="number" id="input-nf" class="form-control form-control-lg text-center"
                        placeholder="Ex: 12345">
                    <button class="btn btn-primary" type="button" onclick="adicionarNFNaLista()">+ Adicionar</button>
                </div>

                <ul id="lista-nfs" class="list-group mb-4 text-start">
                    <!-- Lista de NFs vai aqui -->
                </ul>

                <button class="btn btn-success btn-lg w-100" onclick="confirmarNFs()">Avançar</button>
            </div>
            <button class="btn btn-secondary mt-4" onclick="mostrarTela('tela-colaborador')">Voltar</button>
        </div>

        <div id="tela-acoes" class="d-none">
            <h4 class="mb-3">Conferindo NFs:</h4>
            <div id="display-nfs-lista" class="mb-2"></div>
            <p>Colaborador: <span id="display-colaborador" class="fw-bold"></span></p>

            <div class="row g-3 mt-2">

                <!-- Container dinâmico para fotos de Mercadoria -->
                <div id="container-mercadoria" class="col-12">
                    <!-- Botões gerados via JS -->
                </div>

                <div class="col-12">
                    <hr>
                    <h5 class="text-muted mb-3">Dados Comuns (Geral)</h5>
                </div>

                <div class="col-12">
                    <input type="file" id="input-coleta" name="fotos_coleta[]" accept="image/*" capture="environment"
                        multiple class="d-none" onchange="adicionarFotos('coleta')">
                    <label for="input-coleta" id="btn-coleta" class="btn btn-outline-dark btn-lg w-100 py-4">
                        🚚 FOTOS DA COLETA (Visão Geral)
                        <br><small id="contador-coleta" class="fw-light" style="font-size: 0.9rem;">(Nenhuma
                            foto)</small>
                    </label>
                </div>

                <div class="col-12">
                    <button id="btn-motorista" class="btn btn-outline-dark btn-lg w-100 py-4" data-bs-toggle="modal"
                        data-bs-target="#modalMotorista">
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
            <h4 class="mb-3" id="loading-titulo">Enviando dados...</h4>
            <p id="loading-detalhe" class="text-primary"></p>

            <div class="progress mb-2" style="height: 30px;">
                <div id="barra-progresso" class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                    role="progressbar" style="width: 0%;">
                    0%
                </div>
            </div>

            <p class="text-muted small">Não feche esta janela até completar 100%.</p>
            <div class="spinner-border text-secondary mt-3" role="status" style="width: 2rem; height: 2rem;"></div>
        </div>
    </div>

    <footer class="bg-light text-center py-3 mt-auto border-top">
        <small class="text-muted">
            Versão: <strong>Multi-NF 1.0</strong>
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
                        <label class="form-label">CPF / RG</label>
                        <input type="text" id="mot-cpf" class="form-control" placeholder="CPF ou RG">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Placa do Veículo</label>
                        <input type="text" id="mot-placa" class="form-control" placeholder="ABC-1234">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal"
                        onclick="verificarMotorista()">SALVAR DADOS</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Sucesso -->
    <div class="modal fade" id="modalSucesso" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <div class="mb-3">
                        <span style="font-size: 4rem;">✅</span>
                    </div>
                    <h3 class="mb-3">Sucesso!</h3>
                    <p class="lead">Todas as coletas foram enviadas.</p>
                    <button type="button" class="btn btn-success btn-lg w-100 mt-3" onclick="location.reload()">
                        INICIAR NOVA COLETA
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="script_v3.js"></script> <!-- Ensure we are using the correct script file, user mentioned script.js in prompts but file list had script_v3.js too. Sticking to inclusion in original file unless directed otherwise. Original file included script_v3.js? Let me double check view_file output. It included script_v3.js. Wait, previous view_file of index.php showed script_v3.js at the bottom. But the view_file of script.js showed content. I should probably update script_v3.js if that is what index.php is using.
Let me check the file list again. list_dir showed script.js and script_v3.js.
index.php loads script_v3.js.
I should check script_v3.js content to be sure. I previously viewed script.js. I should probably assume index.php is the entry point and it uses script_v3.js.
Actually, the user prompt asked to edit "script.js" generally, but since index.php links script_v3.js, I must edit script_v3.js.
Let me quickly view script_v3.js to be safe before I edit it.
-->
</body>

</html>