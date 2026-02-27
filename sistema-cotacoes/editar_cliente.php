<?php
session_start();
date_default_timezone_set('America/Sao_Paulo'); // Define fuso horário
// !!! DEFINA A PÁGINA ATIVA AQUI !!!
$pagina_ativa = 'gerenciar_cliente'; // Exemplo para consultar_amostras.php
// Use: 'incluir_amostra' para incluir_ped_amostras.php
// Use: 'gerenciar_cliente' para gerenciar_cliente.php
// Use: 'incluir_orcamento' para incluir_orcamento.php
// Use: 'filtrar' para filtrar.php
// Use: 'consultar_orcamentos' para consultar_orcamentos.php
// Use: 'previsao' para previsao.php

require_once 'header.php'; // Inclui o header
require_once 'conexao.php'; // Conexão PDO
// 1. Verificar se o usuário está logado
if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html'); // Ou sua página de login
    exit();
}

// Inclui a conexão com o banco de dados
require_once 'conexao.php';

// --- BUSCAR REPRESENTANTES (Necessário para o dropdown) ---
$representantes = [];
$_initial_alert_message = null;
$_initial_alert_type = 'warning';
try {
    $sql_rep = "SELECT id, nome, sobrenome FROM cot_representante ORDER BY nome ASC, sobrenome ASC";
    $stmt_rep = $pdo->query($sql_rep);
    $representantes = $stmt_rep->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // error_log("Erro ao buscar representantes em editar_cliente: " . $e->getMessage());
    $_initial_alert_message = "Erro ao carregar lista de representantes. A seleção pode estar indisponível.";
}
// --- FIM BUSCAR REPRESENTANTES ---

// Inicializa variáveis
$cliente = null; // Começa como null, será preenchido ou dará erro
$page_title = "Editar Cliente";
$alert_message = $_initial_alert_message; // Pode começar com o erro de buscar representantes
$alert_type = $_initial_alert_type;
$show_form = false; // Controla se o formulário deve ser exibido

// --- OBTER E VALIDAR ID DO CLIENTE DA URL ---
$cliente_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$cliente_id) {
    // ID inválido ou não fornecido
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'ID do cliente inválido ou não fornecido.'];
    header('Location: gerenciar_clientes.php'); // Volta para a listagem
    exit();
}

