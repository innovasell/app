<?php
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';

try {
    echo "<h2>Setup DB — Módulo de Comissões</h2>";

    // ── 1. Lotes de processamento ─────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS com_commission_batches (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        nome        VARCHAR(150) NULL COMMENT 'Nome descritivo do lote',
        periodo     VARCHAR(20) NULL COMMENT 'Ex: 2026-01',
        total_items INT DEFAULT 0,
        total_nfs   INT DEFAULT 0,
        obs         TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    // Adiciona coluna nome se ainda nao existir (migracao)
    try { $pdo->exec("ALTER TABLE com_commission_batches ADD COLUMN nome VARCHAR(150) NULL AFTER created_at"); } catch(Exception $ex) {}  
    echo "Tabela 'com_commission_batches' OK.<br>";

    // ── CFOPs Configurados ────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS com_cfop_rules (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        cfop        VARCHAR(10) NOT NULL UNIQUE,
        description VARCHAR(200) NULL,
        is_active   TINYINT(1) DEFAULT 1,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    // Insere CFOPs padrao apenas se a tabela estiver vazia
    $cnt = $pdo->query("SELECT COUNT(*) FROM com_cfop_rules")->fetchColumn();
    if ($cnt == 0) {
        $pdo->exec("INSERT INTO com_cfop_rules (cfop, description) VALUES
            ('5102', 'Venda de mercadoria adquirida ou recebida de terceiros'),
            ('5123', 'Venda de mercadoria adquirida ou recebida de terceiros - Simples Nacional'),
            ('6102', 'Venda de mercadoria adquirida - Operação Interestadual'),
            ('6123', 'Venda de mercadoria adquirida - Interestadual Simples Nacional'),
            ('6106', 'Venda de produto industrializado - Interestadual'),
            ('6110', 'Venda de mercadoria sujeita ao regime de substituição tributária - Interestadual'),
            ('5106', 'Venda de produto industrializado - Intraestadual'),
            ('5119', 'Venda de mercadoria sujeita ao regime de substituição tributária - Intraestadual')
        ");
    }
    echo "Tabela 'com_cfop_rules' OK.<br>";

    // ── 2. Itens calculados ───────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS com_commission_items (
        id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        batch_id                INT UNSIGNED NOT NULL,

        -- Identificação
        nfe                     VARCHAR(30)    NOT NULL  COMMENT 'Ex: 001/000023309',
        data_nf                 DATE           NULL,
        pedido                  VARCHAR(20)    NULL,
        cfop                    VARCHAR(6)     NULL,
        codigo                  VARCHAR(30)    NULL,
        descricao               VARCHAR(255)   NULL      COMMENT 'Sem embalagem',
        embalagem               VARCHAR(30)    NULL      COMMENT 'Ex: 1 KG',
        fabricante              VARCHAR(100)   NULL,
        representante           VARCHAR(100)   NULL,
        cliente                 VARCHAR(255)   NULL,

        -- Quantidades e valores brutos
        qtde                    DECIMAL(18,4)  NULL,
        valor_bruto             DECIMAL(18,2)  NULL,
        icms                    DECIMAL(18,2)  NULL DEFAULT 0,
        pis                     DECIMAL(18,2)  NULL DEFAULT 0,
        cofins                  DECIMAL(18,2)  NULL DEFAULT 0,

        -- Valores líquidos (NET)
        venda_net               DECIMAL(18,2)  NULL COMMENT 'valor_bruto - icms - pis - cofins',
        preco_net_un            DECIMAL(18,4)  NULL COMMENT 'venda_net / qtde',

        -- Preço de lista (cot_price_list)
        preco_lista_usd         DECIMAL(18,4)  NULL,
        preco_lista_brl         DECIMAL(18,4)  NULL,
        ptax_usado              DECIMAL(10,4)  NULL,

        -- Desconto
        desconto_brl            DECIMAL(18,4)  NULL COMMENT 'preco_net_un - preco_lista_brl',
        desconto_pct            DECIMAL(8,4)   NULL COMMENT 'percentual 0-1',

        -- Comissão
        comissao_base_pct       DECIMAL(8,4)   NULL COMMENT '0.01, 0.009, 0.007...',
        pm_dias                 DECIMAL(8,2)   NULL COMMENT 'Prazo médio ponderado em dias',
        pm_semanas              DECIMAL(8,2)   NULL COMMENT 'pm_dias / 7',
        ajuste_prazo_pct        DECIMAL(8,4)   NULL COMMENT 'positivo=antecipação, negativo=atraso',
        comissao_final_pct      DECIMAL(8,4)   NULL COMMENT 'base + ajuste, mín 0.0005',
        valor_comissao          DECIMAL(18,2)  NULL COMMENT 'comissao_final_pct * venda_net',

        -- Teto / Prêmio
        flag_teto               TINYINT(1)     DEFAULT 0 COMMENT '1 se valor_comissao > 25000',
        valor_premio            DECIMAL(18,2)  NULL COMMENT '10% do excedente ao teto 25000',

        -- Controle
        flag_aprovacao          TINYINT(1)     DEFAULT 0 COMMENT '1 se desc>20% ou PM>42d',
        lista_nao_encontrada    TINYINT(1)     DEFAULT 0 COMMENT '1 se nao achou preco lista',
        obs                     TEXT           NULL,

        created_at              TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (batch_id) REFERENCES com_commission_batches(id) ON DELETE CASCADE,
        INDEX idx_batch         (batch_id),
        INDEX idx_nfe           (nfe),
        INDEX idx_representante (representante)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Tabela 'com_commission_items' OK.<br>";

    // v7: coluna de vencimentos para auditoria de PM (ignora se já existe)
    try {
        $pdo->exec("ALTER TABLE com_commission_items ADD COLUMN vencimentos_json TEXT NULL AFTER pm_semanas");
        echo "Coluna 'vencimentos_json' adicionada.<br>";
    } catch (PDOException $e) { echo "Coluna 'vencimentos_json' já existe (OK).<br>"; }

    echo "<h3>✅ Setup concluído com sucesso!</h3>";
    echo "<p><a href='comissoes.php'>→ Ir para o módulo de Comissões</a></p>";

} catch (PDOException $e) {
    die("<b>Erro:</b> " . htmlspecialchars($e->getMessage()));
}
