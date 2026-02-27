<?php
session_start();
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
    // error_log("Erro ao buscar representantes em adicionar_cliente: " . $e->getMessage());
    $_initial_alert_message = "Erro ao carregar lista de representantes. A seleção pode estar indisponível.";
}
// --- FIM BUSCAR REPRESENTANTES ---

// Inicializa variáveis para o formulário (sempre vazio inicialmente)
$cliente = [
    'id' => null,
    'nome' => '',
    'contato' => '',
    'email' => '',
    'telefone' => '',
    'id_representante' => null,
    'razao_social' => '',
    'uf' => '',
    'cnpj' => '',
    'Tipo' => ''
];
$page_title = "Adicionar Novo Cliente";
$alert_message = $_initial_alert_message; // Pode começar com o erro de buscar representantes
$alert_type = $_initial_alert_type ?? 'info'; // Usa tipo inicial ou 'info'

// --- PROCESSA O FORMULÁRIO QUANDO ENVIADO (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitiza e obtém os dados do formulário
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $contato = filter_input(INPUT_POST, 'contato', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_SPECIAL_CHARS);
    $id_representante_input = filter_input(INPUT_POST, 'id_representante');
    $razao_social = filter_input(INPUT_POST, 'razao_social', FILTER_SANITIZE_SPECIAL_CHARS);
    $uf = filter_input(INPUT_POST, 'uf', FILTER_SANITIZE_SPECIAL_CHARS);
    $cnpj_raw = filter_input(INPUT_POST, 'cnpj', FILTER_SANITIZE_SPECIAL_CHARS); // CNPJ com máscara
    $tipo = filter_input(INPUT_POST, 'Tipo', FILTER_SANITIZE_SPECIAL_CHARS);

    // Mantém os dados postados no array $cliente para repopular em caso de erro
    $cliente = $_POST; // Pega todos os dados crus do POST
    $cliente['id'] = null; // ID é sempre null para adição
    // Nota: $cliente['cnpj'] já terá o valor de $_POST['cnpj'] (com máscara)

    // --- VALIDAÇÃO E LIMPEZA DO CNPJ ---
    $cnpj_limpo = null;
    $cnpj_valido = false;
    if (!empty($cnpj_raw)) {
        // 1. Verifica o formato esperado xx.xxx.xxx/xxxx-xx
        if (preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/', $cnpj_raw)) {
             // 2. Remove caracteres não numéricos
             $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj_raw);
             // (Opcional: Adicionar validação de dígito verificador aqui)
             $cnpj_valido = true;
        } else {
             $alert_message = "Formato do CNPJ inválido. Use xx.xxx.xxx/xxxx-xx.";
             $alert_type = 'danger';
        }
    } else {
        // Campo CNPJ é obrigatório (verificado abaixo)
    }
    // --- FIM VALIDAÇÃO CNPJ ---


    // Validação de ID representante (permite vazio/nulo)
    $id_representante = null;
    // Só valida se nenhuma outra mensagem de erro já foi definida
    if ($alert_message === null && $id_representante_input !== '' && $id_representante_input !== null) {
        $id_representante_validated = filter_var($id_representante_input, FILTER_VALIDATE_INT);
        if ($id_representante_validated !== false) {
            $id_representante = $id_representante_validated;
        } else {
             $alert_message = "ID do Representante inválido.";
             $alert_type = 'danger';
        }
    }


    // Validações de campos obrigatórios e formato de email
     if ($alert_message === null && empty($nome)) { $alert_message = "O campo 'Nome Fantasia' é obrigatório."; $alert_type = 'warning'; }
     elseif ($alert_message === null && empty($razao_social)) { $alert_message = "O campo 'Razão Social' é obrigatório."; $alert_type = 'warning'; }
     elseif ($alert_message === null && empty($email)) { $alert_message = "O campo 'Email' é obrigatório."; $alert_type = 'warning'; }
     elseif ($alert_message === null && !empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $alert_message = "Formato de e-mail inválido."; $alert_type = 'danger'; } // Valida formato se não vazio
     elseif ($alert_message === null && empty($telefone)) { $alert_message = "O campo 'Telefone' é obrigatório."; $alert_type = 'warning'; }
     elseif ($alert_message === null && empty($cnpj_raw)) { $alert_message = "O campo 'CNPJ' é obrigatório."; $alert_type = 'warning'; }
     elseif ($alert_message === null && !$cnpj_valido && !empty($cnpj_raw)) { /* Mensagem de formato CNPJ já foi definida acima */ } // Só bloqueia se CNPJ foi preenchido e inválido
     elseif ($alert_message === null && empty($contato)) { $alert_message = "O campo 'Contato (Nome)' é obrigatório."; $alert_type = 'warning'; }


     // --- VERIFICA DUPLICIDADE DE CNPJ (somente se passou nas validações anteriores e CNPJ é válido) ---
     if ($alert_message === null && $cnpj_valido && !empty($cnpj_limpo)) {
         try {
             $sqlCheck = "SELECT COUNT(*) FROM cot_clientes WHERE cnpj = :cnpj";
             $stmtCheck = $pdo->prepare($sqlCheck);
             $stmtCheck->bindParam(':cnpj', $cnpj_limpo); // Usa o CNPJ limpo
             $stmtCheck->execute();
             $count = $stmtCheck->fetchColumn();

             if ($count > 0) {
                 $alert_message = "Este CNPJ já está cadastrado no sistema.";
                 $alert_type = 'danger';
             }
         } catch (PDOException $e) {
              // error_log("Erro ao verificar duplicidade de CNPJ (Adicionar): " . $e->getMessage());
              $alert_message = "Erro ao verificar CNPJ. Tente novamente.";
              $alert_type = 'danger';
         }
     }
     // --- FIM VERIFICA DUPLICIDADE ---


     // Se passou em TODAS as validações (incluindo formato e duplicidade do CNPJ)
     if ($alert_message === null) {
        try {
             // --- APENAS INSERT ---
             $sql = "INSERT INTO cot_clientes
                       (nome, contato, email, telefone, id_representante, razao_social, uf, cnpj, Tipo)
                   VALUES
                       (:nome, :contato, :email, :telefone, :id_representante, :razao_social, :uf, :cnpj, :tipo)";
             $stmt = $pdo->prepare($sql);

             // Bind dos parâmetros
             $stmt->bindParam(':nome', $nome);
             $stmt->bindParam(':contato', $contato);
             $stmt->bindParam(':email', $email);
             $stmt->bindParam(':telefone', $telefone);
             $stmt->bindParam(':id_representante', $id_representante, $id_representante === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
             $stmt->bindParam(':razao_social', $razao_social);
             $stmt->bindParam(':uf', $uf);
             $stmt->bindParam(':cnpj', $cnpj_limpo); // SALVA O CNPJ LIMPO
             $stmt->bindParam(':tipo', $tipo);

             // Executa e verifica o resultado
             if ($stmt->execute()) {
                 $affectedRows = $stmt->rowCount(); // Pega o número de linhas afetadas

                 if ($affectedRows > 0) {
                     // SUCESSO REAL: Linha realmente inserida
                     $alert_message = "Cliente adicionado com sucesso!";
                     $alert_type = 'success';

                     // Limpa o formulário resetando o array $cliente
                     $cliente = array_fill_keys(array_keys($cliente), '');
                     $cliente['id'] = null;
                     $cliente['id_representante'] = null;
                     // $cliente['cnpj'] será string vazia devido ao array_fill_keys

                 } else {
                     // Execute() retornou true, mas NENHUMA linha foi inserida. (Incomum para INSERT simples)
                     $stmtError = $stmt->errorInfo();
                     $pdoError = $pdo->errorInfo();
                     $alert_message = "Atenção: Comando executado, mas nenhum cliente foi adicionado (rowCount=0). Verifique os dados ou logs do banco.";
                     $alert_type = 'warning';
                     error_log("INSERT em cot_clientes executado sem erro aparente, mas rowCount=0. PDO Error: " . print_r($pdoError, true) . " Statement Error: " . print_r($stmtError, true));
                     // Mantém os dados no formulário ($cliente já contém os dados do POST)
                 }
             } else {
                 // Erro na execução da query (execute() retornou false)
                 $stmtError = $stmt->errorInfo();
                 $pdoError = $pdo->errorInfo();
                 $errorCode = $stmtError[1] ?? $pdoError[1] ?? 'N/A';
                 $errorMsg = $stmtError[2] ?? $pdoError[2] ?? 'Erro desconhecido';
                 $alert_message = "Erro [" . $errorCode . "] ao adicionar cliente: " . $errorMsg;
                 $alert_type = 'danger';
                 error_log("Falha ao executar INSERT em cot_clientes. PDO Error: " . print_r($pdoError, true) . " Statement Error: " . print_r($stmtError, true));
                 // Mantém os dados no formulário ($cliente já contém os dados do POST)
             }

        } catch (PDOException $e) {
             // Captura exceções durante prepare() ou execute()
             // error_log("Exceção PDO ao INSERIR cliente: " . $e->getMessage());
             if ($e->errorInfo[1] == 1062) { // Código de erro para Duplicate entry
                 $alert_message = "Erro: Este CNPJ já está cadastrado (violação de constraint UNIQUE).";
             } else {
                 $alert_message = "Erro crítico [" . $e->getCode() . "] ao salvar dados: " . $e->getMessage();
             }
             $alert_type = 'danger';
             // Mantém os dados no formulário ($cliente já contém os dados do POST)
        }
    } // Fim if $alert_message === null (antes do INSERT)
} // Fim do if ($_SERVER['REQUEST_METHOD'] === 'POST')

