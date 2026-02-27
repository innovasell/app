<?php
session_start();
// DEFINIR FUSO HORÁRIO LOCAL
date_default_timezone_set('America/Sao_Paulo');
require_once 'conexao.php'; // Inclui a conexão PDO

// --- INÍCIO: Includes e use statements para PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// USANDO O AUTOLOAD DO COMPOSER
require_once __DIR__ . '/vendor/autoload.php';
// --- FIM: Includes e use statements ---


// ==================================================
// === FUNÇÃO PARA LOGAR EM ARQUIVO PERSONALIZADO ===
// ==================================================
function logToFile($message, $logFileName = 'erroslog.txt')
{
    $logFilePath = __DIR__ . '/' . $logFileName;
    $timestamp = date("Y-m-d H:i:s");
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    $logEntry = "[{$timestamp}] " . $message . PHP_EOL;
    @file_put_contents($logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
}
// ==================================================


// 1. Verificar se o usuário está logado
if (!isset($_SESSION['representante_email'])) {
    $_SESSION['message'] = "Erro: Acesso não autorizado. Faça login novamente.";
    $_SESSION['message_type'] = "danger";
    logToFile("Tentativa de acesso não autorizado a processa_pedido_amostra.php.");
    header('Location: index.html');
    exit();
}

// 2. Verificar se o método é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = "Erro: Método de requisição inválido.";
    $_SESSION['message_type'] = "danger";
    logToFile("Tentativa de acesso a processa_pedido_amostra.php com método inválido: " . $_SERVER['REQUEST_METHOD']);
    header('Location: incluir_ped_amostras.php');
    exit();
}

// =====================================================================
// === 3. RECEBER TODOS OS DADOS DO FORMULÁRIO E SESSÃO ===
// =====================================================================
$numero_referencia = trim($_POST['numero_pedido'] ?? '');

// --- Pega email e nome do responsável DIRETAMENTE DA SESSÃO ---
$responsavel_email = trim($_SESSION['representante_email'] ?? 'Email não encontrado na sessão');
$primeiro_nome = $_SESSION['representante_nome'] ?? '';
$sobrenome = $_SESSION['representante_sobrenome'] ?? '';

$responsavel_nome_completo = trim($primeiro_nome . ' ' . $sobrenome);
if (empty($responsavel_nome_completo)) {
    logToFile("Aviso: Nome completo vazio na sessão em processa_pedido. Usando email: " . $responsavel_email);
    $responsavel_nome = $responsavel_email; // $responsavel_nome será usado para o EMAIL
} else {
    $responsavel_nome = $responsavel_nome_completo; // $responsavel_nome será usado para o EMAIL
}
// --- Fim da combinação ---

// Verifica se o nome não está vazio, caso contrário, usa o email como nome também
if (empty($responsavel_nome)) {
    logToFile("Aviso: Nome do representante vazio na sessão. Usando email como nome: " . $responsavel_email);
    $responsavel_nome = $responsavel_email;
}
// --- Fim de pegar dados da sessão ---

$responsavel_nome_completo = ucwords(strtolower($responsavel_nome_completo));

// Definir o que salvar no banco (Assumindo que a coluna 'responsavel_pedido' guarda o email)
$responsavel_pedido_db = $responsavel_email;

// --- Restante do recebimento ---
$id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
$contato_cliente = trim($_POST['cliente_contato'] ?? '');
$email_contato = filter_input(INPUT_POST, 'cliente_email', FILTER_VALIDATE_EMAIL);
$telefone_contato = trim($_POST['cliente_telefone'] ?? '');
$info_projeto = trim($_POST['info_projeto'] ?? '');
$etapa_projeto = trim($_POST['etapa_projeto'] ?? '');
$data_limite_str = trim($_POST['data_limite'] ?? '');
$autorizado_por_email = filter_input(INPUT_POST, 'autorizado_por', FILTER_VALIDATE_EMAIL);
$produto_ids = $_POST['produto_id'] ?? [];
$quantidades = $_POST['quantidade'] ?? [];
$custos = $_POST['custo_por_kg'] ?? [];
$fabricantes = $_POST['fabricante'] ?? [];
$estoques = $_POST['estoque'] ?? [];
$fracionamentos = $_POST['fracionamento'] ?? [];
// =====================================================================


// =====================================================================
// === 4. VALIDAÇÕES ===
// =====================================================================

// =====================================================================
// === 4. VALIDAÇÕES ===
// =====================================================================
$errors = [];
$data_limite_db = null;

