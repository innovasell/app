<?php
/****************************************************
 * Innova Wiki — index.php
 * Requisitos: PHP 7.4+, MySQL, Sessions
 * Coloque este arquivo na mesma pasta do seu config.php e logo.png
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
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function check_csrf($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }

// ---------- Auto-migrate ----------
function ensure_schema(mysqli $conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS faqs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question VARCHAR(255) NOT NULL,
        answer TEXT NOT NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        product VARCHAR(150) NULL,
        supplier VARCHAR(150) NULL,
        CONSTRAINT fk_faq_user FOREIGN KEY (created_by)
            REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $conn->prepare("
        SELECT COUNT(1) AS c FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faqs' AND INDEX_NAME = 'idx_faqs_question'
    ");
    $stmt->execute();
    if ((int)$stmt->get_result()->fetch_assoc()['c'] === 0) {
        $conn->query("CREATE INDEX idx_faqs_question ON faqs (question)");
    }
}
ensure_schema($conn);

// ---------- Utilidades ----------
function has_any_user(mysqli $conn) {
    try {
        $res = $conn->query("SELECT COUNT(*) AS c FROM users");
        return (int)$res->fetch_assoc()['c'] > 0;
    } catch (Throwable $e) { return false; }
}

// ---------- Roteamento ----------
$action = $_GET['action'] ?? $_POST['action'] ?? 'home';

// ---------- Instalador ----------
if (!has_any_user($conn)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install') {
        if (!check_csrf($_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF inválido.'); }
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if ($name === '' || $email === '' || $pass === '') { $error = 'Preencha todos os campos.'; }
        else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
            $stmt->bind_param("sss", $name, $email, $hash); $stmt->execute();
            header('Location: ?installed=1'); exit;
        }
    }
    ?>
    <!doctype html><html lang="pt-br"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalação - Innova Wiki</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head><body>
    <div class="container py-5"><div class="row justify-content-center"><div class="col-lg-6">
      <div class="card shadow-sm"><div class="card-body">
        <h1 class="h4 mb-3">Configuração inicial</h1>
        <p class="text-muted">Crie o primeiro usuário <strong>admin</strong>.</p>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?=h($error)?></div><?php endif;?>
        <form method="post">
          <input type="hidden" name="action" value="install">
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
          <div class="mb-3"><label class="form-label">Nome</label><input type="text" name="name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Senha</label><input type="password" name="password" class="form-control" required></div>
          <button class="btn btn-success w-100">Criar admin</button>
        </form>
      </div></div>
      <p class="text-center text-muted mt-3 small">Versão: <?=h(defined('APP_VERSION')?APP_VERSION:'1.0')?></p>
    </div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fallback: if Bootstrap didn't load (CDN bloqueado), tenta habilitar botões básicos e mostra aviso no console.
(function(){
  function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  ready(function(){
    if (typeof bootstrap === 'undefined') {
      console.warn('[FAQ] Bootstrap não carregou do CDN. Botões de modal/offcanvas podem não funcionar. Considere servir o bootstrap.bundle.min.js localmente.');
    }
  });
})();
</script>
<script>
// Fallback básico para Collapse/Accordion quando Bootstrap não carrega (CDN bloqueado)
(function(){
  function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }
  function getTarget(el){
    var sel = el.getAttribute('data-bs-target') || el.getAttribute('data-target');
    if (sel && sel.startsWith('#')) return document.querySelector(sel);
    var href = el.getAttribute('href'); // fallback <a href="#id">
    if (href && href.startsWith('#')) return document.querySelector(href);
    return null;
  }
  ready(function(){
    if (typeof bootstrap !== 'undefined') return; // Bootstrap presente; usar o oficial
    console.info('[FAQ] Ativando fallback simples de Accordion (Collapse).');

    // Delegação: clique em qualquer trigger de collapse
    document.addEventListener('click', function(ev){
      var el = ev.target;
      // Sobe até encontrar um elemento com data-bs-toggle="collapse"
      while (el && el !== document){
        if (el.hasAttribute('data-bs-toggle') && (el.getAttribute('data-bs-toggle') === 'collapse')) break;
        el = el.parentElement;
      }
      if (!el || el === document) return;
      var target = getTarget(el);
      if (!target) return;
      ev.preventDefault();

      var isShown = target.classList.contains('show');
      // Se for um accordion com data-bs-parent, feche os irmãos
      var parentSel = target.getAttribute('data-bs-parent');
      if (parentSel){
        var parent = document.querySelector(parentSel);
        if (parent){
          qsa('.accordion-collapse.show', parent).forEach(function(c){ 
            if (c !== target){ c.classList.remove('show'); }
          });
          qsa('.accordion-button', parent).forEach(function(b){
            if (b !== el){ b.classList.add('collapsed'); b.setAttribute('aria-expanded','false'); }
          });
        }
      }

      // Toggle atual
      target.classList.toggle('show', !isShown);
      // Atualiza botão
      if (el.classList.contains('accordion-button')){
        el.classList.toggle('collapsed', isShown);
        el.setAttribute('aria-expanded', String(!isShown));
      }
    });
  });
})();
</script>

<script>
// Evita submit acidental: botões que apenas abrem modal/offcanvas dentro de <form>
(function(){
  function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  ready(function(){
    document.querySelectorAll('form button[data-bs-toggle], form button[data-bs-target]').forEach(function(btn){
      if (!btn.hasAttribute('type')) { btn.setAttribute('type', 'button'); }
    });
  });
})();
</script>
</body></html>
    <?php exit;
}

// ---------- Autenticação ----------
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF inválido.'); }
    $email = trim($_POST['email'] ?? ''); $pass = $_POST['password'] ?? '';
    $stmt = $conn->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc();
    if ($u && password_verify($pass, $u['password_hash'])) {
        $_SESSION['user'] = ['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']];
        header('Location: ?'); exit;
    } else { $login_error = 'E-mail ou senha inválidos.'; }
}
if ($action === 'logout') { session_destroy(); header('Location: ?'); exit; }

// ---------- CRUD de FAQs ----------
if ($action === 'create_faq' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!is_admin()) { http_response_code(403); exit('Acesso negado.'); }
    if (!check_csrf($_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF inválido.'); }
    $question = trim($_POST['question'] ?? ''); $answer = trim($_POST['answer'] ?? '');
    $product  = trim($_POST['product'] ?? ''); $supplier = trim($_POST['supplier'] ?? '');
    if ($question==='' || $answer==='') { $_SESSION['flash']=['type'=>'danger','msg'=>'Preencha pergunta e resposta.']; }
    else {
        $uid = (int)user()['id'];
        $stmt = $conn->prepare("INSERT INTO faqs (question, answer, product, supplier, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("ssssi", $question,$answer,$product,$supplier,$uid); $stmt->execute();
        $_SESSION['flash']=['type'=>'success','msg'=>'FAQ criada com sucesso!'];
    }
    header('Location: ?'); exit;
}
if ($action === 'update_faq' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!is_admin()) { http_response_code(403); exit('Acesso negado.'); }
    if (!check_csrf($_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF inválido.'); }
    $id=(int)($_POST['faq_id']??0); $q=trim($_POST['question']??''); $a=trim($_POST['answer']??'');
    $p=trim($_POST['product']??''); $s=trim($_POST['supplier']??'');
    if ($id<=0 || $q==='' || $a==='') { $_SESSION['flash']=['type'=>'danger','msg'=>'Preencha os campos obrigatórios.']; }
    else {
        $stmt=$conn->prepare("UPDATE faqs SET question=?, answer=?, product=?, supplier=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("ssssi",$q,$a,$p,$s,$id); $stmt->execute();
        $_SESSION['flash']=['type'=>'success','msg'=>'FAQ atualizada com sucesso!'];
    }
    header('Location: ?'.http_build_query(['page'=>$_GET['page']??1,'q'=>$_GET['q']??'','pf'=>$_GET['pf']??'','sf'=>$_GET['sf']??''])); exit;
}
if ($action === 'delete_faq' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!is_admin()) { http_response_code(403); exit('Acesso negado.'); }
    if (!check_csrf($_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF inválido.'); }
    $id=(int)($_POST['faq_id']??0);
    if ($id>0) { $stmt=$conn->prepare("DELETE FROM faqs WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute();
        $_SESSION['flash']=['type'=>'success','msg'=>'FAQ excluída com sucesso!'];
    } else { $_SESSION['flash']=['type'=>'danger','msg'=>'ID inválido.']; }
    header('Location: ?'); exit;
}

// ---------- Filtros, busca e paginação ----------
$q      = trim($_GET['q']  ?? '');
$pf     = trim($_GET['pf'] ?? ''); // product (literal)
$sf     = trim($_GET['sf'] ?? ''); // supplier (normalizado)
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 20;
$offset = ($page-1)*$per;

// Produtos (sem genéricos)
$products = $conn->query("
    SELECT DISTINCT product
    FROM faqs
    WHERE product IS NOT NULL AND TRIM(product) <> ''
      AND UPPER(TRIM(product)) NOT IN ('TODOS','TODAS','ALL','GERAL')
    ORDER BY product
")->fetch_all(MYSQLI_ASSOC);

// Fornecedores — normalizados e deduplicados
$rawSup = $conn->query("
  SELECT supplier, COUNT(*) c
  FROM faqs
  WHERE supplier IS NOT NULL AND TRIM(supplier) <> ''
    AND UPPER(TRIM(supplier)) NOT IN ('TODOS','TODAS','ALL','GERAL')
  GROUP BY supplier
")->fetch_all(MYSQLI_ASSOC);
$norm = function($s){
  $s = trim($s);
  $s = preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
  return mb_strtoupper($s, 'UTF-8');
};
$labelize = function($s){
  $t = trim($s);
  $t = str_replace(['-','/'], ' ', $t);
  $t = preg_replace('/\s+/u', ' ', $t);
  return mb_convert_case(mb_strtolower($t, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
};
$suppliersMap = [];
foreach ($rawSup as $row) {
  $key = $norm($row['supplier']);
  if (!isset($suppliersMap[$key])) $suppliersMap[$key] = ['label'=>$labelize($row['supplier']), 'count'=>(int)$row['c']];
  else $suppliersMap[$key]['count'] += (int)$row['c'];
}
uasort($suppliersMap, function($a,$b){ return strcasecmp($a['label'],$b['label']); });

// WHERE dinâmico
$where = []; $params = []; $types = '';
if ($q !== '') { $where[]="(f.question LIKE ? OR f.answer LIKE ? OR f.product LIKE ? OR f.supplier LIKE ?)"; $like="%{$q}%"; $params[]=$like; $params[]=$like; $params[]=$like; $params[]=$like; $types.='ssss'; }
if ($pf !== '') { $where[]="f.product = ?"; $params[]=$pf; $types.='s'; }
if ($sf !== '') {
  $where[] = "UPPER(REPLACE(REPLACE(REPLACE(TRIM(f.supplier), ' ', ''), '-', ''), '/', '')) = ?";
  $params[] = $sf; $types .= 's';
}
$whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

// total/paginação
$sqlCount = "SELECT COUNT(*) AS c FROM faqs f {$whereSql}";
$stmt = $conn->prepare($sqlCount); if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute(); $total = (int)$stmt->get_result()->fetch_assoc()['c'];
$pages = max(1, (int)ceil($total/$per)); if ($page > $pages) { $page = $pages; $offset = ($page-1)*$per; }

// lista da página
$sqlList = "
  SELECT f.*, u.name AS author
  FROM faqs f
  LEFT JOIN users u ON u.id = f.created_by
  {$whereSql}
  ORDER BY f.updated_at DESC, f.created_at DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sqlList);
if ($types) { $bindTypes = $types.'ii'; $bindParams = array_merge($params, [$per, $offset]); }
else { $bindTypes = 'ii'; $bindParams = [$per, $offset]; }
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute(); $faqs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// KPIs (só “sem filtros”)
$showKpis = ($q==='' && $pf==='' && $sf==='');
if ($showKpis) {
    $kpi_total = (int)$conn->query("SELECT COUNT(*) AS c FROM faqs")->fetch_assoc()['c'];
    $kpi_prod = $conn->query("
        SELECT product, COUNT(*) AS c
        FROM faqs
        WHERE product IS NOT NULL AND TRIM(product) <> ''
          AND UPPER(TRIM(product)) NOT IN ('TODOS','TODAS','ALL','GERAL')
        GROUP BY product
        ORDER BY c DESC
        LIMIT 1
    ")->fetch_assoc();
    $kpi_supp = $conn->query("
        SELECT supplier, COUNT(*) AS c
        FROM faqs
        WHERE supplier IS NOT NULL AND TRIM(supplier) <> ''
          AND UPPER(TRIM(supplier)) NOT IN ('TODOS','TODAS','ALL','GERAL')
        GROUP BY supplier
        ORDER BY c DESC
        LIMIT 1
    ")->fetch_assoc();
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Innova Wiki</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .accordion-button .title-row{display:flex;gap:.75rem;align-items:center;width:100%}
    .accordion-button .qtext{flex:1 1 auto;min-width:0}
    .text-truncate-1 {overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical}

    /* ===== MOBILE (≤576px) ===== */
    @media (max-width: 576px) {
      .top-search-desktop {display:none !important}
      .accordion-button {padding:.75rem}
      .accordion-button .qtext{
        font-weight:600;font-size:1rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden
      }
      .meta-xs {display:block;font-size:.86rem;color:#6c757d}
      .badges-md {display:none}
      .faq-badges {gap:.4rem}
      .badge {font-size:.72rem;padding:.35em .55em}
      .pagination {justify-content:center}
      .pagination .page-link {padding:.35rem .6rem;font-size:.9rem}
      .kpi .card-body {padding:.9rem 1rem}
    }
    /* DESKTOP (≥577px) */
    @media (min-width: 577px){
      .meta-xs {display:none}
      .badges-md {display:flex;gap:.4rem}
      .offcanvas {display:none} /* painel só para mobile */
      .top-search-desktop {display:flex}
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
  <div class="container align-items-center">
    <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="?">
      <img src="logo.png?v=<?=h(defined('APP_VERSION')?APP_VERSION:'1')?>" alt="Innova Wiki" height="28" class="align-text-top" loading="lazy" onerror="this.style.display='none'">
      <span class="d-none d-sm-inline">Innova Wiki</span>
    </a>

    <!-- Busca + filtros (DESKTOP) -->
    <form class="ms-auto gap-2 top-search-desktop" method="get" role="search">
      <input type="hidden" name="action" value="home">
      <input class="form-control" style="min-width:260px" type="search" name="q"
             placeholder="Buscar por termos, produto ou fornecedor..." value="<?=h($q)?>">
      <select class="form-select" name="sf" title="Fornecedor">
        <option value="">Fornecedor</option>
        <?php foreach ($suppliersMap as $key => $info): ?>
          <option value="<?=h($key)?>" <?= ($sf===$key?'selected':'') ?>><?=h($info['label'])?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
      <?php if ($q!=='' || $pf!=='' || $sf!==''): ?>
        <a class="btn btn-outline-secondary" href="?"><i class="bi bi-x-lg"></i></a>
      <?php endif; ?>
    </form>

    <!-- Botões do lado direito -->
    <div class="ms-2 d-flex align-items-center gap-2">
      <!-- Botão FILTROS (MOBILE) -->
      <button class="btn btn-outline-secondary d-inline d-sm-none" data-bs-toggle="offcanvas" data-bs-target="#filtersCanvas">
        <i class="bi bi-sliders"></i> Filtros
      </button>

      <?php if (is_logged()): ?>
        <div class="dropdown">
          <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?=h(user()['name'])?> (<?=h(user()['role'])?>)
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php if (is_admin()): ?>
              <li><a class="dropdown-item" href="users.php"><i class="bi bi-people"></i> Colaboradores</a></li>
              <li><a class="dropdown-item" href="import_faqs.php"><i class="bi bi-upload"></i> Importar FAQs</a></li>
              <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#newFaqModal"><i class="bi bi-plus-circle"></i> Nova FAQ</a></li>
              <li><hr class="dropdown-divider"></li>
            <?php endif;?>
            <li><a class="dropdown-item" href="?action=logout"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
          </ul>
        </div>
      <?php else: ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal"><i class="bi bi-box-arrow-in-right"></i> Entrar</button>
      <?php endif;?>
    </div>
  </div>
</nav>

<!-- OFFCANVAS (MOBILE) -->
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="filtersCanvas" style="height:auto; max-height:90vh;">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><i class="bi bi-sliders"></i> Filtros</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <form method="get" class="d-grid gap-2">
      <input type="hidden" name="action" value="home">
      <input class="form-control" type="search" name="q" placeholder="Buscar por termos..." value="<?=h($q)?>">
      <select class="form-select" name="pf">
        <option value="">Produto</option>
        <?php foreach ($products as $p): $v=$p['product']; ?>
          <option value="<?=h($v)?>" <?= $pf===$v?'selected':'' ?>><?=h($v)?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select" name="sf">
        <option value="">Fornecedor</option>
        <?php foreach ($suppliersMap as $key => $info): ?>
          <option value="<?=h($key)?>" <?= ($sf===$key?'selected':'') ?>><?=h($info['label'])?></option>
        <?php endforeach; ?>
      </select>
      <div class="d-flex gap-2">
        <button class="btn btn-primary flex-fill"><i class="bi bi-search"></i> Aplicar</button>
        <a class="btn btn-outline-secondary flex-fill" href="?"><i class="bi bi-x-lg"></i> Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="container my-4">
  <?php if (!empty($_SESSION['flash'])): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?>
    <div class="alert alert-<?=h($f['type'])?>"><?=h($f['msg'])?></div>
  <?php endif; ?>

  <?php if (isset($_GET['installed'])): ?>
    <div class="alert alert-success">Admin criado com sucesso! Faça login para começar.</div>
  <?php endif; ?>

  <?php if ($showKpis): ?>
    <div class="row g-3 mb-3 kpi">
      <div class="col-md-4">
        <div class="card shadow-sm border-0"><div class="card-body">
          <div class="text-muted small">Total de perguntas respondidas</div>
          <div class="fs-3 fw-semibold"><?= number_format($kpi_total ?? 0, 0, ',', '.') ?></div>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm border-0"><div class="card-body">
          <div class="text-muted small">Produto mais questionado</div>
          <div class="fw-semibold"><?= h($kpi_prod['product'] ?? '—') ?></div>
          <div class="text-muted small"><?= isset($kpi_prod['c']) ? (int)$kpi_prod['c'].' perguntas' : '' ?></div>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm border-0"><div class="card-body">
          <div class="text-muted small">Fornecedor mais questionado</div>
          <div class="fw-semibold"><?= h($kpi_supp['supplier'] ?? '—') ?></div>
          <div class="text-muted small"><?= isset($kpi_supp['c']) ? (int)$kpi_supp['c'].' perguntas' : '' ?></div>
        </div></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="d-flex align-items-center mb-3">
    <h1 class="h4 m-0">Perguntas Frequentes</h1>
    <?php if ($q !== '' || $pf !== '' || $sf !== ''): ?>
      <span class="badge bg-info ms-2">Filtro ativo</span>
    <?php endif; ?>
    <span class="text-muted ms-auto small"><?= $total ?> resultado(s)</span>
  </div>

  <?php if (empty($faqs)): ?>
    <div class="alert alert-light border">
      Nenhum item encontrado. Tente outros termos.
      <?php if (is_admin()): ?> <br>Como admin, você pode
        <a href="#" data-bs-toggle="modal" data-bs-target="#newFaqModal">criar uma nova FAQ</a>.
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="accordion" id="faqAccordion">
      <?php foreach ($faqs as $f): $qid = 'q'.$f['id']; ?>
        <div class="accordion-item">
          <h2 class="accordion-header" id="h<?=$qid?>">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c<?=$qid?>">
              <div class="title-row">
                <div class="qtext"><?=h($f['question'])?></div>
                <div class="badges-md ms-auto">
                  <?php if (!empty($f['product'])): ?>
                    <span class="badge text-bg-secondary">Produto: <?=h($f['product'])?></span>
                  <?php endif; ?>
                  <?php if (!empty($f['supplier'])): ?>
                    <span class="badge text-bg-info">Fornecedor: <?=h($f['supplier'])?></span>
                  <?php endif; ?>
                </div>
                <div class="meta-xs w-100 mt-1">
                  <?php
                    $metaParts = [];
                    if (!empty($f['product']))   $metaParts[] = h($f['product']);
                    if (!empty($f['supplier']))  $metaParts[] = h($f['supplier']);
                    echo implode(' • ', $metaParts);
                  ?>
                </div>
              </div>
            </button>
          </h2>
          <div id="c<?=$qid?>" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              <div class="mb-2"><?=nl2br(h($f['answer']))?></div>

              <?php if (!empty($f['product']) || !empty($f['supplier'])): ?>
                <div class="faq-badges d-flex flex-wrap mb-2">
                  <?php if (!empty($f['product'])): ?>
                    <span class="badge text-bg-secondary me-2 mb-2">Produto: <?=h($f['product'])?></span>
                  <?php endif; ?>
                  <?php if (!empty($f['supplier'])): ?>
                    <span class="badge text-bg-info me-2 mb-2">Fornecedor: <?=h($f['supplier'])?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <div class="d-flex justify-content-between text-muted small align-items-center">
                <span><i class="bi bi-person"></i> <?=h($f['author'] ?? '—')?></span>
                <span class="me-2"><i class="bi bi-calendar"></i>
                  Criado: <?=h($f['created_at'])?> &nbsp;|&nbsp; Atualizado: <?=h($f['updated_at'])?>
                </span>
              </div>

              <?php if (is_admin()): ?>
              <div class="mt-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#editFaqModal"
                        data-id="<?=h($f['id'])?>"
                        data-question="<?=h($f['question'])?>"
                        data-answer="<?=h($f['answer'])?>"
                        data-product="<?=h($f['product'])?>"
                        data-supplier="<?=h($f['supplier'])?>">
                  <i class="bi bi-pencil-square"></i> Editar
                </button>
                <button class="btn btn-sm btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#deleteFaqModal"
                        data-id="<?=h($f['id'])?>"
                        data-question="<?=h($f['question'])?>">
                  <i class="bi bi-trash"></i> Excluir
                </button>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach;?>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="mt-3"><ul class="pagination pagination-sm">
        <?php
          $qsBase = $_GET; unset($qsBase['page']); $base = '?'.http_build_query($qsBase);
          $mk = function($p) use ($base){ return $base.($base==='?'?'':'&')."page={$p}"; };
          $start = max(1,$page-2); $end = min($pages,$page+2);
        ?>
        <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?=h($mk(max(1,$page-1)))?>">Anterior</a></li>
        <?php if ($start>1): ?>
          <li class="page-item"><a class="page-link" href="<?=h($mk(1))?>">1</a></li>
          <?php if ($start>2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for($p=$start;$p<=$end;$p++): ?>
          <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?=h($mk($p))?>"><?=$p?></a></li>
        <?php endfor; ?>
        <?php if ($end<$pages): ?>
          <?php if ($end<$pages-1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
          <li class="page-item"><a class="page-link" href="<?=h($mk($pages))?>"><?=$pages?></a></li>
        <?php endif; ?>
        <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?=h($mk(min($pages,$page+1)))?>">Próxima</a></li>
      </ul></nav>
    <?php endif; ?>

  <?php endif; ?>
</div>

<!-- Modal Login -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?=h($_SERVER['PHP_SELF'])?>>
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <div class="modal-header"><h5 class="modal-title">Entrar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <?php if (!empty($login_error)): ?><div class="alert alert-danger"><?=h($login_error)?></div><?php endif; ?>
        <div class="mb-3"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" required autofocus></div>
        <div class="mb-3"><label class="form-label">Senha</label><input type="password" name="password" class="form-control" required></div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary w-100">Entrar</button></div>
    </form>
  </div>
</div>

<?php if (is_admin()): ?>
<!-- Modal Nova FAQ -->
<div class="modal fade" id="newFaqModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="<?=h($_SERVER['PHP_SELF'])?>>
      <input type="hidden" name="action" value="create_faq">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <div class="modal-header"><h5 class="modal-title">Nova Pergunta & Resposta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Pergunta</label><input type="text" name="question" class="form-control" maxlength="255" required></div>
        <div class="mb-3"><label class="form-label">Resposta</label><textarea name="answer" class="form-control" rows="6" required></textarea></div>
        <div class="mb-3"><label class="form-label">Produto</label><input type="text" name="product" class="form-control" maxlength="150"></div>
        <div class="mb-3"><label class="form-label">Fornecedor</label><input type="text" name="supplier" class="form-control" maxlength="150"></div>
      </div>
      <div class="modal-footer"><button class="btn btn-success"><i class="bi bi-save"></i> Salvar</button></div>
    </form>
  </div>
</div>

<!-- Modal Editar FAQ -->
<div class="modal fade" id="editFaqModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="<?=h($_SERVER['PHP_SELF'])?>>
      <input type="hidden" name="action" value="update_faq">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="faq_id" id="edit-faq-id">
      <div class="modal-header"><h5 class="modal-title">Editar Pergunta & Resposta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Pergunta</label><input type="text" name="question" id="edit-question" class="form-control" maxlength="500" required></div>
        <div class="mb-3"><label class="form-label">Resposta</label><textarea name="answer" id="edit-answer" class="form-control" rows="6" required></textarea></div>
        <div class="mb-3"><label class="form-label">Produto</label><input type="text" name="product" id="edit-product" class="form-control" maxlength="150"></div>
        <div class="mb-3"><label class="form-label">Fornecedor</label><input type="text" name="supplier" id="edit-supplier" class="form-control" maxlength="150"></div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Atualizar</button></div>
    </form>
  </div>
</div>

<!-- Modal Excluir FAQ -->
<div class="modal fade" id="deleteFaqModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?=h($_SERVER['PHP_SELF'])?>">
      <input type="hidden" name="action" value="delete_faq">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="faq_id" id="delete-faq-id">
      <div class="modal-header"><h5 class="modal-title">Confirmar exclusão</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="mb-0">Tem certeza que deseja excluir a FAQ:</p>
        <p class="fw-semibold" id="delete-faq-title"></p>
        <div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle"></i> Esta ação não pode ser desfeita.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger"><i class="bi bi-trash"></i> Excluir</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<footer class="border-top py-3 mt-5">
  <div class="container text-muted small d-flex justify-content-between">
    <span>Innova Wiki • Versão <?=h(defined('APP_VERSION') ? APP_VERSION : '1.0')?></span>
    <span>MySQLi • Bootstrap 5</span>
  </div>
</footer><?php if (is_admin()): ?>
<script>
// Preenche modal de edição
document.getElementById('editFaqModal')?.addEventListener('show.bs.modal', e => {
  const b = e.relatedTarget;
  document.getElementById('edit-faq-id').value   = b.getAttribute('data-id') || '';
  document.getElementById('edit-question').value = b.getAttribute('data-question') || '';
  document.getElementById('edit-answer').value   = b.getAttribute('data-answer') || '';
  document.getElementById('edit-product').value  = b.getAttribute('data-product') || '';
  document.getElementById('edit-supplier').value = b.getAttribute('data-supplier') || '';
});
// Preenche modal de exclusão
document.getElementById('deleteFaqModal')?.addEventListener('show.bs.modal', e => {
  const b = e.relatedTarget;
  document.getElementById('delete-faq-id').value = b.getAttribute('data-id') || '';
  document.getElementById('delete-faq-title').textContent = b.getAttribute('data-question') || '';
});
</script>
<?php endif; ?>
</body>
</html>
