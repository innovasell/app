<?php
// api_import_sync.php
// Receives JSON data and syncs to database
// Security: Simple Token

require_once 'config.php';

header('Content-Type: application/json');

// 1. Security Check
$secret_key = "InnovasellSync2024!"; // Change this!
$received_key = $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($received_key !== $secret_key) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 2. Read Input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$conn->begin_transaction();

try {
    $stats = ['updated' => 0, 'inserted' => 0];

    // 3. Process Formulas
    // Expected structure: { "formulas": [ { "id": 1, "nome_formula": "...", "sub_formulacoes": [...] } ] }
    if (isset($data['formulas']) && is_array($data['formulas'])) {
        foreach ($data['formulas'] as $f) {

            // Upsert Formula (Removed descricao)
            $stmt = $conn->prepare("INSERT INTO formulacoes (id, nome_formula, codigo_formula, categoria, data_criacao) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nome_formula=VALUES(nome_formula), codigo_formula=VALUES(codigo_formula), categoria=VALUES(categoria), data_criacao=VALUES(data_criacao)");
            $stmt->bind_param("issss", $f['id'], $f['nome_formula'], $f['codigo_formula'], $f['categoria'], $f['data_criacao']);
            $stmt->execute();

            // Process Sub-Formulas
            if (isset($f['sub_formulacoes'])) {
                foreach ($f['sub_formulacoes'] as $sub) {
                    $stmtSub = $conn->prepare("INSERT INTO sub_formulacoes (id, formulacao_id, nome_sub_formula, modo_preparo) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nome_sub_formula=VALUES(nome_sub_formula), modo_preparo=VALUES(modo_preparo)");
                    $stmtSub->bind_param("iiss", $sub['id'], $f['id'], $sub['nome_sub_formula'], $sub['modo_preparo']);
                    $stmtSub->execute();

                    // Process Fases
                    if (isset($sub['fases'])) {
                        foreach ($sub['fases'] as $fase) {
                            $stmtFase = $conn->prepare("INSERT INTO fases (id, sub_formulacao_id, nome_fase) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nome_fase=VALUES(nome_fase)");
                            $stmtFase->bind_param("iis", $fase['id'], $sub['id'], $fase['nome_fase']);
                            $stmtFase->execute();

                            // Process Ingredientes
                            if (isset($fase['ingredientes'])) {
                                foreach ($fase['ingredientes'] as $ing) {
                                    $stmtIng = $conn->prepare("INSERT INTO ingredientes (id, fase_id, materia_prima, inci_name, percentual, qsp, destaque) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE materia_prima=VALUES(materia_prima), inci_name=VALUES(inci_name), percentual=VALUES(percentual), qsp=VALUES(qsp), destaque=VALUES(destaque)");
                                    $stmtIng->bind_param("iisssii", $ing['id'], $fase['id'], $ing['materia_prima'], $ing['inci_name'], $ing['percentual'], $ing['qsp'], $ing['destaque']);
                                    $stmtIng->execute();
                                }
                            }
                        }
                    }
                }
            }

            // Process Ativos Destaque (Removed imagem_path)
            if (isset($f['ativos_destaque'])) {
                foreach ($f['ativos_destaque'] as $ativo) {
                    $stmtAtivo = $conn->prepare("INSERT INTO ativos_destaque (id, formulacao_id, nome_ativo, descricao) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nome_ativo=VALUES(nome_ativo), descricao=VALUES(descricao)");
                    $stmtAtivo->bind_param("iiss", $ativo['id'], $f['id'], $ativo['nome_ativo'], $ativo['descricao']);
                    $stmtAtivo->execute();
                }
            }
            $stats['updated']++;
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'stats' => $stats]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>