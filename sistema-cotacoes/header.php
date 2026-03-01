<?php
// header.php
if (session_status() == PHP_SESSION_NONE) {
  // 24 hours = 86400 seconds
  ini_set('session.gc_maxlifetime', 86400);
  session_set_cookie_params(86400);
  session_start();
}

if (!isset($_SESSION['representante_email'])) {
  if (basename($_SERVER['PHP_SELF']) != 'index.html') {
    header('Location: index.html');
    exit();
  }
}

$nomeUsuarioLogado = '';
if (isset($_SESSION['representante_nome'])) {
  $nomeUsuarioLogado = trim(($_SESSION['representante_nome'] ?? '') . ' ' . ($_SESSION['representante_sobrenome'] ?? ''));
  if (empty($nomeUsuarioLogado) && isset($_SESSION['representante_email'])) {
    $nomeUsuarioLogado = $_SESSION['representante_email'];
  }
}

// Garante que a variável exista para evitar erros
if (!isset($pagina_ativa)) {
  $pagina_ativa = '';
}

// LÓGICA DE PERMISSÕES
require_once 'conexao.php';

// Atualizar sessão com grupo se ainda não tiver (para migration suave)
if (isset($_SESSION['representante_email']) && !isset($_SESSION['grupo'])) {
  $stmtUser = $pdo->prepare("SELECT grupo, admin FROM cot_representante WHERE email = :email");
  $stmtUser->execute([':email' => $_SESSION['representante_email']]);
  $dadosUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
  if ($dadosUser) {
    $_SESSION['grupo'] = $dadosUser['grupo'] ?? 'geral';
    $_SESSION['admin'] = $dadosUser['admin'];
  } else {
    $_SESSION['grupo'] = 'geral';
  }
}

// Cache de permissões na sessão para evitar query em toda página
if (!isset($_SESSION['permissoes_menu'])) {
  $stmtPerm = $pdo->query("SELECT * FROM cot_menu_permissoes");
  $permissoes = [];
  while ($row = $stmtPerm->fetch(PDO::FETCH_ASSOC)) {
    $permissoes[$row['menu_key']] = json_decode($row['grupos_permitidos'], true);
  }
  $_SESSION['permissoes_menu'] = $permissoes;
}

function check_permission($menu_key)
{
  // Se for admin hardcoded, libera tudo
  if (isset($_SESSION['admin']) && $_SESSION['admin'] == 1)
    return true;
  if (isset($_SESSION['grupo']) && $_SESSION['grupo'] === 'admin')
    return true;

  // Se não tiver permissões definidas, bloqueia por padrão
  if (!isset($_SESSION['permissoes_menu'][$menu_key]))
    return false;

  $userGrupo = $_SESSION['grupo'] ?? 'geral';
  $gruposPermitidos = $_SESSION['permissoes_menu'][$menu_key] ?? [];

  return in_array($userGrupo, $gruposPermitidos);
}

// Lógica para destacar o menu ativo
function is_active($item_key, $active_key, $grupo = false)
{
  if ($grupo) {
    $grupos = [
      'orcamentos' => ['incluir_orcamento', 'filtrar', 'consultar_orcamentos', 'previsao'],
      'amostras' => ['incluir_amostra', 'pesquisar_amostras'],
      'gerenciar' => ['incluir_produto', 'gerenciar_cliente', 'gerenciar_price_list'], // Grouped
      'financeiro' => ['nfs_emitidas'], // UI label handles rename to API
      'cenarios' => ['incluir_cenario', 'consultar_cenarios', 'gerenciar_fornecedores'],
      'pricelists' => ['pricelist_cliente', 'atualizar_budget', 'pricelist_view'],
      'minha_conta' => ['central_gerenciamento', 'gerenciar_usuarios', 'gerenciar_menus'] // User menu
    ];
    return isset($grupos[$item_key]) && in_array($active_key, $grupos[$item_key]);
  } else {
    return $item_key === $active_key;
  }
}

