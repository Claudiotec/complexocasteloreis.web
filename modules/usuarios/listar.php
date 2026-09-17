<?php
// listar.php - Versão SIMPLIFICADA sem verificação de permissão
// =============================================================

// 1. INICIAR SESSÃO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. INCLUIR CONEXÃO COM BANCO
require_once '../../config/database.php';

// 3. VERIFICAR SE ESTÁ LOGADO (SOMENTE ISSO)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// 4. NÃO TEM VERIFICAÇÃO DE PERMISSÃO - ACESSO LIBERADO

// 5. PAGINAÇÃO E FILTROS
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$rol_filter = isset($_GET['rol']) ? (int)$_GET['rol'] : 0;
$estado_filter = isset($_GET['estado']) ? mysqli_real_escape_string($conn, $_GET['estado']) : '';

// 6. CONSULTA SIMPLES
$where = "";
if (!empty($search)) {
    $where = "WHERE (nombre LIKE '%$search%' OR apellido LIKE '%$search%' OR email LIKE '%$search%')";
}
if ($rol_filter > 0) {
    $where .= ($where ? " AND" : " WHERE") . " rol_id = $rol_filter";
}
if (!empty($estado_filter)) {
    $where .= ($where ? " AND" : " WHERE") . " estado = '$estado_filter'";
}

// Contar total
$count_query = "SELECT COUNT(*) as total FROM usuarios $where";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);

// Buscar dados
$query = "SELECT u.*, r.nombre as rol_nombre 
          FROM usuarios u
          LEFT JOIN roles r ON u.rol_id = r.id
          $where
          ORDER BY u.id DESC
          LIMIT $offset, $per_page";
$result = mysqli_query($conn, $query);

// Buscar roles para o filtro
$roles_query = "SELECT id, nombre FROM roles ORDER BY nombre";
$roles_result = mysqli_query($conn, $roles_query);
$roles = mysqli_fetch_all($roles_result, MYSQLI_ASSOC);

