<?php
session_start();

// Redireciona caso o usuário não esteja logado
if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Aviso de Compliance</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" 
    rel="stylesheet"
  >
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card {
      max-width: 700px;
      margin: 5% auto;
      border-left: 6px solid #721c24;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="card-title text-danger fw-bold mb-3">Aviso de Compliance</h4>
      <p class="card-text" style="text-align: justify;">
        Este portal é de uso exclusivo dos representantes autorizados da <strong>Innovasell</strong>. 
        Todas as informações aqui contidas são de propriedade intelectual e comercial da empresa, 
        sendo protegidas por normas de confidencialidade e legislação vigente.
      </p>
      <p class="card-text" style="text-align: justify;">
        O <strong>compartilhamento não autorizado</strong> de cotações, dados de clientes, tabelas de preço ou qualquer outro conteúdo disponível neste sistema com pessoas externas à Innovasell é expressamente proibido.
      </p>
      <p class="card-text" style="text-align: justify;">
        O descumprimento desta norma poderá acarretar <strong>consequências legais, sanções contratuais</strong> e <strong>medidas disciplinares</strong>, conforme previsto em cláusulas de sigilo e compliance interno.
      </p>

      <div class="text-end mt-4">
        <a href="filtrar.php" class="btn btn-success">Estou ciente e desejo continuar</a>
      </div>
    </div>
  </div>
</div>

<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

</body>
</html>
