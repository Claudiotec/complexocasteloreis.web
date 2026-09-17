<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php
// Função para verificar permissão do usuário
if (!function_exists('temPermissao')) {
    function temPermissao($modulo, $acao = 'visualizar') {
        global $pdo;
        
        if (!isset($_SESSION['usuario_id'])) {
            return false;
        }
        
        if ($_SESSION['usuario_perfil'] == 'admin') {
            return true;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT $acao FROM permissoes WHERE usuario_id = ? AND modulo = ?");
            $stmt->execute([$_SESSION['usuario_id'], $modulo]);
            $result = $stmt->fetch();
            return $result && $result[$acao] == 1;
        } catch(PDOException $e) {
            return false;
        }
    }
}

// Função para verificar login
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
    }
}

// ===== VERIFICAR LOGIN =====
if (!isLoggedIn() && basename($_SERVER['PHP_SELF']) != 'login.php' && basename($_SERVER['PHP_SELF']) != 'registrar.php') {
    header("Location: " . SITE_URL . "login.php");
    exit;
}

// ===== DADOS DA EMPRESA =====
if (!isset($empresaData)) {
    try {
        $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
        $empresaData = $stmt->fetch();
        if (!$empresaData) {
            $empresaData = [
                'nome_fantasia' => 'SoftGest',
                'razao_social' => 'SoftGest Sistemas Ltda',
                'logo' => null,
                'cnpj' => '00.000.000/0001-00'
            ];
        }
    } catch(PDOException $e) {
        $empresaData = [
            'nome_fantasia' => 'SoftGest',
            'razao_social' => 'SoftGest Sistemas Ltda',
            'logo' => null,
            'cnpj' => '00.000.000/0001-00'
        ];
    }
}

$nomeEmpresa = $empresaData['nome_fantasia'] ?? $empresaData['razao_social'] ?? 'SoftGest';
$logoEmpresa = $empresaData['logo'] ?? null;
?>

<?php if (isLoggedIn()): ?>
<!-- ============================================
     SIDEBAR PREMIUM
     ============================================ -->
