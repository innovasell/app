<?php
require_once 'conexao.php';

// Debug
// var_dump($_POST); exit;

$num_orcamento = $_POST['num_orcamento'] ?? null;
$id = $_POST['id_linha'] ?? ''; // Pode vir vazio para novos itens

if (!$num_orcamento) {
  die("Número do orçamento obrigatório.");
}

// Coleta dados do formulário
$codigo = $_POST['codigo'] ?? '';
$produto = $_POST['produto'] ?? '';
$unidade = $_POST['unidade'] ?? '';
$origem = $_POST['origem'] ?? '';
$ncm = $_POST['ncm'] ?? '';
$volume = $_POST['volume'] ?? '';
$embalagem = $_POST['embalagem'] ?? '';
$ipi = $_POST['ipi'] ?? '';
$icms = $_POST['icms'] ?? '';
$disponibilidade = $_POST['disponibilidade'] ?? '';
$preco_net = $_POST['preco_net'] ?? '';
$preco_full = $_POST['preco_full'] ?? '';

// Dados complementares para INSERT
$razao_social = $_POST['razao_social'] ?? '';
$uf = $_POST['uf'] ?? '';
$data = $_POST['data'] ?? '';
$cotado_por = $_POST['cotado_por'] ?? '';

try {
  if ($id) {
    // === UPDATE (Editar Item Existente) ===
    $stmt = $pdo->prepare("
      UPDATE cot_cotacoes_importadas
      SET 
        `COD DO PRODUTO` = :codigo,
        `PRODUTO` = :produto,
        `UNIDADE` = :unidade,
        `ORIGEM` = :origem,
        `NCM` = :ncm,
        `VOLUME` = :volume,
        `EMBALAGEM_KG` = :embalagem,
        `IPI %` = :ipi,
        `ICMS` = :icms,
        `DISPONIBILIDADE` = :disponibilidade,
        `PREÇO NET USD/KG` = :preco_net,
        `PREÇO FULL USD/KG` = :preco_full
      WHERE id = :id AND NUM_ORCAMENTO = :num_orcamento
    ");

    $params = [
      ':codigo' => $codigo,
      ':produto' => $produto,
      ':unidade' => $unidade,
      ':origem' => $origem,
      ':ncm' => $ncm,
      ':volume' => $volume,
      ':embalagem' => $embalagem,
      ':ipi' => $ipi,
      ':icms' => $icms,
      ':disponibilidade' => $disponibilidade,
      ':preco_net' => $preco_net,
      ':preco_full' => $preco_full,
      ':id' => $id,
      ':num_orcamento' => $num_orcamento // Segurança extra
    ];
    $stmt->execute($params);

  } else {
    // === INSERT (Adicionar Novo Item) ===
    // O sistema usa uma "flat table", então precisamos repetir os dados do cabeçalho
    $stmt = $pdo->prepare("
      INSERT INTO cot_cotacoes_importadas (
        NUM_ORCAMENTO, `RAZÃO SOCIAL`, UF, `DATA`, COTADO_POR,
        `COD DO PRODUTO`, PRODUTO, UNIDADE, ORIGEM, NCM,
        VOLUME, EMBALAGEM_KG, `IPI %`, ICMS, DISPONIBILIDADE,
        `PREÇO NET USD/KG`, `PREÇO FULL USD/KG`
      ) VALUES (
        :num_orcamento, :razao_social, :uf, :data, :cotado_por,
        :codigo, :produto, :unidade, :origem, :ncm,
        :volume, :embalagem, :ipi, :icms, :disponibilidade,
        :preco_net, :preco_full
      )
    ");

    $params = [
      ':num_orcamento' => $num_orcamento,
      ':razao_social' => $razao_social,
      ':uf' => $uf,
      ':data' => $data,
      ':cotado_por' => $cotado_por,
      ':codigo' => $codigo,
      ':produto' => $produto,
      ':unidade' => $unidade,
      ':origem' => $origem,
      ':ncm' => $ncm,
      ':volume' => $volume,
      ':embalagem' => $embalagem,
      ':ipi' => $ipi,
      ':icms' => $icms,
      ':disponibilidade' => $disponibilidade,
      ':preco_net' => $preco_net,
      ':preco_full' => $preco_full
    ];
    $stmt->execute($params);
  }

  // Redireciona
  header("Location: atualizar_orcamento.php?num=" . urlencode($num_orcamento) . "&sucesso=1");
  exit;

} catch (PDOException $e) {
  die("Erro ao salvar item: " . $e->getMessage());
}
