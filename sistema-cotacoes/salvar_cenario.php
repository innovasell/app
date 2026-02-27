<?php
file_put_contents('debug_hit.txt', 'Hit at ' . date('H:i:s') . ' Method: ' . $_SERVER['REQUEST_METHOD']);
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // DEBUG: Logar o que esta chegando
    file_put_contents('debug_post_payload.txt', print_r($_POST, true));

    try {
        $pdo->beginTransaction();

        // Dados do cabeçalho (SEM cliente, SEM tempo de venda)
        $id_fornecedor = (int) $_POST['id_fornecedor'];

        // Validação básica do fornecedor
        if ($id_fornecedor <= 0) {
            throw new Exception("Fornecedor inválido. Por favor, selecione um fornecedor da lista.");
        }

        $fornecedor = trim($_POST['fornecedor']);
        $dolar_compra = (float) str_replace(',', '.', $_POST['dolar_compra']);
        $dolar_venda = (float) str_replace(',', '.', $_POST['dolar_venda']);
        // Dados do Cabeçalho
        $representante_id = $_SESSION['usuario_id'] ?? 1;
        $criado_por = $_POST['criado_por'] ?? '';
        // Modal e Taxa agora são por item/bloco, o header pode ficar com null ou um valor simbólico "Múltiplos"
        $modal = "Múltiplos";
        $data_criacao = $_POST['data']; // Renamed from $data to $data_criacao to match original variable name
        $validade = $_POST['validade'] ?? date('Y-m-d', strtotime('+7 days'));
        // Taxa também varia, salvaremos 0.00 no header
        $taxa_juros_mensal = 0.00;
        $observacoes = $_POST['observacoes'] ?? '';
        // $dolar_compra and $dolar_venda are already defined above.

        // Gerar número do cenário (timestamp Unix para caber em INT)
        $num_cenario = time();

        // Inserir cenário (sem cliente, sem tempo de venda)
        $sqlCenario = "INSERT INTO cot_cenarios_importacao 
                   (num_cenario, id_fornecedor, fornecedor, representante_id,
                    dolar_compra, dolar_venda, taxa_juros_mensal, 
                    modal,
                    data_criacao, criado_por, observacoes, validade)
                   VALUES 
                   (:num_cenario, :id_fornecedor, :fornecedor, :representante_id,
                    :dolar_compra, :dolar_venda, :taxa_juros_mensal,
                    :modal,
                    :data_criacao, :criado_por, :observacoes, :validade)";

        $stmtCenario = $pdo->prepare($sqlCenario);
        $stmtCenario->execute([
            ':num_cenario' => $num_cenario,
            ':id_fornecedor' => $id_fornecedor,
            ':fornecedor' => $fornecedor,
            ':representante_id' => $representante_id,
            ':dolar_compra' => $dolar_compra,
            ':dolar_venda' => $dolar_venda,
            ':taxa_juros_mensal' => $taxa_juros_mensal,
            ':modal' => $modal,
            ':data_criacao' => $data_criacao,
            ':criado_por' => $criado_por,
            ':observacoes' => $observacoes,
            ':validade' => $validade
        ]);

        // Inserir itens
        if (isset($_POST['itens']) && is_array($_POST['itens'])) {
            $sqlItem = "INSERT INTO cot_cenarios_itens 
                  (num_cenario, id_cliente, cliente, uf, codigo_produto, produto, spec_exclusiva, tempo_venda_meses, qtd, unidade, embalagem, landed_usd_kg,
                   total_landed_usd, valor_futuro, total_valor_futuro, 
                   preco_unit_venda_usd_kg, total_venda_usd, gm_percentual,
                   nome_sub_cenario, data_necessidade, necessidade_cliente, modal, taxa_juros_mensal, tipo_demanda)
                  VALUES 
                  (:num_cenario, :id_cliente, :cliente, :uf, :codigo_produto, :produto, :spec_exclusiva, :tempo_venda_meses, :qtd, :unidade, :embalagem, :landed_usd_kg,
                   :total_landed_usd, :valor_futuro, :total_valor_futuro,
                   :preco_unit_venda_usd_kg, :total_venda_usd, :gm_percentual,
                   :nome_sub_cenario, :data_necessidade, :necessidade_cliente, :modal, :taxa_juros_mensal, :tipo_demanda)";

            $stmtItem = $pdo->prepare($sqlItem);

            foreach ($_POST['itens'] as $item) {
                $stmtItem->execute([
                    ':num_cenario' => $num_cenario,
                    ':id_cliente' => isset($item['id_cliente']) && !empty($item['id_cliente']) ? (int) $item['id_cliente'] : null,
                    ':cliente' => trim($item['cliente'] ?? ''),
                    ':uf' => trim($item['uf'] ?? ''),
                    ':codigo_produto' => trim($item['codigo']),
                    ':produto' => trim($item['produto']),
                    ':spec_exclusiva' => isset($item['spec_exclusiva']) ? (int) $item['spec_exclusiva'] : 0,
                    ':tempo_venda_meses' => isset($item['tempo_venda_meses']) ? (int) $item['tempo_venda_meses'] : 0, // Agora default é 0
                    ':qtd' => (float) $item['qtd'],
                    ':unidade' => trim($item['unidade']),
                    ':embalagem' => trim($item['embalagem'] ?? ''),
                    ':landed_usd_kg' => (float) $item['landed_usd_kg'],
                    ':total_landed_usd' => (float) $item['total_landed_usd'],
                    ':valor_futuro' => (float) $item['valor_futuro'],
                    ':total_valor_futuro' => (float) $item['total_valor_futuro'],
                    ':preco_unit_venda_usd_kg' => (float) $item['preco_unit_venda_usd_kg'],
                    ':total_venda_usd' => (float) $item['total_venda_usd'],
                    ':gm_percentual' => (float) $item['gm_percentual'],
                    ':nome_sub_cenario' => trim($item['nome_sub_cenario'] ?? ''),
                    ':data_necessidade' => (isset($item['data_necessidade']) && $item['data_necessidade'] !== '') ? $item['data_necessidade'] : null,
                    ':necessidade_cliente' => (isset($item['necessidade_cliente']) && $item['necessidade_cliente'] !== '') ? $item['necessidade_cliente'] : null,
                    ':modal' => trim($item['modal'] ?? ''),
                    ':taxa_juros_mensal' => isset($item['taxa_juros_mensal']) ? (float) $item['taxa_juros_mensal'] : 0.00,
                    ':tipo_demanda' => trim($item['tipo_demanda'] ?? '')
                ]);
            }
        }

        $pdo->commit();

        $_SESSION['mensagem'] = 'Cenário de importação salvo com sucesso!';
        $_SESSION['tipo_mensagem'] = 'success';
        header("Location: consultar_cenarios.php?sucesso=1&num_cenario=$num_cenario");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['mensagem'] = 'Erro ao salvar cenário: ' . $e->getMessage();
        $_SESSION['tipo_mensagem'] = 'danger';
        error_log('Erro ao salvar cenário: ' . $e->getMessage());
        header('Location: incluir_cenario_importacao.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['mensagem'] = 'Erro: ' . $e->getMessage();
        $_SESSION['tipo_mensagem'] = 'danger';
        header('Location: incluir_cenario_importacao.php');
        exit();
    }
}
?>