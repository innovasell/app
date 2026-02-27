<?php
/**
 * Setup do Banco de Dados - Sistema de Gestão de Despesas de Viagens
 * 
 * Este script cria a tabela necessária para armazenar as despesas importadas do VIAGEM EXPRESS
 */

require_once 'config.php';

// Detecta se é chamada via API
$isApi = isset($_GET['api']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($isApi) {
    header('Content-Type: application/json');
}

// SQL para criar a tabela
$sql = "CREATE TABLE IF NOT EXISTS viagem_express_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Informações do Cliente
    cod_cliente VARCHAR(50),
    cliente VARCHAR(255),
    
    -- Informações da Nota
    dt_emissao DATE,
    nota_debito_credito VARCHAR(100),
    reserva VARCHAR(100),
    solicitante VARCHAR(255),
    centro_custo VARCHAR(100),
    
    -- Informações da Viagem
    num_viagem VARCHAR(100),
    sucursal VARCHAR(100),
    conta_contabil_pax VARCHAR(100),
    passageiro VARCHAR(255),
    
    -- Produto (coluna principal para categorização)
    produto VARCHAR(255),
    prod_classificacao VARCHAR(255),
    prod_categoria VARCHAR(255),
    fornecedor VARCHAR(255),
    
    -- Localização
    cidade VARCHAR(255),
    classe_voo VARCHAR(50),
    
    -- Datas
    check_in DATE,
    saida_periodo VARCHAR(255),
    
    -- Valores Financeiros
    tarifa_balcao DECIMAL(15,2),
    valor_original VARCHAR(100),
    tarifa_sugerida DECIMAL(15,2),
    cambio DECIMAL(10,4),
    tarifa_reais DECIMAL(15,2),
    taxa_embarque DECIMAL(15,2),
    tx_serv DECIMAL(15,2),
    tx_du DECIMAL(15,2),
    desconto DECIMAL(15,2),
    taxas DECIMAL(15,2),
    outros_recebimentos DECIMAL(15,2),
    taxas_extras DECIMAL(15,2),
    total DECIMAL(15,2),
    
    -- Faturamento
    num_fatura VARCHAR(100),
    vencimento DATE,
    tkt_voucher_os VARCHAR(255),
    cod_integracao VARCHAR(100),
    localizador VARCHAR(100),
    
    -- Classificação Interna
    depto_setor VARCHAR(255),
    motivo_viagem TEXT,
    rota VARCHAR(255),
    rota_completa_cidades TEXT,
    autorizador VARCHAR(255),
    projeto VARCHAR(255),
    matricula VARCHAR(100),
    atividade VARCHAR(255),
    observacao TEXT,
    
    -- Confirmação e Logística
    num_confirmacao VARCHAR(100),
    local_retirada VARCHAR(255),
    local_devolucao VARCHAR(255),
    veiculo VARCHAR(255),
    antecedencia INT,
    numero_sr VARCHAR(100),
    observacao_fatura TEXT,
    
    -- Agência
    agencia_raz_social VARCHAR(255),
    agencia_cnpj VARCHAR(20),
    emissor VARCHAR(255),
    
    -- Categorização (nossa lógica - EDITÁVEL PELO USUÁRIO)
    categoria_despesa ENUM('Passagem Aérea', 'Hotel', 'Seguro', 'Transporte', 'Outros', 'Não Categorizado') DEFAULT 'Não Categorizado',
    categoria_auto BOOLEAN DEFAULT TRUE COMMENT 'TRUE se categorizou automaticamente, FALSE se editado manualmente',
    
    -- Controle
    batch_id VARCHAR(100) COMMENT 'ID do lote de importação',
    data_importacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_cliente (cod_cliente),
    INDEX idx_categoria (categoria_despesa),
    INDEX idx_data_emissao (dt_emissao),
    INDEX idx_batch (batch_id),
    INDEX idx_passageiro (passageiro),
    INDEX idx_num_fatura (num_fatura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Executa a criação da tabela
if ($conn->query($sql) === TRUE) {
    if ($isApi) {
        echo json_encode(['success' => true, 'message' => 'Tabela criada com sucesso!']);
    } else {
        echo "<h3 style='color: green;'>✓ Tabela 'viagem_express_expenses' criada com sucesso!</h3>";
        echo "<p>A estrutura do banco de dados está pronta para receber as importações do VIAGEM EXPRESS.</p>";
        echo "<p><a href='index.php'>← Voltar para Dashboard</a> | <a href='importar.php'>Importar Dados →</a></p>";
    }
} else {
    if ($isApi) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $conn->error]);
    } else {
        echo "<h3 style='color: red;'>✗ Erro ao criar tabela:</h3>";
        echo "<p>" . $conn->error . "</p>";
    }
}

$conn->close();
?>