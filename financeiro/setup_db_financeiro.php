<?php
require_once __DIR__ . '/../sistema-cotacoes/conexao.php';

try {
    echo "Iniciando criação das tabelas do módulo Financeiro...<br><br>";

    $sqlLotes = "CREATE TABLE IF NOT EXISTS fin_lotes (
      id          INT AUTO_INCREMENT PRIMARY KEY,
      regime      ENUM('presumido','real') NOT NULL DEFAULT 'presumido',
      total_nfs   INT DEFAULT 0,
      criado_em   DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlLotes);
    echo "Tabela 'fin_lotes' verificada/criada com sucesso.<br>";

    $sqlNotas = "CREATE TABLE IF NOT EXISTS fin_notas (
      id          INT AUTO_INCREMENT PRIMARY KEY,
      lote_id     INT,
      chNFe       VARCHAR(44) UNIQUE,
      nNF         VARCHAR(20),
      dhEmi       DATE,
      cnpj_emit   VARCHAR(18),
      nome_emit   VARCHAR(150),
      cnpj_dest   VARCHAR(18),
      nome_dest   VARCHAR(150),
      v_prod      DECIMAL(15,2),
      v_desc      DECIMAL(15,2),
      v_frete     DECIMAL(15,2),
      v_icms      DECIMAL(15,2),
      v_ipi       DECIMAL(15,2),
      v_pis       DECIMAL(15,2),
      v_cofins    DECIMAL(15,2),
      v_nf        DECIMAL(15,2),
      FOREIGN KEY (lote_id) REFERENCES fin_lotes(id) ON DELETE CASCADE
    )";
    $pdo->exec($sqlNotas);
    echo "Tabela 'fin_notas' verificada/criada com sucesso.<br>";

    $sqlItens = "CREATE TABLE IF NOT EXISTS fin_itens (
      id            INT AUTO_INCREMENT PRIMARY KEY,
      nota_id       INT,
      n_item        INT,
      c_prod        VARCHAR(60),
      x_prod        VARCHAR(120),
      ncm           VARCHAR(10),
      cfop          VARCHAR(5),
      u_com         VARCHAR(10),
      q_com         DECIMAL(15,4),
      v_un_com      DECIMAL(15,4),
      v_prod        DECIMAL(15,2),
      v_desc        DECIMAL(15,2),
      bc_pis_cofins DECIMAL(15,2),
      p_pis         DECIMAL(5,2),
      v_pis         DECIMAL(15,2),
      p_cofins      DECIMAL(5,2),
      v_cofins      DECIMAL(15,2),
      bc_icms       DECIMAL(15,2),
      p_icms        DECIMAL(5,2),
      v_icms        DECIMAL(15,2),
      bc_ipi        DECIMAL(15,2),
      p_ipi         DECIMAL(5,2),
      v_ipi         DECIMAL(15,2),
      FOREIGN KEY (nota_id) REFERENCES fin_notas(id) ON DELETE CASCADE
    )";
    $pdo->exec($sqlItens);
    echo "Tabela 'fin_itens' verificada/criada com sucesso.<br>";

    // Adicionar coluna tipo em fin_notas (idempotente)
    try {
        $pdo->exec("ALTER TABLE fin_notas ADD COLUMN tipo ENUM('entrada','saida') NOT NULL DEFAULT 'entrada' AFTER lote_id");
        echo "Coluna 'tipo' adicionada à 'fin_notas' com sucesso.<br>";
    } catch (PDOException $e) {
        echo "Coluna 'tipo' já existe em 'fin_notas' (ok).<br>";
    }

    echo "<br><b>Instalação concluída com sucesso!</b>";

} catch (PDOException $e) {
    echo "<br><b>Erro ao criar tabelas:</b> " . $e->getMessage();
}
