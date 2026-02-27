<?php
session_start();
date_default_timezone_set('America/Sao_Paulo'); 

$pagina_ativa = 'previsao'; 

require_once 'header.php'; 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Previsão de Datas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .table th,
    .table td {
      vertical-align: middle;
      text-align: center;
    }
    .btn-group .btn {
      min-width: 80px;
    }
  </style>
</head>
<body>
  <main class="container py-4">
    <div class="d-flex justify-content-between align-items-center pb-4">
      <h2 class="mb-0">Calculadora de Previsão de Datas</h2>
    </div>

    <div class="text-center mb-4">
      <h5>Selecione o intervalo de dias:</h5>
      <div id="botoesIntervalo" class="btn-group flex-wrap" role="group" aria-label="Intervalos de dias">
        <button type="button" class="btn btn-outline-primary" data-interval="5">5 dias</button>
        <button type="button" class="btn btn-outline-primary active" data-interval="7">7 dias</button>
        <button type="button" class="btn btn-outline-primary" data-interval="10">10 dias</button>
        <button type="button" class="btn btn-outline-primary" data-interval="15">15 dias</button>
        <button type="button" class="btn btn-outline-primary" data-interval="20">20 dias</button>
        <button type="button" class="btn btn-outline-primary" data-interval="25">25 dias</button>
        <button type="button" class="btn btn-outline-primary" data-interval="30">30 dias</button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-hover table-sm" id="tabelaDatas">
        <thead class="table-light">
          <tr>
            <th class="table-dark">DIAS</th>
            <th>DATA</th>
            <th class="table-dark">DIAS</th>
            <th>DATA</th>
            <th class="table-dark">DIAS</th>
            <th>DATA</th>
            <th class="table-dark">DIAS</th>
            <th>DATA</th>
          </tr>
        </thead>
        <tbody>
          </tbody>
      </table>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const botoesIntervalo = document.querySelectorAll('#botoesIntervalo .btn');
      const tbody = document.querySelector('#tabelaDatas tbody');
      
      // Função reutilizável para gerar a tabela
      function gerarTabelaPrevisao(intervalo) {
        // Limpa a tabela atual
        tbody.innerHTML = '';
        
        const hoje = new Date();
        const colunasPorLinha = 4; // 4 pares de (DIAS | DATA)
        let totalDiasForecast = 180; // Aumentado para um forecast mais longo
        let linhaHtml = '';
        let contadorColunas = 0;

        for (let dias = intervalo; dias <= totalDiasForecast; dias += intervalo) {
          const dataFutura = new Date(hoje);
          dataFutura.setDate(hoje.getDate() + dias);
          
          const dia = String(dataFutura.getDate()).padStart(2, '0');
          const mes = String(dataFutura.getMonth() + 1).padStart(2, '0');
          const ano = dataFutura.getFullYear();
          const dataFormatada = `${dia}/${mes}/${ano}`;

          linhaHtml += `<td class="table-dark">${dias}</td><td>${dataFormatada}</td>`;
          contadorColunas++;

          // A cada 4 pares, cria uma nova linha na tabela
          if (contadorColunas % colunasPorLinha === 0) {
            tbody.innerHTML += `<tr>${linhaHtml}</tr>`;
            linhaHtml = ''; // Reseta para a próxima linha
          }
        }
        
        // Adiciona a linha restante se não for um múltiplo exato de 4
        if (linhaHtml !== '') {
            tbody.innerHTML += `<tr>${linhaHtml}</tr>`;
        }
      }

      // Adiciona o evento de clique para cada botão
      botoesIntervalo.forEach(function(botao) {
        botao.addEventListener('click', function() {
          // Remove a classe 'active' de todos os botões
          botoesIntervalo.forEach(b => b.classList.remove('active'));
          // Adiciona a classe 'active' apenas no botão clicado
          this.classList.add('active');
          
          const intervaloSelecionado = parseInt(this.getAttribute('data-interval'));
          gerarTabelaPrevisao(intervaloSelecionado);
        });
      });
      
      // Gera a tabela inicial com o intervalo padrão (7 dias)
      gerarTabelaPrevisao(7);
    });
  </script>
</body>
</html>