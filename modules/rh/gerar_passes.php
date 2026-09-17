<?php
// =============================================
// gerar_passes.php - PASSES PREMIUM ELEGANTE
// =============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../../config/database.php';
date_default_timezone_set('Africa/Luanda');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

// =============================================
// FUNÇÕES
// =============================================

function gerarQRCode($dados, $tamanho = 180) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $tamanho . 'x' . $tamanho . '&data=' . urlencode($dados) . '&bgcolor=ffffff&color=1a2332&margin=12';
}

function formatarNumero($num) {
    return 'FUNC-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function gerarCodigoBarras($codigo) {
    return 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($codigo) . '&code=Code128&dpi=96&dataseparator=';
}

// =============================================
// DADOS DA EMPRESA
// =============================================

$empresa = [
    'nome' => 'COMPLEXO ESCOLAR Nº 4006 - JAMBONDO',
    'endereco' => 'Icolo e Bengo - Bom Jesus, Jambondo',
    'telefone' => '925 307 484 / 958 670 657 / 946 646 242',
    'email' => 'escola4006jambondo@gmail.com',
    'site' => 'sistemaescolar4006.onrender.com',
    'slogan' => 'EDUCAR HOJE, TRANSFORMAR AMANHÃ',
    'footer' => 'JUNTOS CONSTRUÍMOS O FUTURO'
];

