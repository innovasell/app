<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once 'conexao.php';


require_once __DIR__ . '/vendor/autoload.php';
require_once 'pdf_generator.php';

function logToFile($message, $logFileName = 'erroslog.txt')
{
    // ... (sua função de log aqui, se necessário manter) ...
    $logFilePath = __DIR__ . '/' . $logFileName;
    $timestamp = date("Y-m-d H:i:s");
    $logEntry = "[{$timestamp}] " . $message . PHP_EOL;
    @file_put_contents($logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
}

if (!isset($_SESSION['representante_email'])) {
    die("Acesso não autorizado. Faça login.");
}

$pedido_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$pedido_id || $pedido_id <= 0) {
    die("Erro: ID do pedido inválido ou não fornecido.");
}

try {
    // Chama a função geradora no modo 'I' (Inline/Browser)
    generateSamplePdf($pedido_id, $pdo, 'I');

} catch (Exception $e) {
    logToFile("ERRO GERAL ao gerar PDF pedido ID {$pedido_id}: " . $e->getMessage());
    die('Erro inesperado ao gerar o PDF: ' . $e->getMessage());
}
?>