if (empty($numero_referencia)) {
    $errors[] = "Número de referência do pedido está faltando.";
}
// Verifica se o email (que será salvo no DB) é válido
if (empty($responsavel_pedido_db) || !filter_var($responsavel_pedido_db, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Email do responsável inválido na sessão.";
}
if ($id_cliente === false || $id_cliente <= 0) {
    $errors[] = "Cliente inválido ou não selecionado.";
}
// [REMOVIDO] Validação de 'autorizado_por' removida.

if (!empty($data_limite_str)) {
    $data_limite_obj = DateTime::createFromFormat('Y-m-d', $data_limite_str);
    if ($data_limite_obj === false) {
        $errors[] = "Formato da Data Limite inválido. Use AAAA-MM-DD.";
    } else {
        $data_limite_db = $data_limite_obj->format('Y-m-d');
    }
}

if (!empty($_POST['cliente_email']) && $email_contato === false) {
    $errors[] = "Formato do E-mail do contato inválido.";
}

$num_itens = count($produto_ids);
if ($num_itens === 0) {
    $errors[] = "Nenhum produto foi adicionado ao pedido.";
} elseif (count($quantidades) !== $num_itens || count($fabricantes) !== $num_itens || count($estoques) !== $num_itens || count($fracionamentos) !== $num_itens) {
    $errors[] = "Erro: Inconsistência nos dados dos produtos enviados.";
} else {
    for ($i = 0; $i < $num_itens; $i++) {
        if (!isset($quantidades[$i]) || !is_numeric($quantidades[$i]) || floatval($quantidades[$i]) <= 0) {
            $errors[] = "Quantidade inválida para o produto ID " . htmlspecialchars($produto_ids[$i]) . ".";
        }
        if (!isset($estoques[$i]) || !in_array($estoques[$i], ['SIM', 'NÃO'])) {
            $errors[] = "Valor inválido para 'Estoque' no produto ID " . htmlspecialchars($produto_ids[$i]) . ".";
        }
        if (!isset($fracionamentos[$i]) || !in_array($fracionamentos[$i], ['SIM', 'NÃO'])) {
            $errors[] = "Valor inválido para 'Fracionamento' no produto ID " . htmlspecialchars($produto_ids[$i]) . ".";
        }
    }
}
// =====================================================================


// 5. Se houver erros de validação, redireciona de volta
if (!empty($errors)) {
    $_SESSION['message'] = "Erro ao processar o pedido:<br>" . implode("<br>", $errors);
    $_SESSION['message_type'] = "danger";
    logToFile("Erro de validação no pedido {$numero_referencia}: " . implode("; ", $errors));
    header('Location: incluir_ped_amostras.php');
    exit();
}


// --- BLOCO PRINCIPAL: Banco de Dados e Email ---
$pedidoSalvo = false;
$emailEnviado = false;
$erroEmail = null;
$id_pedido_amostra = null;

// --- BLOCO PRINCIPAL COM RETRY E AUTO-REPAIR ---
$pedidoSalvo = false;
$emailEnviado = false;
$erroEmail = null;
$id_pedido_amostra = null;
$max_attempts = 2;
$attempt = 0;
$success_transaction = false;

while ($attempt < $max_attempts && !$success_transaction) {
    $attempt++;
    $pdo->beginTransaction();
    try {
        // 1. Inserir pedido principal
        // [MODIFICADO] 'autorizado_por' removido ou setado como NULL/Vazio
        $sql_pedido = "INSERT INTO pedidos_amostra
                         (numero_referencia, id_cliente, responsavel_pedido, contato_cliente, telefone_contato, email_contato, info_projeto, etapa_projeto, data_limite, autorizado_por, data_pedido)
                       VALUES
                         (:numero_referencia, :id_cliente, :responsavel_pedido, :contato_cliente, :telefone_contato, :email_contato, :info_projeto, :etapa_projeto, :data_limite, NULL, NOW())";
        $stmt_pedido = $pdo->prepare($sql_pedido);

        // Binds
        $stmt_pedido->bindParam(':numero_referencia', $numero_referencia);
        $stmt_pedido->bindParam(':id_cliente', $id_cliente, PDO::PARAM_INT);
        $stmt_pedido->bindParam(':responsavel_pedido', $responsavel_pedido_db);
        $stmt_pedido->bindParam(':contato_cliente', $contato_cliente);
        $stmt_pedido->bindParam(':telefone_contato', $telefone_contato);
        $stmt_pedido->bindParam(':email_contato', $email_contato);
        $stmt_pedido->bindParam(':info_projeto', $info_projeto);
        $stmt_pedido->bindParam(':etapa_projeto', $etapa_projeto);
        $stmt_pedido->bindParam(':data_limite', $data_limite_db);
        // :autorizado_por removido do bind pois passamos NULL direto

        $stmt_pedido->execute();
        $id_pedido_amostra = $pdo->lastInsertId();

        // VALIDAÇÃO DE ID
        // Se id for 0 ou falso, provavelmente a tabela não está como AUTO_INCREMENT
        if (!$id_pedido_amostra || $id_pedido_amostra == 0) {
            throw new \Exception("ID_ZERO_DETECTED");
        }

        // 2. Inserir itens
        if ($id_pedido_amostra && $num_itens > 0) {
            $sql_item = "INSERT INTO itens_pedido_amostra
                           (id_pedido_amostra, id_produto, quantidade, custo_por_kg, fabricante, disponivel_estoque, necessita_fracionamento)
                         VALUES
                           (:id_pedido_amostra, :id_produto, :quantidade, :custo_por_kg, :fabricante, :disponivel_estoque, :necessita_fracionamento)";
            $stmt_item = $pdo->prepare($sql_item);
            for ($i = 0; $i < $num_itens; $i++) {
                $custo_val = isset($custos[$i]) ? floatval($custos[$i]) : 0.0;
                $stmt_item->bindParam(':id_pedido_amostra', $id_pedido_amostra, PDO::PARAM_INT);
                $stmt_item->bindParam(':id_produto', $produto_ids[$i], PDO::PARAM_INT);
                $stmt_item->bindParam(':quantidade', $quantidades[$i]);
                $stmt_item->bindParam(':custo_por_kg', $custo_val);
                $stmt_item->bindParam(':fabricante', $fabricantes[$i]);
                $stmt_item->bindParam(':disponivel_estoque', $estoques[$i]);
                $stmt_item->bindParam(':necessita_fracionamento', $fracionamentos[$i]);
                $stmt_item->execute();
            }
        } elseif (!$id_pedido_amostra) {
            throw new \Exception("Falha ao obter o ID do pedido principal após o INSERT.");
        }

        $pdo->commit();
        $pedidoSalvo = true;
        $success_transaction = true;
        logToFile("Pedido {$id_pedido_amostra} (Ref: {$numero_referencia}) salvo com sucesso no BD.");

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Se for a última tentativa, falha geral
        if ($attempt >= $max_attempts) {
            logToFile("ERRO PDOException FATAL ao salvar Pedido {$numero_referencia}: " . $e->getMessage());
            $detailed_error_message = "ERRO DETALHADO DO BANCO DE DADOS (PDOException): <br><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
            $_SESSION['message'] = $detailed_error_message;
            $_SESSION['message_type'] = "danger";
            header('Location: incluir_ped_amostras.php');
            exit();
        }
        // Se não, tenta continuar para próxima iteração
        logToFile("Erro Tentativa {$attempt}: " . $e->getMessage() . ". Tentando novamente...");

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // AUTO-REPAIR LOGIC
        if ($e->getMessage() == "ID_ZERO_DETECTED" && $attempt < $max_attempts) {
            logToFile("ERRO CRITICO: ID da inserção retornou 0. Tentando corrigir Schema do Banco de Dados automaticamente...");
            try {
                // Tenta limpar IDs O e setar AUTO_INCREMENT
                // Executar fora de transação geralmente (DDL faz commit implícito)
                $pdo->exec("DELETE FROM itens_pedido_amostra WHERE id_pedido_amostra = 0");
                $pdo->exec("DELETE FROM pedidos_amostra WHERE id = 0");

                // Tenta remover PK se existir para recriar
                try {
                    $pdo->exec("ALTER TABLE pedidos_amostra DROP PRIMARY KEY");
                } catch (\Exception $ignore) {
                }

                // Aplica AUTO_INCREMENT
                $pdo->exec("ALTER TABLE pedidos_amostra MODIFY id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY");

                logToFile("Schema corrigido com sucesso! Retentando inserção...");
                continue; // Vai para a próxima iteração do WHILE

            } catch (\Exception $exFix) {
                logToFile("Falha ao tentar auto-corrigir o Schema: " . $exFix->getMessage());
                // Deixa cair no erro fatal abaixo
            }
        }

        logToFile("ERRO Exception GERAL em processa_pedido_amostra (Pedido {$numero_referencia}): " . $e->getMessage());
        $_SESSION['message'] = "Ocorreu um erro inesperado durante o processamento (" . htmlspecialchars($e->getMessage()) . "). Verifique os logs ou contate o suporte.";
        $_SESSION['message_type'] = "danger";
        header('Location: incluir_ped_amostras.php');
        exit();
    }
}


// --- INÍCIO: Enviar Email com PDF ---
if ($pedidoSalvo && $id_pedido_amostra) {

    // Output Buffer para garantir que nada (warnings do PDF) saia antes do header
    ob_start();

    try {
        // Aumentar limite de memória para PDF/Email
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', 120);

        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // Tentativa de carregamento manual se o Autoload falhar (Fix para erro Class Not Found)
            $vendorDir = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
            if (file_exists($vendorDir . 'PHPMailer.php')) {
                require_once $vendorDir . 'PHPMailer.php';
                require_once $vendorDir . 'SMTP.php';
                require_once $vendorDir . 'Exception.php';
            } else {
                throw new \Exception("Classe PHPMailer não encontrada e arquivos manuais não localizados em $vendorDir");
            }
        }

        // ** Configurar e Enviar o Email com PHPMailer **
        $mail = new PHPMailer(true);
        require_once 'pdf_generator.php';

        // Gerar o PDF em String (S = String return)
        $pdfContent = generateSamplePdf($id_pedido_amostra, $pdo, 'S');

        // Configurações SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        // CREDENCIAIS
        $mail->Username = 'marketing@innovasell.com.br';
        $mail->Password = 'rqwu hpog vkjb zogr';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        // Remetente e Destinatário
        $mail->setFrom('marketing@innovasell.com.br', 'Sistema de Pedidos Innovasell');
        // REMOVIDO: $autorizado_por_email
        // ADICIONADO: Email do Usuário Logado
        $mail->addAddress($responsavel_pedido_db);

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = 'Confirmação de Pedido de Amostra - N. ' . htmlspecialchars($numero_referencia ?? 'N/A');

        $body = "<p>Olá, {$responsavel_nome}.</p>";
        $body .= "<p>Seu pedido de amostra <strong>{$numero_referencia}</strong> foi registrado com sucesso.</p>";
        $body .= "<p>Segue em anexo o PDF com os detalhes da solicitação.</p>";
        $body .= "<br><p>Atenciosamente,<br>Equipe Innovasell/Hansen</p>";
        $mail->Body = $body;

        // Anexo (PDF Gerado em Memória)
        try {
            $mail->addStringAttachment($pdfContent, "Pedido_Amostra_{$numero_referencia}.pdf", 'base64', 'application/pdf');
        } catch (\Throwable $eAttach) {
            logToFile("ERRO CRÍTICO ao anexar PDF: " . $eAttach->getMessage());
            throw $eAttach; // Re-throw to be caught by main handler
        }

        $mail->send();
        $emailEnviado = true;
        logToFile("Email com PDF enviado para {$responsavel_pedido_db} (Pedido {$id_pedido_amostra}) com sucesso.");

    } catch (\Throwable $e) {
        $emailEnviado = false;
        $erroEmail = ($mail->ErrorInfo) ? $mail->ErrorInfo : $e->getMessage();
        logToFile("ERRO PHPMailer/PDF para {$responsavel_pedido_db} (Pedido {$id_pedido_amostra}): " . $erroEmail);
    }
    // Limpa qualquer saída (warnings, echos do PDF) para não quebrar o header Location
    ob_end_clean();

} else {
    logToFile("AVISO: Envio de email pulado para Pedido Ref {$numero_referencia} porque pedidoSalvo=false.");
}
// --- FIM: Enviar Email ---


// --- Resposta Final ---
if ($pedidoSalvo) {
    // SUCESSO: Define o ID do pedido na sessão para abrir o modal de sucesso
    $_SESSION['pedido_concluido_id'] = $id_pedido_amostra;

    // Define mensagem opcional (apenas como fallback ou log)
    if ($emailEnviado) {
        // Mensagem de sucesso silenciosa (handling será no modal)
    } else {
        $_SESSION['message'] = "Pedido salvo, mas FALHA ao enviar email. Verifique o log.";
        $_SESSION['message_type'] = "warning";
    }
} else {
    // Falha já tratada no catch
}

header('Location: incluir_ped_amostras.php');
exit();

?>