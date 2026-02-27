<?php
require_once 'gerador-formulas/config.php';

// Verify file existence to ensure path is correct relative to execution
if (!file_exists('gerador-formulas/config.php')) {
    die("Erro: Execute este script na raiz do projeto (c:\Users\hecto\Documents\H Hansen\innovasellcloud)");
}

echo "<h1>Instalação do Banco de Dados - Gerador de Fórmulas</h1>";

$sql_tables = [
    "CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        is_admin TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS formulacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome_formula VARCHAR(255) NOT NULL,
        codigo_formula VARCHAR(50) NOT NULL UNIQUE,
        antigo_codigo VARCHAR(50),
        categoria VARCHAR(10),
        desenvolvida_para VARCHAR(255),
        solicitada_por VARCHAR(255),
        caminho_pdf VARCHAR(255),
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS contadores (
        id VARCHAR(50) PRIMARY KEY,
        ultimo_valor INT NOT NULL
    )",
    "CREATE TABLE IF NOT EXISTS ativos_destaque (
        id INT AUTO_INCREMENT PRIMARY KEY,
        formulacao_id INT NOT NULL,
        nome_ativo VARCHAR(255) NOT NULL,
        descricao TEXT,
        FOREIGN KEY (formulacao_id) REFERENCES formulacoes(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS sub_formulacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        formulacao_id INT NOT NULL,
        nome_sub_formula VARCHAR(255) NOT NULL,
        modo_preparo TEXT,
        FOREIGN KEY (formulacao_id) REFERENCES formulacoes(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS fases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sub_formulacao_id INT NOT NULL,
        nome_fase VARCHAR(100) NOT NULL,
        FOREIGN KEY (sub_formulacao_id) REFERENCES sub_formulacoes(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS ingredientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fase_id INT NOT NULL,
        materia_prima VARCHAR(255) NOT NULL,
        inci_name VARCHAR(255),
        percentual DECIMAL(10,4),
        destaque TINYINT(1) DEFAULT 0,
        FOREIGN KEY (fase_id) REFERENCES fases(id) ON DELETE CASCADE
    )"
];

foreach ($sql_tables as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green;'>Tabela criada/verificada com sucesso.</p>";
    } else {
        echo "<p style='color:red;'>Erro ao criar tabela: " . $conn->error . "</p>";
    }
}

// Default User
$email = 'admin@innovasell.com.br';
$pass = '123456';
$hash = password_hash($pass, PASSWORD_DEFAULT);

$stmtCheck = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmtCheck->bind_param("s", $email);
$stmtCheck->execute();
$res = $stmtCheck->get_result();

if ($res->num_rows == 0) {
    $stmtIns = $conn->prepare("INSERT INTO usuarios (nome, email, senha, is_admin) VALUES ('Admin', ?, ?, 1)");
    $stmtIns->bind_param("ss", $email, $hash);
    if ($stmtIns->execute()) {
        echo "<p style='color:blue;'>Usuário Admin padrão criado (Email: $email / Senha: $pass)</p>";
    } else {
        echo "<p style='color:red;'>Erro ao criar usuário: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange;'>Usuário Admin já existe.</p>";
}

echo "<p>Concluído.</p>";
?>