<?php
/****************************************************
 * Innova Wiki — index.php
 ****************************************************/

// 1. CONFIGURAÇÃO E SESSÃO
// 1. CONFIGURAÇÃO E SESSÃO
if (session_status() === PHP_SESSION_NONE) {
  $savePath = ini_get('session.save_path');
  if (!$savePath || !is_writable($savePath)) {
    session_save_path(sys_get_temp_dir());
  }
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'domain' => '', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax']);
  session_start();
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

// Redirect SSO
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit;
}

require_once __DIR__ . '/config.php';

// 2. HELPERS
function h($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function is_logged()
{
  return isset($_SESSION['user']);
}
function user()
{
  return $_SESSION['user'] ?? null;
}
function is_admin()
{
  return is_logged() && (user()['role'] === 'admin');
}
function csrf_token()
{
  if (empty($_SESSION['csrf']))
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function check_csrf($t)
{
  return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

// Função de cor
function get_supplier_color($name)
{
  if (empty($name))
    return '#6c757d';
  $hash = md5($name);
  $hue = hexdec(substr($hash, 0, 6)) % 360;
  return "hsl({$hue}, 75%, 35%)";
}

// 3. DATABASE SCHEMA
function ensure_schema(mysqli $conn)
{
  $conn->query("CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role ENUM('admin','usuario') NOT NULL DEFAULT 'usuario', created_at DATETIME NOT NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $conn->query("CREATE TABLE IF NOT EXISTS faqs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, question VARCHAR(255) NOT NULL, answer TEXT NOT NULL, created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, product VARCHAR(150) NULL, supplier VARCHAR(150) NULL, CONSTRAINT fk_faq_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $stmt = $conn->prepare("SELECT COUNT(1) AS c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faqs' AND INDEX_NAME = 'idx_faqs_question'");
  $stmt->execute();
  if ((int) $stmt->get_result()->fetch_assoc()['c'] === 0) {
    $conn->query("CREATE INDEX idx_faqs_question ON faqs (question)");
  }
}
try {
  ensure_schema($conn);
} catch (Throwable $e) {
  // Falha silenciosa ou log se necessário, pois o usuário pode não ter permissão de CREATE TABLE
  // e as tabelas já podem existir.
}

// 4. AÇÕES POST
$action = $_GET['action'] ?? $_POST['action'] ?? 'home';

// (Lógica de instalação rápida se necessário - omitida para brevidade, mantém a original se tiver)
if (!function_exists('has_any_user')) {
  function has_any_user(mysqli $conn)
  {
    try {
      $res = $conn->query("SELECT COUNT(*) AS c FROM users");
      return (int) $res->fetch_assoc()['c'] > 0;
    } catch (Throwable $e) {
      return false;
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Login
  if ($action === 'login') {
    if (!check_csrf($_POST['csrf'] ?? ''))
      die('CSRF inválido');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $stmt = $conn->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if ($u && password_verify($pass, $u['password_hash'])) {
      $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']];
      header('Location: ?');
      exit;
    } else {
      $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Dados inválidos.'];
    }
  }
  // CRUD
  if (is_admin()) {
    if ($action === 'create_faq') {
      $q = trim($_POST['question']);
      $a = trim($_POST['answer']);
      $p = trim($_POST['product']);
      $s = trim($_POST['supplier']);
      $uid = (int) user()['id'];
      $stmt = $conn->prepare("INSERT INTO faqs (question, answer, product, supplier, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
      $stmt->bind_param("ssssi", $q, $a, $p, $s, $uid);
      $stmt->execute();
      header('Location: ?');
      exit;
    }
    if ($action === 'update_faq') {
      $id = (int) $_POST['faq_id'];
      $q = trim($_POST['question']);
      $a = trim($_POST['answer']);
      $p = trim($_POST['product']);
      $s = trim($_POST['supplier']);
      $stmt = $conn->prepare("UPDATE faqs SET question=?, answer=?, product=?, supplier=?, updated_at=NOW() WHERE id=?");
      $stmt->bind_param("ssssi", $q, $a, $p, $s, $id);
      $stmt->execute();
      header('Location: ?');
      exit;
    }
    if ($action === 'delete_faq') {
      $id = (int) $_POST['faq_id'];
      $stmt = $conn->prepare("DELETE FROM faqs WHERE id=?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      header('Location: ?');
      exit;
    }
  }
  if ($action === 'logout') {
    session_destroy();
    header('Location: ?');
    exit;
  }
}

// 5. KPI / BI LOGIC (Calcula estatísticas globais)
// Fazemos isso antes dos filtros para que os cards mostrem sempre o total do banco
$kpi_total = (int) $conn->query("SELECT COUNT(*) AS c FROM faqs")->fetch_assoc()['c'];

$kpi_prod = $conn->query("
    SELECT product, COUNT(*) AS c FROM faqs 
    WHERE product IS NOT NULL AND TRIM(product) <> '' 
    GROUP BY product ORDER BY c DESC LIMIT 1
")->fetch_assoc();

$kpi_supp = $conn->query("
    SELECT supplier, COUNT(*) AS c FROM faqs 
    WHERE supplier IS NOT NULL AND TRIM(supplier) <> '' 
    GROUP BY supplier ORDER BY c DESC LIMIT 1
")->fetch_assoc();


// 6. FILTROS DA LISTAGEM
$q = trim($_GET['q'] ?? '');
$pf = trim($_GET['pf'] ?? '');
$sf = trim($_GET['sf'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 20;
$offset = ($page - 1) * $per;

// Listas para Dropdowns
$products = $conn->query("SELECT DISTINCT product FROM faqs WHERE product IS NOT NULL AND TRIM(product) <> '' ORDER BY product")->fetch_all(MYSQLI_ASSOC);
$rawSup = $conn->query("SELECT supplier, COUNT(*) c FROM faqs WHERE supplier IS NOT NULL AND TRIM(supplier) <> '' GROUP BY supplier")->fetch_all(MYSQLI_ASSOC);

// Normalização de fornecedores
$norm = function ($s) {
  return mb_strtoupper(preg_replace('/[^\p{L}\p{N}]+/u', '', trim($s)), 'UTF-8');
};
$labelize = function ($s) {
  return mb_convert_case(mb_strtolower(preg_replace('/\s+/u', ' ', str_replace(['-', '/'], ' ', trim($s))), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
};
$suppliersMap = [];
foreach ($rawSup as $row) {
  $key = $norm($row['supplier']);
  if (!isset($suppliersMap[$key]))
    $suppliersMap[$key] = ['label' => $labelize($row['supplier']), 'count' => (int) $row['c']];
  else
    $suppliersMap[$key]['count'] += (int) $row['c'];
}
uasort($suppliersMap, function ($a, $b) {
  return strcasecmp($a['label'], $b['label']);
});

// Query da Lista
$where = [];
$params = [];
$types = '';
if ($q !== '') {
  $terms = array_filter(explode(' ', $q));
  foreach ($terms as $term) {
    $where[] = "(f.question LIKE ? OR f.answer LIKE ? OR f.product LIKE ? OR f.supplier LIKE ?)";
    $like = "%{$term}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
  }
}
if ($pf !== '') {
  $where[] = "f.product = ?";
  $params[] = $pf;
  $types .= 's';
}
if ($sf !== '') {
  $where[] = "UPPER(REPLACE(REPLACE(REPLACE(TRIM(f.supplier), ' ', ''), '-', ''), '/', '')) = ?";
  $params[] = $sf;
  $types .= 's';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Contagem Filtro
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM faqs f {$whereSql}");
if ($types)
  $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalFiltered = (int) $stmt->get_result()->fetch_assoc()['c'];
$pages = max(1, (int) ceil($totalFiltered / $per));

// Lista Filtro
$stmt = $conn->prepare("SELECT f.*, MAX(u.name) AS author FROM faqs f LEFT JOIN users u ON u.id = f.created_by {$whereSql} GROUP BY f.id ORDER BY f.updated_at DESC LIMIT ? OFFSET ?");
if ($types) {
  $bindTypes = $types . 'ii';
  $bindParams = array_merge($params, [$per, $offset]);
} else {
  $bindTypes = 'ii';
  $bindParams = [$per, $offset];
}
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$faqs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


// ==================================================================
// VIEW
// ==================================================================
require_once 'header.php';
?>

<div class="offcanvas offcanvas-bottom" tabindex="-1" id="filtersCanvas" style="height:auto; max-height:90vh;">
  <div class="offcanvas-header bg-light border-bottom">
    <h5 class="offcanvas-title">Filtros</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <form method="get" class="d-grid gap-3">
      <input type="hidden" name="action" value="home">
      <div>
        <label class="form-label small fw-bold">Filtrar por Produto</label>
        <select class="form-select" name="pf">
          <option value="">Todos</option>
          <?php foreach ($products as $p):
            $v = $p['product']; ?>
            <option value="<?= h($v) ?>" <?= $pf === $v ? 'selected' : '' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary">Aplicar</button>
    </form>
  </div>
</div>

<div class="container my-5">

  <?php if (!empty($_SESSION['flash'])):
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']); ?>
    <div class="alert alert-<?= h($f['type']) ?> shadow-sm border-0 mb-4"><?= h($f['msg']) ?></div>
  <?php endif; ?>

  <?php if ($q === '' && $pf === '' && $sf === ''): // Mostra BI apenas se não houver filtro ativo (ou remova o IF para sempre mostrar) ?>
    <div class="row g-3 mb-5">

      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-left: 5px solid #0d6efd !important;">
          <div class="card-body d-flex align-items-center">
            <div class="bg-light p-3 rounded-circle me-3 text-primary">
              <i class="bi bi-chat-text-fill fs-4"></i>
            </div>
            <div>
              <div class="text-uppercase small text-muted fw-bold">Total de Respostas</div>
              <div class="fs-3 fw-bold text-dark"><?= number_format($kpi_total, 0, ',', '.') ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-left: 5px solid #fd7e14 !important;">
          <div class="card-body d-flex align-items-center">
            <div class="bg-light p-3 rounded-circle me-3 text-warning">
              <i class="bi bi-building-fill fs-4" style="color: #fd7e14;"></i>
            </div>
            <div>
              <div class="text-uppercase small text-muted fw-bold">Fornecedor Top</div>
              <div class="fs-5 fw-bold text-dark text-truncate-1" style="max-width: 200px;"
                title="<?= h($kpi_supp['supplier'] ?? '—') ?>">
                <?= h($kpi_supp['supplier'] ?? '—') ?>
              </div>
              <div class="small text-muted"><?= isset($kpi_supp['c']) ? $kpi_supp['c'] . ' perguntas' : '' ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-left: 5px solid #198754 !important;">
          <div class="card-body d-flex align-items-center">
            <div class="bg-light p-3 rounded-circle me-3 text-success">
              <i class="bi bi-box-seam-fill fs-4"></i>
            </div>
            <div>
              <div class="text-uppercase small text-muted fw-bold">Produto Top</div>
              <div class="fs-5 fw-bold text-dark text-truncate-1" style="max-width: 200px;"
                title="<?= h($kpi_prod['product'] ?? '—') ?>">
                <?= h($kpi_prod['product'] ?? '—') ?>
              </div>
              <div class="small text-muted"><?= isset($kpi_prod['c']) ? $kpi_prod['c'] . ' perguntas' : '' ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="d-flex align-items-end mb-4 pb-2 border-bottom">
    <h2 class="h5 m-0 text-uppercase fw-bold text-secondary ls-1">Perguntas Recentes</h2>
    <span class="ms-auto badge bg-light text-dark border">
      <?= $totalFiltered ?> encontrados
    </span>
  </div>

  <?php if (empty($faqs)): ?>
    <div class="text-center py-5 bg-light rounded-3 border border-dashed">
      <i class="bi bi-search fs-1 text-muted opacity-50"></i>
      <h5 class="mt-3 fw-bold text-muted">Nenhum resultado encontrado</h5>
    </div>
  <?php else: ?>

    <div class="accordion" id="faqAccordion">
      <?php foreach ($faqs as $f):
        $qid = 'q' . $f['id']; ?>
        <div class="accordion-item border mb-3 shadow-sm rounded-2 overflow-hidden"
          style="border: 1px solid rgba(0,0,0,0.08);">
          <h2 class="accordion-header" id="h<?= $qid ?>">
            <button class="accordion-button collapsed bg-white py-3" type="button" data-bs-toggle="collapse"
              data-bs-target="#c<?= $qid ?>">
              <div class="title-row">
                <div class="main-content">
                  <?php if (!empty($f['product'])): ?>
                    <span class="product-highlight"><?= h($f['product']) ?></span>
                  <?php endif; ?>
                  <span class="qtext"><?= h($f['question']) ?></span>
                </div>
                <div class="badges-wrapper">
                  <?php if (!empty($f['supplier'])): ?>
                    <span class="badge badge-supplier"
                      style="background-color: <?= get_supplier_color($f['supplier']) ?> !important;">
                      <?= h($f['supplier']) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </button>
          </h2>
          <div id="c<?= $qid ?>" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body bg-light">
              <div class="p-3 bg-white rounded border"><?= nl2br(h($f['answer'])) ?></div>
              <div class="d-flex justify-content-between text-muted small mt-3 align-items-center">
                <span><i class="bi bi-person-fill"></i> Resp: <strong><?= h($f['author'] ?? 'Admin') ?></strong></span>
                <span><i class="bi bi-clock"></i> <?= date('d/m/Y', strtotime($f['updated_at'])) ?></span>
              </div>
              <?php if (is_admin()): ?>
                <div class="mt-3 pt-3 border-top d-flex gap-3">
                  <button class="btn btn-sm btn-outline-primary px-3" data-bs-toggle="modal" data-bs-target="#editFaqModal"
                    data-id="<?= h($f['id']) ?>" data-question="<?= h($f['question']) ?>" data-answer="<?= h($f['answer']) ?>"
                    data-product="<?= h($f['product']) ?>" data-supplier="<?= h($f['supplier']) ?>"><i
                      class="bi bi-pencil-square"></i> Editar</button>
                  <button class="btn btn-sm btn-outline-danger px-3" data-bs-toggle="modal" data-bs-target="#deleteFaqModal"
                    data-id="<?= h($f['id']) ?>" data-question="<?= h($f['question']) ?>"><i class="bi bi-trash"></i>
                    Excluir</button>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="mt-5 mb-5 d-flex justify-content-center">
        <ul class="pagination flex-wrap">
          <?php
          $qs = $_GET;
          unset($qs['page']);
          $b = '?' . http_build_query($qs);
          $lnk = function ($p) use ($b) {
            return $b . ($b === '?' ? '' : '&') . "page=$p";
          };
          $range = 2;
          ?>
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link"
              href="<?= h($lnk(max(1, $page - 1))) ?>"><span aria-hidden="true">&laquo;</span></a></li>
          <?php for ($i = 1; $i <= $pages; $i++):
            if ($i == 1 || $i == $pages || ($i >= $page - $range && $i <= $page + $range)): ?>
              <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link"
                  href="<?= h($lnk($i)) ?>"><?= $i ?></a></li>
            <?php elseif ($i == $page - $range - 1 || $i == $page + $range + 1): ?>
              <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; endfor; ?>
          <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link"
              href="<?= h($lnk(min($pages, $page + 1))) ?>"><span aria-hidden="true">&raquo;</span></a></li>
        </ul>
      </nav>
    <?php endif; ?>

  <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>