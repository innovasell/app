<?php
/**
 * API para obter opções de filtros para relatórios
 */

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => '', 'data' => []];

try {
    // Usa conexão centralizada  
    require_once __DIR__ . '/../conexao.php';

    // Busca eventos/visitas distintos (não nulos e não vazios)
    $eventos = [];
    $resultEventos = $conn->query("SELECT DISTINCT evento_visita FROM viagem_express_expenses 
                                   WHERE evento_visita IS NOT NULL AND evento_visita != '' 
                                   ORDER BY evento_visita");
    if ($resultEventos) {
        while ($row = $resultEventos->fetch_assoc()) {
            $eventos[] = $row['evento_visita'];
        }
    }

    // Busca faturas distintas (não nulas e não vazias)
    $faturas = [];
    $resultFaturas = $conn->query("SELECT DISTINCT num_fatura FROM viagem_express_expenses 
                                   WHERE num_fatura IS NOT NULL AND num_fatura != '' 
                                   ORDER BY num_fatura DESC");
    if ($resultFaturas) {
        while ($row = $resultFaturas->fetch_assoc()) {
            $faturas[] = $row['num_fatura'];
        }
    }

    // Busca colaboradores distintos (passageiros)
    $colaboradores = [];
    $resultColaboradores = $conn->query("SELECT DISTINCT passageiro FROM viagem_express_expenses 
                                         WHERE passageiro IS NOT NULL AND passageiro != '' 
                                         ORDER BY passageiro");
    if ($resultColaboradores) {
        while ($row = $resultColaboradores->fetch_assoc()) {
            $colaboradores[] = $row['passageiro'];
        }
    }

    $response['success'] = true;
    $response['data'] = [
        'eventos' => $eventos,
        'faturas' => $faturas,
        'colaboradores' => $colaboradores
    ];

    $conn->close();

} catch (Exception $e) {
    error_log("ERRO GET REPORT FILTERS: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>