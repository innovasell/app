<?php
// processar_formula.php — cria a formulação e, opcionalmente, gera o PDF
session_start();
require_once __DIR__ . '/vendor/autoload.php'; // passo 1: dependências no topo
require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($v)
{
  return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
function toFloat($v)
{
  $v = str_replace(',', '.', (string) $v);
  return (float) $v;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Método não permitido');
}

// --------- INPUT ---------
$acao = $_POST['acao'] ?? 'salvar_sair';
$formulaName = $_POST['formulaName'] ?? '';
$antigo_codigo = $_POST['antigo_codigo'] ?? null;
$desenvolvida_para = $_POST['desenvolvida_para'] ?? '';
$solicitada_por = $_POST['solicitada_por'] ?? '';
$categoryPrefix = $_POST['category'] ?? 'GEN';
$ativosNomes = $_POST['ativos_nome'] ?? [];
$ativosDescs = $_POST['ativos_desc'] ?? [];
$subForm = $_POST['sub_formulacoes'] ?? []; // PARTE → FASE(S) → INGREDIENTES

if (trim($formulaName) === '') {
  die('Nome da formulação é obrigatório.');
}

// --------- TRANSAÇÃO ---------
$conn->begin_transaction();
try {
  // 2) GERAÇÃO DO CÓDIGO ÚNICO COM FOR UPDATE (como você descreveu)
  $mes = date('m');
  $ano = date('Y');
  $contadorId = "{$categoryPrefix}_{$mes}{$ano}";
  $novoNumero = 1;

  // tenta travar a linha do contador
  $stmt = $conn->prepare("SELECT ultimo_valor FROM contadores WHERE id = ? FOR UPDATE");
  $stmt->bind_param("s", $contadorId);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    $novoNumero = (int) $row['ultimo_valor'] + 1;
    $stmtU = $conn->prepare("UPDATE contadores SET ultimo_valor = ? WHERE id = ?");
    $stmtU->bind_param("is", $novoNumero, $contadorId);
    $stmtU->execute();
    $stmtU->close();
  } else {
    $stmtI = $conn->prepare("INSERT INTO contadores (id, ultimo_valor) VALUES (?, ?)");
    $stmtI->bind_param("si", $contadorId, $novoNumero);
    $stmtI->execute();
    $stmtI->close();
  }
  $stmt->close();

  $numeroFormatado = str_pad($novoNumero, 3, '0', STR_PAD_LEFT);
  $codigoFormula = "{$categoryPrefix}/{$mes}{$ano}{$numeroFormatado}";

  // 3) SALVANDO NO BANCO (tudo com prepared)
  // 3.1 formulacoes
  $stmt = $conn->prepare("
    INSERT INTO formulacoes
      (nome_formula, codigo_formula, antigo_codigo, categoria, desenvolvida_para, solicitada_por, data_criacao)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ");
  $stmt->bind_param(
    "ssssss",
    $formulaName,
    $codigoFormula,
    $antigo_codigo,
    $categoryPrefix,
    $desenvolvida_para,
    $solicitada_por
  );
  $stmt->execute();
  $formulacaoId = $conn->insert_id;
  $stmt->close();

  // 3.2 ativos_destaque
  if (!empty($ativosNomes)) {
    $stmtAt = $conn->prepare("INSERT INTO ativos_destaque (formulacao_id, nome_ativo, descricao) VALUES (?, ?, ?)");
    foreach ($ativosNomes as $i => $nome) {
      $nome = trim((string) $nome);
      if ($nome === '')
        continue;
      $desc = (string) ($ativosDescs[$i] ?? '');
      $stmtAt->bind_param("iss", $formulacaoId, $nome, $desc);
      $stmtAt->execute();
    }
    $stmtAt->close();
  }

  // 3.3 sub_formulacoes, fases, ingredientes
  if (!empty($subForm) && is_array($subForm)) {
    $stmtSub = $conn->prepare("INSERT INTO sub_formulacoes (formulacao_id, nome_sub_formula, modo_preparo) VALUES (?, ?, ?)");
    $stmtFase = $conn->prepare("INSERT INTO fases (sub_formulacao_id, nome_fase) VALUES (?, ?)");
    $stmtIng = $conn->prepare("INSERT INTO ingredientes (fase_id, materia_prima, inci_name, percentual, destaque) VALUES (?, ?, ?, ?, ?)");

    foreach ($subForm as $p) {
      $subNome = trim((string) ($p['nome'] ?? ''));
      if ($subNome === '')
        continue;
      $modoPrep = (string) ($p['modo_preparo'] ?? '');

      $stmtSub->bind_param("iss", $formulacaoId, $subNome, $modoPrep);
      $stmtSub->execute();
      $subId = (int) $conn->insert_id;

      if (!empty($p['fases']) && is_array($p['fases'])) {
        foreach ($p['fases'] as $f) {
          $faseNome = trim((string) ($f['nome'] ?? ''));
          if ($faseNome === '')
            continue;

          $stmtFase->bind_param("is", $subId, $faseNome);
          $stmtFase->execute();
          $faseId = (int) $conn->insert_id;

          if (!empty($f['ingredientes']) && is_array($f['ingredientes'])) {
            $mat = (array) ($f['ingredientes']['materia_prima'] ?? []);
            $inc = (array) ($f['ingredientes']['inci_name'] ?? []);
            $pct = (array) ($f['ingredientes']['percentual'] ?? []);
            $dst = (array) ($f['ingredientes']['destaque'] ?? []);

            $n = max(count($mat), count($inc), count($pct), count($dst));
            for ($i = 0; $i < $n; $i++) {
              $mp = trim((string) ($mat[$i] ?? ''));
              if ($mp === '')
                continue;
              $inm = (string) ($inc[$i] ?? '');
              $per = toFloat($pct[$i] ?? 0);
              $des = (!empty($dst[$i]) && (string) $dst[$i] === '1') ? 1 : 0;

              $stmtIng->bind_param("issdi", $faseId, $mp, $inm, $per, $des);
              $stmtIng->execute();
            }
          }
        }
      }
    }
    $stmtIng->close();
    $stmtFase->close();
    $stmtSub->close();
  }

  $conn->commit();

} catch (mysqli_sql_exception $e) {
  $conn->rollback();
  http_response_code(500);
  die("Erro ao salvar a formulação: " . $e->getMessage());
}

// 4) VERIFICAÇÕES DE AMBIENTE PARA PDF
$templateFile = __DIR__ . '/templatepdf.html';
$tempDir = __DIR__ . '/temp';
if ($acao === 'gerar_pdf') {
  if (!file_exists($templateFile)) {
    die("Fórmula criada, porém o arquivo 'templatepdf.html' não foi encontrado.");
  }
  if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0775, true);
  }
  if (!is_writable($tempDir)) {
    die("Fórmula criada, porém a pasta 'temp/' não é gravável. Ajuste permissões (ex.: 775).");
  }
}

