/* ============================================================
   VARIÁVEIS GLOBAIS
   ============================================================ */
let dadosColeta = {
    colaborador: '',
    nfs: [], // Changed from string 'nf' to array 'nfs'
    motorista: { nome: '', cpf: '', placa: '' }
};

// Baldes para as fotos
let fotosMercadoriaPorNF = {}; // Object to map NF -> Array of files
let listaFotosColeta = [];

/* ============================================================
   NAVEGAÇÃO
   ============================================================ */
function mostrarTela(idTela) {
    const telas = ['tela-inicio', 'tela-colaborador', 'tela-nf', 'tela-acoes', 'tela-loading'];
    telas.forEach(t => {
        const el = document.getElementById(t);
        if (el) el.classList.add('d-none');
    });

    const telaAtiva = document.getElementById(idTela);
    if (telaAtiva) {
        telaAtiva.classList.remove('d-none');

        // Se for a tela de ações, gera os botões dinâmicos
        if (idTela === 'tela-acoes') {
            gerarBotoesMercadoria();
            document.getElementById('display-colaborador').innerText = dadosColeta.colaborador;

            // Mostra resuminho das NFs
            document.getElementById('display-nfs-lista').innerHTML =
                dadosColeta.nfs.map(n => `<span class="badge bg-primary me-1">NF ${n}</span>`).join('');
        }
    }
}

/* ============================================================
   ETAPAS INICIAIS
   ============================================================ */
function selecionarColaborador(nome) {
    dadosColeta.colaborador = nome;
    mostrarTela('tela-nf');
    atualizarListaNFs(); // Limpa/Inicia visualmente
}

function adicionarNFNaLista() {
    const input = document.getElementById('input-nf');
    const valor = input.value.trim();

    if (!valor) return;

    if (dadosColeta.nfs.includes(valor)) {
        alert("Essa NF já foi adicionada!");
        return;
    }

    dadosColeta.nfs.push(valor);
    fotosMercadoriaPorNF[valor] = []; // Inicializa array de fotos

    input.value = '';
    input.focus();
    atualizarListaNFs();
}

function removerNF(nf) {
    dadosColeta.nfs = dadosColeta.nfs.filter(n => n !== nf);
    delete fotosMercadoriaPorNF[nf];
    atualizarListaNFs();
}

function atualizarListaNFs() {
    const lista = document.getElementById('lista-nfs');
    lista.innerHTML = '';

    dadosColeta.nfs.forEach(nf => {
        const li = document.createElement('li');
        li.className = "list-group-item d-flex justify-content-between align-items-center";
        li.innerHTML = `
            <strong>NF ${nf}</strong>
            <button class="btn btn-sm btn-danger" onclick="removerNF('${nf}')">X</button>
        `;
        lista.appendChild(li);
    });
}

function confirmarNFs() {
    if (dadosColeta.nfs.length === 0) {
        alert("Adicione pelo menos uma Nota Fiscal!");
        return;
    }
    mostrarTela('tela-acoes');
}

/* ============================================================
   FUNÇÕES VISUAIS
   ============================================================ */
function gerarBotoesMercadoria() {
    const container = document.getElementById('container-mercadoria');
    container.innerHTML = ''; // Limpa

    dadosColeta.nfs.forEach(nf => {
        const qtd = fotosMercadoriaPorNF[nf].length;
        const btnClass = qtd > 0 ? 'btn-check-ok' : 'btn-outline-dark';
        const txtContador = qtd > 0
            ? `(${qtd} foto${qtd > 1 ? 's' : ''} pronta${qtd > 1 ? 's' : ''})`
            : `(Nenhuma foto)`;

        const html = `
            <div class="mb-3">
                <input type="file" id="input-mercadoria-${nf}" 
                       accept="image/*" capture="environment" multiple class="d-none" 
                       onchange="adicionarFotosMercadoria('${nf}')">
                
                <label for="input-mercadoria-${nf}" id="btn-mercadoria-${nf}" 
                       class="btn ${btnClass} btn-lg w-100 py-3 text-start ps-4">
                    📦 FOTOS DA MERCADORIA <strong>NF ${nf}</strong>
                    <br><small id="contador-mercadoria-${nf}" class="fw-light" style="font-size: 0.9rem;">${txtContador}</small>
                </label>
            </div>
        `;
        container.innerHTML += html;
    });
}

function marcarConcluido(idBotao) {
    const botao = document.getElementById(idBotao);
    if (botao) {
        botao.classList.remove('btn-outline-dark');
        botao.classList.add('btn-check-ok');
    }
}

/* ============================================================
   GERENCIAMENTO DE FOTOS
   ============================================================ */

// Foto de Mercadoria (Específica por NF)
function adicionarFotosMercadoria(nf) {
    const inputId = 'input-mercadoria-' + nf;
    const btnId = 'btn-mercadoria-' + nf;
    const contadorId = 'contador-mercadoria-' + nf;
    const input = document.getElementById(inputId);

    if (!input) { alert("Erro ID " + inputId); return; }

    const arquivos = Array.from(input.files);
    if (arquivos.length === 0) return;

    // Adiciona ao array específico da NF
    arquivos.forEach(arq => fotosMercadoriaPorNF[nf].push(arq));

    // Atualiza Visual
    const qtd = fotosMercadoriaPorNF[nf].length;
    const contador = document.getElementById(contadorId);
    if (contador) {
        contador.innerText = `(${qtd} foto${qtd > 1 ? 's' : ''} pronta${qtd > 1 ? 's' : ''})`;
        contador.style.color = "#198754";
        contador.style.fontWeight = "bold";
    }

    marcarConcluido(btnId);
    input.value = '';
}

