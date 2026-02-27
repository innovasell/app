/* ============================================================
   VARIÁVEIS GLOBAIS
   ============================================================ */
let dadosColeta = {
    colaborador: '',
    nf: '',
    motorista: { nome: '', cpf: '', placa: '' }
};

// Baldes para as fotos
let listaFotosMercadoria = [];
let listaFotosColeta = [];

/* ============================================================
   NAVEGAÇÃO
   ============================================================ */
function mostrarTela(idTela) {
    const telas = ['tela-inicio', 'tela-colaborador', 'tela-nf', 'tela-acoes', 'tela-loading'];
    telas.forEach(t => {
        const el = document.getElementById(t);
        if(el) el.classList.add('d-none');
    });
    
    const telaAtiva = document.getElementById(idTela);
    if(telaAtiva) telaAtiva.classList.remove('d-none');
}

/* ============================================================
   ETAPAS INICIAIS
   ============================================================ */
function selecionarColaborador(nome) {
    dadosColeta.colaborador = nome;
    mostrarTela('tela-nf');
}

function confirmarNF() {
    const nfInput = document.getElementById('input-nf');
    if (!nfInput || !nfInput.value) {
        alert("Por favor, digite a Nota Fiscal.");
        return;
    }
    dadosColeta.nf = nfInput.value;
    
    document.getElementById('display-nf').innerText = dadosColeta.nf;
    document.getElementById('display-colaborador').innerText = dadosColeta.colaborador;
    
    mostrarTela('tela-acoes');
}

/* ============================================================
   FUNÇÕES VISUAIS
   ============================================================ */
function marcarConcluido(idBotao) {
    const botao = document.getElementById(idBotao);
    if(botao) {
        botao.classList.remove('btn-outline-dark');
        botao.classList.add('btn-check-ok');
    }
}

/* ============================================================
   GERENCIAMENTO DE FOTOS
   ============================================================ */
function adicionarFotos(tipo) {
    const inputId = 'input-' + tipo;
    const btnId = 'btn-' + tipo;
    const contadorId = 'contador-' + tipo;
    const input = document.getElementById(inputId);
    
    if (!input) { alert("Erro interno: ID " + inputId + " não encontrado."); return; }

    const arquivos = Array.from(input.files);
    if (arquivos.length === 0) return;

    // Define qual lista usar
    let listaAlvo = (tipo === 'mercadoria') ? listaFotosMercadoria : listaFotosColeta;

    // Adiciona ao array global
    arquivos.forEach(arq => listaAlvo.push(arq));

    // Atualiza visual
    const qtd = listaAlvo.length;
    const contador = document.getElementById(contadorId);
    if(contador) {
        contador.innerText = `(${qtd} foto${qtd > 1 ? 's' : ''} pronta${qtd > 1 ? 's' : ''})`;
        contador.style.color = "#198754"; // Verde
        contador.style.fontWeight = "bold";
    }

    marcarConcluido(btnId);
    
    // Limpa o input para permitir adicionar mais fotos
    input.value = '';
}

/* ============================================================
   MOTORISTA
   ============================================================ */
function verificarMotorista() {
    const nome = document.getElementById('mot-nome').value;
    const cpf = document.getElementById('mot-cpf').value;
    const placa = document.getElementById('mot-placa').value;

    if (nome && cpf && placa) {
        dadosColeta.motorista = { nome, cpf, placa };
        marcarConcluido('btn-motorista');
    } else {
        alert("Preencha todos os campos!");
    }
}

/* ============================================================
   ENVIO
   ============================================================ */
async function enviarDados() {
    // Validação
    if (listaFotosMercadoria.length === 0) { alert("Faltou foto da Mercadoria!"); return; }
    if (listaFotosColeta.length === 0) { alert("Faltou foto da Coleta!"); return; }
    if (!dadosColeta.motorista.nome) { alert("Faltou dados do Motorista!"); return; }

    mostrarTela('tela-loading');

    const formData = new FormData();
    formData.append('colaborador', dadosColeta.colaborador);
    formData.append('nf', dadosColeta.nf);
    formData.append('motorista_nome', dadosColeta.motorista.nome);
    formData.append('motorista_cpf', dadosColeta.motorista.cpf);
    formData.append('motorista_placa', dadosColeta.motorista.placa);
    
    // Anexa Mercadoria
    listaFotosMercadoria.forEach((foto, i) => {
        formData.append(`fotos_mercadoria[${i}]`, foto, `mercadoria_${i}.jpg`);
    });

    // Anexa Coleta
    listaFotosColeta.forEach((foto, i) => {
        formData.append(`fotos_coleta[${i}]`, foto, `coleta_${i}.jpg`);
    });

    try {
        const response = await fetch('backend.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.text();
        console.log("Servidor:", result);

        if (response.ok) {
            alert("✅ Sucesso! Coleta registrada.");
            location.reload();
        } else {
            alert("Erro no servidor: " + result);
            mostrarTela('tela-acoes');
        }
    } catch (error) {
        console.error(error);
        alert("Erro de conexão.");
        mostrarTela('tela-acoes');
    }
}