// 6) SALVAR E SAIR
if ($acao !== 'gerar_pdf') {
  header("Location: pesquisar_formulas.php?ok=1");
  exit;
}

// 5) GERAÇÃO DO PDF (apenas quando solicitado)
$html = file_get_contents($templateFile);

// imagem de categoria (caminho ABSOLUTO — mPDF lê arquivo local)
$imgsDirAbs = __DIR__ . '/assets/src/image/';

$categoryImages = [
  'HC' => 'template_hair_v1.png',
  'SKC' => 'template_skin_v1.png',
  'BC' => 'template_body_v1.png',
  'SUC' => 'template_sun_v1.png',
  'MK' => 'template_make_v1.png',
  'OC' => 'template_oral_v1.png',
  'PC' => 'template_pet_v1.png',
];

$imgName = $categoryImages[$categoryPrefix] ?? 'default.png';

// 1) Categoria: substitui a string completa do src
$html = str_replace('assets/src/image/CATEGORY_IMAGE_PLACEHOLDER', $imgsDirAbs . $imgName, $html);

// 2) Cabeçalho (e quaisquer outras imagens fixas do template)
$html = str_replace('assets/src/image/template_header_v1.png', $imgsDirAbs . 'template_header_v1.png', $html);

// 3) (Opcional) transformar QUALQUER <img src="assets/src/image/..."> em absoluto:
$html = preg_replace_callback(
  '/src="assets\/src\/image\/([^"]+)"/',
  fn($m) => 'src="' . $imgsDirAbs . $m[1] . '"',
  $html
);

// monta conteúdo dinâmico
$formulaContent = "<h1>" . h($formulaName) . "</h1>";
$formulaContent .= "<h2>Código: " . h($codigoFormula) . "</h2>";

if (!empty($desenvolvida_para) || !empty($solicitada_por)) {
  $formulaContent .= "<div style='margin:15px 0;padding:10px;border-left:3px solid #0d6efd;background:#f8f9fa'>";
  if (!empty($desenvolvida_para))
    $formulaContent .= "<p style='margin:0'><strong>Desenvolvido para:</strong> " . h($desenvolvida_para) . "</p>";
  if (!empty($solicitada_por))
    $formulaContent .= "<p style='margin:0'><strong>Solicitado por:</strong> " . h($solicitada_por) . "</p>";
  $formulaContent .= "</div>";
}

// ativos
$temAtivos = false;
foreach ($ativosNomes as $i => $nome) {
  if (trim((string) $nome) !== '') {
    $temAtivos = true;
    break;
  }
}
if ($temAtivos) {
  $formulaContent .= "<h3>Ativos em Destaque</h3><ul>";
  foreach ($ativosNomes as $i => $nome) {
    $nome = trim((string) $nome);
    if ($nome === '')
      continue;
    $desc = (string) ($ativosDescs[$i] ?? '');
    $formulaContent .= "<li><strong>" . h($nome) . ":</strong> " . h($desc) . "</li>";
  }
  $formulaContent .= "</ul>";
}

