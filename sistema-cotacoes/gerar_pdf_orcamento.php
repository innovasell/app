<?php
// --- CONFIGURAÇÃO INICIAL E LOGS ---
// Definir headers de erro OFF para não corromper PDF, mas logar em arquivo
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Função de Log Unificada
function log_debug($msg)
{
    // Usar pasta TMP que sabemos que tem permissão
    $logfile = __DIR__ . '/tmp/pdf_debug_log.txt';
    $time = date('Y-m-d H:i:s');
    file_put_contents($logfile, "[$time] $msg" . PHP_EOL, FILE_APPEND);
}

log_debug("========================================");
log_debug("INICIO DO SCRIPT gerar_pdf_orcamento.php");

// Iniciar Sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar Login
if (!isset($_SESSION['representante_email'])) {
    log_debug("Usuário não logado. Redirecionando.");
    header('Location: index.html');
    exit();
}

// Carregar Dependências
try {
    log_debug("Carregando conexao.php...");
    require_once 'conexao.php';

    log_debug("Carregando vendor/autoload.php...");
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        throw new Exception("Arquivo vendor/autoload.php não encontrado.");
    }
    require_once __DIR__ . '/vendor/autoload.php';
    log_debug("Dependências carregadas.");

} catch (Throwable $e) {
    log_debug("ERRO FATAL AO CARREGAR DEPENDENCIAS: " . $e->getMessage());
    http_response_code(500);
    die("Erro interno do servidor (Dependências).");
}

use Mpdf\Mpdf;

// --- PROCESSAMENTO ---
try {
    $num = trim($_GET['num'] ?? '');
    log_debug("Número do orçamento recebido: " . $num);

    if (strlen($num) < 1 || !ctype_digit($num)) {
        throw new Exception("Número do orçamento inválido ou ausente.");
    }

    // Buscar no Banco
    log_debug("Buscando orçamento no banco...");
    $stmt = $pdo->prepare("SELECT * FROM cot_cotacoes_importadas WHERE NUM_ORCAMENTO = ?");
    $stmt->execute([$num]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($dados)) {
        log_debug("Orçamento não encontrado no banco.");
        // Gerar PDF de erro
        $mpdf = new Mpdf();
        $mpdf->WriteHTML('<h2>Orçamento não encontrado.</h2><p>Verifique o número e tente novamente.</p>');
        $mpdf->Output();
        exit;
    }

    log_debug("Orçamento encontrado. Registros: " . count($dados));

    // Dados Complementares
    $cotado_por_nome = $dados[0]['COTADO_POR'];
    $data_orcamento = date('d/m/Y', strtotime($dados[0]['DATA']));
    $incluir_net = isset($_GET['incluir_net']) && $_GET['incluir_net'] === 'true';

    // Buscar Email do Representante
    // Tentativa 1: Nome Completo Exato
    $stmt_rep = $pdo->prepare("SELECT * FROM cot_representante WHERE UPPER(TRIM(CONCAT(TRIM(nome), ' ', TRIM(sobrenome)))) = ? LIMIT 1");
    $stmt_rep->execute([strtoupper(trim($cotado_por_nome))]);
    $dados_rep = $stmt_rep->fetch(PDO::FETCH_ASSOC);

    // Tentativa 2: Apenas Nome (Primeiro Nome)
    if (!$dados_rep) {
        $partes_nome = explode(' ', trim($cotado_por_nome));
        $primeiro_nome = $partes_nome[0];

        $stmt_rep = $pdo->prepare("SELECT * FROM cot_representante WHERE UPPER(TRIM(nome)) = ? LIMIT 1");
        $stmt_rep->execute([strtoupper($primeiro_nome)]);
        $dados_rep = $stmt_rep->fetch(PDO::FETCH_ASSOC);
    }

    // Fallback se não achar representante
    if (!$dados_rep) {
        $dados_rep = ['nome' => $cotado_por_nome, 'sobrenome' => '', 'email' => '', 'telefone' => ''];
    }

    // Buscar Dados Completos do Cliente (Novo)
    $stmt_cli = $pdo->prepare("SELECT * FROM cot_clientes WHERE razao_social = ? LIMIT 1");
    $stmt_cli->execute([$dados[0]['RAZÃO SOCIAL']]);
    $dados_cliente = $stmt_cli->fetch(PDO::FETCH_ASSOC);

    if (!$dados_cliente) {
        // Fallback básico usando dados da cotação
        $dados_cliente = [
            'razao_social' => $dados[0]['RAZÃO SOCIAL'],
            'uf' => $dados[0]['UF']
        ];
    }

    // --- MONTAR HTML VI LAYOUT HELPER ---
    require_once 'LayoutHelper.php';
    log_debug("Gerando HTML via LayoutHelper...");

    $html = LayoutHelper::getPdfHtml($dados, $dados_cliente, $dados_rep, $num, $data_orcamento, $incluir_net);

    // --- GERAÇÃO DO PDF ---
    log_debug("Instanciando MPDF para geração final...");

    // Limpar buffers antes de começar o MPDF
    while (ob_get_level())
        ob_end_clean();
    ob_start();

    $mpdf = new Mpdf([
        'format' => 'A4-L', // Landscape
        'orientation' => 'L',
        'margin_top' => 0,    // Margem zerada pois o CSS controla (ou margem pequena)
        'margin_bottom' => 0,
        'margin_left' => 0,
        'margin_right' => 0,
        'tempDir' => __DIR__ . '/tmp'
    ]);

    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($html);

    $arquivo_destino = __DIR__ . "/tmp/orcamento_" . $num . ".pdf";
    log_debug("Salvando PDF em: $arquivo_destino");

    // 1. Salvar no Servidor
    $mpdf->Output($arquivo_destino, \Mpdf\Output\Destination::FILE);

    if (file_exists($arquivo_destino)) {
        log_debug("PDF salvo com sucesso. Bytes: " . filesize($arquivo_destino));
    } else {
        throw new Exception("Falha ao gravar arquivo PDF no disco.");
    }

    // 2. Disparar Email (Assíncrono fake via Timeout) - DESATIVADO PARA DEBUG
    // log_debug("Disparando email (mock)...");

    // 3. Entregar ao Navegador
    log_debug("Enviando PDF para o browser...");

    if (file_exists($arquivo_destino)) {
        // Garantir zero output extra
        while (ob_get_level())
            ob_end_clean();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="orcamento_' . $num . '.pdf"');
        header('Content-Length: ' . filesize($arquivo_destino));
        readfile($arquivo_destino);
        log_debug("PDF entregue via readfile.");
    } else {
        // Fallback estranho, mas tenta
        log_debug("Arquivo sumiu? Tentando fallback output.");
        $mpdf->Output('orcamento_' . $num . '.pdf', \Mpdf\Output\Destination::INLINE);
    }

    log_debug("SUCESSO FINAL.");

} catch (Throwable $e) {
    log_debug("ERRO CRITICO (CATCH): " . $e->getMessage());
    log_debug("Trace: " . $e->getTraceAsString());

    http_response_code(500);
    echo "<h1>Erro ao gerar PDF</h1>";
    echo "<p>Ocorreu um erro interno. Detalhes foram registrados no log.</p>";
}