<aside class="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <?php if (!empty($logoEmpresa) && file_exists("assets/uploads/" . $logoEmpresa)): ?>
                <img src="<?= SITE_URL ?>assets/uploads/<?= $logoEmpresa ?>" alt="Logo">
            <?php else: ?>
                <span class="brand-text-icon"><?= substr($nomeEmpresa, 0, 2) ?></span>
            <?php endif; ?>
        </div>
        <div class="brand-text">
            <h2><?= htmlspecialchars($nomeEmpresa) ?></h2>
            <span>Enterprise</span>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav">
        <a href="<?= SITE_URL ?>" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large nav-icon"></i>
            <span class="nav-text">Dashboard</span>
        </a>
        
        <?php if (temPermissao('Clientes', 'visualizar')): ?>
        <a href="<?= SITE_URL ?>modules/clientes/">
            <i class="fas fa-users nav-icon"></i>
            <span class="nav-text">Clientes</span>
            <span class="nav-badge"><?= $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn() ?></span>
        </a>
        <?php endif; ?>
        
        <?php if (temPermissao('Produtos', 'visualizar')): ?>
        <a href="<?= SITE_URL ?>modules/produtos/">
            <i class="fas fa-box nav-icon"></i>
            <span class="nav-text">Produtos</span>
            <span class="nav-badge"><?= $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn() ?></span>
        </a>
        <?php endif; ?>
        
        <?php if (temPermissao('Estoque', 'visualizar')): ?>
        <a href="<?= SITE_URL ?>modules/estoque/">
            <i class="fas fa-warehouse nav-icon"></i>
            <span class="nav-text">Estoque</span>
            <span class="nav-badge"><?= $pdo->query("SELECT SUM(quantidade) FROM produtos")->fetchColumn() ?? 0 ?></span>
        </a>
        <?php endif; ?>
        
        <?php if (temPermissao('Faturas', 'visualizar')): ?>
        <a href="<?= SITE_URL ?>modules/fatura_proforma/">
            <i class="fas fa-file-invoice nav-icon"></i>
            <span class="nav-text">Faturas</span>
            <span class="nav-badge"><?= $pdo->query("SELECT COUNT(*) FROM faturas_proforma")->fetchColumn() ?></span>
        </a>
        <?php endif; ?>
        
        <?php if (temPermissao('Recibos', 'visualizar')): ?>
        <a href="<?= SITE_URL ?>modules/fatura_recibo/">
            <i class="fas fa-receipt nav-icon"></i>
            <span class="nav-text">Recibos</span>
            <span class="nav-badge"><?= $pdo->query("SELECT COUNT(*) FROM faturas_recibo")->fetchColumn() ?></span>
        </a>
        <?php endif; ?>
        
        <?php if (temPermissao('Fluxo de Caixa', 'visualizar')): ?>
        <a href="<?= SITE_URL ?>modules/caixa/">
            <i class="fas fa-coins nav-icon"></i>
            <span class="nav-text">Fluxo de Caixa</span>
        </a>
        <?php endif; ?>
        
        <!-- ===== RH - CORRIGIDO ===== -->
        <?php if (temPermissao('RH', 'visualizar')): ?>
        <a href="<?= SITE_URL ?>modules/rh/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/rh/') !== false ? 'active' : '' ?>">
            <i class="fas fa-users-cog nav-icon"></i>
            <span class="nav-text">RH</span>
            <span class="nav-badge"><?= $pdo->query("SELECT COUNT(*) FROM forca_trabalho")->fetchColumn() ?></span>
        </a>
        <?php endif; ?>
        
        <?php if (temPermissao('Marketing', 'visualizar')): ?>
        <a href="<?= SITE_URL ?>modules/marketing/">
            <i class="fas fa-chart-line nav-icon"></i>
            <span class="nav-text">Marketing</span>
            <span class="nav-badge"><?= $pdo->query("SELECT COUNT(*) FROM plano_marketing")->fetchColumn() ?></span>
        </a>
        <?php endif; ?>
        
        <?php if (temPermissao('Correspondência', 'visualizar')): ?>
        <a href="<?= SITE_URL ?>modules/correspondencia/">
            <i class="fas fa-envelope nav-icon"></i>
            <span class="nav-text">Correspondência</span>
            <span class="nav-badge"><?= $pdo->query("SELECT COUNT(*) FROM correspondencias")->fetchColumn() ?></span>
        </a>
        <?php endif; ?>

        <a href="<?= SITE_URL ?>network_access.php" class="nav-link">
            <i class="fas fa-wifi nav-icon"></i>
            <span class="nav-text">🌐 Acesso à Rede</span>
        </a>
        
        <?php if (temPermissao('Empresa', 'visualizar')): ?>
        <a href="<?= SITE_URL ?>modules/empresa/">
            <i class="fas fa-building nav-icon"></i>
            <span class="nav-text">Empresa</span>
        </a>
        <?php endif; ?>
        
        <?php if ($_SESSION['usuario_perfil'] == 'admin'): ?>
        <a href="<?= SITE_URL ?>modules/usuarios/">
            <i class="fas fa-user-shield nav-icon"></i>
            <span class="nav-text">Usuários</span>
            <span class="nav-badge admin-badge">Admin</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div>
                <div class="user-name"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?></div>
                <div class="user-email"><?= htmlspecialchars($_SESSION['usuario_email'] ?? '') ?></div>
                <div class="user-role"><?= ucfirst($_SESSION['usuario_perfil'] ?? '') ?></div>
            </div>
        </div>
        <div class="sidebar-actions">
            <a href="<?= SITE_URL ?>modules/usuarios/perfil.php" class="btn-profile">
                <i class="fas fa-user-cog"></i> Perfil
            </a>
            <a href="<?= SITE_URL ?>logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>
</aside>

<!-- Overlay mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ============================================
     STYLES PREMIUM
     ============================================ -->
<style>
/* ===== SIDEBAR PREMIUM ===== */
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, #0f1724 0%, #1a2332 50%, #1e2d3d 100%);
    color: #fff;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 1000;
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-y: auto;
    box-shadow: 4px 0 30px rgba(0,0,0,0.3);
    border-right: 1px solid rgba(255,255,255,0.05);
}