// =============================================
// BUSCAR FUNCIONÁRIOS
// =============================================

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$funcionario = null;
$todosFuncionarios = [];
$qrCodeUrl = '';
$codigoBarrasUrl = '';

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$id]);
        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $stmtTodos = $pdo->query("SELECT id, nome, num_agente, categoria_actual, cargo, disciplina_lecciona, foto, data_admissao, status FROM funcionarios WHERE status = 'ativo' ORDER BY nome");
    $todosFuncionarios = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

    if ($funcionario) {
        $dadosQR = json_encode([
            'id' => $funcionario['id'],
            'nome' => $funcionario['nome'],
            'agente' => $funcionario['num_agente'],
            'categoria' => $funcionario['categoria_actual'] ?? 'Funcionário',
            'empresa' => $empresa['nome'],
            'validade' => date('Y-m-d', strtotime('+1 year'))
        ]);
        $qrCodeUrl = gerarQRCode($dadosQR);
        $codigoBarrasUrl = gerarCodigoBarras($funcionario['id']);
    }
} catch (Exception $e) {
    die("❌ Erro ao buscar dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🪪 Passes Premium</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== RESET ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #eef2f7; color: #1a2332; display: flex; min-height: 100vh; }
        .main-content { margin-left: 280px; flex: 1; padding: 20px; max-width: calc(100% - 280px); min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; }

        /* ===== HEADER ===== */
        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 25px; }
        .page-title { font-size: 28px; font-weight: 800; color: #1a2332; }
        .page-title span { color: #c9a84c; }
        .page-subtitle { color: #64748b; font-size: 14px; margin-top: 4px; }

        .btn { padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; border: none; }
        .btn-gold { background: linear-gradient(135deg, #c9a84c, #f5d76e); color: #1a2332; }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197,165,50,0.3); }
        .btn-outline { background: transparent; color: #c9a84c; border: 2px solid #c9a84c; }
        .btn-outline:hover { background: rgba(197,165,50,0.1); transform: translateY(-2px); }
        .btn-secondary { background: #eef2f7; color: #1a2332; }
        .btn-secondary:hover { background: #e2e8f0; transform: translateY(-2px); }
        .btn-success { background: #2ecc71; color: white; }
        .btn-success:hover { background: #27ae60; transform: translateY(-2px); }
        .btn-purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        .btn-purple:hover { background: #7c3aed; transform: translateY(-2px); }

        .rh-menu {
            background: white; border-radius: 12px; padding: 12px 20px; margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7;
            display: flex; flex-wrap: wrap; gap: 5px; align-items: center;
        }
        .rh-menu a {
            padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 500;
            transition: all 0.3s ease; color: #4a5568; display: inline-flex; align-items: center; gap: 8px;
        }
        .rh-menu a:hover { background: rgba(197,165,50,0.1); color: #c9a84c; }
        .rh-menu a.active { background: linear-gradient(135deg, #c9a84c, #f5d76e); color: #1a2332; font-weight: 600; box-shadow: 0 2px 15px rgba(197,165,50,0.3); }

        .funcionarios-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; margin-bottom: 25px;
        }
        .funcionario-item {
            background: white; padding: 14px 18px; border-radius: 10px; border: 2px solid #eef2f7;
            transition: all 0.3s; cursor: pointer; display: flex; justify-content: space-between; align-items: center;
        }
        .funcionario-item:hover { border-color: #c9a84c; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .funcionario-item .nome { font-weight: 600; font-size: 14px; color: #1a2332; }
        .funcionario-item .agente { font-size: 12px; color: #94a3b8; }
        .funcionario-item .badge { background: #c9a84c; color: #1a2332; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; white-space: nowrap; }
        .funcionario-item.selected { border-color: #c9a84c; background: #fefcf3; }

        /* ============================================================
           PASSE PREMIUM ELEGANTE
           ============================================================ */
        .passe-wrapper {
            max-width: 480px;
            margin: 0 auto;
            background: transparent;
            perspective: 1000px;
        }

        .passe-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 5px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(201,168,76,0.15);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
        }
        .passe-container:hover {
            transform: translateY(-8px) scale(1.005);
            box-shadow: 0 30px 80px rgba(0,0,0,0.2), 0 10px 30px rgba(201,168,76,0.1);
        }

        /* Efeito de brilho no passe */
        .passe-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at 30% 20%, rgba(201,168,76,0.03), transparent 70%);
            pointer-events: none;
            z-index: 1;
        }

        /* ===== FRENTE DO PASSE ===== */
        .passe-front {
            background: linear-gradient(160deg, #080e1e 0%, #0c1a30 25%, #112544 60%, #1a3555 100%);
            padding: 0;
            position: relative;
            overflow: hidden;
            min-height: 520px;
        }

        /* Efeito de textura sutil */
        .passe-front::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 1;
        }

        /* Elementos decorativos */
        .passe-front .deco-top {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 80px;
            background: linear-gradient(180deg, rgba(201,168,76,0.06) 0%, transparent 100%);
            pointer-events: none;
            z-index: 1;
        }

        .passe-front .deco-bottom {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(0deg, rgba(0,0,0,0.2) 0%, transparent 100%);
            pointer-events: none;
            z-index: 1;
        }

        .passe-front .gold-strip {
            height: 4px;
            background: linear-gradient(90deg, #b8952e, #e8c84a, #f5d76e, #e8c84a, #b8952e);
            width: 100%;
            position: relative;
            z-index: 2;
            box-shadow: 0 0 30px rgba(232,200,74,0.15);
        }

        .passe-front .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 140px;
            font-weight: 900;
            color: rgba(255,255,255,0.015);
            letter-spacing: 15px;
            pointer-events: none;
            font-family: 'Georgia', serif;
            z-index: 1;
            text-shadow: 0 0 50px rgba(232,200,74,0.02);
        }

        .passe-front .watermark-icon {
            position: absolute;
            bottom: 30px;
            right: 30px;
            font-size: 90px;
            opacity: 0.03;
            pointer-events: none;
            z-index: 1;
        }

        /* Cabeçalho */
        .passe-front .header {
            padding: 22px 25px 0;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .passe-front .header .school {
            font-size: 15px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1.4;
            text-shadow: 0 2px 20px rgba(0,0,0,0.3);
        }

        .passe-front .header .school .gold {
            color: #e8c84a;
            text-shadow: 0 0 30px rgba(232,200,74,0.1);
        }

        .passe-front .header .sub {
            font-size: 9px;
            font-weight: 500;
            color: rgba(255,255,255,0.4);
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-top: 3px;
        }

        .passe-front .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(232,200,74,0.25), transparent);
            margin: 10px 30px 0;
            position: relative;
            z-index: 2;
        }

        .passe-front .slogan {
            text-align: center;
            font-size: 8px;
            font-weight: 700;
            color: rgba(232,200,74,0.5);
            letter-spacing: 5px;
            text-transform: uppercase;
            padding: 6px 0 12px;
            position: relative;
            z-index: 2;
        }

        /* Foto */
        .passe-front .foto-area {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            margin: 0 auto 4px;
            border: 3px solid rgba(232,200,74,0.5);
            overflow: hidden;
            background: rgba(255,255,255,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            font-weight: 700;
            color: rgba(255,255,255,0.15);
            box-shadow: 0 0 40px rgba(232,200,74,0.06), inset 0 0 40px rgba(232,200,74,0.02);
            position: relative;
            z-index: 2;
            transition: all 0.3s;
        }
        .passe-front .foto-area:hover {
            border-color: #e8c84a;
            box-shadow: 0 0 60px rgba(232,200,74,0.15);
        }
        .passe-front .foto-area img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Nome e Cargo */
        .passe-front .nome {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            position: relative;
            z-index: 2;
            line-height: 1.2;
            text-shadow: 0 2px 20px rgba(0,0,0,0.2);
        }

        .passe-front .cargo_text {
            font-size: 14px;
            color: #e8c84a;
            font-weight: 600;
            text-align: center;
            letter-spacing: 1px;
            position: relative;
            z-index: 2;
            margin-bottom: 4px;
            text-shadow: 0 0 30px rgba(232,200,74,0.05);
        }

        /* Grid de Informações */
        .passe-front .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin: 8px 22px 14px;
            position: relative;
            z-index: 2;
        }

        .passe-front .info-grid .item {
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
            padding: 8px 10px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.04);
            backdrop-filter: blur(10px);
            transition: all 0.3s;
        }
        .passe-front .info-grid .item:hover {
            background: rgba(255,255,255,0.07);
            border-color: rgba(232,200,74,0.1);
        }

        .passe-front .info-grid .item .label {
            font-size: 7px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            letter-spacing: 1px;
            font-weight: 600;
        }

        .passe-front .info-grid .item .value {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            margin-top: 1px;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.5px;
        }

        .passe-front .info-grid .item .value.gold {
            color: #e8c84a;
            text-shadow: 0 0 20px rgba(232,200,74,0.1);
        }

        /* Assinatura */
        .passe-front .assinatura {
            padding: 6px 25px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 2;
            border-top: 1px solid rgba(255,255,255,0.04);
            margin: 0 22px;
            padding-top: 10px;
        }

        .passe-front .assinatura .label {
            font-size: 7px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.2);
            letter-spacing: 1px;
        }

        .passe-front .assinatura .line {
            flex: 1;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin: 0 12px;
        }

        .passe-front .assinatura .cargo-ass {
            font-size: 7px;
            color: rgba(255,255,255,0.25);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Rodapé */
        .passe-front .footer {
            text-align: center;
            padding: 10px 0 16px;
            font-size: 10px;
            font-weight: 700;
            color: rgba(255,255,255,0.15);
            letter-spacing: 4px;
            text-transform: uppercase;
            position: relative;
            z-index: 2;
            background: rgba(0,0,0,0.15);
            margin-top: 4px;
        }

        .passe-front .footer .gold {
            color: rgba(232,200,74,0.3);
        }

        /* ===== VERSO DO PASSE ===== */
        .passe-back {
            background: linear-gradient(135deg, #f8fafc, #f1f3f5);
            padding: 20px 22px;
            border-top: 3px solid #e8c84a;
            display: flex;
            gap: 18px;
            align-items: flex-start;
            min-height: 180px;
            position: relative;
        }

        .passe-back .qr-area {
            flex-shrink: 0;
            text-align: center;
            background: white;
            padding: 10px;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.3s;
        }
        .passe-back .qr-area:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            transform: scale(1.02);
        }

        .passe-back .qr-area img {
            width: 110px;
            height: 110px;
            display: block;
            border-radius: 4px;
        }

        .passe-back .qr-area .id {
            font-size: 9px;
            font-weight: 700;
            color: #1a2332;
            font-family: 'Courier New', monospace;
            letter-spacing: 1.5px;
            margin-top: 5px;
        }

        .passe-back .regras {
            flex: 1;
            color: #374151;
            font-size: 8.5px;
            line-height: 1.7;
        }

        .passe-back .regras .title {
            font-weight: 700;
            font-size: 10px;
            color: #1a2332;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-bottom: 2px solid rgba(232,200,74,0.2);
            padding-bottom: 5px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .passe-back .regras .title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, rgba(232,200,74,0.2), transparent);
        }

        .passe-back .regras ul {
            list-style: none;
            padding: 0;
        }

        .passe-back .regras ul li {
            padding: 2px 0;
            padding-left: 14px;
            position: relative;
        }

        .passe-back .regras ul li::before {
            content: '◆';
            position: absolute;
            left: 0;
            color: #e8c84a;
            font-size: 6px;
            top: 4px;
            opacity: 0.6;
        }

        .passe-back .contato {
            margin-top: 8px;
            font-size: 7.5px;
            color: #6b7280;
            border-top: 1px solid rgba(0,0,0,0.04);
            padding-top: 7px;
            text-align: center;
            line-height: 1.9;
        }

        .passe-back .contato strong {
            color: #1a2332;
        }

        /* ===== CÓDIGO DE BARRAS ===== */
        .passe-barcode {
            background: white;
            padding: 8px 20px;
            text-align: center;
            border-top: 1px solid rgba(0,0,0,0.04);
            background: linear-gradient(135deg, #fafafa, #f5f5f5);
        }

        .passe-barcode img {
            max-width: 200px;
            height: auto;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.05));
        }

        /* ===== AÇÕES ===== */
        .passe-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            border: 2px dashed #eef2f7;
            margin-top: 20px;
        }
        .empty-state .icone { font-size: 60px; margin-bottom: 15px; }
        .empty-state h3 { color: #1a2332; }
        .empty-state p { color: #94a3b8; max-width: 400px; margin: 10px auto; }

        /* ===== IMPRESSÃO ===== */
        @media print {
            .no-print { display: none !important; }
            .main-content { margin: 0; max-width: 100%; padding: 10px; }
            .passe-wrapper { max-width: 100%; }
            .passe-container { box-shadow: none !important; border: 2px solid #c9a84c; border-radius: 12px; }
            .passe-container:hover { transform: none !important; }
            body { background: white; }
            .passe-front { min-height: 480px; }
            .passe-back { min-height: 160px; }
            .passe-front .info-grid .item { background: rgba(255,255,255,0.06); }
        }

        /* ===== RESPONSIVO ===== */
        @media (max-width: 992px) {
            .main-content { margin-left: 0; max-width: 100%; padding: 15px; }
        }

        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .rh-menu { flex-direction: column; align-items: stretch; }
            .rh-menu a { justify-content: center; }
            .funcionarios-grid { grid-template-columns: 1fr 1fr; }
            .passe-back { flex-direction: column; align-items: center; text-align: center; }
            .passe-front .info-grid { grid-template-columns: 1fr; }
            .passe-front .assinatura { flex-direction: column; gap: 6px; }
            .passe-wrapper { max-width: 100%; }
            .passe-front .header .school { font-size: 13px; }
            .passe-front .nome { font-size: 17px; }
            .passe-front .foto-area { width: 90px; height: 90px; }
            .passe-back .qr-area img { width: 90px; height: 90px; }
        }

        @media (max-width: 480px) {
            .funcionarios-grid { grid-template-columns: 1fr; }
            .passe-actions { flex-direction: column; }
            .passe-actions .btn { justify-content: center; }
            .passe-front .info-grid { gap: 4px; }
            .passe-front .info-grid .item { padding: 5px 6px; }
            .passe-front .info-grid .item .value { font-size: 12px; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="main-content">
        <div class="container">
            <!-- ===== HEADER ===== -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">🪪 <span>Passes</span> Premium</h1>
                    <p class="page-subtitle">Carteiras profissionais com design exclusivo e QR Code</p>
                </div>
                <div class="no-print">
                    <a href="configurar_passe.php" class="btn btn-purple"><i class="fas fa-cog"></i> Configurar</a>
                    <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
            </div>

            <!-- ===== MENU RH ===== -->
            <div class="rh-menu no-print">
                <a href="index.php"><span>📊</span> Dashboard</a>
                <a href="funcionarios.php"><span>👤</span> Funcionários</a>
                <a href="add_funcionario.php"><span>➕</span> Novo</a>
                <a href="presenca_qr.php"><span>📱</span> Presença QR</a>
                <a href="gerar_passes.php" class="active"><span>🪪</span> Passes</a>
                <a href="projetor.php"><span>📽️</span> Projetor</a>
                <a href="validar_qr.php"><span>✅</span> Validar QR</a>
            </div>

            <!-- ===== LISTA DE FUNCIONÁRIOS ===== -->
            <div class="no-print">
                <h3 style="color: #1a2332; margin-bottom: 12px;">👥 Selecionar Funcionário</h3>
                <div class="funcionarios-grid">
                    <?php if (empty($todosFuncionarios)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px; color: #94a3b8;">
                            Nenhum funcionário ativo encontrado.
                        </div>
                    <?php else: ?>
                        <?php foreach($todosFuncionarios as $f): ?>
                        <div class="funcionario-item <?= ($id > 0 && $id == $f['id']) ? 'selected' : '' ?>" 
                             onclick="window.location.href='gerar_passes.php?id=<?= $f['id'] ?>'">
                            <div>
                                <div class="nome"><?= htmlspecialchars($f['nome'] ?? 'Sem nome') ?></div>
                                <div class="agente">Nº: <?= htmlspecialchars($f['num_agente'] ?? 'N/A') ?></div>
                            </div>
                            <span class="badge"><?= htmlspecialchars($f['categoria_actual'] ?? 'Funcionário') ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== PASSE PREMIUM ===== -->
            <?php if ($funcionario): 
                $id_formatado = formatarNumero($funcionario['id']);
                $cargo_exibicao = !empty($funcionario['cargo']) ? $funcionario['cargo'] : 'PROFESSOR';
                if (!empty($funcionario['disciplina_lecciona'])) {
                    $cargo_exibicao = 'Professor de ' . $funcionario['disciplina_lecciona'];
                }
                $foto_path = !empty($funcionario['foto']) ? '../../' . $funcionario['foto'] : '';
                $tem_foto = !empty($foto_path) && file_exists(__DIR__ . '/../' . $funcionario['foto']);
                $data_admissao = $funcionario['data_admissao'] ?? date('Y-m-d');
            ?>
            <div style="margin-top: 30px;">
                <div class="passe-wrapper">
                    <div class="passe-container" id="passeContainer">

                        <!-- ===== FRENTE ===== -->
                        <div class="passe-front">
                            <div class="deco-top"></div>
                            <div class="deco-bottom"></div>
                            <div class="watermark">CCR</div>
                            <div class="watermark-icon">🏫</div>
                            <div class="gold-strip"></div>

                            <div class="header">
                                <div class="school">
                                    COMPLEXO ESCOLAR<br>
                                    <span class="gold">Nº 4006 - JAMBONDO</span>
                                </div>
                                <div class="sub">EDUCAR HOJE, TRANSFORMAR AMANHÃ</div>
                            </div>

                            <div class="divider"></div>
                            <div class="slogan">EDUCAR HOJE, TRANSFORMAR AMANHÃ</div>

                            <div class="foto-area">
                                <?php if ($tem_foto): ?>
                                    <img src="<?= $foto_path ?>" alt="Foto">
                                <?php else: ?>
                                    <?= strtoupper(substr($funcionario['nome'], 0, 2)) ?>
                                <?php endif; ?>
                            </div>

                            <div class="nome"><?= strtoupper(htmlspecialchars($funcionario['nome'])) ?></div>
                            <div class="cargo_text"><?= htmlspecialchars($cargo_exibicao) ?></div>

                            <div class="info-grid">
                                <div class="item">
                                    <div class="label">ID FUNC.</div>
                                    <div class="value gold"><?= $id_formatado ?></div>
                                </div>
                                <div class="item">
                                    <div class="label">CARGO</div>
                                    <div class="value"><?= htmlspecialchars($funcionario['cargo'] ?? 'PROFESSOR') ?></div>
                                </div>
                                <div class="item">
                                    <div class="label">ADMISSÃO</div>
                                    <div class="value"><?= date('d/m/Y', strtotime($data_admissao)) ?></div>
                                </div>
                                <div class="item">
                                    <div class="label">TIPO SANG.</div>
                                    <div class="value">O+</div>
                                </div>
                            </div>

                            <div class="assinatura">
                                <span class="label">ASSINATURA</span>
                                <span class="line"></span>
                                <span class="cargo-ass">DIRETOR</span>
                            </div>

                            <div class="footer">
                                <span class="gold">✦</span> JUNTOS CONSTRUÍMOS O FUTURO <span class="gold">✦</span>
                            </div>
                        </div>

                        <!-- ===== VERSO ===== -->
                        <div class="passe-back">
                            <div class="qr-area">
                                <img src="<?= $qrCodeUrl ?>" alt="QR Code" loading="lazy">
                                <div class="id"><?= $id_formatado ?></div>
                            </div>
                            <div class="regras">
                                <div class="title">REGRAS DE UTILIZAÇÃO</div>
                                <ul>
                                    <li>Este passe é pessoal e intransmissível.</li>
                                    <li>Deve ser utilizado durante o expediente.</li>
                                    <li>Em caso de perda, comunicar imediatamente à administração.</li>
                                    <li>Devolver ao Complexo Escolar em caso de cessação de funções.</li>
                                </ul>
                                <div class="contato">
                                    <strong><?= $empresa['endereco'] ?></strong><br>
                                    <?= $empresa['telefone'] ?><br>
                                    <?= $empresa['site'] ?><br>
                                    <?= $empresa['email'] ?>
                                </div>
                            </div>
                        </div>

                        <!-- ===== CÓDIGO DE BARRAS ===== -->
                        <div class="passe-barcode">
                            <img src="<?= $codigoBarrasUrl ?>" alt="Código de Barras" loading="lazy">
                        </div>
                    </div>
                </div>

                <!-- ===== AÇÕES ===== -->
                <div class="passe-actions no-print">
                    <button onclick="window.print()" class="btn btn-gold"><i class="fas fa-print"></i> Imprimir Passe</button>
                    <button onclick="baixarPasse()" class="btn btn-success"><i class="fas fa-download"></i> Baixar PDF</button>
                    <a href="projetor.php?id=<?= $funcionario['id'] ?>" class="btn btn-purple"><i class="fas fa-tv"></i> Projetor</a>
                    <a href="validar_qr.php?id=<?= $funcionario['id'] ?>" class="btn btn-outline"><i class="fas fa-qrcode"></i> Validar</a>
                    <a href="gerar_passes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Limpar</a>
                </div>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <div class="icone">🪪</div>
                <h3>Selecione um Funcionário</h3>
                <p>Clique em um funcionário na lista acima para gerar seu passe profissional com QR Code.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        function baixarPasse() {
            const conteudo = document.getElementById('passeContainer').innerHTML;
            const win = window.open('', '_blank');
            win.document.write(`
                <!DOCTYPE html>
                <html>
                <head><title>Passe Premium</title><meta charset="UTF-8">
                <style>
                    *{margin:0;padding:0;box-sizing:border-box}
                    body{font-family:'Inter',Arial,sans-serif;background:#0a1628;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
                    .passe-wrapper{max-width:480px;width:100%;margin:0 auto;background:transparent}
                    .passe-container{background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);border:1px solid rgba(201,168,76,0.15);position:relative}
                    .passe-front{background:linear-gradient(160deg,#080e1e 0%,#0c1a30 25%,#112544 60%,#1a3555 100%);padding:0;position:relative;overflow:hidden;min-height:520px}
                    .passe-front .gold-strip{height:4px;background:linear-gradient(90deg,#b8952e,#e8c84a,#f5d76e,#e8c84a,#b8952e);width:100%;position:relative;z-index:2;box-shadow:0 0 30px rgba(232,200,74,0.15)}
                    .passe-front .watermark{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:140px;font-weight:900;color:rgba(255,255,255,0.015);letter-spacing:15px;pointer-events:none;font-family:Georgia,serif;z-index:1}
                    .passe-front .watermark-icon{position:absolute;bottom:30px;right:30px;font-size:90px;opacity:.03;pointer-events:none;z-index:1}
                    .passe-front .header{padding:22px 25px 0;text-align:center;position:relative;z-index:2}
                    .passe-front .header .school{font-size:15px;font-weight:800;color:#fff;letter-spacing:2px;text-transform:uppercase;line-height:1.4}
                    .passe-front .header .school .gold{color:#e8c84a}
                    .passe-front .header .sub{font-size:9px;font-weight:500;color:rgba(255,255,255,0.4);letter-spacing:4px;text-transform:uppercase;margin-top:3px}
                    .passe-front .divider{height:1px;background:linear-gradient(90deg,transparent,rgba(232,200,74,0.25),transparent);margin:10px 30px 0;position:relative;z-index:2}
                    .passe-front .slogan{text-align:center;font-size:8px;font-weight:700;color:rgba(232,200,74,0.5);letter-spacing:5px;text-transform:uppercase;padding:6px 0 12px;position:relative;z-index:2}
                    .passe-front .foto-area{width:110px;height:110px;border-radius:50%;margin:0 auto 4px;border:3px solid rgba(232,200,74,0.5);overflow:hidden;background:rgba(255,255,255,0.04);display:flex;align-items:center;justify-content:center;font-size:34px;font-weight:700;color:rgba(255,255,255,0.15);box-shadow:0 0 40px rgba(232,200,74,0.06);position:relative;z-index:2}
                    .passe-front .foto-area img{width:100%;height:100%;object-fit:cover}
                    .passe-front .nome{font-size:20px;font-weight:800;color:#fff;text-align:center;text-transform:uppercase;letter-spacing:1.5px;position:relative;z-index:2;line-height:1.2}
                    .passe-front .cargo_text{font-size:14px;color:#e8c84a;font-weight:600;text-align:center;letter-spacing:1px;position:relative;z-index:2;margin-bottom:4px}
                    .passe-front .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:8px 22px 14px;position:relative;z-index:2}
                    .passe-front .info-grid .item{background:rgba(255,255,255,0.04);border-radius:10px;padding:8px 10px;text-align:center;border:1px solid rgba(255,255,255,0.04)}
                    .passe-front .info-grid .item .label{font-size:7px;text-transform:uppercase;color:rgba(255,255,255,0.3);letter-spacing:1px;font-weight:600}
                    .passe-front .info-grid .item .value{font-size:14px;font-weight:700;color:#fff;margin-top:1px;font-family:'Courier New',monospace;letter-spacing:.5px}
                    .passe-front .info-grid .item .value.gold{color:#e8c84a}
                    .passe-front .assinatura{padding:6px 25px 0;display:flex;justify-content:space-between;align-items:center;position:relative;z-index:2;border-top:1px solid rgba(255,255,255,0.04);margin:0 22px;padding-top:10px}
                    .passe-front .assinatura .label{font-size:7px;text-transform:uppercase;color:rgba(255,255,255,0.2);letter-spacing:1px}
                    .passe-front .assinatura .line{flex:1;border-bottom:1px solid rgba(255,255,255,0.08);margin:0 12px}
                    .passe-front .assinatura .cargo-ass{font-size:7px;color:rgba(255,255,255,0.25);text-transform:uppercase;letter-spacing:1px}
                    .passe-front .footer{text-align:center;padding:10px 0 16px;font-size:10px;font-weight:700;color:rgba(255,255,255,0.15);letter-spacing:4px;text-transform:uppercase;position:relative;z-index:2;background:rgba(0,0,0,0.15);margin-top:4px}
                    .passe-front .footer .gold{color:rgba(232,200,74,0.3)}
                    .passe-back{background:linear-gradient(135deg,#f8fafc,#f1f3f5);padding:20px 22px;border-top:3px solid #e8c84a;display:flex;gap:18px;align-items:flex-start;min-height:180px}
                    .passe-back .qr-area{flex-shrink:0;text-align:center;background:#fff;padding:10px;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,0.06);border:1px solid rgba(0,0,0,0.04)}
                    .passe-back .qr-area img{width:110px;height:110px;display:block;border-radius:4px}
                    .passe-back .qr-area .id{font-size:9px;font-weight:700;color:#1a2332;font-family:'Courier New',monospace;letter-spacing:1.5px;margin-top:5px}
                    .passe-back .regras{flex:1;color:#374151;font-size:8.5px;line-height:1.7}
                    .passe-back .regras .title{font-weight:700;font-size:10px;color:#1a2332;text-transform:uppercase;letter-spacing:1.5px;border-bottom:2px solid rgba(232,200,74,0.2);padding-bottom:5px;margin-bottom:6px;display:flex;align-items:center;gap:8px}
                    .passe-back .regras .title::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,rgba(232,200,74,0.2),transparent)}
                    .passe-back .regras ul{list-style:none;padding:0}
                    .passe-back .regras ul li{padding:2px 0;padding-left:14px;position:relative}
                    .passe-back .regras ul li::before{content:'◆';position:absolute;left:0;color:#e8c84a;font-size:6px;top:4px;opacity:.6}
                    .passe-back .contato{margin-top:8px;font-size:7.5px;color:#6b7280;border-top:1px solid rgba(0,0,0,0.04);padding-top:7px;text-align:center;line-height:1.9}
                    .passe-back .contato strong{color:#1a2332}
                    .passe-barcode{background:#fff;padding:8px 20px;text-align:center;border-top:1px solid rgba(0,0,0,0.04);background:linear-gradient(135deg,#fafafa,#f5f5f5)}
                    .passe-barcode img{max-width:200px;height:auto}
                    @media print{body{background:#fff}.passe-container{box-shadow:none!important;border:2px solid #c9a84c;border-radius:12px}.passe-front{min-height:480px}.passe-back{min-height:160px}}
                    @media(max-width:768px){.passe-back{flex-direction:column;align-items:center;text-align:center}.passe-front .info-grid{grid-template-columns:1fr}.passe-front .assinatura{flex-direction:column;gap:6px}.passe-front .foto-area{width:90px;height:90px}.passe-back .qr-area img{width:90px;height:90px}}
                </style>
                </head>
                <body>
                    <div class="passe-wrapper">${conteudo}</div>
                    <script>window.onload=function(){setTimeout(function(){window.print()},500)}<\/script>
                </body>
                </html>
            `);
            win.document.close();
        }

        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) {
                sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('active');
            }
        }
        function closeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) {
                sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('active');
            }
        }
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) closeSidebar();
        });
        console.log('🪪 Passes Premium carregado com sucesso!');
    </script>
</body>
</html>