<?php
// ============================================
// index.php - SoftGest Dashboard
// ============================================

// ===== CONFIGURAÇÕES INICIAIS =====
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== INICIAR SESSÃO ANTES DE TUDO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== PRIMEIRO: VERIFICAR SE O USUÁRIO ESTÁ LOGADO =====
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// ===== SEGUNDO: Carregar configurações de modo =====
require_once 'config/app_modes.php';

// ===== TERCEIRO: Carregar banco de dados =====
require_once 'config/database.php';

// ===== QUARTO: Carregar cliente de licença (se existir) =====
if (file_exists('modules/license/LicenseClient.php')) {
    require_once 'modules/license/LicenseClient.php';
}

// ===== CONECTAR AO BANCO BASEADO NO MODO =====
try {
    $pdo = conectarBanco();
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// ===== QUINTO: Verificar licença =====
require_once 'includes/license_check.php';

if (!verificarEBlquear()) {
    exit;
}

// =============================================
// ===== VERIFICAR SE O USUÁRIO É PROFESSOR =====
// =============================================
function isProfessor($pdo, $usuario_id) {
    try {
        // Buscar nome do usuário
        $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            return false;
        }
        
        $nome_usuario = trim($usuario['nome']);
        
        // Buscar na tabela distribuicao_professores
        $stmt = $pdo->prepare("
            SELECT * FROM distribuicao_professores 
            WHERE professor_nome = ? 
            LIMIT 1
        ");
        $stmt->execute([$nome_usuario]);
        $professor = $stmt->fetch();
        
        if ($professor) {
            // Salvar dados do professor na sessão
            $_SESSION['professor_id'] = $professor['id'];
            $_SESSION['professor_nome'] = $professor['professor_nome'];
            $_SESSION['professor_dados'] = $professor;
            $_SESSION['is_professor'] = true;
            $_SESSION['professor_tipo'] = $professor['tipo'] ?? 'PROFESSOR';
            $_SESSION['professor_turma_id'] = $professor['turma_id'];
            $_SESSION['professor_turma_nome'] = $professor['turma_nome'];
            $_SESSION['professor_classe'] = $professor['classe'];
            $_SESSION['professor_disciplinas'] = $professor['disciplinas'];
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Erro ao verificar professor: " . $e->getMessage());
        return false;
    }
}

function isAluno($pdo, $usuario_id) {
    try {
        $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            return false;
        }
        
        $nome_usuario = trim($usuario['nome']);
        
        $stmt = $pdo->prepare("
            SELECT id 
            FROM alunos 
            WHERE nome = ? 
            LIMIT 1
        ");
        $stmt->execute([$nome_usuario]);
        $aluno = $stmt->fetch();
        
        if ($aluno) {
            $_SESSION['aluno_id'] = $aluno['id'];
            $_SESSION['is_aluno'] = true;
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Erro ao verificar aluno: " . $e->getMessage());
        return false;
    }
}


// ===== VERIFICAÇÃO E REDIRECIONAMENTO =====
$isProfessor = isProfessor($pdo, $_SESSION['usuario_id']);
$isAluno = isAluno($pdo, $_SESSION['usuario_id']);

// Se for professor, redirecionar para o template de professor
if ($isProfessor) {
    header('Location: modules/escola/professor_dashboard.php');
    exit;
}

// Se for aluno, redirecionar para o template de aluno
if ($isAluno) {
    header('Location: modules/escola/aluno_dashboard.php');
    exit;
}

// ===== VERIFICAR LICENÇA =====
function verificarLicenca() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'licencas'");
        if ($stmt->rowCount() == 0) {
            return [
                'status' => 'ativa',
                'tipo' => 'local',
                'codigo_licenca' => 'LOCAL-' . date('Ymd'),
                'data_expiracao' => null,
                'dias_restantes' => '∞'
            ];
        }
        
        $stmt = $pdo->prepare("SELECT * FROM licencas WHERE status = 'ativa' ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $licenca = $stmt->fetch();
        
        if (!$licenca) {
            return [
                'status' => 'ativa',
                'tipo' => 'local',
                'codigo_licenca' => 'DEV-' . date('Ymd'),
                'data_expiracao' => null,
                'dias_restantes' => '∞'
            ];
        }
        
        return $licenca;
        
    } catch (Exception $e) {
        return [
            'status' => 'ativa',
            'tipo' => 'local',
            'codigo_licenca' => 'FALLBACK-' . date('Ymd'),
            'data_expiracao' => null,
            'dias_restantes' => '∞'
        ];
    }
}

// ===== BUSCAR DADOS DA LICENÇA =====
function getLicencaInfo() {
    global $pdo;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'licencas'");
        if ($stmt->rowCount() == 0) {
            return [
                'status' => 'ativa',
                'tipo' => 'local',
                'codigo_licenca' => 'LOCAL-' . date('Ymd'),
                'data_expiracao' => null,
                'data_expiracao_formatada' => 'Ilimitado',
                'dias_restantes' => '∞',
                'cor_status' => '#3498db',
                'icone_status' => '🔵'
            ];
        }
        
        $stmt = $pdo->prepare("
            SELECT *, 
            DATEDIFF(data_expiracao, NOW()) as dias_restantes
            FROM licencas 
            WHERE status = 'ativa' 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $licenca = $stmt->fetch();
        
        if (!$licenca) {
            return [
                'status' => 'ativa',
                'tipo' => 'local',
                'codigo_licenca' => 'DEV-' . date('Ymd'),
                'data_expiracao' => null,
                'data_expiracao_formatada' => 'Ilimitado',
                'dias_restantes' => '∞',
                'cor_status' => '#3498db',
                'icone_status' => '🔵'
            ];
        }
        
        if ($licenca['data_expiracao']) {
            $licenca['data_expiracao_formatada'] = date('d/m/Y', strtotime($licenca['data_expiracao']));
            $licenca['dias_restantes'] = max(0, $licenca['dias_restantes']);
            
            if ($licenca['dias_restantes'] <= 7) {
                $licenca['cor_status'] = '#e74c3c';
                $licenca['icone_status'] = '🔴';
            } elseif ($licenca['dias_restantes'] <= 30) {
                $licenca['cor_status'] = '#f39c12';
                $licenca['icone_status'] = '🟡';
            } else {
                $licenca['cor_status'] = '#2ecc71';
                $licenca['icone_status'] = '🟢';
            }
        } else {
            $licenca['data_expiracao_formatada'] = 'Ilimitado';
            $licenca['dias_restantes'] = '∞';
            $licenca['cor_status'] = '#3498db';
            $licenca['icone_status'] = '🔵';
        }
        
        return $licenca;
        
    } catch (Exception $e) {
        return [
            'status' => 'ativa',
            'tipo' => 'local',
            'codigo_licenca' => 'ERROR-' . date('Ymd'),
            'data_expiracao' => null,
            'data_expiracao_formatada' => 'Ilimitado',
            'dias_restantes' => '∞',
            'cor_status' => '#3498db',
            'icone_status' => '🔵'
        ];
    }
}

// ===== EXECUTAR VERIFICAÇÕES =====
$licenca = verificarLicenca();
$licencaInfo = getLicencaInfo();
$modo = getModoAtual();

// ===== BUSCAR DADOS DA EMPRESA =====
function getEmpresaData() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
        $empresa = $stmt->fetch();
        if (!$empresa) {
            return [
                'nome_fantasia' => 'SoftGest',
                'razao_social' => 'SoftGest Sistemas Ltda',
                'logo' => null,
                'cnpj' => '00.000.000/0001-00'
            ];
        }
        return $empresa;
    } catch(PDOException $e) {
        return [
            'nome_fantasia' => 'SoftGest',
            'razao_social' => 'SoftGest Sistemas Ltda',
            'logo' => null,
            'cnpj' => '00.000.000/0001-00'
        ];
    }
}

