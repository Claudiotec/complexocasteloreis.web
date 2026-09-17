<?php
// ============================================
// BALANÇO PATRIMONIAL
// ============================================

require_once '../../config/app_modes.php';
require_once '../../config/database.php';
require_once 'config.php';

// ===== CORREÇÃO DA SESSÃO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pdo = conectarBanco();

$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');

// ===== FUNÇÃO PARA VERIFICAR SE TABELA EXISTE =====
function tabelaExiste($pdo, $tabela) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Verificar se as tabelas existem
$tabelasExistem = tabelaExiste($pdo, 'lancamentos_contabeis') && 
                  tabelaExiste($pdo, 'plano_contas') && 
                  tabelaExiste($pdo, 'lancamentos_itens') &&
                  tabelaExiste($pdo, 'saldo_contas');

if ($tabelasExistem) {
    // Buscar dados do período
    $stmt = $pdo->prepare("
        SELECT 
            pc.codigo,
            pc.nome,
            pc.tipo,
            pc.nivel,
            sc.saldo_final,
            sc.saldo_debito,
            sc.saldo_credito
        FROM plano_contas pc
        LEFT JOIN saldo_contas sc ON pc.id = sc.conta_id AND sc.ano = ? AND sc.mes = ?
        WHERE pc.tipo IN ('ATIVO', 'PASSIVO', 'PATRIMONIO_LIQUIDO')
        AND pc.status = 'ativo'
        ORDER BY pc.codigo
    ");
    $stmt->execute([$ano, $mes]);
    $contas = $stmt->fetchAll();

    // Separar por tipo
    $ativo = array_filter($contas, fn($c) => $c['tipo'] == 'ATIVO');
    $passivo = array_filter($contas, fn($c) => $c['tipo'] == 'PASSIVO');
    $pl = array_filter($contas, fn($c) => $c['tipo'] == 'PATRIMONIO_LIQUIDO');

    // Calcular totais
    $totalAtivo = array_sum(array_column($ativo, 'saldo_final'));
    $totalPassivo = array_sum(array_column($passivo, 'saldo_final'));
    $totalPL = array_sum(array_column($pl, 'saldo_final'));
    $totalPassivoPL = $totalPassivo + $totalPL;

    // Verificar se bate
    $diferenca = $totalAtivo - $totalPassivoPL;
} else {
    $contas = [];
    $ativo = [];
    $passivo = [];
    $pl = [];
    $totalAtivo = 0;
    $totalPassivo = 0;
    $totalPL = 0;
    $totalPassivoPL = 0;
    $diferenca = 0;
}

// ===== NÃO INCLUIR O HEADER AQUI - O SISTEMA PRINCIPAL JÁ INCLUIU =====
// include_once '../../includes/header.php';  <-- REMOVER!
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balanço Patrimonial - SoftGest</title>
    <style>
        /* ============================================
           ESTILOS DO MÓDULO - SEM SIDEBAR DUPLICADO
           ============================================ */
        .contabilidade-module {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 0;
        }
        
        .module-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            box-sizing: border-box;
            padding: 0;
        }
        
        .module-header {
            background: linear-gradient(135deg, #1a2332, #2c3e50);
            color: white;
            padding: 18px 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            width: 100%;
            box-sizing: border-box;
        }
        .module-header h1 { font-size: 24px; margin: 0; }
        .module-header h1 span { color: #f5d76e; }
        .module-header .subtitle { opacity: 0.8; font-size: 13px; margin-top: 3px; }
        
        .module-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .module-actions a {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            white-space: nowrap;
        }
        .module-actions a:hover { background: rgba(255,255,255,0.2); transform: translateY(-2px); }
        .module-actions a.primary { background: #f5d76e; color: #1a2332; border: none; }
        .module-actions a.primary:hover { background: #e6c753; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 20px;
        }
        .card h3 { color: #1a2332; margin-bottom: 12px; font-size: 15px; }
        
        .filtros {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        .filtros .form-group { margin: 0; }
        .filtros label { font-size: 13px; color: #94a3b8; display: block; margin-bottom: 3px; }
        .filtros select, .filtros input {
            padding: 8px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }
        .filtros button {
            padding: 8px 20px;
            background: #f5d76e;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            color: #1a2332;
        }
        .filtros button:hover { background: #e6c753; }
        
        .balanco-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-top: 15px;
        }
        @media (max-width: 992px) { .balanco-grid { grid-template-columns: 1fr; } }
        
        .coluna h3 {
            font-size: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eef2f7;
            margin-bottom: 15px;
        }
        .coluna h3 .badge { font-size: 12px; font-weight: 400; color: #94a3b8; }
        
        .conta-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .conta-item .codigo { color: #94a3b8; font-size: 11px; margin-right: 8px; }
        .conta-item .valor { font-weight: 600; }
        .conta-item .valor.positivo { color: #2ecc71; }
        .conta-item .valor.negativo { color: #e74c3c; }
        
        .conta-total {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-weight: 700;
            font-size: 15px;
            border-top: 2px solid #1a2332;
            margin-top: 8px;
        }
        .conta-total .valor { font-size: 17px; }
        .conta-total .valor.positivo { color: #2ecc71; }
        .conta-total .valor.negativo { color: #e74c3c; }
        
        .resultado {
            margin-top: 15px;
            padding: 15px 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 700;
            font-size: 16px;
        }
        .resultado.ok { background: #d1fae5; color: #065f46; }
        .resultado.erro { background: #fee2e2; color: #991b1b; }
        
        .periodo-info {
            text-align: center;
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .indicadores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .indicador-item {
            text-align: center;
            padding: 10px;
        }
        .indicador-item .label { font-size: 13px; color: #94a3b8; }
        .indicador-item .value { font-size: 22px; font-weight: 700; color: #c9a84c; }
        
        .footer-info {
            text-align: center;
            padding: 15px 0 5px;
            color: #94a3b8;
            font-size: 13px;
            border-top: 1px solid #eef2f7;
            margin-top: 15px;
            width: 100%;
        }
        .footer-info strong { color: #c9a84c; }

        .alert-install {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
        }
        .alert-install .icon { font-size: 48px; }
        .alert-install h2 { color: #92400e; margin: 10px 0; }
        .alert-install p { color: #78350f; }
        .alert-install .btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 30px;
            background: #f59e0b;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
        }
        .alert-install .btn:hover { background: #d97706; }

        @media (max-width: 768px) {
            .module-header { padding: 15px; flex-direction: column; text-align: center; }
            .module-actions { justify-content: center; }
            .balanco-grid { gap: 15px; }
            .conta-item { font-size: 12px; }
            .conta-total { font-size: 13px; }
            .conta-total .valor { font-size: 15px; }
        }
        
        @media (max-width: 480px) {
            .module-header h1 { font-size: 20px; }
            .filtros { flex-direction: column; align-items: stretch; }
            .filtros button { width: 100%; }
        }
    </style>
</head>
<body>

<div class="contabilidade-module">
    <div class="module-container">
        <!-- Header -->
        <div class="module-header">
            <div>
                <h1>⚖️ <span>Balanço Patrimonial</span></h1>
                <div class="subtitle">Posição financeira da empresa</div>
            </div>
            <div class="module-actions">
                <a href="index.php">📊 Dashboard</a>
                <a href="dre.php">📈 DRE</a>
                <a href="javascript:window.print()" class="primary">🖨️ Imprimir</a>
            </div>
        </div>

        <?php if (!$tabelasExistem): ?>
        <div class="alert-install">
            <div class="icon">📦</div>
            <h2>Módulo não instalado</h2>
            <p>As tabelas de contabilidade não foram encontradas.</p>
            <a href="install.php" class="btn">🔧 Instalar Módulo</a>
        </div>
        <?php else: ?>

        <!-- Filtros -->
        <div class="card">
            <form method="GET" action="" class="filtros">
                <div class="form-group">
                    <label>Ano</label>
                    <select name="ano">
                        <?php for ($a = date('Y')-2; $a <= date('Y'); $a++): ?>
                        <option value="<?= $a ?>" <?= $a == $ano ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mês</label>
                    <select name="mes">
                        <?php 
                        $meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
                        for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= $meses[$m-1] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit">Filtrar</button>
            </form>
        </div>

        <div class="periodo-info">
            📅 Período: <?= $meses[$mes-1] ?? 'Setembro' ?> de <?= $ano ?>
        </div>

        <div class="balanco-grid">
            <!-- ATIVO -->
            <div class="coluna">
                <h3>📦 ATIVO <span class="badge">Bens e Direitos</span></h3>
                <?php foreach ($ativo as $conta): ?>
                <div class="conta-item">
                    <span>
                        <span class="codigo"><?= $conta['codigo'] ?></span>
                        <?= htmlspecialchars($conta['nome']) ?>
                    </span>
                    <span class="valor positivo">
                        Kz <?= number_format($conta['saldo_final'] ?? 0, 2, ',', '.') ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($ativo)): ?>
                <div style="color: #94a3b8; padding: 10px 0;">Nenhuma conta de ativo registrada</div>
                <?php endif; ?>
                <div class="conta-total">
                    <span>TOTAL DO ATIVO</span>
                    <span class="valor positivo">Kz <?= number_format($totalAtivo, 2, ',', '.') ?></span>
                </div>
            </div>

            <!-- PASSIVO + PL -->
            <div class="coluna">
                <h3>📋 PASSIVO + PATRIMÔNIO LÍQUIDO</h3>
                <h4 style="margin: 10px 0 5px; color: #e74c3c; font-size: 14px;">PASSIVO (Obrigações)</h4>
                <?php foreach ($passivo as $conta): ?>
                <div class="conta-item">
                    <span>
                        <span class="codigo"><?= $conta['codigo'] ?></span>
                        <?= htmlspecialchars($conta['nome']) ?>
                    </span>
                    <span class="valor negativo">
                        Kz <?= number_format($conta['saldo_final'] ?? 0, 2, ',', '.') ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($passivo)): ?>
                <div style="color: #94a3b8; padding: 10px 0;">Nenhuma conta de passivo registrada</div>
                <?php endif; ?>
                <div class="conta-total" style="border-color: #e74c3c;">
                    <span>TOTAL DO PASSIVO</span>
                    <span class="valor negativo">Kz <?= number_format($totalPassivo, 2, ',', '.') ?></span>
                </div>

                <h4 style="margin: 15px 0 5px; color: #c9a84c; font-size: 14px;">PATRIMÔNIO LÍQUIDO</h4>
                <?php foreach ($pl as $conta): ?>
                <div class="conta-item">
                    <span>
                        <span class="codigo"><?= $conta['codigo'] ?></span>
                        <?= htmlspecialchars($conta['nome']) ?>
                    </span>
                    <span class="valor">
                        Kz <?= number_format($conta['saldo_final'] ?? 0, 2, ',', '.') ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($pl)): ?>
                <div style="color: #94a3b8; padding: 10px 0;">Nenhuma conta de patrimônio líquido registrada</div>
                <?php endif; ?>
                <div class="conta-total" style="border-color: #c9a84c;">
                    <span>TOTAL DO PATRIMÔNIO LÍQUIDO</span>
                    <span class="valor" style="color: #c9a84c;">Kz <?= number_format($totalPL, 2, ',', '.') ?></span>
                </div>

                <div class="conta-total" style="border-color: #1a2332; background: #f8fafc; margin-top: 15px;">
                    <span>TOTAL PASSIVO + PL</span>
                    <span class="valor">Kz <?= number_format($totalPassivoPL, 2, ',', '.') ?></span>
                </div>
            </div>
        </div>

        <!-- Verificação -->
        <div class="resultado <?= abs($diferenca) < 0.01 ? 'ok' : 'erro' ?>">
            <?php if (abs($diferenca) < 0.01): ?>
                ✅ Balanço Patrimonial FECHADO! (Ativo = Passivo + PL)
            <?php else: ?>
                ⚠️ Balanço NÃO FECHA! Diferença: Kz <?= number_format($diferenca, 2, ',', '.') ?>
                <br><small style="font-weight: 400;">Verifique os lançamentos contábeis</small>
            <?php endif; ?>
        </div>

        <!-- Indicadores -->
        <div class="card">
            <h3>📊 Indicadores Financeiros</h3>
            <div class="indicadores-grid">
                <div class="indicador-item">
                    <div class="label">Liquidez Geral</div>
                    <div class="value">
                        <?= $totalPassivo > 0 ? number_format($totalAtivo / $totalPassivo, 2, ',', '.') : 'N/A' ?>
                    </div>
                </div>
                <div class="indicador-item">
                    <div class="label">Endividamento</div>
                    <div class="value">
                        <?= $totalAtivo > 0 ? number_format(($totalPassivo / $totalAtivo) * 100, 1, ',', '.') . '%' : 'N/A' ?>
                    </div>
                </div>
                <div class="indicador-item">
                    <div class="label">PL / Ativo</div>
                    <div class="value">
                        <?= $totalAtivo > 0 ? number_format(($totalPL / $totalAtivo) * 100, 1, ',', '.') . '%' : 'N/A' ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer-info">
            <p>© <?= date('Y') ?> <strong>SoftGest</strong> - Módulo de Contabilidade v<?= CONTABILIDADE_VERSION ?></p>
        </div>
    </div>
</div>

</body>
</html>