.sidebar::-webkit-scrollbar {
    width: 4px;
}
.sidebar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.02);
}
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(201, 168, 76, 0.3);
    border-radius: 10px;
}
.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(201, 168, 76, 0.5);
}

/* Brand */
.sidebar-brand {
    padding: 25px 24px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    gap: 14px;
}

.brand-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: linear-gradient(135deg, #c9a84c, #f5d76e);
    flex-shrink: 0;
    box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
}

.brand-icon img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.brand-text-icon {
    font-size: 20px;
    font-weight: 800;
    color: #0f1724;
    letter-spacing: -0.5px;
}

.brand-text h2 {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
    color: #fff;
    letter-spacing: -0.3px;
}

.brand-text span {
    font-size: 11px;
    color: #c9a84c;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 2px;
    opacity: 0.8;
}

/* Navigation */
.sidebar-nav {
    flex: 1;
    padding: 16px 12px 20px;
}

.sidebar-nav a {
    display: flex;
    align-items: center;
    padding: 11px 16px;
    color: rgba(255,255,255,0.55);
    text-decoration: none;
    border-radius: 10px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin-bottom: 2px;
    gap: 14px;
    position: relative;
    font-size: 14px;
    font-weight: 500;
}

.sidebar-nav a .nav-icon {
    font-size: 18px;
    width: 22px;
    text-align: center;
    flex-shrink: 0;
    transition: all 0.3s;
}

.sidebar-nav a .nav-text {
    flex: 1;
}

.sidebar-nav a .nav-badge {
    background: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.6);
    font-size: 10px;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 20px;
    min-width: 20px;
    text-align: center;
    transition: all 0.3s;
}

.sidebar-nav a .admin-badge {
    background: #e74c3c;
    color: #fff;
    font-size: 9px;
    padding: 2px 12px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sidebar-nav a:hover {
    background: rgba(255,255,255,0.06);
    color: #fff;
    transform: translateX(4px);
}

.sidebar-nav a:hover .nav-badge {
    background: rgba(255,255,255,0.15);
    color: #fff;
}

.sidebar-nav a.active {
    background: rgba(201, 168, 76, 0.12);
    color: #c9a84c;
    border: 1px solid rgba(201, 168, 76, 0.15);
}

.sidebar-nav a.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 24px;
    background: #c9a84c;
    border-radius: 0 4px 4px 0;
}

.sidebar-nav a.active .nav-icon {
    color: #c9a84c;
}

.sidebar-nav a.active .nav-badge {
    background: rgba(201, 168, 76, 0.2);
    color: #c9a84c;
}

/* Footer */
.sidebar-footer {
    padding: 16px 20px 20px;
    border-top: 1px solid rgba(255,255,255,0.06);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 12px;
}

.user-avatar {
    width: 42px;
    height: 42px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: rgba(255,255,255,0.4);
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.06);
}

.user-name {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    line-height: 1.2;
}

.user-email {
    font-size: 11px;
    color: rgba(255,255,255,0.35);
}

.user-role {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #c9a84c;
    font-weight: 600;
    margin-top: 1px;
}

.sidebar-actions {
    display: flex;
    gap: 8px;
}

.sidebar-actions a {
    flex: 1;
    text-align: center;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-profile {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.6);
}

.btn-profile:hover {
    background: rgba(255,255,255,0.12);
    color: #fff;
}

.btn-logout {
    background: rgba(231, 76, 60, 0.15);
    color: #e74c3c;
}

.btn-logout:hover {
    background: rgba(231, 76, 60, 0.25);
    color: #ff6b6b;
}

/* Overlay */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    z-index: 999;
    animation: fadeIn 0.3s ease;
}

.sidebar-overlay.active {
    display: block;
}

/* ===== RESPONSIVIDADE ===== */
@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* ===== SCROLLBAR GLOBAL ===== */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
}
::-webkit-scrollbar-thumb {
    background: #c9a84c;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #b8973a;
}
</style>

<?php endif; ?>