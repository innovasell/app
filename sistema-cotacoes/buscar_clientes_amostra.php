<?php
// buscar_clientes_amostra.php
// Script específico para buscar dados completos de clientes para o formulário de pedido de amostra.

require_once 'conexao.php'; // Garante que $pdo (ou sua conexão) esteja disponível

// Define o tipo de conteúdo como JSON ANTES de qualquer output
header('Content-Type: application/json');

$termo = $_GET['q'] ?? ''; // Pega o termo de busca da URL (?q=...)
$clientes = []; // Inicializa um array vazio para os resultados

// --- Validação Opcional: Buscar apenas se o termo tiver um tamanho mínimo ---
// Descomente a linha abaixo se quiser exigir pelo menos X caracteres
// define('MIN_SEARCH_LENGTH', 2); // Exemplo: mínimo 2 caracteres
// if (strlen($termo) >= MIN_SEARCH_LENGTH) {
// --- Fim da Validação Opcional ---

// Garante que o termo não está vazio para evitar buscar tudo
if (!empty($termo)) {
    try {
        // Prepara a consulta SQL - SELECIONE TODAS AS COLUNAS NECESSÁRIAS!
        $sql = "SELECT
                    id,
                    razao_social,
                    cnpj,
                    uf,
                    contato,
                    email,
                    telefone
                FROM
                    cot_clientes  -- Confirme se 'cot_clientes' é o nome correto da sua tabela
                WHERE
                    -- Busca em campos relevantes (ajuste conforme sua necessidade)
                    razao_social LIKE :termo OR
                    cnpj LIKE :termo OR
                    nome LIKE :termo -- Se você também tem um campo 'nome' para buscar
                    -- Considerar buscar em email ou contato?
                    -- email LIKE :termo OR
                    -- contato LIKE :termo
                ORDER BY
                    razao_social ASC -- Ordena para facilitar a visualização
                LIMIT 50"; // Limita o número de resultados para performance

        $stmt = $pdo->prepare($sql);

        // Adiciona os wildcards '%' para buscar correspondências parciais
        $searchTerm = '%' . $termo . '%';
        $stmt->bindParam(':termo', $searchTerm, PDO::PARAM_STR);

        $stmt->execute();

        // Busca todos os resultados como um array associativo
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Em caso de erro no banco, retorna um JSON com a mensagem de erro
        // IMPORTANTE: Em produção, logue o erro em vez de expô-lo diretamente!
        error_log("Erro em buscar_clientes_amostra.php: " . $e->getMessage()); // Loga o erro
        echo json_encode(['erro' => 'Erro ao consultar o banco de dados.']);
        exit; // Interrompe o script
    }
} // Fecha o if (!empty($termo))

// --- Descomente a linha abaixo se adicionou a validação de tamanho mínimo ---
// } // Fecha o if (strlen($termo) >= MIN_SEARCH_LENGTH) {
// --- Fim da Validação Opcional ---


// Envia a resposta como JSON (será um array vazio [] se não houver termo ou resultados)
echo json_encode($clientes);

?>