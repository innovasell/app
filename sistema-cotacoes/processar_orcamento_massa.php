<?php
session_start();
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['arquivo_csv'])) {

    $file = $_FILES['arquivo_csv'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Erro no upload do arquivo.");
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'csv') {
        die("Apenas arquivos CSV são permitidos.");
    }

    $handle = fopen($file['tmp_name'], "r");
    if ($handle === false) {
        die("Não foi possível abrir o arquivo.");
    }

    // Prepare statement to find product details
    $stmt = $pdo->prepare("SELECT codigo, produto, unidade, origem, ncm, ipi FROM cot_estoque WHERE codigo = :codigo LIMIT 1");

    $itensEncontrados = [];
    $erros = [];

    // Read Header
    $header = fgetcsv($handle, 0, ";");

    // Map columns (Simpler mapping since we provide template)
    // Indexes: 0=CODIGO_PRODUTO, 1=QUANTIDADE, 2=EMBALAGEM, 3=DISPONIBILIDADE, 4=PRECO_NET_USD

    while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
        // Basic validation
        if (count($data) < 5)
            continue;

        $codigo = trim($data[0]);
        $quantidade = trim($data[1]);
        $embalagem = trim($data[2]);
        $disponibilidade = trim($data[3]);
        $preco_net = trim($data[4]);

        if (empty($codigo))
            continue;

        // Fetch Product Details
        $stmt->execute([':codigo' => $codigo]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($produto) {
            // Format numbers
            $quantidade = str_replace(',', '.', $quantidade);
            $embalagem = str_replace(',', '.', $embalagem);
            $preco_net = str_replace(',', '.', $preco_net);
            $ipi = str_replace('.', ',', $produto['ipi']); // Format back to comma for UI

            $item = [
                'codigo' => $produto['codigo'],
                'produto' => $produto['produto'],
                'unidade' => $produto['unidade'],
                'origem' => $produto['origem'],
                'ncm' => $produto['ncm'],
                'ipi' => $ipi,
                'volume' => $quantidade,
                'embalagem' => $embalagem,
                'disponibilidade' => $disponibilidade,
                'preco_net' => number_format((float) $preco_net, 4, ',', ''), // Format for display
                // ICMS will be calculated on client selection
                'icms' => '',
                // Preco full will be calculated by JS on load
                'preco_full' => ''
            ];
            $itensEncontrados[] = $item;
        } else {
            $erros[] = "Produto não encontrado: $codigo";
        }
    }

    fclose($handle);

    if (!empty($itensEncontrados)) {
        $_SESSION['itens_orcamento_massa'] = $itensEncontrados;
        if (!empty($erros)) {
            $_SESSION['erros_orcamento_massa'] = $erros;
        }
        header("Location: incluir_orcamento.php?load_massa=1");
        exit();
    } else {
        echo "Nenhum item válido encontrado no arquivo.";
        if (!empty($erros)) {
            echo "<br>Erros:<br>" . implode("<br>", $erros);
        }
    }

} else {
    header("Location: incluir_orcamento_massa.php");
    exit();
}
