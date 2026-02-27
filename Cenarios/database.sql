-- database.sql

CREATE TABLE IF NOT EXISTS clientes (
    id_maino INT PRIMARY KEY,
    razao_social VARCHAR(255) NOT NULL,
    cnpj VARCHAR(20),
    cidade VARCHAR(100),
    uf CHAR(2)
);

CREATE TABLE IF NOT EXISTS produtos (
    id_maino INT PRIMARY KEY,
    codigo_interno VARCHAR(50),
    descricao VARCHAR(255),
    unidade VARCHAR(10)
);

CREATE TABLE IF NOT EXISTS estoque (
    id_produto INT PRIMARY KEY,
    quantidade_atual DECIMAL(15,4) DEFAULT 0,
    data_atualizacao DATETIME,
    FOREIGN KEY (id_produto) REFERENCES produtos(id_maino)
);

CREATE TABLE IF NOT EXISTS movimentacoes (
    id_maino INT PRIMARY KEY,
    id_cliente INT,
    id_produto INT,
    data DATE,
    quantidade DECIMAL(15,4),
    valor_unitario DECIMAL(15,2),
    tipo ENUM('entrada', 'saida'),
    INDEX idx_analise (id_cliente, id_produto, data),
    FOREIGN KEY (id_cliente) REFERENCES clientes(id_maino),
    FOREIGN KEY (id_produto) REFERENCES produtos(id_maino)
);

-- Tabela de configuração para controle de rotinas (Carga Incremental ETL)
CREATE TABLE IF NOT EXISTS sync_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(50) UNIQUE NOT NULL,
    valor VARCHAR(255) NOT NULL,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserindo chave inicial para a ultima sincronização de movimentações, o valor inicial assegura um Full Load ou no mínimo puxa os dados históricos desde 2000 caso a api permita
INSERT IGNORE INTO sync_config (chave, valor) VALUES ('ultima_sincronizacao_movimentacoes', '2000-01-01 00:00:00');