// (Restante do código PHP para definir $alert_message final e o HTML)

// Se não for POST ou se houve erro, $cliente conterá os dados a serem exibidos (vazio ou do POST com erro)

// Define a mensagem de alerta final (se não houver msg do POST, usa a msg inicial se existir)
if($alert_message === null && isset($_initial_alert_message)) {
    $alert_message = $_initial_alert_message;
    $alert_type = $_initial_alert_type;
}

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
        body { padding-top: 20px; padding-bottom: 60px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Título da página -->
        <h1 class="mb-4"><?= htmlspecialchars($page_title) ?></h1>

        <?php if ($alert_message): ?>
        <div class="alert alert-<?= htmlspecialchars($alert_type) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($alert_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Formulário Principal de Adição -->
        <form id="formClienteAdicionar" action="adicionar_cliente.php" method="POST">
            <!-- NÃO há campo oculto 'id' aqui -->

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
                    <label for="telefone" class="form-label">Telefone (apenas numeros) <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>" required>
                </div>

                 <div class="col-md-6">
                    <label for="cnpj" class="form-label">CNPJ (apenas numeros)<span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?= htmlspecialchars($cliente['cnpj'] ?? '') ?>" required>
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
                                // Seleciona representante se o formulário foi reenviado com erro
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
                <i class="bi bi-plus-circle"></i> Adicionar Cliente
            </button>
            <a href="javascript:void(0);" onclick="history.back(); return false;" class="btn btn-secondary btn-lg">
                 <i class="bi bi-x-lg"></i> Cancelar
            </a>

        </form>
        <!-- Fim Formulário Principal -->

    </div> <!-- Fim .container -->

      <!-- Scripts -->

    <!-- 1. jQuery (Necessário para os outros) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>

    <!-- 2. jQuery Mask Plugin (SEM integrity e crossorigin) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <!-- 3. Bootstrap JS Bundle (com integrity e crossorigin) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>

    <!-- 4. Seu script customizado (para aplicar a máscara) -->
    <script>
    $(document).ready(function() {
        // Aplica a máscara ao campo CNPJ
        $('#cnpj').mask('00.000.000/0000-00');
        // console.log('Máscara CNPJ aplicada.'); // Pode remover ou manter o console.log

        // (Seu outro código JS dentro do ready, se houver)
    });
    </script>

</body>
</html>