$empresaData = getEmpresaData();
$nomeEmpresa = $empresaData['nome_fantasia'] ?? $empresaData['razao_social'] ?? 'SoftGest';
$logoEmpresa = $empresaData['logo'] ?? null;

// ===== DADOS DO USUÁRIO =====
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$usuario_perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_email = $_SESSION['usuario_email'] ?? '';

// ===== PERMISSÕES =====
function userHasAccess($modulo) {
    global $pdo;
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    $perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
    
    if ($perfil == 'admin') {
        return true;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT visualizar FROM permissoes WHERE usuario_id = ? AND modulo = ?");
        $stmt->execute([$usuario_id, $modulo]);
        $result = $stmt->fetch();
        return $result && $result['visualizar'] == 1;
    } catch(PDOException $e) {
        return false;
    }
}

// ===== DADOS DO DASHBOARD =====
$totalClientes = 0;
$totalProdutos = 0;
$totalFaturas = 0;
$totalFuncionarios = 0;
$totalRecibos = 0;
$totalPlanos = 0;
$totalCorrespondencias = 0;
$totalEntradas = 0;
$totalSaidas = 0;
$saldoAtual = 0;
$vendasMes = 0;
$totalEstoque = 0;
$movRecentes = [];

// ===== DADOS DO MÓDULO ESCOLA =====
$totalAlunos = 0;
$totalProfessores = 0;
$totalTurmas = 0;
$totalMatriculas = 0;

if (userHasAccess('Clientes')) {
    try {
        $totalClientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
    } catch (Exception $e) {}
}
if (userHasAccess('Produtos') || userHasAccess('Estoque')) {
    try {
        $totalProdutos = $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
        $totalEstoque = $pdo->query("SELECT SUM(quantidade) FROM produtos")->fetchColumn() ?? 0;
    } catch (Exception $e) {}
}
if (userHasAccess('Faturas')) {
    try {
        $totalFaturas = $pdo->query("SELECT COUNT(*) FROM faturas_proforma")->fetchColumn();
    } catch (Exception $e) {}
}
if (userHasAccess('Recibos')) {
    try {
        $totalRecibos = $pdo->query("SELECT COUNT(*) FROM faturas_recibo")->fetchColumn();
    } catch (Exception $e) {}
}
if (userHasAccess('Fluxo de Caixa')) {
    try {
        $totalEntradas = $pdo->query("SELECT SUM(valor) as total FROM movimentacoes_caixa WHERE tipo = 'entrada' AND status = 'confirmado'")->fetchColumn() ?? 0;
        $totalSaidas = $pdo->query("SELECT SUM(valor) as total FROM movimentacoes_caixa WHERE tipo = 'saida' AND status = 'confirmado'")->fetchColumn() ?? 0;
        $saldoAtual = $totalEntradas - $totalSaidas;
        
        $mes = date('Y-m');
        $vendasMes = $pdo->query("SELECT SUM(valor) as total FROM movimentacoes_caixa WHERE tipo = 'entrada' AND categoria = 'Vendas' AND DATE_FORMAT(data_movimento, '%Y-%m') = '$mes' AND status = 'confirmado'")->fetchColumn() ?? 0;
        
        $movRecentes = $pdo->query("SELECT * FROM movimentacoes_caixa WHERE status = 'confirmado' ORDER BY data_movimento DESC LIMIT 5")->fetchAll();
    } catch (Exception $e) {}
}
if (userHasAccess('RH')) {
    try {
        $totalFuncionarios = $pdo->query("SELECT COUNT(*) FROM funcionarios")->fetchColumn();
    } catch (Exception $e) {}
}
if (userHasAccess('Marketing')) {
    try {
        $totalPlanos = $pdo->query("SELECT COUNT(*) FROM plano_marketing")->fetchColumn();
    } catch (Exception $e) {}
}
if (userHasAccess('Correspondência')) {
    try {
        $totalCorrespondencias = $pdo->query("SELECT COUNT(*) FROM correspondencias")->fetchColumn();
    } catch (Exception $e) {}
}

// ===== DADOS DO MÓDULO ESCOLA =====
if (userHasAccess('Escola')) {
    try {
        $totalAlunos = $pdo->query("SELECT COUNT(*) FROM alunos")->fetchColumn() ?? 0;
    } catch (Exception $e) {}
    try {
        $totalProfessores = $pdo->query("SELECT COUNT(*) FROM professores")->fetchColumn() ?? 0;
    } catch (Exception $e) {}
    try {
        $totalTurmas = $pdo->query("SELECT COUNT(*) FROM turmas")->fetchColumn() ?? 0;
    } catch (Exception $e) {}
    try {
        $totalMatriculas = $pdo->query("SELECT COUNT(*) FROM matriculas WHERE status = 'ativa'")->fetchColumn() ?? 0;
    } catch (Exception $e) {}
}

$isAdmin = ($usuario_perfil == 'admin');
$perfilBadgeColor = $usuario_perfil == 'admin' ? '#e74c3c' : ($usuario_perfil == 'gerente' ? '#f59e0b' : '#3498db');

