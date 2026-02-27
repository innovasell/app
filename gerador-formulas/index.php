<?php
// gerador-formulas/index.php - Redirecionador SSO
session_start();

// Mapeia sso_user para user_id (local) se necessário
// O código original verificava isset($_SESSION['user_id'])
// Se o SSO estiver ativo, podemos definir user_id fake ou real se tiver tabela de usuários sincronizada.
// Para manter simples: se sso_user existe, define user_id e vai para pesquisar_formulas.

if (isset($_SESSION['sso_user'])) {

    // Adaptar dados do SSO para o esperado pelo Formularium
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = $_SESSION['sso_user']['id'];
        $_SESSION['usuario_nome'] = $_SESSION['sso_user']['nome'];
        $_SESSION['usuario_email'] = $_SESSION['sso_user']['email'];
    }

    header('Location: pesquisar_formulas.php');
    exit();
}

// Se não logado, manda pro login global
header('Location: ../login.php');
exit();
?>