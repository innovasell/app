<?php
// events/login.php - Redirecionador para SSO
// O login local foi desativado em favor do Login Unificado

session_start();

// Se já estiver logado localmente, vai para o index
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

// Se não, redireciona para o login central na raiz
header("Location: ../login.php");
exit;
?>