?>
<style>
  /* Styling injected by header.php */
  @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
  @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap');

  .navbar-custom {
    background-color: #ffffff;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    font-family: 'Montserrat', sans-serif;
    padding-top: 15px;
    padding-bottom: 15px;
  }

  .navbar-brand img {
    height: 50px;
    /* Adjust as needed */
    width: auto;
  }

  .navbar-custom .nav-link {
    color: #40883c;
    font-weight: 600;
    font-size: 14px;
    margin: 0 8px;
    transition: all 0.3s ease;
    text-transform: uppercase;
  }

  .navbar-custom .nav-link:hover,
  .navbar-custom .nav-link.active,
  .navbar-custom .nav-link:focus {
    color: #2c5e29;
    background-color: rgba(64, 136, 60, 0.1);
    border-radius: 5px;
  }

  .dropdown-menu {
    border: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
  }

  .dropdown-item {
    color: #555;
    font-size: 14px;
    padding: 10px 20px;
  }

  .dropdown-item:hover {
    background-color: #f8f9fa;
    color: #40883c;
  }

  .dropdown-item.active {
    background-color: #40883c;
    color: #fff;
  }

  .navbar-text {
    color: #666;
    font-weight: 500;
    font-size: 13px;
  }

  /* Responsive Adjustments */
  @media (max-width: 1600px) {
    .navbar-custom {
      padding-top: 5px;
      padding-bottom: 5px;
    }

    .navbar-custom .nav-link {
      font-size: 11px;
      margin: 0 2px;
      padding-left: 3px;
      padding-right: 3px;
      white-space: nowrap;
    }

    .navbar-brand img {
      height: 35px;
    }

    .navbar-text {
      font-size: 11px;
    }
  }

  /* Submenu Styles */
  .dropdown-submenu {
    position: relative;
  }

  .dropdown-submenu .dropdown-menu {
    top: 0;
    left: 100%;
    margin-top: -1px;
  }

  /* Show submenu on hover for desktop */
  @media (min-width: 992px) {
    .dropdown-submenu:hover>.dropdown-menu {
      display: block;
    }
  }
</style>

