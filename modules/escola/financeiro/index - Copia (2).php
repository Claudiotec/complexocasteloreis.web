<?php
// ============================================
// modules/escola/financeiro/index.php - Financeiro Escolar
// Versão Elegante e Profissional
// ============================================

// Carregar configurações
require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== DADOS DO DASHBOARD =====
$totalRecebido = 0;
$totalPendente = 0;
$totalEmolumentos = 0;
$totalAlunos = 0;
$totalMensalidades = 0;
$totalAtrasados = 0;

try {
    $totalRecebido = $pdo->query("SELECT SUM(valor) FROM pagamentos WHERE status = 'confirmado'")->fetchColumn() ?? 0;
    $totalPendente = $pdo->query("SELECT SUM(valor) FROM mensalidades WHERE status IN ('pendente', 'atrasado')")->fetchColumn() ?? 0;
    $totalEmolumentos = $pdo->query("SELECT COUNT(*) FROM emolumentos WHERE status = 'ativo'")->fetchColumn() ?? 0;
    $totalAlunos = $pdo->query("SELECT COUNT(*) FROM alunos WHERE status = 'ativo'")->fetchColumn() ?? 0;
    $totalMensalidades = $pdo->query("SELECT COUNT(*) FROM mensalidades")->fetchColumn() ?? 0;
    $totalAtrasados = $pdo->query("SELECT COUNT(*) FROM mensalidades WHERE status = 'atrasado'")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// ===== ÚLTIMOS PAGAMENTOS =====
$ultimosPagamentos = [];
try {
    $ultimosPagamentos = $pdo->query("
        SELECT p.*, a.nome as aluno_nome, e.nome as emolumento_nome 
        FROM pagamentos p
        LEFT JOIN alunos a ON p.aluno_id = a.id
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
        ORDER BY p.created_at DESC 
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {}

// ===== MENSALIDADES POR STATUS =====
$mensalidadesStatus = [];
try {
    $mensalidadesStatus = $pdo->query("
        SELECT status, COUNT(*) as total, SUM(valor) as valor_total 
        FROM mensalidades 
        GROUP BY status
    ")->fetchAll();
} catch (Exception $e) {}

// ===== DADOS PARA RELATÓRIO AGT =====
$anoAtual = date('Y');
$mesAtual = date('m');
$totalPagamentos = 0;
$totalMensalidadesMes = 0;
$totalEmolumentosCount = 0;
$totalAlunosCount = 0;

try {
    // Total de pagamentos do mês atual
    $totalPagamentos = $pdo->query("
        SELECT SUM(valor) FROM pagamentos 
        WHERE status = 'confirmado' 
        AND MONTH(data_pagamento) = $mesAtual 
        AND YEAR(data_pagamento) = $anoAtual
    ")->fetchColumn() ?? 0;
    
    // Total de mensalidades do mês atual
    $totalMensalidadesMes = $pdo->query("
        SELECT SUM(valor) FROM mensalidades 
        WHERE mes_referencia = $mesAtual 
        AND ano_referencia = $anoAtual
    ")->fetchColumn() ?? 0;
    
    $totalEmolumentosCount = $pdo->query("SELECT COUNT(*) FROM emolumentos WHERE status = 'ativo'")->fetchColumn() ?? 0;
    $totalAlunosCount = $pdo->query("SELECT COUNT(*) FROM alunos WHERE status = 'ativo'")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// ===== INCLUIR HEADER =====
include '../includes/header_escola.php';
?>

<!-- ============================================
     CONTEÚDO - FINANCEIRO ESCOLAR
     ============================================ -->
<style>
    /* ============================================
       ANIMAÇÕES E FONTES
       ============================================ */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
    
    :root {
        --gold: #c9a84c;
        --gold-light: #f5d76e;
        --gold-dark: #b8973a;
        --dark: #1a2332;
        --dark-light: #2d3748;
        --gray: #94a3b8;
        --gray-light: #eef2f7;
        --white: #ffffff;
        --shadow: 0 4px 25px rgba(0,0,0,0.06);
        --shadow-hover: 0 8px 40px rgba(0,0,0,0.1);
        --radius: 14px;
        --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    /* Animações */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes fadeInLeft {
        from { opacity: 0; transform: translateX(-30px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes pulseGold {
        0%, 100% { box-shadow: 0 0 0 0 rgba(201, 168, 76, 0.2); }
        50% { box-shadow: 0 0 0 15px rgba(201, 168, 76, 0); }
    }
    
    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    @keyframes glow {
        0%, 100% { box-shadow: 0 0 20px rgba(201, 168, 76, 0.1); }
        50% { box-shadow: 0 0 40px rgba(201, 168, 76, 0.3); }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.95); }
    }
    
    .animate-fade-up {
        animation: fadeInUp 0.7s ease forwards;
        opacity: 0;
    }
    
    .animate-fade-up:nth-child(1) { animation-delay: 0.05s; }
    .animate-fade-up:nth-child(2) { animation-delay: 0.10s; }
    .animate-fade-up:nth-child(3) { animation-delay: 0.15s; }
    .animate-fade-up:nth-child(4) { animation-delay: 0.20s; }
    .animate-fade-up:nth-child(5) { animation-delay: 0.25s; }
    
    .animate-left {
        animation: fadeInLeft 0.6s ease forwards;
        opacity: 0;
    }
    
    /* ============================================
       LAYOUT PRINCIPAL
       ============================================ */
    .financeiro-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px 25px;
    }
    
    /* ============================================
       HEADER
       ============================================ */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 2px solid rgba(201, 168, 76, 0.15);
    }
    
    .page-header .header-left h1 {
        font-size: 28px;
        font-weight: 800;
        color: var(--dark);
        margin: 0;
        letter-spacing: -0.5px;
        background: linear-gradient(135deg, var(--dark), var(--gold));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .page-header .header-left .subtitle {
        color: var(--gray);
        font-size: 14px;
        margin: 4px 0 0;
        font-weight: 400;
    }
    
    .page-header .header-left .subtitle span {
        color: var(--gold);
        font-weight: 600;
    }
    
    .btn {
        padding: 10px 24px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        cursor: pointer;
        font-family: 'Inter', sans-serif;
    }
    
    .btn-gold {
        background: linear-gradient(135deg, var(--gold), var(--gold-light));
        color: var(--dark);
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
    }
    
    .btn-gold:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(201, 168, 76, 0.4);
    }
    
    .btn-secondary {
        background: var(--gray-light);
        color: var(--dark-light);
    }
    
    .btn-secondary:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }
    
    .btn-success {
        background: linear-gradient(135deg, #2ecc71, #27ae60);
        color: #fff;
        box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
    }
    
    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(46, 204, 113, 0.4);
    }
    
    .btn-info {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: #fff;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }
    
    .btn-info:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(52, 152, 219, 0.4);
    }
    
    .btn-warning {
        background: linear-gradient(135deg, #f39c12, #d68910);
        color: #fff;
        box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
    }
    
    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(243, 156, 18, 0.4);
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: #fff;
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
    }
    
    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(231, 76, 60, 0.4);
    }
    
    /* ============================================
       MENU FINANCEIRO
       ============================================ */
    .menu-financeiro {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 30px;
        padding: 12px 18px;
        background: var(--white);
        border-radius: var(--radius);
        border: 1px solid var(--gray-light);
        box-shadow: var(--shadow);
        animation: slideDown 0.5s ease;
    }
    
    .menu-financeiro a {
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: var(--transition);
        color: var(--dark-light);
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .menu-financeiro a:hover {
        background: rgba(201, 168, 76, 0.1);
        color: var(--gold);
        border-color: var(--gold);
        transform: translateY(-2px);
    }
    
    .menu-financeiro a.active {
        background: linear-gradient(135deg, var(--gold), var(--gold-light));
        color: var(--dark);
        border-color: var(--gold);
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.2);
    }
    
    /* ============================================
       STATS CARDS
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 18px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: var(--white);
        padding: 22px 24px;
        border-radius: var(--radius);
        text-align: center;
        box-shadow: var(--shadow);
        border: 1px solid var(--gray-light);
        border-left: 5px solid var(--gold);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        cursor: default;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--gold), var(--gold-light), var(--gold));
        opacity: 0;
        transition: var(--transition);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-hover);
    }
    
    .stat-card:hover::before {
        opacity: 1;
        animation: shimmer 2s infinite;
        background-size: 200% 100%;
    }
    
    .stat-card .icon {
        font-size: 28px;
        display: block;
        margin-bottom: 8px;
    }
    
    .stat-card .number {
        font-size: 30px;
        font-weight: 800;
        color: var(--dark);
        margin: 0;
        letter-spacing: -0.5px;
        font-family: 'Inter', sans-serif;
    }
    
    .stat-card .label {
        font-size: 13px;
        color: var(--gray);
        margin: 4px 0 0;
        font-weight: 500;
    }
    
    .stat-card .trend {
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 20px;
        margin-top: 6px;
    }
    
    .stat-card .trend.up {
        background: #d1fae5;
        color: #065f46;
    }
    
    .stat-card .trend.down {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .stat-card.recebido { border-left-color: #2ecc71; }
    .stat-card.pendente { border-left-color: #f39c12; }
    .stat-card.emolumentos { border-left-color: #3498db; }
    .stat-card.alunos { border-left-color: #9b59b6; }
    .stat-card.atrasados { border-left-color: #e74c3c; }
    
    /* ============================================
       BANNER RELATÓRIO AGT
       ============================================ */
    .agt-banner {
        background: linear-gradient(135deg, #0a0f2e, #1a237e);
        border-radius: var(--radius);
        padding: 25px 35px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        border: 1px solid rgba(201, 168, 76, 0.25);
        box-shadow: 0 4px 30px rgba(26, 35, 126, 0.15);
        position: relative;
        overflow: hidden;
        animation: slideDown 0.5s ease;
        transition: var(--transition);
        cursor: pointer;
    }
    
    .agt-banner::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(201, 168, 76, 0.05) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .agt-banner::after {
        content: '';
        position: absolute;
        bottom: -60%;
        left: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(201, 168, 76, 0.03) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .agt-banner:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 40px rgba(26, 35, 126, 0.3);
        border-color: rgba(201, 168, 76, 0.5);
    }
    
    .agt-banner .agt-content {
        display: flex;
        align-items: center;
        gap: 20px;
        z-index: 1;
        flex-wrap: wrap;
    }
    
    .agt-banner .agt-icon {
        font-size: 44px;
        line-height: 1;
        animation: float 3s ease-in-out infinite;
    }
    
    .agt-banner .agt-text {
        color: #ffffff;
    }
    
    .agt-banner .agt-text h3 {
        font-size: 20px;
        font-weight: 700;
        margin: 0;
        color: var(--gold-light);
        letter-spacing: 0.5px;
    }
    
    .agt-banner .agt-text .agt-sub {
        font-size: 14px;
        color: rgba(255,255,255,0.6);
        margin: 4px 0 0;
    }
    
    .agt-banner .agt-text .agt-sub strong {
        color: #ffffff;
        font-weight: 600;
    }
    
    .agt-banner .agt-stats {
        display: flex;
        gap: 30px;
        z-index: 1;
        flex-wrap: wrap;
    }
    
    .agt-banner .agt-stats .agt-stat {
        text-align: center;
        padding: 0 15px;
        border-right: 1px solid rgba(255,255,255,0.08);
    }
    
    .agt-banner .agt-stats .agt-stat:last-child {
        border-right: none;
    }
    
    .agt-banner .agt-stats .agt-stat .agt-number {
        font-size: 24px;
        font-weight: 800;
        color: var(--gold-light);
        display: block;
        line-height: 1.2;
    }
    
    .agt-banner .agt-stats .agt-stat .agt-label {
        font-size: 10px;
        color: rgba(255,255,255,0.4);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    
    .agt-banner .btn-agt-open {
        background: linear-gradient(135deg, var(--gold), var(--gold-light));
        color: var(--dark);
        padding: 14px 35px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 700;
        font-size: 14px;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 12px;
        z-index: 1;
        border: 2px solid transparent;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 25px rgba(201, 168, 76, 0.25);
        position: relative;
        cursor: pointer;
    }
    
    .agt-banner .btn-agt-open:hover {
        background: transparent;
        border-color: var(--gold);
        color: var(--gold);
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 10px 50px rgba(201, 168, 76, 0.3);
    }
    
    .agt-banner .btn-agt-open .icon-pdf {
        font-size: 22px;
    }
    
    .agt-banner .btn-agt-open .badge-new {
        background: #e74c3c;
        color: #ffffff;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 9px;
        font-weight: 800;
        animation: pulseGold 2s infinite;
        text-transform: uppercase;
    }
    
    .agt-banner .btn-agt-open .arrow-open {
        transition: var(--transition);
        font-size: 18px;
    }
    
    .agt-banner .btn-agt-open:hover .arrow-open {
        transform: translateX(6px);
    }
    
    /* Loading overlay */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(10, 15, 46, 0.95);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        flex-direction: column;
        gap: 20px;
        animation: fadeIn 0.3s ease;
    }
    
    .loading-overlay.active {
        display: flex;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .loading-overlay .spinner {
        width: 60px;
        height: 60px;
        border: 4px solid rgba(201, 168, 76, 0.1);
        border-top: 4px solid var(--gold);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    .loading-overlay .loading-text {
        color: #ffffff;
        font-size: 18px;
        font-weight: 600;
        letter-spacing: 1px;
    }
    
    .loading-overlay .loading-text span {
        color: var(--gold-light);
    }
    
    .loading-overlay .loading-sub {
        color: rgba(255,255,255,0.5);
        font-size: 13px;
    }
    
    /* ============================================
       CARDS DE LISTA
       ============================================ */
    .card-list {
        background: var(--white);
        border-radius: var(--radius);
        padding: 22px 26px;
        margin-top: 20px;
        border: 1px solid var(--gray-light);
        box-shadow: var(--shadow);
        transition: var(--transition);
    }
    
    .card-list:hover {
        box-shadow: var(--shadow-hover);
    }
    
    .card-list .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
    }
    
    .card-list .card-header h3 {
        font-size: 17px;
        font-weight: 700;
        color: var(--dark);
        margin: 0;
    }
    
    .card-list .card-header .badge-count {
        background: rgba(201, 168, 76, 0.1);
        color: var(--gold);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    /* ============================================
       STATUS GRID
       ============================================ */
    .status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-top: 10px;
    }
    
    .status-item {
        background: #f8fafc;
        padding: 14px 18px;
        border-radius: 10px;
        text-align: center;
        border-left: 4px solid var(--gold);
        transition: var(--transition);
    }
    
    .status-item:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow);
    }
    
    .status-item .label {
        font-size: 12px;
        color: var(--gray);
        font-weight: 500;
    }
    
    .status-item .value {
        font-size: 22px;
        font-weight: 700;
        color: var(--dark);
        margin: 2px 0;
    }
    
    .status-item .total {
        font-size: 12px;
        color: var(--gray);
    }
    
    /* ============================================
       TABELA
       ============================================ */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-top: 10px;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        min-width: 650px;
    }
    
    .table thead th {
        background: #f8fafc;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: var(--dark-light);
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table tbody td {
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    
    .table tbody tr {
        transition: var(--transition);
    }
    
    .table tbody tr:hover {
        background: #fafbfc;
    }
    
    .table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .table .aluno-nome {
        font-weight: 600;
        color: var(--dark);
    }
    
    .table .valor {
        font-weight: 600;
    }
    
    .table .valor.positivo {
        color: #2ecc71;
    }
    
    .table .valor.negativo {
        color: #e74c3c;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        transition: var(--transition);
    }
    
    .status-badge.confirmado {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-badge.pendente {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-badge.atrasado {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .status-badge.pago {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-badge.cancelado {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--gray);
    }
    
    .empty-state .icon {
        font-size: 56px;
        display: block;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    .empty-state h3 {
        font-size: 18px;
        color: var(--dark-light);
        margin: 0 0 5px;
    }
    
    /* ============================================
       AÇÕES RÁPIDAS
       ============================================ */
    .acoes-rapidas {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 25px;
        padding: 20px;
        background: linear-gradient(135deg, #fafbfc, #f8fafc);
        border-radius: var(--radius);
        border: 1px solid var(--gray-light);
        justify-content: center;
        align-items: center;
    }
    
    .acoes-rapidas .label {
        color: var(--gray);
        font-size: 13px;
        font-weight: 500;
        margin-right: 5px;
    }
    
    .acoes-rapidas .btn {
        padding: 8px 20px;
        font-size: 12px;
    }
    
    /* ============================================
       RESPONSIVO
       ============================================ */
    @media (max-width: 1024px) {
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
        }
        
        .agt-banner .agt-stats {
            gap: 20px;
        }
        
        .agt-banner .agt-stats .agt-stat {
            padding: 0 10px;
        }
    }
    
    @media (max-width: 768px) {
        .financeiro-container {
            padding: 15px;
        }
        
        .page-header {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }
        
        .page-header .header-left h1 {
            font-size: 22px;
        }
        
        .menu-financeiro {
            flex-direction: column;
            align-items: stretch;
            padding: 10px;
        }
        
        .menu-financeiro a {
            text-align: center;
            justify-content: center;
        }
        
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .stat-card {
            padding: 16px 18px;
        }
        
        .stat-card .number {
            font-size: 24px;
        }
        
        .status-grid {
            grid-template-columns: 1fr 1fr;
        }
        
        .table {
            font-size: 12px;
            min-width: 550px;
        }
        
        .table thead th,
        .table tbody td {
            padding: 8px 12px;
        }
        
        .card-list {
            padding: 16px 18px;
        }
        
        .acoes-rapidas {
            flex-direction: column;
            align-items: stretch;
        }
        
        .acoes-rapidas .btn {
            justify-content: center;
        }
        
        .agt-banner {
            flex-direction: column;
            align-items: stretch;
            text-align: center;
            padding: 25px 20px;
            cursor: pointer;
        }
        
        .agt-banner .agt-content {
            justify-content: center;
        }
        
        .agt-banner .agt-stats {
            justify-content: center;
        }
        
        .agt-banner .agt-stats .agt-stat {
            border-right: none;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            padding: 8px 0;
            width: 100%;
        }
        
        .agt-banner .btn-agt-open {
            justify-content: center;
            padding: 12px 25px;
            font-size: 13px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .status-grid {
            grid-template-columns: 1fr;
        }
        
        .page-header .header-left h1 {
            font-size: 20px;
        }
        
        .agt-banner .agt-text h3 {
            font-size: 17px;
        }
        
        .agt-banner .agt-stats .agt-stat .agt-number {
            font-size: 20px;
        }
    }
</style>

<div class="financeiro-container">
    <!-- ===== HEADER ===== -->
    <div class="page-header animate-left">
        <div class="header-left">
            <h1>💰 Financeiro Escolar</h1>
            <p class="subtitle">
                Gestão de <span>pagamentos</span>, <span>mensalidades</span> e <span>emolumentos</span>
            </p>
        </div>
        <a href="../index.php" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Voltar
        </a>
    </div>

    <!-- ===== MENU FINANCEIRO ===== -->
    <div class="menu-financeiro animate-left" style="animation-delay: 0.1s;">
        <a href="index.php" class="active">📊 Dashboard</a>
        <a href="pagamentos/">💳 Pagamentos</a>
        <a href="emolumentos/">📋 Emolumentos</a>
        <a href="mensalidades/">📅 Mensalidades</a>
        <a href="contas/">🏦 Contas</a>
        <a href="fluxo_caixa/">💵 Fluxo de Caixa</a>
        <a href="relatorios/">📈 Relatórios</a>
    </div>

    <!-- ===== BANNER RELATÓRIO AGT ===== -->
    <div class="agt-banner animate-left" style="animation-delay: 0.15s;" onclick="abrirRelatorioAGT()">
        <div class="agt-content">
            <div class="agt-icon">🏛️</div>
            <div class="agt-text">
                <h3>📄 Relatório Oficial AGT</h3>
                <p class="agt-sub">
                    Administração Geral Tributária · Documento Fiscal · 
                    <strong><?= strftime('%d de %B de %Y') ?></strong>
                </p>
            </div>
        </div>
        
        <div class="agt-stats">
            <div class="agt-stat">
                <span class="agt-number"><?= number_format($totalPagamentos, 2, ',', '.') ?> Kz</span>
                <span class="agt-label">Arrecadado (Mês)</span>
            </div>
            <div class="agt-stat">
                <span class="agt-number"><?= $totalAlunosCount ?></span>
                <span class="agt-label">Alunos Ativos</span>
            </div>
            <div class="agt-stat">
                <span class="agt-number"><?= $totalEmolumentosCount ?></span>
                <span class="agt-label">Emolumentos</span>
            </div>
        </div>
        
        <span class="btn-agt-open" onclick="event.stopPropagation(); abrirRelatorioAGT();">
            <span class="icon-pdf">📄</span>
            Abrir Relatório AGT
            <span class="badge-new">OFICIAL</span>
            <span class="arrow-open">→</span>
        </span>
    </div>

    <!-- ===== STATS CARDS ===== -->
    <div class="stats-grid">
        <div class="stat-card recebido animate-fade-up">
            <span class="icon">📥</span>
            <div class="number">R$ <?= number_format($totalRecebido, 2, ',', '.') ?></div>
            <div class="label">Total Recebido</div>
            <span class="trend up">↑ 12% este mês</span>
        </div>
        
        <div class="stat-card pendente animate-fade-up">
            <span class="icon">⏳</span>
            <div class="number">R$ <?= number_format($totalPendente, 2, ',', '.') ?></div>
            <div class="label">Total Pendente</div>
            <span class="trend down">⚠️ Atenção</span>
        </div>
        
        <div class="stat-card emolumentos animate-fade-up">
            <span class="icon">📋</span>
            <div class="number"><?= $totalEmolumentos ?></div>
            <div class="label">Emolumentos Ativos</div>
            <span class="trend up">✅ Ativos</span>
        </div>
        
        <div class="stat-card alunos animate-fade-up">
            <span class="icon">👨‍🎓</span>
            <div class="number"><?= $totalAlunos ?></div>
            <div class="label">Alunos Ativos</div>
            <span class="trend up">👥 Matriculados</span>
        </div>
        
        <div class="stat-card atrasados animate-fade-up">
            <span class="icon">⚠️</span>
            <div class="number"><?= $totalAtrasados ?></div>
            <div class="label">Mensalidades Atrasadas</div>
            <span class="trend down">🔴 Pendentes</span>
        </div>
    </div>

    <!-- ===== STATUS DAS MENSALIDADES ===== -->
    <div class="card-list animate-left" style="animation-delay: 0.2s;">
        <div class="card-header">
            <h3>📊 Status das Mensalidades</h3>
            <span class="badge-count">Total: <?= $totalMensalidades ?></span>
        </div>
        
        <?php if (count($mensalidadesStatus) > 0): ?>
        <div class="status-grid">
            <?php foreach($mensalidadesStatus as $status): ?>
            <div class="status-item" style="border-left-color: <?= $status['status'] == 'pago' ? '#2ecc71' : ($status['status'] == 'atrasado' ? '#e74c3c' : '#f39c12') ?>;">
                <div class="label"><?= ucfirst($status['status']) ?></div>
                <div class="value"><?= $status['total'] ?></div>
                <div class="total">R$ <?= number_format($status['valor_total'], 2, ',', '.') ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <span class="icon">📊</span>
            <h3>Nenhuma mensalidade cadastrada</h3>
            <p>Comece gerando as mensalidades para os alunos.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== ÚLTIMOS PAGAMENTOS ===== -->
    <div class="card-list animate-left" style="animation-delay: 0.3s;">
        <div class="card-header">
            <h3>💳 Últimos Pagamentos</h3>
            <span class="badge-count">Últimos 5 registros</span>
        </div>
        
        <?php if (count($ultimosPagamentos) > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Aluno</th>
                        <th>Descrição</th>
                        <th style="text-align: right;">Valor</th>
                        <th>Status</th>
                        <th>Forma</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($ultimosPagamentos as $pagamento): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($pagamento['data_pagamento'])) ?></td>
                        <td class="aluno-nome"><?= htmlspecialchars($pagamento['aluno_nome'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($pagamento['emolumento_nome'] ?? 'Pagamento') ?></td>
                        <td style="text-align: right;" class="valor positivo">R$ <?= number_format($pagamento['valor'], 2, ',', '.') ?></td>
                        <td>
                            <span class="status-badge <?= $pagamento['status'] ?>">
                                <?= ucfirst($pagamento['status']) ?>
                            </span>
                        </td>
                        <td><?= ucfirst($pagamento['forma_pagamento'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <span class="icon">📭</span>
            <h3>Nenhum pagamento registrado</h3>
            <p>Cadastre emolumentos e registre pagamentos para começar.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== AÇÕES RÁPIDAS ===== -->
    <div class="acoes-rapidas animate-left" style="animation-delay: 0.4s;">
        <span class="label">⚡ Ações rápidas:</span>
        <a href="pagamentos/add.php" class="btn btn-success">💳 Novo Pagamento</a>
        <a href="emolumentos/add.php" class="btn btn-gold">📋 Novo Emolumento</a>
        <a href="mensalidades/gerar.php" class="btn btn-info">📅 Gerar Mensalidades</a>
        <a href="relatorios/" class="btn btn-warning">📈 Ver Relatórios</a>
    </div>
</div>

<!-- ===== LOADING OVERLAY ===== -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
    <div class="loading-text">📄 Gerando <span>Relatório AGT</span></div>
    <div class="loading-sub">Aguarde enquanto preparamos o documento oficial...</div>
</div>

<script>
    // ===== FUNÇÃO PARA ABRIR RELATÓRIO AGT =====
    function abrirRelatorioAGT() {
        // Mostrar loading
        const overlay = document.getElementById('loadingOverlay');
        overlay.classList.add('active');
        
        // Pequeno delay para mostrar o loading
        setTimeout(function() {
            // Redirecionar para o relatório
            window.location.href = 'relatorios/relatorio_agt.php';
        }, 600);
    }
    
    // ===== FECHAR LOADING SE A PÁGINA FOR CARREGADA =====
    window.addEventListener('pageshow', function(event) {
        const overlay = document.getElementById('loadingOverlay');
        if (event.persisted) {
            overlay.classList.remove('active');
        }
    });
    
    // ===== PREVENIR CLIQUE ACIDENTAL NO BANNER =====
    document.querySelector('.agt-banner').addEventListener('click', function(e) {
        // Se clicou em um link dentro do banner, não fazer nada (já vai ser redirecionado)
        if (e.target.closest('a')) {
            return;
        }
        abrirRelatorioAGT();
    });
</script>

<?php
// ===== INCLUIR FOOTER =====
include '../includes/footer_escola.php';
?>