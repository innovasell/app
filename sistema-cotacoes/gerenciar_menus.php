<?php
session_start();
$pagina_ativa = 'gerenciar_menus';

ob_start();
require_once 'header.php';
$headerContent = ob_get_clean();

// Proteção
if (!check_permission('gestao')) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Acesso negado.</div></div>";
    exit();
}

require_once 'conexao.php';

// Definir estrutura dos menus para exibição (rótulos amigáveis)
$menusEstrutura = [
    'dashboard' => 'Dashboard (Gráficos)',
    'orcamentos' => 'Orçamentos (Menu Completo)',
    'amostras' => 'Amostras (Solicitações)',
    'estoque' => 'Estoque (Produtos)',
    'financeiro' => 'Financeiro (NFs Emitidas)',
    'cenarios' => 'Cenários de Importação',
    'clientes' => 'Gerenciar Clientes',
    'gestao' => 'Gestão (Central de Gerenciamento)',
    'price_list_view' => 'Price List (Consulta Pública)',
    'price_list_manage' => 'Price List (Gerenciar Upload)'
];

// Buscar permissões atuais
$dbPermissoes = [];
try {
    $stmt = $pdo->query("SELECT * FROM cot_menu_permissoes");
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbPermissoes[$row['menu_key']] = json_decode($row['grupos_permitidos'], true) ?? [];
        }
    }
} catch (Exception $e) {
    // Silencia ou loga. Se tabela não existe, array fica vazio e nada quebra.
    $erroDb = $e->getMessage();
}

$gruposDisponiveis = ['admin', 'gestor', 'geral'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permissões de Menu - H Hansen</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts: Montserrat -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Montserrat', sans-serif; background-color: #f4f6f9; }</style>
</head>
<body>
<?= $headerContent ?>


<div class="container py-5">
    
    <?php if (isset($erroDb)): ?>
        <div class="alert alert-warning shadow-sm">
            <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Aviso</h4>
            <p>Não foi possível carregar as permissões salvas (Tabela 'cot_menu_permissoes' provavelmente não existe).</p>
            <hr>
            <p class="mb-0">Execute <a href="update_db_gestao.php" target="_blank" class="fw-bold">update_db_gestao.php</a> para corrigir.</p>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-success"><i class="fas fa-list-check me-2"></i>Permissões de Menu</h2>
        <div>
            <a href="central_gerenciamento.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
            <button class="btn btn-success ms-2" onclick="salvarPermissoes()">
                <i class="fas fa-save me-2"></i>Salvar Alterações
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">Menu / Funcionalidade</th>
                            <th class="text-center">Admin</th>
                            <th class="text-center">Gestor</th>
                            <th class="text-center">Geral</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menusEstrutura as $key => $label): ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?= htmlspecialchars($label) ?></td>
                            
                            <?php foreach ($gruposDisponiveis as $grupo): 
                                $checked = '';
                                if (isset($dbPermissoes[$key]) && in_array($grupo, $dbPermissoes[$key])) {
                                    $checked = 'checked';
                                }
                                
                                // Admin sempre tem acesso e não pode ser desmarcado
                                $disabled = ($grupo === 'admin') ? 'disabled checked' : '';
                            ?>
                            <td class="text-center">
                                <div class="form-check d-inline-block">
                                    <input class="form-check-input perm-check" type="checkbox" 
                                           data-menu="<?= $key ?>" 
                                           data-grupo="<?= $grupo ?>" 
                                           <?= $checked ?> <?= $disabled ?>>
                                </div>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-warning mt-3">
                <i class="fas fa-info-circle me-1"></i>
                <strong>Nota:</strong> O grupo "Admin" sempre tem acesso a todos os menus por segurança.
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function salvarPermissoes() {
    let permissoes = {};
    
    // Coletar dados
    document.querySelectorAll('.perm-check').forEach(chk => {
        let menu = chk.getAttribute('data-menu');
        let grupo = chk.getAttribute('data-grupo');
        
        if (!permissoes[menu]) {
            permissoes[menu] = []; // Inicializa array
            permissoes[menu].push('admin'); // Garante admin sempre
        }
        
        if (chk.checked && grupo !== 'admin') {
            permissoes[menu].push(grupo);
        }
    });

    fetch('salvar_permissoes_menu.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(permissoes)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Sucesso!', 'Permissões atualizadas.', 'success').then(() => location.reload());
        } else {
            Swal.fire('Erro!', data.message || 'Erro ao salvar.', 'error');
        }
    })
    .catch(err => Swal.fire('Erro!', 'Erro na requisição.', 'error'));
}
</script>
</body>
</html>
