<?php
// atualizar_formula.php — processa SALVAR (sem PDF), sem prepared statements
session_start();
require_once 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido');
}

// Helpers de segurança
function q(mysqli $conn, ?string $v): string {
    if ($v === null) return "NULL";
    return "'" . $conn->real_escape_string($v) . "'";
}
function toFloat($v): float {
    if ($v === null || $v === '') return 0.0;
    // aceita "10,5" ou "10.5"
    $v = str_replace(',', '.', (string)$v);
    return (float)$v;
}

// ---------- INPUT ----------
$formulacaoId      = (int)($_POST['id'] ?? 0);
$formulaName       = $_POST['formulaName']       ?? '';
$antigo_codigo     = $_POST['antigo_codigo']     ?? null;
$desenvolvida_para = $_POST['desenvolvida_para'] ?? '';
$solicitada_por    = $_POST['solicitada_por']    ?? '';
$categoryPrefix    = $_POST['category']          ?? 'GEN';

$ativosNomes = $_POST['ativos_nome'] ?? [];
$ativosDescs = $_POST['ativos_desc'] ?? [];

$subForm = $_POST['sub_formulacoes'] ?? []; // PARTE → FASE(S) → INGREDIENTES

if ($formulacaoId <= 0)          die('ID da formulação inválido.');
if (trim($formulaName) === '')   die('Nome da formulação é obrigatório.');

// ---------- TRANSAÇÃO ----------
$conn->begin_transaction();
try {
    // 1) Atualiza cabeçalho (sem prepared)
    $sql = "UPDATE formulacoes SET
                nome_formula      = " . q($conn, $formulaName) . ",
                antigo_codigo     = " . q($conn, $antigo_codigo) . ",
                categoria         = " . q($conn, $categoryPrefix) . ",
                desenvolvida_para = " . q($conn, $desenvolvida_para) . ",
                solicitada_por    = " . q($conn, $solicitada_por) . "
            WHERE id = {$formulacaoId}";
    $conn->query($sql);

    // 2) Limpeza — ordem: ingredientes → fases → sub_formulacoes → ativos
    //    (DELETE com JOIN, 1 único parâmetro numérico, zero prepared)
    $conn->query("
        DELETE i
          FROM ingredientes i
          JOIN fases f ON f.id = i.fase_id
          JOIN sub_formulacoes s ON s.id = f.sub_formulacao_id
         WHERE s.formulacao_id = {$formulacaoId}
    ");

    $conn->query("
        DELETE f
          FROM fases f
          JOIN sub_formulacoes s ON s.id = f.sub_formulacao_id
         WHERE s.formulacao_id = {$formulacaoId}
    ");

    $conn->query("DELETE FROM sub_formulacoes WHERE formulacao_id = {$formulacaoId}");
    $conn->query("DELETE FROM ativos_destaque  WHERE formulacao_id = {$formulacaoId}");

    // 3) Inserções
    // 3.1 Ativos
    if (!empty($ativosNomes)) {
        foreach ($ativosNomes as $i => $nome) {
            $nome = trim((string)$nome);
            if ($nome === '') continue;
            $desc = (string)($ativosDescs[$i] ?? '');
            $conn->query("
                INSERT INTO ativos_destaque (formulacao_id, nome_ativo, descricao)
                VALUES ({$formulacaoId}, " . q($conn, $nome) . ", " . q($conn, $desc) . ")
            ");
        }
    }

    // 3.2 Partes → Fases → Ingredientes
    if (!empty($subForm) && is_array($subForm)) {
        foreach ($subForm as $p) {
            $subNome  = trim((string)($p['nome'] ?? ''));
            if ($subNome === '') continue;
            $modoPrep = (string)($p['modo_preparo'] ?? '');

            // sub_formulacao
            $conn->query("
                INSERT INTO sub_formulacoes (formulacao_id, nome_sub_formula, modo_preparo)
                VALUES ({$formulacaoId}, " . q($conn, $subNome) . ", " . q($conn, $modoPrep) . ")
            ");
            $subId = (int)$conn->insert_id;

            // fases
            if (!empty($p['fases']) && is_array($p['fases'])) {
                foreach ($p['fases'] as $f) {
                    $faseNome = trim((string)($f['nome'] ?? ''));
                    if ($faseNome === '') continue;

                    $conn->query("
                        INSERT INTO fases (sub_formulacao_id, nome_fase)
                        VALUES ({$subId}, " . q($conn, $faseNome) . ")
                    ");
                    $faseId = (int)$conn->insert_id;

                    // ingredientes
                    if (!empty($f['ingredientes']) && is_array($f['ingredientes'])) {
                        $mat = (array)($f['ingredientes']['materia_prima'] ?? []);
                        $inc = (array)($f['ingredientes']['inci_name']      ?? []);
                        $pct = (array)($f['ingredientes']['percentual']     ?? []);
                        $dst = (array)($f['ingredientes']['destaque']       ?? []);

                        $n = max(count($mat), count($inc), count($pct), count($dst));
                        for ($i=0; $i<$n; $i++) {
                            $mp  = trim((string)($mat[$i] ?? '')); if ($mp === '') continue;
                            $inm = (string)($inc[$i] ?? '');
                            $per = toFloat($pct[$i] ?? 0);
                            $des = (!empty($dst[$i]) && (string)$dst[$i] === '1') ? 1 : 0;

                            $conn->query("
                                INSERT INTO ingredientes (fase_id, materia_prima, inci_name, percentual, destaque)
                                VALUES (
                                    {$faseId},
                                    " . q($conn, $mp) . ",
                                    " . q($conn, $inm) . ",
                                    {$per},
                                    {$des}
                                )
                            ");
                        }
                    }
                }
            }
        }
    }

    // 4) Commit e redirect
    $conn->commit();
    header("Location: view_formula.php?id=" . $formulacaoId);
    exit();

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    http_response_code(500);
    die("Erro ao atualizar a formulação: " . $e->getMessage());
}