// Mensagens da sessão
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listar Usuários - SoftGest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .navbar-custom { background: #2c3e50; }
        .navbar-custom .navbar-brand { color: #fff; }
        .navbar-custom .nav-link { color: rgba(255,255,255,.8); }
        .navbar-custom .nav-link:hover { color: #fff; }
        .avatar-circle {
            width: 40px;
            height: 40px;
            background: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: bold;
            font-size: 18px;
        }
        .table-actions .btn { margin: 0 2px; }
        .badge-status { min-width: 80px; }
    </style>
</head>
<body>
    <!-- NAVBAR SIMPLES -->
    <nav class="navbar navbar-expand-lg navbar-custom mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-users-cog me-2"></i> SoftGest
            </a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="fas fa-user-circle me-1"></i>
                    <?= isset($_SESSION['user_nome']) ? htmlspecialchars($_SESSION['user_nome']) : 'Usuário' ?>
                </span>
                <a href="../../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- TÍTULO -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-users text-primary me-2"></i>Lista de Usuários
                <span class="badge bg-secondary ms-2"><?= $total_rows ?></span>
            </h2>
            <div>
                <a href="criar.php" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i> Novo Usuário
                </a>
                <a href="../dashboard/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </a>
            </div>
        </div>

        <!-- MENSAGENS -->
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- FILTROS -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-1"></i> Buscar
                        </label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Nome, email..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-user-tag me-1"></i> Função
                        </label>
                        <select name="rol" class="form-select">
                            <option value="0">Todas</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?= $rol['id'] ?>" <?= $rol_filter == $rol['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rol['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-circle me-1"></i> Status
                        </label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            <option value="activo" <?= $estado_filter == 'activo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inactivo" <?= $estado_filter == 'inactivo' ? 'selected' : '' ?>>Inativo</option>
                            <option value="bloqueado" <?= $estado_filter == 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                        <a href="listar.php" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i> Limpar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- TABELA -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold">
                        <i class="fas fa-list me-2"></i> 
                        <?= $total_rows ?> usuário(s) encontrado(s)
                    </span>
                    <span class="text-muted small">
                        Mostrando <?= ($offset + 1) ?> - <?= min($offset + $per_page, $total_rows) ?>
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" id="tabelaUsuarios">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Função</th>
                                <th>Status</th>
                                <th>Último Acesso</th>
                                <th style="width: 180px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php $contador = $offset + 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td class="fw-bold"><?= $contador++ ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-2">
                                                    <?= strtoupper(substr($row['nombre'], 0, 1)) ?>
                                                </div>
                                                <?= htmlspecialchars($row['nombre'] . ' ' . $row['apellido']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="mailto:<?= htmlspecialchars($row['email']) ?>">
                                                <?= htmlspecialchars($row['email']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= htmlspecialchars($row['rol_nombre']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $status = [
                                                'activo' => ['class' => 'success', 'icon' => 'fa-check-circle'],
                                                'inactivo' => ['class' => 'secondary', 'icon' => 'fa-circle'],
                                                'bloqueado' => ['class' => 'danger', 'icon' => 'fa-lock']
                                            ];
                                            $s = $status[$row['estado']] ?? ['class' => 'secondary', 'icon' => 'fa-circle'];
                                            ?>
                                            <span class="badge bg-<?= $s['class'] ?> badge-status">
                                                <i class="fas <?= $s['icon'] ?> me-1"></i>
                                                <?= ucfirst($row['estado']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['ultimo_acceso'] && $row['ultimo_acceso'] != '0000-00-00 00:00:00'): ?>
                                                <small><?= date('d/m/Y H:i', strtotime($row['ultimo_acceso'])) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Nunca</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="table-actions">
                                            <div class="btn-group btn-group-sm">
                                                <a href="ver.php?id=<?= $row['id'] ?>" class="btn btn-outline-info" title="Ver">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="editar.php?id=<?= $row['id'] ?>" class="btn btn-outline-warning" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                                    <a href="excluir.php?id=<?= $row['id'] ?>" class="btn btn-outline-danger" 
                                                       onclick="return confirm('Tem certeza que deseja excluir este usuário?')" title="Excluir">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small ms-2">(Você)</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fas fa-users-slash fa-3x d-block mb-3"></i>
                                        <h5>Nenhum usuário encontrado</h5>
                                        <p class="text-muted">Ajuste os filtros ou crie um novo usuário</p>
                                        <a href="criar.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus me-1"></i> Criar usuário
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- PAGINAÇÃO -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&rol=<?= $rol_filter ?>&estado=<?= $estado_filter ?>">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        </li>
                        
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>&rol=<?= $rol_filter ?>&estado=<?= $estado_filter ?>">1</a>
                            </li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&rol=<?= $rol_filter ?>&estado=<?= $estado_filter ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&rol=<?= $rol_filter ?>&estado=<?= $estado_filter ?>">
                                    <?= $total_pages ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&rol=<?= $rol_filter ?>&estado=<?= $estado_filter ?>">
                                Próximo <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- RODAPÉ -->
        <div class="mt-3 text-muted small text-center">
            <i class="fas fa-database me-1"></i>
            Total: <?= $total_rows ?> registros | 
            Atualizado: <?= date('d/m/Y H:i:s') ?>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            var table = $('#tabelaUsuarios').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                },
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[0, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [6] },
                    { className: 'align-middle', targets: '_all' }
                ],
                searching: <?= empty($search) && $rol_filter == 0 && empty($estado_filter) ? 'true' : 'false' ?>,
                paging: <?= $total_rows > 10 ? 'true' : 'false' ?>,
                info: true
            });

            // Auto-submit ao mudar selects
            $('select[name="rol"], select[name="estado"]').on('change', function() {
                $(this).closest('form').submit();
            });

            // Busca automática com delay
            var timeout;
            $('input[name="search"]').on('keyup', function() {
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    $('form').submit();
                }, 500);
            });
        });
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>