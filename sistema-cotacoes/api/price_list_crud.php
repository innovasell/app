<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Opcional: checar se está logado
if (!isset($_SESSION['representante_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

require_once __DIR__ . '/../conexao.php';

try {
    // Garante que a coluna lead_time exista (para compatibilidade com instalações antigas)
    try {
        $pdo->exec("ALTER TABLE cot_price_list ADD COLUMN IF NOT EXISTS lead_time VARCHAR(100) DEFAULT ''");
    } catch (PDOException $e) {
        // Ignora erro caso a sintaxe ADD COLUMN IF NOT EXISTS não seja suportada na versão antiga do MariaDB/MySQL
        try {
            $pdo->exec("ALTER TABLE cot_price_list ADD COLUMN lead_time VARCHAR(100) DEFAULT ''");
        } catch (PDOException $e2) {
            // Se já existe, vai dar erro, então apenas ignoramos
        }
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'list') {
            $stmt = $pdo->query("SELECT * FROM cot_price_list ORDER BY fabricante, codigo");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'create') {
            $stmt = $pdo->prepare("
                INSERT INTO cot_price_list (fabricante, classificacao, codigo, produto, fracionado, embalagem, preco_net_usd, lead_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                trim($input['fabricante'] ?? ''),
                trim($input['classificacao'] ?? ''),
                trim($input['codigo'] ?? ''),
                trim($input['produto'] ?? ''),
                trim($input['fracionado'] ?? 'Não'),
                trim($input['embalagem'] ?? ''),
                (float)($input['preco_net_usd'] ?? 0),
                trim($input['lead_time'] ?? '')
            ]);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            exit;
            
        } elseif ($action === 'update') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE cot_price_list 
                SET fabricante = ?, classificacao = ?, codigo = ?, produto = ?, fracionado = ?, embalagem = ?, preco_net_usd = ?, lead_time = ?
                WHERE id = ?
            ");
            $stmt->execute([
                trim($input['fabricante'] ?? ''),
                trim($input['classificacao'] ?? ''),
                trim($input['codigo'] ?? ''),
                trim($input['produto'] ?? ''),
                trim($input['fracionado'] ?? 'Não'),
                trim($input['embalagem'] ?? ''),
                (float)($input['preco_net_usd'] ?? 0),
                trim($input['lead_time'] ?? ''),
                $id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Atualizado com sucesso']);
            exit;

        } elseif ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM cot_price_list WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Excluído com sucesso']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Ação não suportada']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}
