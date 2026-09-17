<?php
// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Painel Pedagógico' ?> - SoftGest</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ===== RESET ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #c9a84c;
            --primary-dark: #b8973a;
            --primary-light: #f5edd6;
            --primary-gradient: linear-gradient(135deg, #c9a84c, #b8973a);
            --secondary: #1a2332;
            --secondary-light: #2d3748;
            --text-primary: #1a2332;
            --text-secondary: #64748b;
            --text-light: #94a3b8;
            --bg-body: #f1f5f9;
            --bg-white: #ffffff;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.12);
            --shadow-xl: 0 20px 60px rgba(0,0,0,0.15);
            --radius: 16px;
            --radius-sm: 10px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 280px;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--secondary);
            padding: 20px;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1000;
            transition: var(--transition);
            overflow-y: auto;
            box-shadow: 4px 0 30px rgba(0,0,0,0.2);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }

        .sidebar-brand {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 20px;
        }
        .sidebar-brand .logo-icon {
            width: 56px;
            height: 56px;
            background: var(--primary-gradient);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 26px;
            color: var(--secondary);
            box-shadow: 0 4px 20px rgba(201, 168, 76, 0.3);
            transition: var(--transition);
        }
        .sidebar-brand .logo-icon:hover { transform: rotate(-5deg) scale(1.05); }
        .sidebar-brand h1 { color: white; font-size: 20px; font-weight: 700; letter-spacing: -0.5px; }
        .sidebar-brand h1 span { color: var(--primary); }
        .sidebar-brand p { color: var(--text-light); font-size: 12px; margin-top: 2px; }

        .sidebar-user {
            background: rgba(255,255,255,0.06);
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid rgba(255,255,255,0.05);
            transition: var(--transition);
        }
        .sidebar-user:hover { background: rgba(255,255,255,0.1); }
        .sidebar-user .avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            color: var(--secondary);
            flex-shrink: 0;
        }
        .sidebar-user .user-info .name { color: white; font-weight: 600; font-size: 14px; }
        .sidebar-user .user-info .email { color: var(--text-light); font-size: 11px; }

        .sidebar-nav { display: flex; flex-direction: column; gap: 3px; }
        .sidebar-nav .nav-label {
            color: var(--text-light);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 14px 4px;
            font-weight: 600;
            opacity: 0.6;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            font-size: 13px;
            font-weight: 500;
            position: relative;
        }
        .sidebar-nav a i { width: 18px; text-align: center; font-size: 15px; }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.08); color: white; transform: translateX(4px); }
        .sidebar-nav a.active {
            background: var(--primary-gradient);
            color: var(--secondary);
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
        }
        .sidebar-nav a .badge-nav {
            margin-left: auto;
            background: rgba(255,255,255,0.15);
            padding: 1px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        .sidebar-nav a.active .badge-nav { background: rgba(26,35,50,0.2); color: var(--secondary); }

        .sidebar-footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            font-size: 13px;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.08); color: white; }
        .sidebar-footer a i { width: 18px; text-align: center; }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px 25px;
            min-height: 100vh;
            transition: var(--transition);
        }

        /* ===== TOPBAR ===== */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: var(--bg-white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
            border: 1px solid rgba(0,0,0,0.04);
        }
        .topbar-left { display: flex; align-items: center; gap: 12px; }
        .topbar-left .page-title h2 { font-size: 18px; font-weight: 700; color: var(--text-primary); }
        .topbar-left .page-title p { font-size: 12px; color: var(--text-secondary); margin-top: 1px; }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .topbar-right .date-time { text-align: right; font-size: 12px; color: var(--text-secondary); }
        .topbar-right .date-time .time { font-weight: 600; color: var(--text-primary); }
        .topbar-right .status-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #ecfdf5;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            color: #065f46;
        }
        .topbar-right .status-indicator .dot {
            width: 6px;
            height: 6px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 1100;
            background: var(--secondary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            font-size: 18px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
        }
        .sidebar-toggle:hover { background: var(--primary); color: var(--secondary); }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }

        /* ===== CARDS ===== */
        .card {
            background: var(--bg-white);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(0,0,0,0.04);
            transition: var(--transition);
            margin-bottom: 20px;
        }
        .card:hover { box-shadow: var(--shadow-md); }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }
        .card-header h3 {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header h3 i { color: var(--primary); }
        .card-header .badge-count {
            background: var(--primary-light);
            color: var(--primary-dark);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        /* ===== BADGES ===== */
        .badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-success { background: #ecfdf5; color: #065f46; }
        .badge-danger { background: #fef2f2; color: #991b1b; }
        .badge-warning { background: #fffbeb; color: #92400e; }
        .badge-primary { background: var(--primary-light); color: var(--primary-dark); }
        .badge-info { background: #eff6ff; color: #1e40af; }
        .badge .dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            display: inline-block;
        }
        .badge-success .dot { background: #22c55e; }
        .badge-danger .dot { background: #ef4444; }
        .badge-warning .dot { background: #f59e0b; }
        .badge-primary .dot { background: var(--primary); }
        .badge-info .dot { background: #3b82f6; }

        .status-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .status-badge.ativa { background: #ecfdf5; color: #065f46; }
        .status-badge.inativa { background: #fef2f2; color: #991b1b; }
        .status-badge.pendente { background: #fffbeb; color: #92400e; }
        .status-badge.concluida { background: #eff6ff; color: #1e40af; }
        .status-badge .dot { width: 5px; height: 5px; border-radius: 50%; display: inline-block; }
        .status-badge.ativa .dot { background: #22c55e; }
        .status-badge.inativa .dot { background: #ef4444; }
        .status-badge.pendente .dot { background: #f59e0b; }
        .status-badge.concluida .dot { background: #3b82f6; }

        /* ===== TABELAS ===== */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        table thead th {
            padding: 10px 14px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: var(--text-primary);
        }
        table tbody tr { transition: var(--transition); }
        table tbody tr:hover { background: #f8fafc; }
        table tbody tr:last-child td { border-bottom: none; }

        /* ===== FORM ===== */
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 4px;
            color: var(--text-secondary);
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-sm);
            font-size: 13px;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.15);
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* ===== BOTÕES ===== */
        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--primary-gradient);
            color: var(--secondary);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
        }
        .btn-success {
            background: #22c55e;
            color: white;
        }
        .btn-success:hover { background: #16a34a; }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        .btn-warning:hover { background: #d97706; }
        .btn-info {
            background: #3b82f6;
            color: white;
        }
        .btn-info:hover { background: #2563eb; }
        .btn-outline {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: var(--text-secondary);
        }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }

        /* ===== EMPTY STATE ===== */
        .empty-state { text-align: center; padding: 30px 20px; color: var(--text-secondary); }
        .empty-state .icon { font-size: 40px; margin-bottom: 8px; opacity: 0.5; }
        .empty-state h4 { font-size: 15px; color: var(--text-primary); margin-bottom: 2px; }
        .empty-state p { font-size: 13px; color: var(--text-secondary); }

        /* ===== ALERT ===== */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 15px;
            font-size: 13px;
            font-weight: 500;
        }
        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        /* ===== FOOTER ===== */
        .footer {
            text-align: center;
            padding: 15px;
            color: var(--text-secondary);
            font-size: 12px;
            border-top: 1px solid #e2e8f0;
            margin-top: 10px;
            line-height: 1.8;
        }
        .footer .escola-nome {
            font-weight: 600;
            color: var(--text-primary);
        }
        .footer .escola-endereco {
            display: block;
            font-size: 11px;
            color: var(--text-light);
        }

        /* ===== GRID ===== */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; }

        /* ===== ANIMAÇÕES ===== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        .animate-fade-up { animation: fadeInUp 0.5s ease-out both; }
        .animate-fade-left { animation: fadeInLeft 0.5s ease-out both; }
        .animate-scale { animation: scaleIn 0.4s ease-out both; }
        .delay-1 { animation-delay: 0.05s; }
        .delay-2 { animation-delay: 0.1s; }
        .delay-3 { animation-delay: 0.15s; }
        .delay-4 { animation-delay: 0.2s; }
        .delay-5 { animation-delay: 0.25s; }
        .delay-6 { animation-delay: 0.3s; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .row { grid-template-columns: 1fr 1fr; }
            .row-2 { grid-template-columns: 1fr; }
            .grid-2 { grid-template-columns: 1fr; }
            .grid-3 { grid-template-columns: 1fr 1fr; }
            .grid-4 { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .sidebar-toggle { display: flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0; padding: 60px 12px 15px; }
            .topbar { flex-direction: column; align-items: stretch; gap: 8px; padding: 12px 15px; }
            .topbar-left .page-title h2 { font-size: 16px; }
            .topbar-right { flex-wrap: wrap; justify-content: space-between; }
            .row { grid-template-columns: 1fr; }
            .grid-3 { grid-template-columns: 1fr; }
            .grid-4 { grid-template-columns: 1fr; }
            .card { padding: 14px; }
        }
        @media (max-width: 480px) {
            .grid-2 { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            table thead th, table tbody td { padding: 6px 10px; font-size: 11px; }
        }




        /* ===== TOAST NOTIFICATIONS ===== */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 380px;
            width: 100%;
        }

        .toast {
            background: var(--bg-white);
            border-radius: var(--radius-sm);
            padding: 14px 18px;
            box-shadow: var(--shadow-lg);
            border-left: 4px solid var(--primary);
            animation: slideInRight 0.4s ease-out;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.3s ease;
            position: relative;
        }

        .toast:hover {
            transform: translateX(-4px);
            box-shadow: var(--shadow-xl);
        }

        .toast.hiding {
            animation: slideOutRight 0.4s ease-in forwards;
        }

        .toast-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .toast-icon.success { background: #d4edda; color: #155724; }
        .toast-icon.danger { background: #f8d7da; color: #721c24; }
        .toast-icon.warning { background: #fff3cd; color: #856404; }
        .toast-icon.info { background: #cce5ff; color: #004085; }
        .toast-icon.primary { background: #d4edda; color: #155724; }

        .toast-content {
            flex: 1;
            min-width: 0;
        }

        .toast-title {
            font-weight: 600;
            font-size: 13px;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .toast-message {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.3;
        }

        .toast-time {
            font-size: 10px;
            color: var(--text-light);
            margin-top: 4px;
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .toast-close:hover {
            color: var(--text-primary);
        }

        @keyframes slideInRight {
            from {
                transform: translateX(120%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(120%);
                opacity: 0;
            }
        }

        /* Responsivo */
        @media (max-width: 480px) {
            .toast-container {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
                width: auto;
            }
        }

        /* ============================================
           LISTA NOMINAL - DROPDOWN
           ============================================ */
        .lista-nominal-dropdown {
            background: #1e293b;
            border-radius: 8px;
            padding: 12px;
            margin: 4px 8px 8px 8px;
            border: 1px solid #334155;
            max-height: 500px;
            overflow-y: auto;
        }

        .lista-campos,
        .lista-filtros {
            margin-bottom: 12px;
        }

        .lista-label {
            color: #94a3b8;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .lista-checkbox-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            max-height: 150px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .lista-checkbox-group::-webkit-scrollbar {
            width: 3px;
        }

        .lista-checkbox-group::-webkit-scrollbar-track {
            background: #1e293b;
        }

        .lista-checkbox-group::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 3px;
        }

        .lista-checkbox {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 6px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 12px;
            color: #cbd5e1;
        }

        .lista-checkbox:hover {
            background: #334155;
        }

        .lista-checkbox input[type="checkbox"] {
            width: 14px;
            height: 14px;
            accent-color: #c9a84c;
            cursor: pointer;
            flex-shrink: 0;
        }

        .lista-checkbox input[type="checkbox"]:checked + span {
            color: #f1f5f9;
            font-weight: 500;
        }

        .lista-actions {
            display: flex;
            gap: 6px;
            margin-top: 6px;
        }

        .lista-actions button {
            background: transparent;
            border: 1px solid #334155;
            color: #94a3b8;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .lista-actions button:hover {
            background: #334155;
            color: #f1f5f9;
        }

        /* Filtros */
        .lista-filtro-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px;
        }

        .lista-select {
            width: 100%;
            padding: 6px 8px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 4px;
            color: #e2e8f0;
            font-size: 11px;
            cursor: pointer;
        }

        .lista-select:focus {
            outline: none;
            border-color: #c9a84c;
        }

        .lista-select option {
            background: #1e293b;
            color: #e2e8f0;
        }

        /* Botão Gerar */
        .btn-gerar-lista {
            width: 100%;
            padding: 8px;
            background: #c9a84c;
            color: #1a2332;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-gerar-lista:hover {
            background: #b8973a;
            transform: translateY(-1px);
        }

        /* Resultados */
        .lista-resultados {
            margin-top: 12px;
            border-top: 1px solid #334155;
            padding-top: 12px;
        }

        .lista-resultados-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px;
            color: #94a3b8;
            font-size: 12px;
        }

        .lista-resultados-actions {
            display: flex;
            gap: 6px;
        }

        .lista-resultados-actions button {
            padding: 4px 10px;
            border: none;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-export-csv {
            background: #2ecc71;
            color: #fff;
        }

        .btn-export-csv:hover {
            background: #27ae60;
        }

        .btn-export-pdf {
            background: #e74c3c;
            color: #fff;
        }

        .btn-export-pdf:hover {
            background: #c0392b;
        }

        .btn-export-print {
            background: #3498db;
            color: #fff;
        }

        .btn-export-print:hover {
            background: #2980b9;
        }

        .lista-tabela-wrapper {
            max-height: 300px;
            overflow-y: auto;
            border-radius: 6px;
            border: 1px solid #334155;
        }

        .lista-tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .lista-tabela thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .lista-tabela th {
            background: #334155;
            color: #e2e8f0;
            padding: 6px 8px;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }

        .lista-tabela td {
            padding: 5px 8px;
            color: #cbd5e1;
            border-bottom: 1px solid #1e293b;
        }

        .lista-tabela tr:hover td {
            background: #1e293b;
        }

        .lista-empty {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 13px;
        }

        .lista-empty i {
            display: block;
            font-size: 24px;
            margin-bottom: 6px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .lista-checkbox-group {
                grid-template-columns: 1fr;
            }
            
            .lista-filtro-row {
                grid-template-columns: 1fr;
            }
            
            .lista-resultados-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .lista-resultados-actions {
                justify-content: center;
            }
        }


    </style>
</head>
<body>

    <!-- ===== SIDEBAR OVERLAY ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== TOGGLE SIDEBAR ===== -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>