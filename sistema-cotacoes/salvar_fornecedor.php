<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['representante_email'])) {
    header('Location: index.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = isset($_POST['id']) && !empty($_POST['id']) ? (int) $_POST['id'] : null;
        $nome = trim($_POST['nome']);
        $pais = trim($_POST['pais'] ?? '');
        $contato = trim($_POST['contato'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $ativo = isset($_POST['ativo']) ? (int) $_POST['ativo'] : 1;
        $observacoes = trim($_POST['observacoes'] ?? '');

        if (empty($nome)) {
            throw new Exception('Nome do fornecedor é obrigatório.');
        }

        if ($id) {
            // Atualizar
            $sql = "UPDATE cot_fornecedores SET 
                nome = :nome,
                pais = :pais,
                contato = :contato,
                email = :email,
                telefone = :telefone,
                ativo = :ativo,
                observacoes = :observacoes,
                updated_at = CURRENT_TIMESTAMP
              WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':nome' => $nome,
                ':pais' => $pais,
                ':contato' => $contato,
                ':email' => $email,
                ':telefone' => $telefone,
                ':ativo' => $ativo,
                ':observacoes' => $observacoes
            ]);

            $_SESSION['mensagem'] = 'Fornecedor atualizado com sucesso!';
            $_SESSION['tipo_mensagem'] = 'success';
        } else {
            // Inserir
            $sql = "INSERT INTO cot_fornecedores (nome, pais, contato, email, telefone, ativo, observacoes)
              VALUES (:nome, :pais, :contato, :email, :telefone, :ativo, :observacoes)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $nome,
                ':pais' => $pais,
                ':contato' => $contato,
                ':email' => $email,
                ':telefone' => $telefone,
                ':ativo' => $ativo,
                ':observacoes' => $observacoes
            ]);

            $_SESSION['mensagem'] = 'Fornecedor cadastrado com sucesso!';
            $_SESSION['tipo_mensagem'] = 'success';
        }

        header('Location: gerenciar_fornecedores.php');
        exit();

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $_SESSION['mensagem'] = 'Erro: Já existe um fornecedor com esse nome.';
        } else {
            $_SESSION['mensagem'] = 'Erro ao salvar fornecedor: ' . $e->getMessage();
        }
        $_SESSION['tipo_mensagem'] = 'danger';
        header('Location: gerenciar_fornecedores.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['mensagem'] = $e->getMessage();
        $_SESSION['tipo_mensagem'] = 'danger';
        header('Location: gerenciar_fornecedores.php');
        exit();
    }
}
?>