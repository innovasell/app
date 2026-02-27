<?php
/****************************************************
 * users.php — Cadastro de Colaboradores (Admin)
 * Requisitos: PHP 7.4+, MySQLi, Sessions
 * Coloque este arquivo na mesma pasta do seu config.php e faq.php
 ****************************************************/

/**
 * Sessões robustas (antes de qualquer saída)
 */
if (session_status() === PHP_SESSION_NONE) {
    $savePath = ini_get('session.save_path');
    if (!$savePath || !is_writable($savePath)) {
        session_save_path(sys_get_temp_dir());
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

require_once __DIR__ . '/config.php'; // $conn, APP_VERSION

// ---------- Helpers ----------
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function is_logged(){ return isset($_SESSION['user']); }
function user(){ return $_SESSION['user'] ?? null; }
function is_admin(){ return is_logged() && (user()['role'] === 'admin'); }
function csrf_token(){
    if (empty($_SESSION['csrf_users'])) $_SESSION['csrf_users'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_users'];
}
function check_csrf($t){ return isset($_SESSION['csrf_users']) && hash_equals($_SESSION['csrf_users'], $t); }

// ---------- Gate: apenas admin ----------
if (!is_logged() || !is_admin()) {
    http_response_code(403);
    ?>
    <!doctype html>
    <html lang="pt-br">
    <head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Acesso negado</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
      <div class="container py-5">
        <div class="alert alert-danger">
          <strong>Acesso negado.</strong> Apenas administradores podem cadastrar colaboradores.
        </div>
        <a class="btn btn-primary" href="faq.php">Voltar para o FAQ</a>
      </div>
    </body></html>
    <?php
    exit;
}

// ---------- Ações ----------
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        if (!check_csrf($_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF inválido.'); }

        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = (isset($_POST['is_admin']) && $_POST['is_admin'] == '1') ? 'admin' : 'usuario';

        if ($name === '' || $email === '' || $pass === '') {
            $flash = ['type'=>'danger','msg'=>'Preencha Nome, E-mail e Senha.'];
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssss", $name, $email, $hash, $role);
                $stmt->execute();
                $flash = ['type'=>'success','msg'=>'Colaborador criado com sucesso!'];
            } catch (mysqli_sql_exception $e) {
                // 1062 => duplicate
                if ($e->getCode() == 1062) {
                    $flash = ['type'=>'danger','msg'=>'Este e-mail já está em uso.'];
                } else {
                    $flash = ['type'=>'danger','msg'=>'Erro ao criar colaborador: '.$e->getMessage()];
                }
            }
        }
    }

    // (Opcional) Trocar papel rapidamente
    if ($action === 'toggle_role') {
        if (!check_csrf($_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF inválido.'); }
        $uid = (int)($_POST['uid'] ?? 0);
        $newRole = ($_POST['role'] ?? 'usuario') === 'admin' ? 'admin' : 'usuario';

        if ($uid > 0) {
            // Evita o admin remover a si mesmo do papel admin sem querer? (permitido, mas podemos bloquear)
            $stmt = $conn->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $newRole, $uid);
            $stmt->execute();
            $flash = ['type'=>'success','msg'=>'Papel atualizado.'];
            // Se mexeu no próprio papel, sincroniza sessão
            if ($uid === (int)user()['id']) {
                $_SESSION['user']['role'] = $newRole;
            }
        } else {
            $flash = ['type'=>'danger','msg'=>'ID inválido.'];
        }
    }

    // (Opcional) Resetar senha rapidamente
    if ($action === 'reset_pass') {
        if (!check_csrf($_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF inválido.'); }
        $uid = (int)($_POST['uid'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';
        if ($uid > 0 && $newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $hash, $uid);
            $stmt->execute();
            $flash = ['type'=>'success','msg'=>'Senha atualizada.'];
        } else {
            $flash = ['type'=>'danger','msg'=>'Informe a nova senha.'];
        }
    }
}

// ---------- Lista de usuários ----------
$users = [];
$res = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
if ($res) { $users = $res->fetch_all(MYSQLI_ASSOC); }

?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Colaboradores • Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="faq.php"><i class="bi bi-question-circle"></i> FAQ</a>
    <span class="navbar-text ms-2">/ Colaboradores</span>
    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="text-muted small d-none d-md-inline">Logado como: <?=h(user()['name'])?> (<?=h(user()['role'])?>)</span>
      <a class="btn btn-outline-secondary btn-sm" href="faq.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
  </div>
</nav>

<div class="container my-4">
  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h5 mb-3">Novo colaborador</h2>

          <?php if ($flash): ?>
            <div class="alert alert-<?=h($flash['type'])?>"><?=h($flash['msg'])?></div>
          <?php endif; ?>

          <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="create_user">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

            <div class="mb-3">
              <label class="form-label">Nome</label>
              <input type="text" name="name" class="form-control" required maxlength="120">
            </div>

            <div class="mb-3">
              <label class="form-label">E-mail</label>
              <input type="email" name="email" class="form-control" required maxlength="190">
            </div>

            <div class="mb-3">
              <label class="form-label">Senha</label>
              <input type="password" name="password" class="form-control" required>
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="1" id="is_admin" name="is_admin">
              <label class="form-check-label" for="is_admin">
                Tornar administrador
              </label>
            </div>

            <button class="btn btn-success w-100">
              <i class="bi bi-person-plus"></i> Criar colaborador
            </button>
          </form>
        </div>
      </div>
      <p class="text-muted small mt-2 mb-0">Versão: <?=h(defined('APP_VERSION') ? APP_VERSION : '1.0')?></p>
    </div>

    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h5 mb-3">Usuários existentes</h2>

          <?php if (empty($users)): ?>
            <div class="alert alert-light border mb-0">Nenhum usuário encontrado.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Papel</th>
                    <th>Criado em</th>
                    <th class="text-end">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $u): ?>
                    <tr>
                      <td><?=h($u['name'])?></td>
                      <td><?=h($u['email'])?></td>
                      <td>
                        <span class="badge <?= $u['role']==='admin' ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                          <?=h($u['role'])?>
                        </span>
                      </td>
                      <td><span class="text-muted small"><?=h($u['created_at'])?></span></td>
                      <td class="text-end">
                        <!-- Trocar papel -->
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="toggle_role">
                          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                          <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                          <input type="hidden" name="role" value="<?= $u['role']==='admin' ? 'usuario' : 'admin' ?>">
                          <button class="btn btn-sm btn-outline-warning" <?= (int)$u['id'] === (int)user()['id'] ? '' : '' ?>>
                            <i class="bi bi-shield-lock"></i>
                            <?= $u['role']==='admin' ? 'Tornar usuário' : 'Tornar admin' ?>
                          </button>
                        </form>

                        <!-- Resetar senha (abre modal) -->
                        <button
                          class="btn btn-sm btn-outline-secondary"
                          data-bs-toggle="modal"
                          data-bs-target="#resetPassModal"
                          data-uid="<?= (int)$u['id'] ?>"
                          data-name="<?= h($u['name']) ?>"
                          data-email="<?= h($u['email']) ?>"
                        >
                          <i class="bi bi-key"></i> Resetar senha
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Reset de Senha -->
<div class="modal fade" id="resetPassModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="reset_pass">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="uid" id="reset-uid">
      <div class="modal-header">
        <h5 class="modal-title">Resetar senha</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2" id="reset-desc"></p>
        <div class="mb-3">
          <label class="form-label">Nova senha</label>
          <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="alert alert-warning small mb-0">
          <i class="bi bi-exclamation-triangle"></i>
          A senha será substituída imediatamente.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">
          <i class="bi bi-check2-circle"></i> Atualizar senha
        </button>
      </div>
    </form>
  </div>
</div>

<footer class="border-top py-3 mt-5">
  <div class="container text-muted small d-flex justify-content-between">
    <span>Colaboradores • Admin</span>
    <span>Versão <?=h(defined('APP_VERSION') ? APP_VERSION : '1.0')?> • MySQLi • Bootstrap 5</span>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Preenche modal de reset de senha
const resetPassModal = document.getElementById('resetPassModal');
resetPassModal && resetPassModal.addEventListener('show.bs.modal', event => {
  const btn = event.relatedTarget;
  const uid = btn.getAttribute('data-uid');
  const name = btn.getAttribute('data-name');
  const email = btn.getAttribute('data-email');
  document.getElementById('reset-uid').value = uid;
  document.getElementById('reset-desc').textContent = `Definir nova senha para: ${name} <${email}>`;
});
</script>
</body>
</html>
