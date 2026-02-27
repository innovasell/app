<?php
session_start();
$pagina_ativa = 'gerenciar_price_list';
require_once 'header.php';
require_once 'conexao.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Price List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h2>Gerenciar Price List</h2>

        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert alert-success">Importação realizada com sucesso!</div>
        <?php endif; ?>

        <?php if (isset($_GET['sucesso_add'])): ?>
            <div class="alert alert-success">Item adicionado com sucesso!</div>
        <?php endif; ?>

        <?php if (isset($_GET['erro'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_GET['erro']) ?>
            </div>
        <?php endif; ?>

        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                Importar Nova Lista de Preços
            </div>
            <div class="card-body">
                <p class="card-text">
                    <strong>Atenção:</strong> Ao importar uma nova lista, todos os dados da lista atual serão apagados e
                    substituídos pelos novos.
                    O arquivo deve ser um <strong>CSV</strong> separado por <strong>ponto e vírgula (;)</strong>.
                </p>

                <div class="mb-4">
                    <a href="download_template_price_list.php" class="btn btn-outline-success">
                        <i class="fas fa-file-csv me-2"></i>Baixar Modelo CSV
                    </a>
                    <button type="button" class="btn btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#modalAdicionarItem">
                        <i class="fas fa-plus me-2"></i>Incluir Item Manualmente
                    </button>
                </div>
                <form action="importar_price_list.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="arquivo_csv" class="form-label">Selecione o arquivo CSV</label>
                        <input class="form-control" type="file" id="arquivo_csv" name="arquivo_csv" accept=".csv"
                            required>
                    </div>
                    <button type="submit" class="btn btn-primary"
                        onclick="return confirm('Tem certeza? A lista atual será substituída.');">
                        <i class="fas fa-file-import me-2"></i>Importar e Substituir
                    </button>
                </form>
            </div>
        </div>

        <!-- Modal Adicionar Item -->
        <div class="modal fade" id="modalAdicionarItem" tabindex="-1" aria-labelledby="modalAdicionarItemLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalAdicionarItemLabel">Adicionar Item Manualmente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="adicionar_item_price_list.php" method="POST">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="fabricante" class="form-label">Fabricante</label>
                                    <input type="text" class="form-control" id="fabricante" name="fabricante" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="classificacao" class="form-label">Classificação</label>
                                    <input type="text" class="form-control" id="classificacao" name="classificacao">
                                </div>
                                <div class="col-md-4">
                                    <label for="codigo" class="form-label">Código</label>
                                    <input type="text" class="form-control" id="codigo" name="codigo">
                                </div>
                                <div class="col-md-8">
                                    <label for="produto" class="form-label">Produto</label>
                                    <input type="text" class="form-control" id="produto" name="produto" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="fracionado" class="form-label">Fracionado</label>
                                    <select class="form-select" id="fracionado" name="fracionado">
                                        <option value="Não">Não</option>
                                        <option value="Sim">Sim</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="embalagem" class="form-label">Embalagem</label>
                                    <input type="number" step="0.0001" class="form-control" id="embalagem" name="embalagem" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="preco_net_usd" class="form-label">Preço Net USD</label>
                                    <input type="number" step="0.0001" class="form-control" id="preco_net_usd" name="preco_net_usd" required>
                                </div>
                                <div class="col-md-12">
                                    <label for="lead_time" class="form-label">Lead Time</label>
                                    <input type="text" class="form-control" id="lead_time" name="lead_time">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Salvar Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                Estatísticas Atuais
                <?php
                $lastUpload = file_exists('last_upload_price_list.txt') ? file_get_contents('last_upload_price_list.txt') : 'Desconhecido';
                echo "<span class='float-end badge bg-info text-dark'>Última Atualização: $lastUpload</span>";
                ?>
            </div>
            <div class="card-body">
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cot_price_list");
                    $total = $stmt->fetchColumn();
                    echo "<p>Total de itens cadastrados: <strong>$total</strong></p>";

                    $stmt = $pdo->query("SELECT COUNT(DISTINCT fabricante) as fabs FROM cot_price_list");
                    $fabs = $stmt->fetchColumn();
                    echo "<p>Fabricantes distintos: <strong>$fabs</strong></p>";

                } catch (PDOException $e) {
                    if ($e->getCode() == '42S02' || strpos($e->getMessage(), "doesn't exist") !== false) {
                        // Tabela não existe, tenta criar
                        try {
                            $sql = "CREATE TABLE IF NOT EXISTS cot_price_list (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                fabricante VARCHAR(100),
                                classificacao VARCHAR(50),
                                codigo VARCHAR(50),
                                produto VARCHAR(255),
                                fracionado VARCHAR(10),
                                embalagem DECIMAL(10,4),
                                preco_net_usd DECIMAL(10,4),
                                INDEX idx_codigo (codigo)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                            $pdo->exec($sql);
                            echo "<div class='alert alert-warning'>Tabela de preços criada automaticamente. Por favor, atualize a página.</div>";
                        } catch (PDOException $ex) {
                            echo "<div class='alert alert-danger'>Erro ao criar tabela: " . $ex->getMessage() . "</div>";
                        }
                    } else {
                        echo "Erro ao buscar estatísticas: " . $e->getMessage();
                    }
                }
                ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>