// --- BUSCAR DADOS DO CLIENTE PARA EDIÇÃO (antes de processar POST) ---
try {
    $sql_fetch = "SELECT * FROM cot_clientes WHERE id = :id";
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->bindParam(':id', $cliente_id, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $cliente = $stmt_fetch->fetch(PDO::FETCH_ASSOC); // Tenta buscar o cliente


    if ($cliente) {
        $page_title = "Editar Cliente: " . htmlspecialchars(!empty($cliente['razao_social']) ? $cliente['razao_social'] : ($cliente['nome'] ?? 'ID '.$cliente_id));
        $show_form = true; // Cliente encontrado, pode mostrar o formulário
    } else {
        // Cliente não encontrado com o ID fornecido
        $_SESSION['flash_message'] = ['type' => 'warning', 'text' => "Cliente com ID {$cliente_id} não encontrado."];
        header('Location: gerenciar_clientes.php'); // Volta para a listagem
        exit();
    }
} catch (PDOException $e) {
    // error_log("Erro ao buscar cliente ID {$cliente_id} para edição: " . $e->getMessage());
    $alert_message = "Erro ao carregar dados do cliente para edição.";
    $alert_type = 'danger';
    // Não mostra o formulário se não conseguiu carregar os dados
}


// --- PROCESSA O FORMULÁRIO QUANDO ENVIADO (POST Request) ---
// --- PROCESSA O FORMULÁRIO QUANDO ENVIADO (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recebe o ID do campo oculto (mais confiável que o GET na URL para POST)
    $cliente_id_post = filter_input(INPUT_POST, 'cliente_id_hidden', FILTER_VALIDATE_INT);

    // Sanitiza e obtém os dados do formulário
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $contato = filter_input(INPUT_POST, 'contato', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_SPECIAL_CHARS);
    $id_representante_input = filter_input(INPUT_POST, 'id_representante');
    $razao_social = filter_input(INPUT_POST, 'razao_social', FILTER_SANITIZE_SPECIAL_CHARS);
    $uf = filter_input(INPUT_POST, 'uf', FILTER_SANITIZE_SPECIAL_CHARS);
    $cnpj_raw = filter_input(INPUT_POST, 'cnpj', FILTER_SANITIZE_SPECIAL_CHARS);
    $tipo = filter_input(INPUT_POST, 'Tipo', FILTER_SANITIZE_SPECIAL_CHARS);

    // Usa os dados do POST para repopular em caso de erro
    // Guardamos o ID original buscado do GET ($cliente_id) para consistência
    $cliente = $_POST; // Pega todos os dados crus
    $cliente['id'] = $cliente_id; // Usa o ID original carregado na página
    $cliente['cnpj'] = $cnpj_raw; // Mantém formato com máscara para repopular

    // --- VALIDAÇÃO E LIMPEZA DO CNPJ ---
    $cnpj_limpo = null;
    $cnpj_valido = false;
    if (!empty($cnpj_raw)) {
        if (preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/', $cnpj_raw)) {
             $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj_raw);
             $cnpj_valido = true;
        } else {
             $alert_message = "Formato do CNPJ inválido. Use xx.xxx.xxx/xxxx-xx.";
             $alert_type = 'danger';
        }
    }
    // --- FIM VALIDAÇÃO CNPJ ---

    // Validação de ID representante
    $id_representante = null;
    if ($alert_message === null && $id_representante_input !== '' && $id_representante_input !== null) {
        $id_representante_validated = filter_var($id_representante_input, FILTER_VALIDATE_INT);
        if ($id_representante_validated !== false) { $id_representante = $id_representante_validated; }
        else { $alert_message = "ID do Representante inválido."; $alert_type = 'danger'; }
    }

    // Outras validações (obrigatórios, email, formato CNPJ)
     if ($alert_message === null && empty($nome)) { $alert_message = "'Nome Fantasia' obrigatório."; $alert_type = 'warning'; }
     elseif ($alert_message === null && empty($razao_social)) { $alert_message = "'Razão Social' obrigatório."; $alert_type = 'warning'; }
     elseif ($alert_message === null && empty($email)) { $alert_message = "'Email' obrigatório."; $alert_type = 'warning'; }
     elseif ($alert_message === null && !empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $alert_message = "Formato de e-mail inválido."; $alert_type = 'danger'; }
     elseif ($alert_message === null && empty($telefone)) { $alert_message = "'Telefone' obrigatório."; $alert_type = 'warning'; }
     elseif ($alert_message === null && empty($cnpj_raw)) { $alert_message = "'CNPJ' obrigatório."; $alert_type = 'warning'; }
     elseif ($alert_message === null && !$cnpj_valido && !empty($cnpj_raw)) { /* Mensagem de formato já definida */ }
     elseif ($alert_message === null && empty($contato)) { $alert_message = "'Contato (Nome)' obrigatório."; $alert_type = 'warning'; }

     // --- VERIFICA DUPLICIDADE DE CNPJ (IGNORANDO O PRÓPRIO CLIENTE) ---
     if ($alert_message === null && $cnpj_valido && !empty($cnpj_limpo)) {
         try {
             // Verifica se o CNPJ existe para OUTRO ID
             $sqlCheck = "SELECT COUNT(*) FROM cot_clientes WHERE cnpj = :cnpj AND id != :id_atual";
             $stmtCheck = $pdo->prepare($sqlCheck);
             $stmtCheck->bindParam(':cnpj', $cnpj_limpo);
             $stmtCheck->bindParam(':id_atual', $cliente_id, PDO::PARAM_INT); // Usa o ID original
             $stmtCheck->execute();
             $count = $stmtCheck->fetchColumn();

             if ($count > 0) {
                 $alert_message = "Este CNPJ já está cadastrado para outro cliente.";
                 $alert_type = 'danger';
             }
         } catch (PDOException $e) {
              // error_log("Erro ao verificar duplicidade de CNPJ (Editar ID: {$cliente_id}): " . $e->getMessage());
              $alert_message = "Erro ao verificar CNPJ. Tente novamente.";
              $alert_type = 'danger';
         }
     }
     // --- FIM VERIFICA DUPLICIDADE ---

     // Se passou em TODAS as validações
     if ($alert_message === null) {
        try {
             // --- AGORA É UPDATE ---
             $sql = "UPDATE cot_clientes SET
                        nome = :nome,
                        contato = :contato,
                        email = :email,
                        telefone = :telefone,
                        id_representante = :id_representante,
                        razao_social = :razao_social,
                        uf = :uf,
                        cnpj = :cnpj,
                        Tipo = :tipo
                    WHERE id = :id_atual"; // <<< CONDIÇÃO WHERE É ESSENCIAL

             $stmt = $pdo->prepare($sql);

             // Bind dos parâmetros (incluindo o ID na cláusula WHERE)
             $stmt->bindParam(':nome', $nome);
             $stmt->bindParam(':contato', $contato);
             $stmt->bindParam(':email', $email);
             $stmt->bindParam(':telefone', $telefone);
             $stmt->bindParam(':id_representante', $id_representante, $id_representante === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
             $stmt->bindParam(':razao_social', $razao_social);
             $stmt->bindParam(':uf', $uf);
             $stmt->bindParam(':cnpj', $cnpj_limpo); // Salva CNPJ limpo
             $stmt->bindParam(':tipo', $tipo);
             $stmt->bindParam(':id_atual', $cliente_id, PDO::PARAM_INT); // <<< BIND DO ID PARA O WHERE

             // Executa e verifica o resultado
             if ($stmt->execute()) {
                 // $affectedRows = $stmt->rowCount(); // Opcional: verificar se alguma linha foi realmente alterada
                 // if ($affectedRows > 0) { ... }

                 // SUCESSO
                 $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Cliente atualizado com sucesso!'];
                 header('Location: gerenciar_cliente.php'); // Volta para a listagem após sucesso
                 exit();

             } else {
                 // Erro na execução
                 $stmtError = $stmt->errorInfo();
                 $alert_message = "Erro [" . ($stmtError[1] ?? 'N/A') . "] ao atualizar cliente: " . ($stmtError[2] ?? 'Erro desconhecido');
                 $alert_type = 'danger';
                 error_log("Falha ao executar UPDATE em cot_clientes (ID: {$cliente_id}). Erro Stmt: " . print_r($stmtError, true));
                 // Mantém os dados no formulário ($cliente já contém os dados do POST)
                 // Não redireciona, exibe o erro na própria página de edição
                 $show_form = true; // Garante que o form ainda é exibido com o erro
             }

        } catch (PDOException $e) {
            // error_log("Exceção PDO ao ATUALIZAR cliente ID {$cliente_id}: " . $e->getMessage());
            if ($e->errorInfo[1] == 1062) {
                $alert_message = "Erro: Este CNPJ já está cadastrado para outro cliente (violação de constraint).";
            } else {
                $alert_message = "Erro crítico [" . $e->getCode() . "] ao salvar dados: " . $e->getMessage();
            }
            $alert_type = 'danger';
            // Mantém os dados no formulário
            $show_form = true;
        }
    } else {
        // Se houve erro de validação antes de tentar o UPDATE
        $show_form = true; // Garante que o formulário seja exibido com a mensagem de erro de validação
    }
} // Fim do if ($_SERVER['REQUEST_METHOD'] === 'POST')