// Foto da Coleta (Geral)
function adicionarFotos(tipo) {
    // Mantido original para 'coleta', já que 'mercadoria' agora é separado
    if (tipo !== 'coleta') return;

    const inputId = 'input-coleta';
    const btnId = 'btn-coleta';
    const contadorId = 'contador-coleta';
    const input = document.getElementById(inputId);

    if (!input) return;

    const arquivos = Array.from(input.files);
    if (arquivos.length === 0) return;

    arquivos.forEach(arq => listaFotosColeta.push(arq));

    const qtd = listaFotosColeta.length;
    const contador = document.getElementById(contadorId);
    if (contador) {
        contador.innerText = `(${qtd} foto${qtd > 1 ? 's' : ''} pronta${qtd > 1 ? 's' : ''})`;
        contador.style.color = "#198754";
        contador.style.fontWeight = "bold";
    }

    marcarConcluido(btnId);
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
   ENVIO EM LOTE
   ============================================================ */
async function enviarDados() {
    // 1. Validação Geral
    if (dadosColeta.nfs.length === 0) { alert("Nenhuma NF adicionada!"); return; }

    // Verifica se TODAS as NFs tem fotos
    for (let nf of dadosColeta.nfs) {
        if (fotosMercadoriaPorNF[nf].length === 0) {
            alert(`Faltou foto da Mercadoria para a NF ${nf}!`);
            return;
        }
    }

    if (listaFotosColeta.length === 0) { alert("Faltou foto da Coleta (Geral)!"); return; }
    if (!dadosColeta.motorista.nome) { alert("Faltou dados do Motorista!"); return; }

    mostrarTela('tela-loading');
    const loadingTitulo = document.getElementById('loading-titulo');
    const loadingDetalhe = document.getElementById('loading-detalhe');
    const barra = document.getElementById('barra-progresso');

    const totalNfs = dadosColeta.nfs.length;
    let nfsEnviadas = 0;

    // Loop de envio sequencial
    for (let i = 0; i < totalNfs; i++) {
        const nfAtual = dadosColeta.nfs[i];

        // Atualiza UI
        loadingTitulo.innerText = `Enviando NF ${nfAtual} (${i + 1}/${totalNfs})...`;
        loadingDetalhe.innerText = "Aguarde o upload das fotos...";

        // Zera barra para este item (ou poderiamos fazer global, mas individual dá melhor feedback)
        barra.style.width = '0%';
        barra.innerText = '0%';

        try {
            await enviarUmaNF(nfAtual, (progressoPercent) => {
                barra.style.width = progressoPercent + '%';
                barra.innerText = progressoPercent + '%';
            });
            nfsEnviadas++;
        } catch (erro) {
            console.error(erro);
            alert(`Erro ao enviar NF ${nfAtual}: ${erro.message}`);
            // Pergunta se quer continuar ou parar? 
            if (!confirm("Houve um erro. Deseja tentar enviar as próximas NFs?")) {
                mostrarTela('tela-acoes');
                return;
            }
        }
    }

    // Se chegou aqui, finalizou
    loadingTitulo.innerText = "Concluído!";
    barra.style.width = '100%';
    barra.classList.add('bg-success');

    setTimeout(() => {
        // Exibe o modal de sucesso
        const modalEl = document.getElementById('modalSucesso');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        } else {
            // Fallback se não encontrar o modal
            alert("✅ Todas as NFs foram processadas!");
            location.reload();
        }
    }, 500);
}

// Função auxiliar que retorna uma Promise para enviar UMA NF
function enviarUmaNF(nf, onProgress) {
    return new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('colaborador', dadosColeta.colaborador);
        formData.append('nf', nf);
        formData.append('motorista_nome', dadosColeta.motorista.nome);
        formData.append('motorista_cpf', dadosColeta.motorista.cpf);
        formData.append('motorista_placa', dadosColeta.motorista.placa);

        // Anexa Mercadoria daquela NF
        const fotosMerc = fotosMercadoriaPorNF[nf];
        fotosMerc.forEach((foto, i) => {
            formData.append(`fotos_mercadoria[${i}]`, foto, `mercadoria_${nf}_${i}.jpg`);
        });

        // Anexa Coleta (Geral) - duplicada para cada envio, conforme solicitado ("repetindo o envio das imagens")
        listaFotosColeta.forEach((foto, i) => {
            formData.append(`fotos_coleta[${i}]`, foto, `coleta_${i}.jpg`);
        });

        const xhr = new XMLHttpRequest();

        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable && onProgress) {
                const percentual = Math.round((e.loaded / e.total) * 100);
                onProgress(percentual);
            }
        };

        xhr.onload = function () {
            if (xhr.status === 200) {
                console.log(`NF ${nf} enviada. Resp:`, xhr.responseText);
                resolve(xhr.responseText);
            } else {
                reject(new Error(xhr.statusText));
            }
        };

        xhr.onerror = function () {
            reject(new Error("Erro de conexão"));
        };

        xhr.open('POST', 'backend.php', true);
        xhr.send(formData);
    });
}