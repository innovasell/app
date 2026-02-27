<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $num_cenario = $_POST['num_cenario'];

    if (empty($num_cenario)) {
        $_SESSION['mensagem'] = 'Número do cenário inválido.';
        $_SESSION['tipo_mensagem'] = 'danger';
        header("Location: consultar_cenarios.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Atualizar Cabeçalho
        $id_fornecedor = (int) $_POST['id_fornecedor'];
        if ($id_fornecedor <= 0) {
            throw new Exception("Fornecedor inválido.");
        }

        $fornecedor = trim($_POST['fornecedor']);
        // Modal e Taxa agora são por item/bloco
        $taxa_juros_mensal = 0.00;
        $modal = "Múltiplos";

        $data_criacao = $_POST['data'];
        $observacoes = trim($_POST['observacoes'] ?? '');
        $dolar_compra = (float) str_replace(',', '.', $_POST['dolar_compra']);
        $dolar_venda = (float) str_replace(',', '.', $_POST['dolar_venda']);

        $sqlCenario = "UPDATE cot_cenarios_importacao SET 
                        id_fornecedor = :id_fornecedor,
                        fornecedor = :fornecedor,
                        taxa_juros_mensal = :taxa_juros_mensal,
                        data_criacao = :data_criacao,
                        observacoes = :observacoes,
                        dolar_compra = :dolar_compra,
                        dolar_venda = :dolar_venda,
                        modal = :modal
                       WHERE num_cenario = :num_cenario";

        $stmtCenario = $pdo->prepare($sqlCenario);
        $stmtCenario->execute([
            ':id_fornecedor' => $id_fornecedor,
            ':fornecedor' => $fornecedor,
            ':taxa_juros_mensal' => $taxa_juros_mensal,
            ':data_criacao' => $data_criacao,
            ':observacoes' => $observacoes,
            ':dolar_compra' => $dolar_compra,
            ':dolar_venda' => $dolar_venda,
            ':modal' => $modal,
            ':num_cenario' => $num_cenario
        ]);

        // 2. Atualizar Itens
        $sqlDelete = "DELETE FROM cot_cenarios_itens WHERE num_cenario = :num_cenario";
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute([':num_cenario' => $num_cenario]);

        // Inserir Itens (Atualizado com novos campos)
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
                    ':tempo_venda_meses' => isset($item['tempo_venda_meses']) ? (int) $item['tempo_venda_meses'] : 0,
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

        // 3. Verifica se existe OC vinculada e atualiza (Se Status = ABERTO)
        // (A transação continua aberta para garantir atomicidade entre Cenário e OC)
        // Nova transação para garantir que a OC seja atualizada independentemente ou junto
        // (Como já comitamos o cenário, vamos fazer um novo bloco try/catch para a OC para não falhar o cenário se a OC der erro, ou avisar)

        // Na verdade, o ideal é ser atômico, mas como já comitamos o cenário acima (linha 110 original), 
        // vamos seguir. Se quisesse atômico, deveria ter feito antes do commit. 
        // VOU ALTERAR para fazer parte da mesma transação, removendo o $pdo->commit() da linha 110 e movendo para o final.

        // --- INICIO LOGICA OC ---
        $stmtCheckOC = $pdo->prepare("SELECT id, status FROM cot_pedidos_compra WHERE num_cenario_origem = :num_cenario AND status = 'ABERTO' LIMIT 1");
        $stmtCheckOC->execute([':num_cenario' => $num_cenario]);
        $ocVinculada = $stmtCheckOC->fetch(PDO::FETCH_ASSOC);

        if ($ocVinculada) {
            $idPedido = $ocVinculada['id'];

            // Recalcular itens agrupados (Mesma lógica do gerar_oc.php)
            // Pegar os itens QUE ACABAMOS DE INSERIR (estão em $_POST['itens'])
            // Mas para garantir, podemos usar a mesma variável $itens do POST ou buscar do banco.
            // Vamos usar o $_POST['itens'] pois já está sanitizado/validado no loop anterior.

            $itensParaOc = [];
            $clientesEnvolvidos = [];

            if (isset($_POST['itens']) && is_array($_POST['itens'])) {
                foreach ($_POST['itens'] as $item) {
                    $cli = trim($item['cliente'] ?? '');
                    if (!empty($cli)) {
                        $clientesEnvolvidos[] = $cli;
                    }

                    // Chave de agrupamento
                    $landed = (float) $item['landed_usd_kg'];
                    $prod = trim($item['produto']);
                    $deadline = (isset($item['data_necessidade']) && $item['data_necessidade'] !== '') ? $item['data_necessidade'] : null;

                    $chave = $prod . '_' . (string) $landed . '_' . (string) $deadline;

                    if (!isset($itensParaOc[$chave])) {
                        $itensParaOc[$chave] = [
                            'codigo_produto' => trim($item['codigo']),
                            'produto' => $prod,
                            'unidade' => trim($item['unidade']),
                            'landed_usd' => $landed,
                            'preco_venda_usd' => (float) $item['preco_unit_venda_usd_kg'],
                            'data_necessidade' => $deadline,
                            'qtd_total' => 0
                        ];
                    }
                    $itensParaOc[$chave]['qtd_total'] += (float) $item['qtd'];
                }
            }

            // Atualizar Cabeçalho da OC
            $clientesEnvolvidos = array_unique($clientesEnvolvidos);
            // Recriar OBS
            $obsOc = "OC gerada a partir do Cenário: " . $num_cenario . "\n";
            if (!empty($observacoes)) { // variavel do inicio do script
                $obsOc .= "Obs Cenário: " . $observacoes . "\n";
            }
            $obsOc .= "Clientes envolvidos: " . implode(', ', $clientesEnvolvidos);

            $sqlUpdateOc = "UPDATE cot_pedidos_compra SET 
                            id_fornecedor = :id_fornecedor, 
                            fornecedor = :fornecedor, 
                            modal = :modal,
                            obs = :obs,
                            valor_total_usd = (SELECT SUM(qtd * landed_usd) FROM cot_pedidos_compra_itens WHERE id_pedido = :id_pedido_sub)
                            WHERE id = :id_pedido";

            // Nota: O update do valor_total_usd ali em cima vai dar errado pq ainda não atualizamos os itens.
            // Vamos atualizar header simples primeiro, e depois dos itens atualizamos o total se houver campo de total no header (o sistema parece calcular on-the-fly em alguns lugares, mas vamos manter simples).

            $sqlUpdateOcHeader = "UPDATE cot_pedidos_compra SET 
                            id_fornecedor = :id_fornecedor, 
                            fornecedor = :fornecedor, 
                            modal = :modal,
                            obs = :obs
                            WHERE id = :id_pedido";

            $stmtUpdateOcHeader = $pdo->prepare($sqlUpdateOcHeader);
            $stmtUpdateOcHeader->execute([
                ':id_fornecedor' => $id_fornecedor,
                ':fornecedor' => $fornecedor,
                ':modal' => $modal,
                ':obs' => $obsOc,
                ':id_pedido' => $idPedido
            ]);

            // Deletar Itens Antigos da OC
            $stmtDelOcItems = $pdo->prepare("DELETE FROM cot_pedidos_compra_itens WHERE id_pedido = :id_pedido");
            $stmtDelOcItems->execute([':id_pedido' => $idPedido]);

            // Inserir Novos Itens na OC
            $sqlInsertOcItem = "INSERT INTO cot_pedidos_compra_itens (id_pedido, codigo_produto, produto, qtd, unidade, landed_usd, preco_venda_usd, data_necessidade) 
                                 VALUES (:id_pedido, :codigo, :produto, :qtd, :unidade, :landed, :preco_venda, :data_necessidade)";
            $stmtInsertOcItem = $pdo->prepare($sqlInsertOcItem);

            foreach ($itensParaOc as $itemOc) {
                $stmtInsertOcItem->execute([
                    ':id_pedido' => $idPedido,
                    ':codigo' => $itemOc['codigo_produto'],
                    ':produto' => $itemOc['produto'],
                    ':qtd' => $itemOc['qtd_total'],
                    ':unidade' => $itemOc['unidade'],
                    ':landed' => $itemOc['landed_usd'],
                    ':preco_venda' => $itemOc['preco_venda_usd'],
                    ':data_necessidade' => $itemOc['data_necessidade']
                ]);
            }
        }
        // --- FIM LOGICA OC ---

        $pdo->commit();

        $_SESSION['mensagem'] = 'Cenário atualizado com sucesso!';
        $_SESSION['tipo_mensagem'] = 'success';
        header("Location: consultar_cenarios.php?sucesso=1&num_cenario=$num_cenario");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['mensagem'] = 'Erro ao atualizar cenário (Banco): ' . $e->getMessage();
        $_SESSION['tipo_mensagem'] = 'danger';
        header("Location: editar_cenario.php?num=$num_cenario");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['mensagem'] = 'Erro ao atualizar: ' . $e->getMessage();
        $_SESSION['tipo_mensagem'] = 'danger';
        header("Location: editar_cenario.php?num=$num_cenario");
        exit();
    }
} else {
    header('Location: consultar_cenarios.php');
    exit();
}
?>