?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .container{
            padding-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Caixa de Alerta para o Título e Aviso -->
    <div class="alert alert-warning pb-3" role="alert"> 
            <h4 class="alert-heading"><?= htmlspecialchars($page_title) ?></h4>
            <hr>
            <p class="mb-0"> 
                <i class="bi bi-exclamation-triangle-fill"></i> <!-- Ícone de aviso (opcional) -->
                <strong>Atenção:</strong> Cuidado ao alterar as informações de um cliente.
            </p>
        </div>
        <!--

        <?php if ($alert_message): ?>
        <div class="alert alert-<?= htmlspecialchars($alert_type) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($alert_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($show_form): // Só mostra o formulário se $show_form for true ?>
        <!- Formulário Principal de Edição -->
        <form id="formClienteEditar" action="editar_cliente.php?id=<?= htmlspecialchars($cliente_id) ?>" method="POST">
        <!-- === ADICIONE ESTE CAMPO OCULTO === -->
        <input type="hidden" name="cliente_id_hidden" value="<?= htmlspecialchars($cliente['id'] ?? $cliente_id) ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nome" class="form-label">Nome fantasia <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="razao_social" class="form-label">Razão Social <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="razao_social" name="razao_social" value="<?= htmlspecialchars($cliente['razao_social'] ?? '') ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="telefone" class="form-label">Telefone <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>" required>
                </div>

                 <div class="col-md-6">
                    <label for="cnpj" class="form-label">CNPJ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?= htmlspecialchars($cliente['cnpj'] ?? '') ?>" required>
                     <!-- Considere adicionar máscara/validação JS para CNPJ -->
                </div>
                 <div class="col-md-6">
                    <label for="contato" class="form-label">Contato (Nome) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="contato" name="contato" value="<?= htmlspecialchars($cliente['contato'] ?? '') ?>" required>
                </div>


                <div class="col-md-4">
                    <label for="uf" class="form-label">UF</label>
                    <input type="text" class="form-control" id="uf" name="uf" maxlength="2" value="<?= htmlspecialchars($cliente['uf'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="Tipo" class="form-label">Tipo</label>
                    <select class="form-select" id="Tipo" name="Tipo">
                        <option value="" <?= !isset($cliente['Tipo']) || $cliente['Tipo'] === '' ? 'selected' : '' ?>>-- Selecione --</option>
                        <option value="Pessoa Juridica" <?= (isset($cliente['Tipo']) && $cliente['Tipo'] === 'Pessoa Juridica') ? 'selected' : '' ?>>Pessoa Jurídica</option>
                        <option value="Pessoa Fisica" <?= (isset($cliente['Tipo']) && $cliente['Tipo'] === 'Pessoa Fisica') ? 'selected' : '' ?>>Pessoa Física</option>
                        <option value="Estrangeiro" <?= (isset($cliente['Tipo']) && $cliente['Tipo'] === 'Estrangeiro') ? 'selected' : '' ?>>Estrangeiro</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="id_representante" class="form-label">Representante</label>
                    <select class="form-select" id="id_representante" name="id_representante">
                         <option value="">-- Selecione um Representante --</option>
                        <?php
                        if (is_array($representantes) && !empty($representantes)):
                            foreach ($representantes as $rep):
                                if (!is_array($rep)) continue;
                                $nome_completo = trim(htmlspecialchars($rep['nome'] ?? '') . ' ' . htmlspecialchars($rep['sobrenome'] ?? ''));
                                if (empty($nome_completo)) continue;
                                // Verifica se este representante deve ser pré-selecionado
                                $selected = (isset($cliente['id_representante']) && $cliente['id_representante'] != '' && isset($rep['id']) && $cliente['id_representante'] == $rep['id']) ? 'selected' : '';
                            ?>
                            <option value="<?= htmlspecialchars($rep['id']) ?>" <?= $selected ?>>
                                <?= $nome_completo ?>
                            </option>
                            <?php
                            endforeach;
                        elseif (empty($_initial_alert_message)):
                        ?>
                            <option value="" disabled>Nenhum representante cadastrado</option>
                        <?php
                        endif;
                        ?>
                    </select>
                </div>

            </div>

            <hr class="my-4">

            <button class="btn btn-primary btn-lg" type="submit">
                <i class="bi bi-check-lg"></i> Atualizar Cliente
            </button>
            <a href="javascript:void(0);" onclick="history.back(); return false;" class="btn btn-secondary btn-lg">
                 <i class="bi bi-x-lg"></i> Cancelar
            </a>

        </form>
        <!-- Fim Formulário Principal -->
        <?php endif; // Fim do if ($show_form) ?>

    </div> <!-- Fim .container -->

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
    <!-- jQuery Mask Plugin -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>

    <!-- Seu script $(document).ready() -->
    <script>
    $(document).ready(function() {
        // Aplica a máscara ao campo CNPJ
        $('#cnpj').mask('00.000.000/0000-00');

        // (Seu outro código JS, se houver)
    });
    </script>

</body>
</html>