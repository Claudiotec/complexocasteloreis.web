<?php
// ============================================
// RAZÃO ANALÍTICO
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
$usuario_perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
$isAdmin = ($usuario_perfil == 'admin');

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
                  tabelaExiste($pdo, 'lancamentos_itens');

// Buscar contas para o filtro
$contas = [];
if ($tabelasExistem) {
    $contas = $pdo->query("SELECT id, codigo, nome, tipo FROM plano_contas WHERE status = 'ativo' ORDER BY codigo")->fetchAll();
}

$conta_id = $_GET['conta_id'] ?? 0;
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

$movimentos = [];
$saldo_inicial = 0;
$total_debito = 0;
$total_credito = 0;
$conta_selecionada = null;

if ($tabelasExistem && $conta_id > 0) {
    // Buscar dados da conta
    $stmt = $pdo->prepare("SELECT * FROM plano_contas WHERE id = ?");
    $stmt->execute([$conta_id]);
    $conta_selecionada = $stmt->fetch();
    
    if ($conta_selecionada) {
        // Buscar movimentos do período
        $stmt = $pdo->prepare("
            SELECT 
                l.data_lancamento,
                l.numero_lancamento,
                l.historico,
                l.tipo_documento,
                li.tipo_movimento,
                li.valor,
                li.historico_item
            FROM lancamentos_itens li
            JOIN lancamentos_contabeis l ON li.lancamento_id = l.id
            WHERE li.conta_id = ?
            AND l.status = 'confirmado'
            AND l.data_lancamento BETWEEN ? AND ?
            ORDER BY l.data_lancamento, l.id
        ");
        $stmt->execute([$conta_id, $data_inicio, $data_fim]);
        $movimentos = $stmt->fetchAll();
        
        // Calcular totais
        foreach ($movimentos as $mov) {
            if ($mov['tipo_movimento'] == 'DEBITO') {
                $total_debito += $mov['valor'];
            } else {
                $total_credito += $mov['valor'];
            }
        }
        
        // Calcular saldo inicial (antes do período)
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN tipo_movimento = 'DEBITO' THEN valor ELSE 0 END) as debitos,
                SUM(CASE WHEN tipo_movimento = 'CREDITO' THEN valor ELSE 0 END) as creditos
            FROM lancamentos_itens li
            JOIN lancamentos_contabeis l ON li.lancamento_id = l.id
            WHERE li.conta_id = ?
            AND l.status = 'confirmado'
            AND l.data_lancamento < ?
        ");
        $stmt->execute([$conta_id, $data_inicio]);
        $saldo_anterior = $stmt->fetch();
        
        if ($conta_selecionada['natureza'] == 'DEVEDORA') {
            $saldo_inicial = ($saldo_anterior['debitos'] ?? 0) - ($saldo_anterior['creditos'] ?? 0);
        } else {
            $saldo_inicial = ($saldo_anterior['creditos'] ?? 0) - ($saldo_anterior['debitos'] ?? 0);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Razão Analítico - SoftGest</title>
    <style>
        .main-content .content-area {
            padding: 20px 25px !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        
        .contabilidade-module {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
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
            padding: 20px 25px;
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
        
        .table-responsive { 
            overflow-x: auto; 
            width: 100%; 
            -webkit-overflow-scrolling: touch;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 13px; 
            min-width: 700px;
        }
        th { 
            text-align: left; 
            padding: 10px 12px; 
            font-size: 11px; 
            color: #94a3b8; 
            text-transform: uppercase; 
            border-bottom: 2px solid #eef2f7; 
        }
        td { 
            padding: 10px 12px; 
            border-bottom: 1px solid #f1f5f9; 
        }
        tr:hover { background: #f8fafc; }
        
        .debito { color: #2ecc71; font-weight: 600; }
        .credito { color: #e74c3c; font-weight: 600; }
        .saldo { font-weight: 700; }
        .saldo.positivo { color: #2ecc71; }
        .saldo.negativo { color: #e74c3c; }
        
        .total-row {
            font-weight: 700;
            background: #f8fafc;
            border-top: 2px solid #1a2332;
        }
        .total-row td { padding: 12px; }
        
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
        
        .info-conta {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .info-conta .item .label { font-size: 12px; color: #94a3b8; }
        .info-conta .item .value { font-size: 16px; font-weight: 600; color: #1a2332; }
        
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
        
        @media (max-width: 768px) {
            .module-header { padding: 15px; flex-direction: column; text-align: center; }
            .module-actions { justify-content: center; }
            .filtros { flex-direction: column; align-items: stretch; }
            .filtros button { width: 100%; }
            .info-conta { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="contabilidade-module">
    <div class="module-container">
        <!-- Header -->
        <div class="module-header">
            <div>
                <h1>📚 <span>Razão Analítico</span></h1>
                <div class="subtitle">Movimentação detalhada por conta</div>
            </div>
            <div class="module-actions">
                <a href="index.php">📊 Dashboard</a>
                <a href="diario.php">📖 Diário</a>
                <a href="balanco.php">⚖️ Balanço</a>
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
                    <label>Conta</label>
                    <select name="conta_id" required>
                        <option value="">Selecione uma conta</option>
                        <?php foreach ($contas as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $conta_id ? 'selected' : '' ?>>
                            <?= $c['codigo'] ?> - <?= htmlspecialchars($c['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Data Início</label>
                    <input type="date" name="data_inicio" value="<?= $data_inicio ?>">
                </div>
                <div class="form-group">
                    <label>Data Fim</label>
                    <input type="date" name="data_fim" value="<?= $data_fim ?>">
                </div>
                <button type="submit">Filtrar</button>
            </form>
        </div>

        <?php if ($conta_selecionada && $movimentos): ?>
        <!-- Informações da Conta -->
        <div class="info-conta">
            <div class="item">
                <div class="label">Conta</div>
                <div class="value"><?= $conta_selecionada['codigo'] ?> - <?= htmlspecialchars($conta_selecionada['nome']) ?></div>
            </div>
            <div class="item">
                <div class="label">Tipo</div>
                <div class="value"><?= $conta_selecionada['tipo'] ?></div>
            </div>
            <div class="item">
                <div class="label">Natureza</div>
                <div class="value"><?= $conta_selecionada['natureza'] ?></div>
            </div>
            <div class="item">
                <div class="label">Saldo Inicial</div>
                <div class="value <?= $saldo_inicial >= 0 ? 'positivo' : 'negativo' ?>">
                    Kz <?= number_format($saldo_inicial, 2, ',', '.') ?>
                </div>
            </div>
        </div>

        <!-- Movimentos -->
        <div class="card">
            <h3>📋 Movimentos do Período</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Nº Lançamento</th>
                            <th>Histórico</th>
                            <th>Tipo</th>
                            <th style="text-align: right;">Débito</th>
                            <th style="text-align: right;">Crédito</th>
                            <th style="text-align: right;">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $saldo_atual = $saldo_inicial;
                        foreach ($movimentos as $mov):
                            if ($mov['tipo_movimento'] == 'DEBITO') {
                                $saldo_atual += $mov['valor'];
                            } else {
                                $saldo_atual -= $mov['valor'];
                            }
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($mov['data_lancamento'])) ?></td>
                            <td><strong><?= htmlspecialchars($mov['numero_lancamento']) ?></strong></td>
                            <td><?= htmlspecialchars($mov['historico']) ?></td>
                            <td><?= $mov['tipo_documento'] ?></td>
                            <td style="text-align: right;">
                                <?php if ($mov['tipo_movimento'] == 'DEBITO'): ?>
                                <span class="debito">Kz <?= number_format($mov['valor'], 2, ',', '.') ?></span>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($mov['tipo_movimento'] == 'CREDITO'): ?>
                                <span class="credito">Kz <?= number_format($mov['valor'], 2, ',', '.') ?></span>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <span class="saldo <?= $saldo_atual >= 0 ? 'positivo' : 'negativo' ?>">
                                    Kz <?= number_format($saldo_atual, 2, ',', '.') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="4" style="text-align: right;">TOTAIS</td>
                            <td style="text-align: right; color: #2ecc71;">
                                Kz <?= number_format($total_debito, 2, ',', '.') ?>
                            </td>
                            <td style="text-align: right; color: #e74c3c;">
                                Kz <?= number_format($total_credito, 2, ',', '.') ?>
                            </td>
                            <td style="text-align: right;">
                                <span class="saldo <?= ($saldo_inicial + $total_debito - $total_credito) >= 0 ? 'positivo' : 'negativo' ?>">
                                    Kz <?= number_format($saldo_inicial + $total_debito - $total_credito, 2, ',', '.') ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php elseif ($conta_selecionada && empty($movimentos)): ?>
        <div class="card" style="text-align: center; padding: 40px; color: #94a3b8;">
            <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
            <h3>Nenhum movimento encontrado</h3>
            <p>Não há lançamentos para esta conta no período selecionado.</p>
        </div>
        <?php elseif (!$conta_selecionada): ?>
        <div class="card" style="text-align: center; padding: 40px; color: #94a3b8;">
            <div style="font-size: 48px; margin-bottom: 10px;">🔍</div>
            <h3>Selecione uma conta</h3>
            <p>Escolha uma conta no filtro acima para visualizar os movimentos.</p>
        </div>
        <?php endif; ?>

        <?php endif; ?>

        <div class="footer-info">
            <p>© <?= date('Y') ?> <strong>SoftGest</strong> - Módulo de Contabilidade v<?= CONTABILIDADE_VERSION ?></p>
        </div>
    </div>
</div>

</body>
</html>