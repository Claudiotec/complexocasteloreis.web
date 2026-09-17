<?php
// funcionarios.php - Lista de funcionários da tabela funcionarios
require_once '../../config/database.php';

// =============================================
// CONFIGURAÇÃO
// =============================================
date_default_timezone_set('Africa/Luanda');

// =============================================
// BUSCAR FUNCIONÁRIOS DA TABELA funcionarios
// =============================================
$stmt = $pdo->query("SELECT * FROM funcionarios ORDER BY nome");
$funcionarios = $stmt->fetchAll();

// =============================================
// CONTAGEM POR STATUS
// =============================================
$stmtAtivos = $pdo->query("SELECT COUNT(*) as total FROM funcionarios WHERE status = 'ativo'");
$totalAtivos = $stmtAtivos->fetch()['total'];

$stmtInativos = $pdo->query("SELECT COUNT(*) as total FROM funcionarios WHERE status = 'inativo'");
$totalInativos = $stmtInativos->fetch()['total'];

$stmtFerias = $pdo->query("SELECT COUNT(*) as total FROM funcionarios WHERE status = 'ferias'");
$totalFerias = $stmtFerias->fetch()['total'];

$totalFuncionarios = count($funcionarios);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funcionários - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f0f4f8; 
            color: #1a2332;
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 20px;
            max-width: calc(100% - 280px);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .page-title { font-size: 28px; font-weight: 800; color: #1a2332; }
        .page-title span { color: #c9a84c; }
        .page-subtitle { color: #64748b; font-size: 14px; margin-top: 4px; }
        
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197,165,50,0.3); }
        
        .btn-outline {
            background: transparent;
            color: #c9a84c;
            padding: 10px 24px;
            border: 2px solid #c9a84c;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-outline:hover { background: rgba(197,165,50,0.1); transform: translateY(-2px); }
        
        .btn-small {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            margin: 2px;
        }
        .btn-small.edit { background: #f59e0b; color: white; }
        .btn-small.edit:hover { background: #d97706; }
        .btn-small.folha { background: #8b5cf6; color: white; }
        .btn-small.folha:hover { background: #7c3aed; }
        .btn-small.delete { background: #ef4444; color: white; }
        .btn-small.delete:hover { background: #dc2626; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 18px 20px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .stat-card .number { font-size: 28px; font-weight: 800; color: #1a2332; }
        .stat-card .label { font-size: 13px; color: #94a3b8; margin-top: 4px; }
        .stat-card .icon { font-size: 24px; display: block; margin-bottom: 8px; }
        .stat-card.ativo { border-left: 4px solid #2ecc71; }
        .stat-card.ferias { border-left: 4px solid #f39c12; }
        .stat-card.inativo { border-left: 4px solid #e74c3c; }
        .stat-card.total { border-left: 4px solid #3498db; }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eef2f7;
            overflow-x: auto;
        }
        .table-container table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table-container thead { background: #f8fafc; }
        .table-container th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .table-container td { padding: 10px 16px; border-bottom: 1px solid #f1f5f9; }
        .table-container tr:hover { background: #f8fafc; }
        
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-badge.ativo { background: #d1fae5; color: #065f46; }
        .status-badge.ferias { background: #fef3c7; color: #92400e; }
        .status-badge.inativo { background: #fee2e2; color: #991b1b; }
        
        .no-data {
            text-align: center; 
            padding: 40px; 
            color: #999;
        }
        .no-data .icon { font-size: 48px; display: block; margin-bottom: 10px; }
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; max-width: 100%; padding: 15px; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .actions { flex-direction: column; }
            .actions a { width: 100%; justify-content: center; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">👤 <span>Funcionários</span></h1>
                    <p class="page-subtitle">Gestão completa de colaboradores</p>
                </div>
                <div>
                    <a href="index.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Voltar ao RH</a>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="actions">
                <a href="add_funcionario.php" class="btn-gold"><i class="fas fa-user-plus"></i> Novo Funcionário</a>
                <a href="presenca_qr.php" class="btn-outline"><i class="fas fa-qrcode"></i> Presença QR</a>
                <a href="assiduidade.php" class="btn-outline"><i class="fas fa-chart-bar"></i> Assiduidade</a>
            </div>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <span class="icon">👥</span>
                    <div class="number"><?= $totalFuncionarios ?></div>
                    <div class="label">Total Funcionários</div>
                </div>
                <div class="stat-card ativo">
                    <span class="icon">✅</span>
                    <div class="number"><?= $totalAtivos ?></div>
                    <div class="label">Ativos</div>
                </div>
                <div class="stat-card ferias">
                    <span class="icon">🏖️</span>
                    <div class="number"><?= $totalFerias ?></div>
                    <div class="label">Férias</div>
                </div>
                <div class="stat-card inativo">
                    <span class="icon">⛔</span>
                    <div class="number"><?= $totalInativos ?></div>
                    <div class="label">Inativos</div>
                </div>
            </div>
            
            <!-- Tabela -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Cargo</th>
                            <th>Departamento</th>
                            <th>Salário</th>
                            <th>Status</th>
                            <th>Admissão</th>
                            <th style="text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($funcionarios) > 0): ?>
                            <?php foreach($funcionarios as $func): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($func['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($func['cargo'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($func['departamento'] ?? '—') ?></td>
                                <td>R$ <?= number_format($func['salario'] ?? 0, 2, ',', '.') ?></td>
                                <td>
                                    <span class="status-badge <?= $func['status'] ?? 'ativo' ?>">
                                        <?= ucfirst($func['status'] ?? 'ativo') ?>
                                    </span>
                                </td>
                                <td><?= isset($func['data_admissao']) && $func['data_admissao'] != '1969-12-31' ? date('d/m/Y', strtotime($func['data_admissao'])) : '—' ?></td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <a href="edit_funcionario.php?id=<?= $func['id'] ?>" class="btn-small edit">✏️ Editar</a>
                                    <a href="folha.php?funcionario=<?= $func['id'] ?>" class="btn-small folha">💰 Folha</a>
                                    <a href="presenca_qr.php?gerar_qr=1&funcionario=<?= $func['id'] ?>" class="btn-small" style="background: #10b981; color: white;">📱 QR</a>
                                    <a href="excluir_funcionario.php?id=<?= $func['id'] ?>" class="btn-small delete" onclick="return confirm('Tem certeza que deseja excluir este funcionário?')">🗑️</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="no-data">
                                        <span class="icon">👤</span>
                                        <p>Nenhum funcionário cadastrado.</p>
                                        <p style="margin-top: 10px;">
                                            <a href="add_funcionario.php" class="btn-gold">➕ Adicionar Funcionário</a>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Total de registros -->
            <div style="margin-top: 15px; text-align: right; font-size: 13px; color: #94a3b8;">
                Total: <strong><?= count($funcionarios) ?></strong> funcionários
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>