// partes → fases → ingredientes
if (!empty($subForm)) {
  foreach ($subForm as $p) {
    if (empty($p['nome']))
      continue;

    $formulaContent .= "<h2 style='background:#e9ecef;padding:10px;border-radius:5px;margin-top:20px;'>" . h($p['nome']) . "</h2>";

    if (!empty($p['fases'])) {
      $formulaContent .= "<h3>Fases e Ingredientes</h3>";
      foreach ($p['fases'] as $f) {
        if (empty($f['nome']))
          continue;
        $formulaContent .= "<h4>" . h($f['nome']) . "</h4>";
        $formulaContent .= "<table border='1' cellpadding='5' cellspacing='0' width='100%'>
          <thead><tr><th>Matéria-prima</th><th>INCI Name</th><th>Percentual</th></tr></thead><tbody>";
        $mat = (array) ($f['ingredientes']['materia_prima'] ?? []);
        $inc = (array) ($f['ingredientes']['inci_name'] ?? []);
        $pct = (array) ($f['ingredientes']['percentual'] ?? []);
        $n = max(count($mat), count($inc), count($pct));
        for ($i = 0; $i < $n; $i++) {
          $mp = trim((string) ($mat[$i] ?? ''));
          if ($mp === '')
            continue;
          $inm = (string) ($inc[$i] ?? '');
          $per = (string) ($pct[$i] ?? '');
          $formulaContent .= "<tr><td>" . h($mp) . "</td><td>" . h($inm) . "</td><td>" . h($per) . "%</td></tr>";
        }
        $formulaContent .= "</tbody></table>";
      }
    }
    $modo = (string) ($p['modo_preparo'] ?? '');
    if ($modo !== '') {
      $formulaContent .= "<h3>Modo de Preparo</h3><p>" . nl2br(h($modo)) . "</p>";
    }
  }
}

// injeta conteúdo no template (usa marcador {{CONTENT}} ou <!--CONTENT-->; fallback antes do </body>)
if (strpos($html, '{{CONTENT}}') !== false) {
  $html = str_replace('{{CONTENT}}', $formulaContent, $html);
} elseif (strpos($html, '<!--CONTENT-->') !== false) {
  $html = str_replace('<!--CONTENT-->', $formulaContent, $html);
} elseif (stripos($html, '</body>') !== false) {
  $html = str_ireplace('</body>', $formulaContent . '</body>', $html);
} else {
  $html .= $formulaContent;
}

// cria e salva PDF
$codigoSan = str_replace('/', '-', $codigoFormula);
$pdfAbsPath = $tempDir . "/{$codigoSan}.pdf";
$pdfRelPath = "temp/{$codigoSan}.pdf";


try {

  $headerH = 13; // só p/ controlar a altura da imagem
  $footerH = 25;

  $mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    // REMOVA AS MARGENS DAQUI. O CSS @page vai controlar isso.
    // 'margin_top'    => 0,
    // 'margin_bottom' => 0,
    'margin_left'   => 0, // Pode manter as laterais se quiser
    'margin_right'  => 0,
    'fontDir'       => [__DIR__ . '/fonts'],
    'fontdata'      => [
      'publicsans' => [
        'R'=>'PublicSans-Light.ttf','B'=>'PublicSans-Bold.ttf',
        'I'=>'PublicSans-Italic.ttf','BI'=>'PublicSans-BoldItalic.ttf',
      ],
    ],
    'default_font'  => 'publicsans',
    'tempDir'       => $tempDir,
  ]);

  // REMOVA ESTAS LINHAS. Elas não são necessárias e podem causar conflitos.
  // $mpdf->setAutoTopMargin    = 'stretch';
  // $mpdf->setAutoBottomMargin = 'stretch';
  // $mpdf->autoMarginPadding   = 0;

  // Caminhos absolutos para as imagens
  $headerImg = __DIR__ . '/assets/src/image/template_header_v1.png';
  $footerImg = __DIR__ . '/assets/src/image/template_footer_v1.png';

  // SIMPLIFIQUE O HTML DO HEADER E FOOTER.
  // Apenas a tag <img> é suficiente. O mPDF cuida do posicionamento.
  $mpdf->SetHTMLHeader(
    '<div style="height:20mm;border-top:1px solid #999; text-align:center; font:12px Arial; line-height:20mm;">FOOTER TESTE</div>'
  );

  $mpdf->SetHTMLFooter(
    '<div style="height:20mm;border-top:1px solid #999; text-align:center; font:12px Arial; line-height:20mm;">FOOTER TESTE</div>'
  );

  $mpdf->WriteHTML($html);
  $mpdf->Output($pdfAbsPath, \Mpdf\Output\Destination::FILE);

  // atualiza caminho no BD
  $stmt = $conn->prepare("UPDATE formulacoes SET caminho_pdf = ? WHERE id = ?");
  $stmt->bind_param("si", $pdfRelPath, $formulacaoId);
  $stmt->execute();
  $stmt->close();

  // abre em nova aba
  header("Location: " . $pdfRelPath);
  exit;

} catch (\Mpdf\MpdfException $e) {
  http_response_code(500);
  die("Fórmula criada, mas ocorreu erro ao gerar o PDF: " . $e->getMessage());
}