include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - <?= htmlspecialchars($nomeEmpresa) ?></title>
    
    <!-- ========================================== -->
    <!-- SERVICE WORKER PARA OFFLINE -->
    <!-- ========================================== -->
    <script>
    // Registrar Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/softgest_web/sw.js')
            .then(registration => {
                console.log('✅ Service Worker registrado com sucesso!');
                console.log('📦 Scope:', registration.scope);
            })
            .catch(error => {
                console.log('❌ Erro ao registrar Service Worker:', error);
            });
    }
    </script>
    
    <style>
        /* ============================================
           RESET E ESTILOS GERAIS
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            overflow-x: hidden;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* ============================================
           SIDEBAR
           ============================================ */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1a2332 0%, #2c3e50 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
            box-shadow: 2px 0 20px rgba(0,0,0,0.2);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .sidebar-brand {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .brand-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: #1a2332;
            overflow: hidden;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            flex-shrink: 0;
        }
        
        .brand-icon img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .brand-text h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            color: #fff;
        }
        
        .brand-text span {
            font-size: 14px;
            color: #f5d76e;
            font-weight: 300;
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 15px 10px;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-bottom: 2px;
            gap: 12px;
            position: relative;
        }
        
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
            transform: translateX(5px);
        }
        
        .sidebar-nav a.active {
            background: rgba(245, 215, 110, 0.2);
            color: #f5d76e;
            box-shadow: inset 3px 0 0 #f5d76e;
        }
        
        .sidebar-nav a .nav-icon {
            font-size: 20px;
            width: 30px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .sidebar-nav a .nav-text {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
        }
        
        .sidebar-nav a .nav-badge {
            background: #f5d76e;
            color: #1a2332;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            min-width: 20px;
            text-align: center;
        }
        
        .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
        }
        
        .user-email {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }
        
        .btn-logout-sidebar {
            display: block;
            text-align: center;
            padding: 8px;
            background: rgba(231, 76, 60, 0.3);
            color: #e74c3c;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .btn-logout-sidebar:hover {
            background: rgba(231, 76, 60, 0.5);
            color: #fff;
        }
        
        /* ============================================
           MAIN CONTENT
           ============================================ */
        .main-content {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
            background: #f0f2f5;
        }
        
        /* ============================================
           TOP BAR
           ============================================ */
        .top-bar-gold {
            background: linear-gradient(135deg, #ffffff 0%, #fefcf3 100%);
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 20px rgba(197,165,50,0.15);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 2px solid #f5d76e;
            animation: slideDown 0.5s ease;
        }
        
        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .menu-toggle {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #1a2332;
            display: none;
            padding: 5px 12px;
            border-radius: 6px;
            transition: background 0.3s;
        }
        
        .menu-toggle:hover {
            background: #f0f2f5;
        }
        
        .top-bar-nav {
            display: flex;
            gap: 5px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .top-bar-nav a {
            color: #4a5568;
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .top-bar-nav a:hover {
            background: rgba(197,165,50,0.1);
            color: #c9a84c;
        }
        
        .top-bar-nav a.active {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            font-weight: 600;
            box-shadow: 0 2px 15px rgba(197,165,50,0.3);
        }
        
        .top-bar-nav a .badge-novo {
            background: #e74c3c;
            color: white;
            font-size: 9px;
            padding: 1px 8px;
            border-radius: 10px;
            position: absolute;
            top: -5px;
            right: -5px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .welcome-text {
            font-size: 14px;
            color: #4a5568;
            font-weight: 500;
        }
        
        .date-time-gold {
            font-size: 14px;
            color: #c9a84c;
            background: rgba(197,165,50,0.1);
            padding: 6px 18px;
            border-radius: 20px;
            border: 1px solid rgba(197,165,50,0.2);
            font-weight: 500;
            animation: glowPulse 2s ease-in-out infinite;
            white-space: nowrap;
        }
        
        @keyframes glowPulse {
            0%, 100% { box-shadow: 0 0 5px rgba(197,165,50,0.1); }
            50% { box-shadow: 0 0 20px rgba(197,165,50,0.2); }
        }
        
        /* ===== MODO SWITCHER BANNER ===== */
        .mode-banner {
            background: <?= APP_MODE_COLOR ?>;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            margin: 15px 30px 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .mode-banner .info { display: flex; align-items: center; gap: 10px; }
        .mode-banner .info .icon { font-size: 24px; }
        .mode-banner .info .text { font-weight: 600; }
        .mode-banner .info .sub { font-size: 13px; opacity: 0.8; }
        .mode-banner .actions { display: flex; gap: 8px; }
        .mode-banner .actions a {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 5px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.3s;
        }
        .mode-banner .actions a:hover { background: rgba(255,255,255,0.3); }
        .mode-banner .actions a.active { background: rgba(255,255,255,0.4); }
        
        @media (max-width: 768px) {
            .mode-banner {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
                margin: 15px 15px 20px 15px;
            }
            .mode-banner .actions { justify-content: center; flex-wrap: wrap; }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ============================================
           CONTENT AREA
           ============================================ */
        .content-area {
            padding: 25px 30px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 800;
            color: #1a2332;
            margin: 0;
            background: linear-gradient(135deg, #1a2332, #c9a84c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: fadeInLeft 0.8s ease forwards;
            opacity: 0;
        }
        
        .page-subtitle {
            color: #94a3b8;
            font-size: 15px;
            margin: 5px 0 0;
            font-weight: 400;
        }
        
        /* ===== BANNER PARA PROFESSORES ===== */
        .professor-warning {
            background: #fef9e7;
            border: 2px solid #f39c12;
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .professor-warning .icon {
            font-size: 32px;
        }
        
        .professor-warning .text {
            flex: 1;
        }
        
        .professor-warning .text h3 {
            color: #f39c12;
            margin: 0 0 5px 0;
        }
        
        .professor-warning .text p {
            color: #4a5568;
            margin: 0;
        }
        
        .professor-warning .btn-ir {
            background: #f39c12;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .professor-warning .btn-ir:hover {
            background: #e67e22;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
        }
        
        /* ============================================
           ANIMAÇÕES
           ============================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .animate {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        
        .animate-title {
            animation: fadeInLeft 0.8s ease forwards;
            opacity: 0;
        }
        
        /* ============================================
           BANNER DE LICENÇA
           ============================================ */
        .license-banner {
            background: white;
            border-radius: 12px;
            padding: 18px 25px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
        }
        
        .license-banner .status {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .license-banner .status .icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .license-banner .status .info h4 {
            font-size: 14px;
            color: #94a3b8;
            font-weight: 500;
            margin: 0;
        }
        
        .license-banner .status .info h3 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }
        
        .license-banner .details {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .license-banner .details .item {
            text-align: center;
        }
        
        .license-banner .details .item .label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .license-banner .details .item .value {
            font-size: 16px;
            font-weight: 600;
            color: #1a2332;
        }
        
        .license-banner .details .item .value .mono {
            font-family: monospace;
            font-size: 14px;
        }
        
        .license-banner .alert {
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        /* ============================================
           STATS GRID
           ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            border: 1px solid #eef2f7;
            position: relative;
            overflow: hidden;
            border-left: 4px solid <?= APP_MODE_COLOR ?>;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #c9a84c, #f5d76e);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        
        .stat-icon {
            font-size: 32px;
            width: 55px;
            height: 55px;
            background: #f8fafc;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            color: #1a2332;
            margin: 0;
        }
        
        .stat-info p {
            font-size: 14px;
            color: #94a3b8;
            margin: 0;
        }
        
        .stat-trend {
            margin-left: auto;
            font-size: 13px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            background: #f1f5f9;
            flex-shrink: 0;
        }
        
        .stat-trend.up {
            background: #d1fae5;
            color: #059669;
        }
        
        .stat-trend.down {
            background: #fee2e2;
            color: #dc2626;
        }
        
        /* ============================================
           MODULES GRID
           ============================================ */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .module-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid #eef2f7;
            position: relative;
            overflow: hidden;
        }
        
        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #c9a84c, #f5d76e);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .module-card:hover::before {
            opacity: 1;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        
        .module-icon {
            font-size: 36px;
            margin-bottom: 12px;
            display: block;
        }
        
        .module-card h3 {
            font-size: 16px;
            color: #1a2332;
            margin: 0 0 6px 0;
            font-weight: 600;
        }
        
        .module-card p {
            font-size: 13px;
            color: #94a3b8;
            margin: 0 0 12px 0;
        }
        
        .module-count {
            font-size: 12px;
            color: #c9a84c;
            background: rgba(197,165,50,0.1);
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 500;
        }
        
        .module-arrow {
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 20px;
            color: #cbd5e1;
            transition: all 0.3s;
        }
        
        .module-card:hover .module-arrow {
            color: #c9a84c;
            transform: translateX(5px);
        }
        
        /* ============================================
           INDICADOR DE STATUS DA CONEXÃO
           ============================================ */
        #statusConexao {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            z-index: 9999;
            background: #d1fae5;
            color: #065f46;
            border: 1px solid rgba(46, 204, 113, 0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            cursor: default;
            user-select: none;
        }
        
        #statusConexao #statusDot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #2ecc71;
            transition: all 0.3s ease;
        }
        
        #statusConexao.offline {
            background: #fee2e2;
            color: #991b1b;
            border-color: rgba(231, 76, 60, 0.2);
        }
        
        #statusConexao.offline #statusDot {
            background: #e74c3c;
            animation: pulse-red 1s infinite;
        }
        
        @keyframes pulse-red {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        
        /* ============================================
           SIDEBAR OVERLAY (MOBILE)
           ============================================ */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            cursor: pointer;
        }
        
        .sidebar-overlay.active {
            display: block !important;
        }
        
        /* ============================================
           DASHBOARD FOOTER
           ============================================ */
        .dashboard-footer {
            text-align: center;
            padding: 20px 0 10px;
            border-top: 1px solid #eef2f7;
            margin-top: 20px;
        }
        
        .dashboard-footer p {
            color: #94a3b8;
            font-size: 14px;
            margin: 0;
        }
        
        .dashboard-footer strong {
            color: #c9a84c;
        }
        
        .footer-version {
            font-size: 12px !important;
            color: #cbd5e1 !important;
            margin-top: 4px !important;
        }
        
        /* ============================================
           RESPONSIVIDADE
           ============================================ */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .top-bar-gold {
                padding: 10px 15px;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .top-bar-nav {
                display: none;
                width: 100%;
                padding-top: 10px;
                border-top: 1px solid rgba(197,165,50,0.2);
                flex-wrap: wrap;
                gap: 5px;
            }
            
            .top-bar-nav.open {
                display: flex;
            }
            
            .top-bar-nav a {
                font-size: 13px;
                padding: 6px 14px;
            }
            
            .top-bar-right .welcome-text {
                display: none;
            }
            
            #statusConexao {
                bottom: 10px;
                right: 10px;
                font-size: 11px;
                padding: 6px 12px;
            }
        }
        
        @media (max-width: 768px) {
            .content-area {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            
            .modules-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .page-title {
                font-size: 22px;
            }
            
            .date-time-gold {
                font-size: 12px;
                padding: 4px 10px;
            }
            
            .top-bar-gold {
                padding: 8px 12px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-icon {
                font-size: 24px;
                width: 45px;
                height: 45px;
            }
            
            .stat-info h3 {
                font-size: 20px;
            }
            
            #statusConexao {
                bottom: 8px;
                right: 8px;
                font-size: 10px;
                padding: 4px 10px;
                gap: 5px;
            }
            
            #statusConexao #statusDot {
                width: 6px;
                height: 6px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .top-bar-right {
                gap: 10px;
            }
            
            .top-bar-gold {
                flex-direction: column;
                align-items: stretch;
            }
            
            .top-bar-left {
                justify-content: space-between;
            }
            
            .top-bar-right {
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- ============================================
         SIDEBAR
         ============================================ -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <?php if (!empty($logoEmpresa) && file_exists("assets/uploads/" . $logoEmpresa)): ?>
                    <img src="<?= $logoEmpresa ?>" alt="Logo">
                <?php else: ?>
                    <?= substr($nomeEmpresa, 0, 2) ?>
                <?php endif; ?>
            </div>
            <div class="brand-text">
                <h2><?= htmlspecialchars($nomeEmpresa) ?></h2>
                <span>Web</span>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="index.php" class="active">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
            
            <?php if (userHasAccess('Clientes')): ?>
            <a href="modules/clientes/">
                <span class="nav-icon">👤</span>
                <span class="nav-text">Clientes</span>
                <span class="nav-badge"><?= $totalClientes ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (userHasAccess('Produtos')): ?>
            <a href="modules/produtos/">
                <span class="nav-icon">📦</span>
                <span class="nav-text">Produtos</span>
                <span class="nav-badge"><?= $totalProdutos ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (userHasAccess('Estoque')): ?>
            <a href="modules/estoque/">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Estoque</span>
                <span class="nav-badge"><?= $totalEstoque ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (userHasAccess('Faturas')): ?>
            <a href="modules/fatura_proforma/">
                <span class="nav-icon">📄</span>
                <span class="nav-text">Faturas</span>
                <span class="nav-badge"><?= $totalFaturas ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (userHasAccess('Recibos')): ?>
            <a href="modules/fatura_recibo/">
                <span class="nav-icon">🧾</span>
                <span class="nav-text">Recibos</span>
                <span class="nav-badge"><?= $totalRecibos ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (userHasAccess('Fluxo de Caixa')): ?>
            <a href="modules/caixa/">
                <span class="nav-icon">💰</span>
                <span class="nav-text">Fluxo de Caixa</span>
                <span class="nav-badge" style="background: #2ecc71;"><?= count($movRecentes) ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (userHasAccess('RH')): ?>
            <a href="modules/rh/">
                <span class="nav-icon">👥</span>
                <span class="nav-text">RH</span>
                <span class="nav-badge"><?= $totalFuncionarios ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (userHasAccess('Marketing')): ?>
            <a href="modules/marketing/">
                <span class="nav-icon">📈</span>
                <span class="nav-text">Marketing</span>
                <span class="nav-badge"><?= $totalPlanos ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (userHasAccess('Correspondência')): ?>
            <a href="modules/correspondencia/">
                <span class="nav-icon">✉️</span>
                <span class="nav-text">Correspondência</span>
                <span class="nav-badge"><?= $totalCorrespondencias ?></span>
            </a>
            <?php endif; ?>

            <?php if (userHasAccess('Escola')): ?>
            <a href="modules/escola/" style="background: rgba(201, 168, 76, 0.1); border-left: 3px solid #c9a84c;">
                <span class="nav-icon">🎓</span>
                <span class="nav-text">Escola</span>
                <span class="nav-badge" style="background: #c9a84c; color: #1a2332;">
                    <?= $totalAlunos ?>
                </span>
            </a>
            <?php endif; ?>


            <?php if (userHasAccess('Contabilidade')): ?>
            <a href="modules/contabilidade/" style="background: rgba(52, 152, 219, 0.1); border-left: 3px solid #3498db;">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Contabilidade</span>
                <span class="nav-badge" style="background: #3498db; color: white;">
                    <?= $totalLancamentos ?? 0 ?>
                </span>
            </a>
            <?php endif; ?>

    
            
            <div style="margin: 15px 10px 10px 10px; padding: 12px 15px; background: <?= APP_MODE_COLOR ?>15; border-radius: 8px; border-left: 3px solid <?= APP_MODE_COLOR ?>;">
                <div style="display: flex; align-items: center; gap: 8px; color: <?= APP_MODE_COLOR ?>; font-size: 13px; font-weight: 600;">
                    <span><?= APP_MODE_ICON ?></span>
                    <span>Modo: <?= APP_MODE_NAME ?></span>
                </div>
                <div style="margin-top: 4px; color: #94a3b8; font-size: 11px;">
                    <?= DB_TYPE ?> | <?= DB_HOST ?>
                </div>
            </div>

            <?php if (userHasAccess('Empresa')): ?>
            <a href="modules/empresa/">
                <span class="nav-icon">⚙️</span>
                <span class="nav-text">Empresa</span>
            </a>
            <?php endif; ?>
            
            <?php if ($isAdmin): ?>
            <a href="modules/usuarios/">
                <span class="nav-icon">🔐</span>
                <span class="nav-text">Usuários</span>
                <span class="nav-badge" style="background: #e74c3c;">Admin</span>
            </a>
            <?php endif; ?>
            
            <?php if ($isAdmin): ?>
            <a href="admin/sync_database.php">
                <span class="nav-icon">🔄</span>
                <span class="nav-text">Sincronizar DB</span>
            </a>
            <?php endif; ?>
            
            <?php if ($isAdmin): ?>
            <a href="admin/database_config.php">
                <span class="nav-icon">🗄️</span>
                <span class="nav-text">Banco de Dados</span>
            </a>
            <?php endif; ?>
            
            <!-- ===== NOVO BOTÃO IMPORTAR SQL ===== -->
            <?php if ($isAdmin): ?>
            <a href="admin/import_sql.php" style="background: rgba(52, 152, 219, 0.15); border-left: 3px solid #3498db;">
                <span class="nav-icon">📥</span>
                <span class="nav-text">Importar SQL</span>
                <span class="nav-badge" style="background: #3498db; color: white;">Upload</span>
            </a>
            <?php endif; ?>
            
            <?php if ($licencaInfo): ?>
            <div style="margin-top: 20px; padding: 12px 15px; background: rgba(46, 204, 113, 0.1); border-radius: 8px; border-left: 3px solid <?= $licencaInfo['cor_status'] ?>;">
                <div style="display: flex; align-items: center; gap: 8px; color: <?= $licencaInfo['cor_status'] ?>; font-size: 13px; font-weight: 600;">
                    <span><?= $licencaInfo['icone_status'] ?></span>
                    <span>Licença: <?= ucfirst($licencaInfo['status'] ?? 'Ativa') ?></span>
                </div>
                <div style="margin-top: 5px; color: #94a3b8; font-size: 12px;">
                    <?php if ($licencaInfo['dias_restantes'] === '∞'): ?>
                        ♾️ Ilimitado
                    <?php else: ?>
                        ⏳ <?= $licencaInfo['dias_restantes'] ?> dias restantes
                    <?php endif; ?>
                </div>
                <?php if ($licencaInfo['dias_restantes'] !== '∞' && $licencaInfo['dias_restantes'] <= 7): ?>
                <div style="margin-top: 5px; color: #e74c3c; font-size: 11px; font-weight: 600;">
                    ⚠️ Expira em breve!
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </nav>
        

        <!-- Adicione no menu lateral -->
        <a href="<?= SITE_URL ?>admin/sync_auto_start.php" class="menu-item" style="display: flex; align-items: center; gap: 10px; padding: 12px 18px; color: #1a2332; text-decoration: none; border-radius: 10px; transition: all 0.3s; background: linear-gradient(135deg, #2ecc7115, #27ae6015); border-left: 4px solid #2ecc71;">
            <span style="font-size: 20px;">🔄</span>
            <span>Sincronização Auto</span>
            <span style="margin-left: auto; font-size: 11px; background: #2ecc71; color: white; padding: 2px 10px; border-radius: 12px; animation: pulse 1.5s infinite;">ATIVO</span>
        </a>


        <!-- ===== BOTÃO MODO DESENVOLVEDOR ===== -->
        <a href="admin/dev_auth.php" style="
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.15), rgba(168, 85, 247, 0.15));
            border-left: 4px solid #6c63ff;
            margin-top: 20px;
            border-radius: 8px;
            padding: 12px 18px;
        ">
            <span class="nav-icon">🔧</span>
            <span class="nav-text" style="background: linear-gradient(135deg, #6c63ff, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 700;">
                Modo Desenvolvedor
            </span>
            <span class="nav-badge" style="background: linear-gradient(135deg, #6c63ff, #a855f7); color: white; font-size: 8px; padding: 2px 8px;">
                🔐 PRO
            </span>
        </a>






        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">👤</div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($usuario_nome) ?></div>
                    <div class="user-email"><?= htmlspecialchars($usuario_email) ?></div>
                    <div style="font-size: 10px; color: #<?= $perfilBadgeColor ?>; margin-top: 2px; font-weight: 600;">
                        <?= ucfirst($usuario_perfil) ?>
                    </div>
                </div>
            </div>
            <a href="modules/usuarios/perfil.php" style="display: block; text-align: center; padding: 8px; background: rgba(197, 165, 50, 0.2); color: #c9a84c; text-decoration: none; border-radius: 6px; font-size: 13px; transition: all 0.3s; margin-bottom: 8px;">
                ⚙️ Meu Perfil
            </a>
            <a href="logout.php" class="btn-logout-sidebar">🚪 Sair</a>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <main class="main-content">
        <div class="top-bar-gold">
            <div class="top-bar-left">
                <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Abrir menu">☰</button>
                <div class="top-bar-nav" id="topBarNav">
                    <a href="index.php" class="active">Dashboard</a>
                    <?php if (userHasAccess('Fluxo de Caixa')): ?>
                    <a href="modules/caixa/">💰 Caixa</a>
                    <?php endif; ?>
                    <?php if (userHasAccess('Estoque')): ?>
                    <a href="modules/estoque/">📦 Estoque</a>
                    <?php endif; ?>
                    <?php if (userHasAccess('Faturas')): ?>
                    <a href="modules/fatura_proforma/">📄 Faturas</a>
                    <?php endif; ?>
                    <?php if (userHasAccess('RH')): ?>
                    <a href="modules/rh/">👥 RH</a>
                    <?php endif; ?>
                    <?php if (userHasAccess('Marketing')): ?>
                    <a href="modules/marketing/">📈 Marketing</a>
                    <?php endif; ?>
                    <?php if (userHasAccess('Correspondência')): ?>
                    <a href="modules/correspondencia/">✉️ Correspondência</a>
                    <?php endif; ?>
                    <?php if (userHasAccess('Escola')): ?>
                    <a href="modules/escola/" style="background: rgba(201, 168, 76, 0.15); color: #c9a84c; font-weight: 600;">
                        🎓 Escola
                        <span class="badge-novo" style="background: #c9a84c; color: #1a2332; font-size: 8px; padding: 1px 6px; border-radius: 8px; position: absolute; top: -4px; right: -4px; font-weight: 700;">NOVO</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="top-bar-right">
                <span class="welcome-text">Bem-vindo, <?= htmlspecialchars($usuario_nome) ?></span>
                <span class="date-time-gold" id="currentDateTime"></span>
            </div>
        </div>

        <!-- ===== BANNER DO MODO ===== -->
        <div class="mode-banner">
            <div class="info">
                <span class="icon"><?= APP_MODE_ICON ?></span>
                <div>
                    <div class="text"><?= APP_MODE_NAME ?></div>
                    <div class="sub"><?= DB_TYPE ?> | <?= DB_HOST ?> | <?= DB_NAME ?></div>
                </div>
            </div>
            <div class="actions">
                <a href="?modo=local" class="<?= isModoLocal() ? 'active' : '' ?>">💻 Local</a>
                <a href="?modo=public" class="<?= isModoPublico() ? 'active' : '' ?>">☁️ Público</a>
                <?php if ($isAdmin): ?>
                <a href="admin/sync_database.php">🔄 Sincronizar</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-area">
            <div class="page-header">
                <h1 class="page-title animate-title">📊 Dashboard</h1>
                <p class="page-subtitle">Visão geral do seu sistema de gestão</p>
            </div>

            <!-- ===== BANNER PARA PROFESSORES ===== -->
            <?php if ($isProfessor): ?>
            <div class="professor-warning animate" style="animation-delay: 0.1s;">
                <div class="icon">👨‍🏫</div>
                <div class="text">
                    <h3>Bem-vindo, Professor(a)!</h3>
                    <p>Você está logado como professor. Acesse o <strong>Painel do Professor</strong> para ver suas turmas, horários e alunos.</p>
                </div>
                <a href="modules/escola/professor_dashboard.php" class="btn-ir">📚 Ir para Painel do Professor →</a>
            </div>
            <?php endif; ?>

            <!-- ===== BANNER DE LICENÇA ===== -->
            <?php if ($licencaInfo): ?>
            <div class="license-banner" style="border-left: 4px solid <?= $licencaInfo['cor_status'] ?>;">
                <div class="status">
                    <div class="icon" style="background: <?= $licencaInfo['cor_status'] ?>; color: white;">
                        <?= $licencaInfo['icone_status'] ?>
                    </div>
                    <div class="info">
                        <h4>Status da Licença</h4>
                        <h3 style="color: <?= $licencaInfo['cor_status'] ?>;">
                            <?= ucfirst($licencaInfo['status'] ?? 'Ativa') ?>
                        </h3>
                    </div>
                </div>
                
                <div class="details">
                    <div class="item">
                        <div class="label">Tipo</div>
                        <div class="value"><?= ucfirst($licencaInfo['tipo'] ?? 'N/A') ?></div>
                    </div>
                    <div class="item">
                        <div class="label">Código</div>
                        <div class="value"><span class="mono"><?= htmlspecialchars($licencaInfo['codigo_licenca'] ?? 'N/A') ?></span></div>
                    </div>
                    <div class="item">
                        <div class="label">Expiração</div>
                        <div class="value" style="color: <?= $licencaInfo['cor_status'] ?>;">
                            <?= $licencaInfo['data_expiracao_formatada'] ?? 'N/A' ?>
                        </div>
                    </div>
                    <div class="item">
                        <div class="label">Dias Restantes</div>
                        <div class="value" style="color: <?= $licencaInfo['cor_status'] ?>; font-size: 22px;">
                            <?php if ($licencaInfo['dias_restantes'] === '∞'): ?>
                                ♾️
                            <?php else: ?>
                                <?= $licencaInfo['dias_restantes'] ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($licencaInfo['dias_restantes'] !== '∞' && $licencaInfo['dias_restantes'] <= 7): ?>
                <div class="alert" style="background: #e74c3c; color: white;">
                    ⚠️ Renove sua licença!
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="license-banner" style="border-left: 4px solid #e74c3c; background: #fff5f5;">
                <div style="display: flex; align-items: center; gap: 15px; width: 100%; justify-content: center;">
                    <span style="font-size: 24px;">⚠️</span>
                    <span style="font-weight: 600; color: #991b1b;">Nenhuma licença ativa encontrada!</span>
                    <a href="license/activate.php" style="color: #991b1b; font-weight: 600; text-decoration: underline;">Ativar agora →</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Cards de Resumo -->
            <div class="stats-grid">
                <?php if (userHasAccess('Clientes')): ?>
                <div class="stat-card animate" style="animation-delay: 0.1s;">
                    <div class="stat-icon">👤</div>
                    <div class="stat-info">
                        <h3><?= $totalClientes ?></h3>
                        <p>Clientes</p>
                    </div>
                    <div class="stat-trend up">↑ 12%</div>
                </div>
                <?php endif; ?>
                
                <?php if (userHasAccess('Produtos')): ?>
                <div class="stat-card animate" style="animation-delay: 0.2s;">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <h3><?= $totalProdutos ?></h3>
                        <p>Produtos</p>
                    </div>
                    <div class="stat-trend up">↑ 8%</div>
                </div>
                <?php endif; ?>
                
                <?php if (userHasAccess('Faturas')): ?>
                <div class="stat-card animate" style="animation-delay: 0.3s;">
                    <div class="stat-icon">📄</div>
                    <div class="stat-info">
                        <h3><?= $totalFaturas ?></h3>
                        <p>Faturas</p>
                    </div>
                    <div class="stat-trend up">↑ 5%</div>
                </div>
                <?php endif; ?>
                
                <?php if (userHasAccess('Fluxo de Caixa')): ?>
                <div class="stat-card animate" style="animation-delay: 0.4s;">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3 style="color: <?= $saldoAtual >= 0 ? '#2ecc71' : '#e74c3c' ?>;">
                            R$ <?= number_format($saldoAtual, 2, ',', '.') ?>
                        </h3>
                        <p>Saldo em Caixa</p>
                    </div>
                    <div class="stat-trend <?= $saldoAtual >= 0 ? 'up' : 'down' ?>">
                        <?= $saldoAtual >= 0 ? '↑' : '↓' ?> <?= number_format(abs(($vendasMes > 0 ? ($saldoAtual / $vendasMes) * 100 : 0)), 1) ?>%
                    </div>
                </div>
                <?php endif; ?>

                <?php if (userHasAccess('Escola')): ?>
                <div class="stat-card animate" style="animation-delay: 0.5s; border-left-color: #c9a84c;">
                    <div class="stat-icon">🎓</div>
                    <div class="stat-info">
                        <h3><?= $totalAlunos ?></h3>
                        <p>Alunos Matriculados</p>
                    </div>
                    <div class="stat-trend up" style="background: rgba(201, 168, 76, 0.2); color: #c9a84c;">
                        🆕 <?= $totalTurmas ?> turmas
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Grid de Módulos -->
            <div class="modules-grid">
                <?php if (userHasAccess('Clientes')): ?>
                <div class="module-card animate" style="animation-delay: 0.6s;" onclick="window.location.href='modules/clientes/'">
                    <div class="module-icon">👤</div>
                    <h3>Clientes</h3>
                    <p>Cadastro e gestão de clientes</p>
                    <span class="module-count"><?= $totalClientes ?> cadastrados</span>
                    <span class="module-arrow">→</span>
                </div>
                <?php endif; ?>

                <?php if (userHasAccess('Produtos')): ?>
                <div class="module-card animate" style="animation-delay: 0.7s;" onclick="window.location.href='modules/produtos/'">
                    <div class="module-icon">📦</div>
                    <h3>Produtos</h3>
                    <p>Cadastro e gestão de produtos</p>
                    <span class="module-count"><?= $totalProdutos ?> cadastrados</span>
                    <span class="module-arrow">→</span>
                </div>
                <?php endif; ?>

                <?php if (userHasAccess('Estoque')): ?>
                <div class="module-card animate" style="animation-delay: 0.8s;" onclick="window.location.href='modules/estoque/'">
                    <div class="module-icon">📊</div>
                    <h3>Controle de Estoque</h3>
                    <p>Gerenciamento de produtos</p>
                    <span class="module-count"><?= $totalEstoque ?> itens</span>
                    <span class="module-arrow">→</span>
                </div>
                <?php endif; ?>

                <?php if (userHasAccess('Faturas')): ?>
                <div class="module-card animate" style="animation-delay: 0.9s;" onclick="window.location.href='modules/fatura_proforma/'">
                    <div class="module-icon">📄</div>
                    <h3>Fatura Proforma</h3>
                    <p>Criar e gerenciar faturas</p>
                    <span class="module-count"><?= $totalFaturas ?> emitidas</span>
                    <span class="module-arrow">→</span>
                </div>
                <?php endif; ?>

                <?php if (userHasAccess('Recibos')): ?>
                <div class="module-card animate" style="animation-delay: 1.0s;" onclick="window.location.href='modules/fatura_recibo/'">
                    <div class="module-icon">🧾</div>
                    <h3>Fatura Recibo</h3>
                    <p>Emissão de recibos</p>
                    <span class="module-count"><?= $totalRecibos ?> emitidos</span>
                    <span class="module-arrow">→</span>
                </div>
                <?php endif; ?>

                <?php if (userHasAccess('Fluxo de Caixa')): ?>
                <div class="module-card animate" style="animation-delay: 1.1s;" onclick="window.location.href='modules/caixa/'">
                    <div class="module-icon">💰</div>
                    <h3>Fluxo de Caixa</h3>
                    <p>Vendas, entradas e saídas</p>
                    <span class="module-count">Saldo: R$ <?= number_format($saldoAtual, 2, ',', '.') ?></span>
                    <span class="module-arrow">→</span>
                </div>
                <?php endif; ?>

                <?php if (userHasAccess('RH')): ?>
                <div class="module-card animate" style="animation-delay: 1.2s;" onclick="window.location.href='modules/rh/'">
                    <div class="module-icon">👥</div>
                    <h3>Recursos Humanos</h3>
                    <p>Funcionários e folha</p>
                    <span class="module-count"><?= $totalFuncionarios ?> funcionários</span>
                    <span class="module-arrow">→</span>
                </div>
                <?php endif; ?>

                <?php if (userHasAccess('Marketing')): ?>
                <div class="module-card animate" style="animation-delay: 1.3s;" onclick="window.location.href='modules/marketing/'">
                    <div class="module-icon">📈</div>
                    <h3>Plano de Marketing</h3>
                    <p>Planejamento de marketing</p>
                    <span class="module-count"><?= $totalPlanos ?> planos</span>
                    <span class="module-arrow">→</span>
                </div>
                <?php endif; ?>

                <?php if (userHasAccess('Correspondência')): ?>
                <div class="module-card animate" style="animation-delay: 1.4s;" onclick="window.location.href='modules/correspondencia/'">
                    <div class="module-icon">✉️</div>
                    <h3>Correspondência</h3>
                    <p>Envio de correspondências</p>
                    <span class="module-count"><?= $totalCorrespondencias ?> enviadas</span>
                    <span class="module-arrow">→</span>
                </div>
                <?php endif; ?>

                <?php if (userHasAccess('Escola')): ?>
                <div class="module-card animate" style="animation-delay: 1.5s; border: 2px solid rgba(201, 168, 76, 0.3);" onclick="window.location.href='modules/escola/'">
                    <div class="module-icon">🎓</div>
                    <h3 style="color: #c9a84c;">Gestão Escolar</h3>
                    <p>Alunos, professores, turmas, notas e mais</p>
                    <span class="module-count" style="background: #c9a84c; color: #1a2332; font-weight: 700;">
                        📚 <?= $totalAlunos ?> alunos | <?= $totalProfessores ?> professores
                    </span>
                    <span class="module-arrow">→</span>
                    <span style="position: absolute; top: 10px; right: 10px; background: #c9a84c; color: #1a2332; font-size: 9px; padding: 2px 10px; border-radius: 10px; font-weight: 700; text-transform: uppercase;">
                        NOVO
                    </span>
                </div>
                <?php endif; ?>

                <?php if (userHasAccess('Empresa')): ?>
                <div class="module-card animate" style="animation-delay: 1.6s;" onclick="window.location.href='modules/empresa/'">
                    <div class="module-icon">🏢</div>
                    <h3>Dados da Empresa</h3>
                    <p>Configure suas informações</p>
                    <span class="module-count">Configurações</span>
                    <span class="module-arrow">→</span>
                </div>
                <?php endif; ?>

                <!-- ===== NOVO CARD DE IMPORTAÇÃO SQL ===== -->
                <?php if ($isAdmin): ?>
                <div class="module-card animate" style="animation-delay: 1.7s; border: 2px solid rgba(52, 152, 219, 0.3);" onclick="window.location.href='admin/import_sql.php'">
                    <div class="module-icon">📥</div>
                    <h3 style="color: #3498db;">Importar Dados SQL</h3>
                    <p>Importe registros de arquivos SQL</p>
                    <span class="module-count" style="background: #3498db; color: white;">
                        ⬆️ Upload
                    </span>
                    <span class="module-arrow">→</span>
                    <span style="position: absolute; top: 10px; right: 10px; background: #3498db; color: white; font-size: 9px; padding: 2px 10px; border-radius: 10px; font-weight: 700;">
                        ADMIN
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (userHasAccess('Fluxo de Caixa') && count($movRecentes) > 0): ?>
            <div style="background: white; border-radius: 12px; padding: 25px; margin-top: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7;">
                <h3 style="color: #1a2332; margin-bottom: 15px;">💰 Últimas Movimentações</h3>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="padding: 10px 15px; text-align: left; font-size: 12px; color: #94a3b8; text-transform: uppercase;">Data</th>
                                <th style="padding: 10px 15px; text-align: left; font-size: 12px; color: #94a3b8; text-transform: uppercase;">Tipo</th>
                                <th style="padding: 10px 15px; text-align: left; font-size: 12px; color: #94a3b8; text-transform: uppercase;">Categoria</th>
                                <th style="padding: 10px 15px; text-align: left; font-size: 12px; color: #94a3b8; text-transform: uppercase;">Descrição</th>
                                <th style="padding: 10px 15px; text-align: right; font-size: 12px; color: #94a3b8; text-transform: uppercase;">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($movRecentes as $mov): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 10px 15px; font-size: 14px;"><?= date('d/m/Y', strtotime($mov['data_movimento'])) ?></td>
                                <td style="padding: 10px 15px;">
                                    <span style="display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; background: <?= $mov['tipo'] == 'entrada' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $mov['tipo'] == 'entrada' ? '#065f46' : '#991b1b' ?>;">
                                        <?= $mov['tipo'] == 'entrada' ? '📥 Entrada' : '📤 Saída' ?>
                                    </span>
                                </td>
                                <td style="padding: 10px 15px; font-size: 14px;"><?= htmlspecialchars($mov['categoria']) ?></td>
                                <td style="padding: 10px 15px; font-size: 14px;"><?= htmlspecialchars(substr($mov['descricao'] ?? '', 0, 30)) ?></td>
                                <td style="padding: 10px 15px; text-align: right; font-weight: 600; color: <?= $mov['tipo'] == 'entrada' ? '#2ecc71' : '#e74c3c' ?>;">
                                    <?= $mov['tipo'] == 'entrada' ? '+' : '-' ?> R$ <?= number_format($mov['valor'], 2, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 15px; text-align: right;">
                    <a href="modules/caixa/movimentacoes.php" style="color: #c9a84c; text-decoration: none; font-weight: 600;">Ver todas →</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="dashboard-footer">
                <p>© <?= date('Y') ?> <strong><?= htmlspecialchars($nomeEmpresa) ?></strong> - Sistema de Gestão Empresarial</p>
                <p class="footer-version">
                    <?= APP_MODE_ICON ?> <?= APP_MODE_NAME ?> | <?= DB_TYPE ?> | <?= DB_HOST ?>
                    <?php if (DEBUG_MODE): ?>
                    <span style="color: #f39c12;">| 🐛 Debug</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </main>
</div>

<!-- ============================================ -->
<!-- INDICADOR DE STATUS DA CONEXÃO -->
<!-- ============================================ -->
<div id="statusConexao">
    <span id="statusDot"></span>
    <span id="statusTexto">🟢 Online</span>
</div>

<script>
// ============================================
// SIDEBAR - TOGGLE
// ============================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (!sidebar) return;
    
    sidebar.classList.toggle('open');
    if (overlay) {
        overlay.classList.toggle('active');
    }
    
    if (sidebar.classList.contains('open')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar) {
        sidebar.classList.remove('open');
    }
    if (overlay) {
        overlay.classList.remove('active');
    }
    document.body.style.overflow = '';
}

// ============================================
// RELÓGIO
// ============================================
function updateDateTime() {
    const now = new Date();
    const options = {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    };
    const element = document.getElementById('currentDateTime');
    if (element) {
        element.textContent = now.toLocaleDateString('pt-BR', options);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Fechar sidebar ao clicar fora
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggleBtn = document.querySelector('.menu-toggle');
        
        if (sidebar && sidebar.classList.contains('open')) {
            const isClickInside = sidebar.contains(event.target) || (toggleBtn && toggleBtn.contains(event.target));
            if (!isClickInside) {
                closeSidebar();
            }
        }
    });
    
    // Fechar sidebar em resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            closeSidebar();
        }
    });
});

// ============================================
// STATUS DA CONEXÃO
// ============================================
(function() {
    const statusDiv = document.getElementById('statusConexao');
    const statusDot = document.getElementById('statusDot');
    const statusTexto = document.getElementById('statusTexto');
    
    function updateConnectionStatus(online) {
        if (!statusDiv || !statusDot || !statusTexto) return;
        
        if (online) {
            statusDiv.classList.remove('offline');
            statusTexto.textContent = '🟢 Online';
        } else {
            statusDiv.classList.add('offline');
            statusTexto.textContent = '🔴 Offline';
        }
    }
    
    // Status inicial
    updateConnectionStatus(navigator.onLine);
    
    // Monitorar mudanças
    window.addEventListener('online', () => {
        updateConnectionStatus(true);
        console.log('🟢 Conexão restaurada!');
    });
    
    window.addEventListener('offline', () => {
        updateConnectionStatus(false);
        console.log('🔴 Conexão perdida!');
    });
    
    // Verificar servidor periodicamente
    function verificarServidor() {
        fetch('/softgest_web/api/sync.php?acao=ping', {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (response.ok) {
                updateConnectionStatus(true);
                console.log('✅ Servidor respondendo');
            } else {
                updateConnectionStatus(false);
                console.log('❌ Servidor sem resposta');
            }
        })
        .catch(() => {
            updateConnectionStatus(false);
            console.log('❌ Erro ao conectar ao servidor');
        });
    }
    
    // Verificar a cada 30 segundos
    setInterval(verificarServidor, 30000);
    
    // Verificar ao carregar
    setTimeout(verificarServidor, 2000);
})();
</script>

</body>
</html>