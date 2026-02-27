<?php
// sistema-cotacoes/index.php - Redirecionador SSO
session_start();

if (isset($_SESSION['representante_email'])) {
    header("Location: bi.php"); // Dashboard
    exit;
}

header("Location: ../login.php");
exit;
?>