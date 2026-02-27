<?php
class AnaliseConsumo {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function calcularMetricas($idCliente, $idProduto) {
        $anoAtual = 2026; // Conforme o contexto do sistema

        // 1. Média de Preço 2026 e Histórica
        $sqlPrecos = "SELECT 
            AVG(CASE WHEN YEAR(data) = :ano THEN valor_unitario END) as media_atual,
            AVG(CASE WHEN YEAR(data) < :ano THEN valor_unitario END) as media_historica
            FROM movimentacoes 
            WHERE id_cliente = :cli AND id_produto = :prod";
        
        $stmt = $this->db->prepare($sqlPrecos);
        $stmt->execute(['ano' => $anoAtual, 'cli' => $idCliente, 'prod' => $idProduto]);
        $precos = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Consumo Médio Mensal (Baseado em Saídas)
        $sqlConsumo = "SELECT 
            SUM(quantidade) as total_saido,
            COUNT(DISTINCT DATE_FORMAT(data, '%Y-%m')) as meses_ativos
            FROM movimentacoes 
            WHERE id_cliente = :cli AND id_produto = :prod AND tipo = 'saida'";
        
        $stmt = $this->db->prepare($sqlConsumo);
        $stmt->execute(['cli' => $idCliente, 'prod' => $idProduto]);
        $consumo = $stmt->fetch(PDO::FETCH_ASSOC);

        $mediaMensal = ($consumo['meses_ativos'] > 0) ? ($consumo['total_saido'] / $consumo['meses_ativos']) : 0;

        // 3. Previsão de Estoque
        $sqlEstoque = "SELECT quantidade_atual FROM estoque WHERE id_produto = :prod";
        $stmt = $this->db->prepare($sqlEstoque);
        $stmt->execute(['prod' => $idProduto]);
        $est = $stmt->fetch(PDO::FETCH_ASSOC);
        $estoqueAtual = $est['quantidade_atual'] ?? 0;

        $previsaoMeses = ($mediaMensal > 0) ? ($estoqueAtual / $mediaMensal) : -1;

        return [
            'media_2026' => $precos['media_atual'] ?? 0,
            'media_historica' => $precos['media_historica'] ?? 0,
            'consumo_mensal' => $mediaMensal,
            'previsao_estoque' => $previsaoMeses,
            'alerta_estoque' => ($previsaoMeses >= 0 && $previsaoMeses < 1) // Menos de 1 mês
        ];
    }
}
?>