<nav class="navbar navbar-expand-lg navbar-light navbar-custom sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="bi.php">
      <img src="assets/LOGO.svg" alt="H Hansen">
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown"
      aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

        <?php if (check_permission('dashboard')): ?>
          <li class="nav-item">
            <a class="nav-link" href="bi.php"><i class="fas fa-chart-line me-1"></i> Dashboard</a>
          </li>
        <?php endif; ?>

        <?php if (check_permission('price_list_view') || check_permission('budget_cliente')): ?>
          <li class="nav-item dropdown <?php echo (in_array($pagina_ativa, ['pricelist_view', 'pricelist_cliente', 'atualizar_budget'])) ? 'active' : ''; ?>">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownPriceLists" role="button"
              data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-tags me-1"></i> Price Lists
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdownPriceLists">
              <?php if (check_permission('price_list_view')): ?>
                <li><a class="dropdown-item <?php echo is_active('pricelist_view', $pagina_ativa) ? 'active' : ''; ?>"
                       href="price_list.php">Price List Geral</a></li>
              <?php endif; ?>
              <?php if (check_permission('budget_cliente')): ?>
                <?php if (check_permission('price_list_view')): ?>
                  <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li><a class="dropdown-item <?php echo is_active('pricelist_cliente', $pagina_ativa) ? 'active' : ''; ?>"
                       href="pricelist_cliente.php">BUDGET 2026</a></li>
                <li><a class="dropdown-item <?php echo is_active('atualizar_budget', $pagina_ativa) ? 'active' : ''; ?>"
                       href="atualizar_budget.php"><i class="fas fa-upload me-1 text-muted small"></i> Atualizar BUDGET</a></li>
              <?php endif; ?>
            </ul>
          </li>
        <?php endif; ?>

        <?php if (check_permission('orcamentos')): ?>
          <li class="nav-item dropdown <?php echo is_active('orcamentos', $pagina_ativa, true) ? 'active' : ''; ?>">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownOrcamentos" role="button"
              data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-file-invoice-dollar me-1"></i> Orçamentos
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdownOrcamentos">
              <li><a class="dropdown-item <?php echo is_active('incluir_orcamento', $pagina_ativa) ? 'active' : ''; ?>"
                  href="incluir_orcamento.php">Incluir Orçamento</a></li>
              <li><a class="dropdown-item <?php echo is_active('filtrar', $pagina_ativa) ? 'active' : ''; ?>"
                  href="filtrar.php">Pesquisar Detalhado</a></li>
              <li><a class="dropdown-item <?php echo is_active('consultar_orcamentos', $pagina_ativa) ? 'active' : ''; ?>"
                  href="consultar_orcamentos.php">Consultar Orçamentos</a></li>
              <li><a class="dropdown-item <?php echo is_active('previsao', $pagina_ativa) ? 'active' : ''; ?>"
                  href="previsao.php">Previsão de Datas</a></li>
            </ul>
          </li>
        <?php endif; ?>

        <?php if (check_permission('amostras')): ?>
          <li class="nav-item dropdown <?php echo is_active('amostras', $pagina_ativa, true) ? 'active' : ''; ?>">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAmostras" role="button"
              data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-vial me-1"></i> Solic. Amostras
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdownAmostras">
              <li><a class="dropdown-item <?php echo is_active('incluir_amostra', $pagina_ativa) ? 'active' : ''; ?>"
                  href="incluir_ped_amostras.php">Incluir Pedido</a></li>
              <li><a class="dropdown-item <?php echo is_active('pesquisar_amostras', $pagina_ativa) ? 'active' : ''; ?>"
                  href="filtrar_amostras.php">Pesquisar Amostras</a></li>
            </ul>
          </li>
        <?php endif; ?>

        <!-- NOVO MENU GERENCIAR (Combina Clientes e Produtos) -->
        <!-- NOVO MENU GERENCIAR (Combina Clientes, Produtos e Price List) -->
        <?php if (check_permission('clientes') || check_permission('estoque') || check_permission('price_list_manage')): ?>
          <li class="nav-item dropdown <?php echo is_active('gerenciar', $pagina_ativa, true) ? 'active' : ''; ?>">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownGerenciar" role="button"
              data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-cogs me-1"></i> Gerenciar
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdownGerenciar">
              <?php if (check_permission('clientes')): ?>
                <li><a class="dropdown-item <?php echo is_active('gerenciar_cliente', $pagina_ativa) ? 'active' : ''; ?>"
                    href="gerenciar_cliente.php">Clientes</a></li>
              <?php endif; ?>

              <?php if (check_permission('estoque')): ?>
                <li class="dropdown-submenu">
                  <a class="dropdown-item dropdown-toggle" href="#">Produtos</a>
                  <ul class="dropdown-menu">
                    <li><a class="dropdown-item <?php echo is_active('incluir_produto', $pagina_ativa) ? 'active' : ''; ?>"
                        href="incluir_produto.php">Incluir Produto</a></li>
                    <li><a
                        class="dropdown-item <?php echo is_active('gerenciar_produtos', $pagina_ativa) ? 'active' : ''; ?>"
                        href="gerenciar_produtos.php">Gerenciar Produtos</a></li>
                  </ul>
                </li>
              <?php endif; ?>

              <?php if (check_permission('price_list_manage')): ?>
                <li><a class="dropdown-item <?php echo is_active('gerenciar_price_list', $pagina_ativa) ? 'active' : ''; ?>"
                    href="gerenciar_price_list.php">Price List</a></li>
              <?php endif; ?>
            </ul>
          </li>
        <?php endif; ?>

        <?php if (check_permission('financeiro')): ?>
          <li class="nav-item dropdown <?php echo is_active('financeiro', $pagina_ativa, true) ? 'active' : ''; ?>">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownFinanceiro" role="button"
              data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-network-wired me-1"></i> API
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdownFinanceiro">
              <li><a class="dropdown-item <?php echo is_active('nfs_emitidas', $pagina_ativa) ? 'active' : ''; ?>"
                  href="nfs_emitidas.php">NFs Emitidas</a></li>
            </ul>
          </li>
        <?php endif; ?>

        <?php if (check_permission('cenarios')): ?>
          <li class="nav-item dropdown <?php echo is_active('cenarios', $pagina_ativa, true) ? 'active' : ''; ?>">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownCenarios" role="button"
              data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-globe-americas me-1"></i> Cenários Importação
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdownCenarios">
              <li><a class="dropdown-item <?php echo is_active('incluir_cenario', $pagina_ativa) ? 'active' : ''; ?>"
                  href="incluir_cenario_importacao.php">Novo Cenário</a></li>
              <li><a class="dropdown-item <?php echo is_active('consultar_cenarios', $pagina_ativa) ? 'active' : ''; ?>"
                  href="consultar_cenarios.php">Consultar Cenários</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a
                  class="dropdown-item <?php echo is_active('gerenciar_fornecedores', $pagina_ativa) ? 'active' : ''; ?>"
                  href="gerenciar_fornecedores.php">Gerenciar Fornecedores</a></li>
            </ul>
          </li>
        <?php endif; ?>

      </ul>

      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
        <!-- MINHA CONTA DROPDOWN -->
        <?php if (!empty($nomeUsuarioLogado)): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMinhaConta" role="button"
              data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-user-circle me-1"></i> Minha Conta
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownMinhaConta">
              <li class="px-3 py-2 text-muted" style="font-size: 0.85em; cursor: default;">
                <small>Bem vindo</small><br>
                <strong><?php echo htmlspecialchars(ucwords(strtolower($nomeUsuarioLogado))); ?></strong>
              </li>
              <li>
                <hr class="dropdown-divider">
              </li>

              <?php if (check_permission('gestao')): ?>
                <li><a class="dropdown-item <?php echo is_active('gerenciar_usuarios', $pagina_ativa) ? 'active' : ''; ?>"
                    href="gerenciar_usuarios.php">USUÁRIOS</a></li>
                <li><a class="dropdown-item <?php echo is_active('gerenciar_menus', $pagina_ativa) ? 'active' : ''; ?>"
                    href="gerenciar_menus.php">MENUS</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
              <?php endif; ?>

              <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> SAIR</a>
              </li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>