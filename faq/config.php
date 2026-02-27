<?php
/*
 * Arquivo de Configuração do Banco de Dados
 *
 * IMPORTANTE: Guarde este arquivo em um local seguro
 * e nunca o exponha publicamente.
 */

// Suas credenciais fornecidas
// Credenciais de Acesso (Hostinger - Atualizado)
$db_host = "localhost";
$db_user = "u849249951_innovasell";
$db_pass = "Invti@169";
$db_name = "u849249951_innovasell";
$db_port = "3306";

// Cria a conexão com o banco de dados usando o estilo orientado a objetos do MySQLi
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// ==================================================================
// <-- ADICIONE ESTA LINHA AQUI
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// ==================================================================

// Verifica se a conexão foi bem-sucedida
if ($conn->connect_error) {
    // Se houver um erro, interrompe a execução do script e exibe a mensagem de erro.
    // Em um ambiente de produção, seria melhor registrar esse erro em um log em vez de exibi-lo na tela.
    die("Falha na conexão com o banco de dados: " . $conn->connect_error);
}

// Define o conjunto de caracteres para UTF-8 (essencial para acentos e caracteres especiais)
if (!$conn->set_charset("utf8mb4")) {
    // Se houver um erro ao definir o charset, exibe uma mensagem.
    printf("Erro ao definir o charset para utf8mb4: %s\n", $conn->error);
    exit();
}

// A variável $conn agora está pronta para ser usada nos seus outros scripts PHP.

// Adicione esta linha no final do seu arquivo config.php
define('APP_VERSION', '1.1');