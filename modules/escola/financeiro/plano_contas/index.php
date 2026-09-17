<?php
// ============================================
// modules/escola/financeiro/plano_contas/index.php
// Plano de Contas - Visualização e Gestão
// ============================================

// Carregar configurações
require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão (apenas admin)
if ($_SESSION['usuario_perfil'] !== 'admin') {
    header('Location: ' . SITE_URL);
    exit;
}

try {
    $pdo = conectarBanco();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// ============================================
// FUNÇÃO PARA CRIAR PLANO DE CONTAS PADRÃO
// ============================================
function criarPlanoContasPadrao($pdo) {
    $planos = [
        // ATIVO
        ['codigo' => '1', 'nome' => 'ATIVO', 'tipo' => 'ativo', 'nivel' => 1],
        ['codigo' => '1.1', 'nome' => 'Ativo Circulante', 'tipo' => 'ativo', 'nivel' => 2],
        ['codigo' => '1.1.1', 'nome' => 'Caixa', 'tipo' => 'ativo', 'nivel' => 3],
        ['codigo' => '1.1.2', 'nome' => 'Bancos', 'tipo' => 'ativo', 'nivel' => 3],
        ['codigo' => '1.1.3', 'nome' => 'Aplicações Financeiras', 'tipo' => 'ativo', 'nivel' => 3],
        ['codigo' => '1.1.4', 'nome' => 'Contas a Receber', 'tipo' => 'ativo', 'nivel' => 3],
        ['codigo' => '1.1.5', 'nome' => 'Estoques', 'tipo' => 'ativo', 'nivel' => 3],
        ['codigo' => '1.2', 'nome' => 'Ativo Não Circulante', 'tipo' => 'ativo', 'nivel' => 2],
        ['codigo' => '1.2.1', 'nome' => 'Imobilizado', 'tipo' => 'ativo', 'nivel' => 3],
        ['codigo' => '1.2.2', 'nome' => 'Intangível', 'tipo' => 'ativo', 'nivel' => 3],
        
        // PASSIVO
        ['codigo' => '2', 'nome' => 'PASSIVO', 'tipo' => 'passivo', 'nivel' => 1],
        ['codigo' => '2.1', 'nome' => 'Passivo Circulante', 'tipo' => 'passivo', 'nivel' => 2],
        ['codigo' => '2.1.1', 'nome' => 'Fornecedores', 'tipo' => 'passivo', 'nivel' => 3],
        ['codigo' => '2.1.2', 'nome' => 'Obrigações Trabalhistas', 'tipo' => 'passivo', 'nivel' => 3],
        ['codigo' => '2.1.3', 'nome' => 'Obrigações Tributárias', 'tipo' => 'passivo', 'nivel' => 3],
        ['codigo' => '2.1.4', 'nome' => 'Contas a Pagar', 'tipo' => 'passivo', 'nivel' => 3],
        ['codigo' => '2.2', 'nome' => 'Passivo Não Circulante', 'tipo' => 'passivo', 'nivel' => 2],
        ['codigo' => '2.2.1', 'nome' => 'Financiamentos', 'tipo' => 'passivo', 'nivel' => 3],
        ['codigo' => '2.3', 'nome' => 'Patrimônio Líquido', 'tipo' => 'passivo', 'nivel' => 2],
        ['codigo' => '2.3.1', 'nome' => 'Capital Social', 'tipo' => 'passivo', 'nivel' => 3],
        ['codigo' => '2.3.2', 'nome' => 'Reservas', 'tipo' => 'passivo', 'nivel' => 3],
        
        // RECEITA
        ['codigo' => '3', 'nome' => 'RECEITA', 'tipo' => 'receita', 'nivel' => 1],
        ['codigo' => '3.1', 'nome' => 'Receita Operacional', 'tipo' => 'receita', 'nivel' => 2],
        ['codigo' => '3.1.1', 'nome' => 'Mensalidades', 'tipo' => 'receita', 'nivel' => 3],
        ['codigo' => '3.1.2', 'nome' => 'Matrículas', 'tipo' => 'receita', 'nivel' => 3],
        ['codigo' => '3.1.3', 'nome' => 'Emolumentos', 'tipo' => 'receita', 'nivel' => 3],
        ['codigo' => '3.1.4', 'nome' => 'Taxas Diversas', 'tipo' => 'receita', 'nivel' => 3],
        ['codigo' => '3.2', 'nome' => 'Receita Não Operacional', 'tipo' => 'receita', 'nivel' => 2],
        ['codigo' => '3.2.1', 'nome' => 'Aluguéis', 'tipo' => 'receita', 'nivel' => 3],
        ['codigo' => '3.2.2', 'nome' => 'Aplicações Financeiras', 'tipo' => 'receita', 'nivel' => 3],
        
        // DESPESA
        ['codigo' => '4', 'nome' => 'DESPESA', 'tipo' => 'despesa', 'nivel' => 1],
        ['codigo' => '4.1', 'nome' => 'Despesa Operacional', 'tipo' => 'despesa', 'nivel' => 2],
        ['codigo' => '4.1.1', 'nome' => 'Salários e Encargos', 'tipo' => 'despesa', 'nivel' => 3],
        ['codigo' => '4.1.2', 'nome' => 'Material Escolar', 'tipo' => 'despesa', 'nivel' => 3],
        ['codigo' => '4.1.3', 'nome' => 'Alimentação', 'tipo' => 'despesa', 'nivel' => 3],
        ['codigo' => '4.1.4', 'nome' => 'Água e Energia', 'tipo' => 'despesa', 'nivel' => 3],
        ['codigo' => '4.1.5', 'nome' => 'Telefonia e Internet', 'tipo' => 'despesa', 'nivel' => 3],
        ['codigo' => '4.1.6', 'nome' => 'Material de Limpeza', 'tipo' => 'despesa', 'nivel' => 3],
        ['codigo' => '4.1.7', 'nome' => 'Manutenção e Reparos', 'tipo' => 'despesa', 'nivel' => 3],
        ['codigo' => '4.1.8', 'nome' => 'Publicidade e Marketing', 'tipo' => 'despesa', 'nivel' => 3],
        ['codigo' => '4.2', 'nome' => 'Despesa Não Operacional', 'tipo' => 'despesa', 'nivel' => 2],
        ['codigo' => '4.2.1', 'nome' => 'Juros e Multas', 'tipo' => 'despesa', 'nivel' => 3],
        ['codigo' => '4.2.2', 'nome' => 'Perdas e Baixas', 'tipo' => 'despesa', 'nivel' => 3],
    ];
    
    $count = 0;
    try {
        // CORRIGIDO: Desabilitar verificação de chaves estrangeiras
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Limpar contas existentes (DELETE em vez de TRUNCATE)
        $pdo->exec("DELETE FROM orcamento_itens");
        $pdo->exec("DELETE FROM orcamento");
        $pdo->exec("DELETE FROM plano_contas");
        
        // Resetar auto_increment
        $pdo->exec("ALTER TABLE plano_contas AUTO_INCREMENT = 1");
        $pdo->exec("ALTER TABLE orcamento AUTO_INCREMENT = 1");
        $pdo->exec("ALTER TABLE orcamento_itens AUTO_INCREMENT = 1");
        
        foreach ($planos as $plano) {
            $stmt = $pdo->prepare("
                INSERT INTO plano_contas (codigo, nome, tipo, nivel, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $plano['codigo'],
                $plano['nome'],
                $plano['tipo'],
                $plano['nivel']
            ]);
            $count++;
        }
        
        // Reabilitar verificação de chaves estrangeiras
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        return ['status' => 'success', 'count' => $count, 'message' => "Plano de Contas criado com $count contas"];
    } catch (Exception $e) {
        // Reabilitar verificação de chaves estrangeiras em caso de erro
        try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Exception $ex) {}
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// ============================================
// PROCESSAR AÇÕES
// ============================================
$mensagem = '';
$tipo_mensagem = '';

if (isset($_GET['acao'])) {
    if ($_GET['acao'] === 'criar_padrao') {
        $resultado = criarPlanoContasPadrao($pdo);
        if ($resultado['status'] === 'success') {
            $mensagem = "✅ " . $resultado['message'];
            $tipo_mensagem = 'success';
        } else {
            $mensagem = "❌ Erro ao recriar plano de contas: " . $resultado['message'];
            $tipo_mensagem = 'error';
        }
    }
    
    if ($_GET['acao'] === 'limpar') {
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("DELETE FROM orcamento_itens");
            $pdo->exec("DELETE FROM orcamento");
            $pdo->exec("DELETE FROM plano_contas");
            $pdo->exec("ALTER TABLE plano_contas AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE orcamento AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE orcamento_itens AUTO_INCREMENT = 1");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $mensagem = "✅ Plano de Contas limpo com sucesso!";
            $tipo_mensagem = 'success';
        } catch (Exception $e) {
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Exception $ex) {}
            $mensagem = "❌ Erro ao limpar plano de contas: " . $e->getMessage();
            $tipo_mensagem = 'error';
        }
    }
}

// ============================================
// BUSCAR DADOS DO PLANO DE CONTAS
// ============================================
$contas = [];
$totais = [
    'total' => 0,
    'ativo' => 0,
    'passivo' => 0,
    'receita' => 0,
    'despesa' => 0
];

try {
    $stmt = $pdo->query("
        SELECT * FROM plano_contas 
        ORDER BY codigo
    ");
    $contas = $stmt->fetchAll();
    
    $totais['total'] = count($contas);
    foreach ($contas as $conta) {
        if ($conta['tipo'] === 'ativo') $totais['ativo']++;
        elseif ($conta['tipo'] === 'passivo') $totais['passivo']++;
        elseif ($conta['tipo'] === 'receita') $totais['receita']++;
        elseif ($conta['tipo'] === 'despesa') $totais['despesa']++;
    }
} catch (Exception $e) {
    $contas = [];
}

// ============================================
// INCLUIR HEADER
// ============================================
include '../../includes/header_escola.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plano de Contas - Financeiro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 25px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .page-header h1 {
            font-size: 24px;
            color: #1a2332;
        }
        
        .page-header .subtitle {
            color: #94a3b8;
            font-size: 14px;
        }
        
        .btn {
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary { background: #3498db; color: #fff; }
        .btn-primary:hover { background: #2980b9; transform: translateY(-2px); }
        
        .btn-secondary { background: #e2e8f0; color: #1a2332; }
        .btn-secondary:hover { background: #cbd5e1; transform: translateY(-2px); }
        
        .btn-success { background: #2ecc71; color: #fff; }
        .btn-success:hover { background: #27ae60; transform: translateY(-2px); }
        
        .btn-danger { background: #e74c3c; color: #fff; }
        .btn-danger:hover { background: #c0392b; transform: translateY(-2px); }
        
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
        }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3); }
        
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert.info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: #fff;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #c9a84c;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .stat-card .label {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
        }
        
        .stat-card.ativo { border-left-color: #3498db; }
        .stat-card.passivo { border-left-color: #e74c3c; }
        .stat-card.receita { border-left-color: #2ecc71; }
        .stat-card.despesa { border-left-color: #f39c12; }
        .stat-card.total { border-left-color: #8b5cf6; }
        
        .table-responsive {
            overflow-x: auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 5px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .table thead th {
            background: #f8fafc;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #1a2332;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .table tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        
        .table tbody tr:hover { background: #f8fafc; }
        
        .table .codigo {
            font-weight: 600;
            color: #c9a84c;
            font-family: monospace;
        }
        
        .table .tipo-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .table .tipo-badge.ativo { background: #dbeafe; color: #1e40af; }
        .table .tipo-badge.passivo { background: #fee2e2; color: #991b1b; }
        .table .tipo-badge.receita { background: #d1fae5; color: #065f46; }
        .table .tipo-badge.despesa { background: #fef3c7; color: #92400e; }
        
        .table .nivel-1 { font-weight: 700; font-size: 14px; color: #1a2332; }
        .table .nivel-2 { padding-left: 30px !important; font-weight: 600; color: #2d3748; }
        .table .nivel-3 { padding-left: 60px !important; color: #4a5568; }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #94a3b8;
        }
        
        .empty-state .icon {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            font-size: 18px;
            color: #1a2332;
            margin-bottom: 5px;
        }
        
        .submenu {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 25px;
            padding: 12px 18px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            align-items: center;
        }
        
        .submenu a {
            padding: 6px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            color: #4a5568;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .submenu a:hover {
            background: rgba(201, 168, 76, 0.1);
            color: #c9a84c;
            border-color: #c9a84c;
        }
        
        .submenu a.active {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            border-color: #c9a84c;
            font-weight: 600;
        }
        
        .submenu .divider {
            width: 1px;
            height: 30px;
            background: #e2e8f0;
        }
        
        .submenu .label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 6px 4px;
        }
        
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .submenu { flex-direction: column; align-items: stretch; }
            .submenu a { text-align: center; }
            .submenu .divider { display: none; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>📋 Plano de Contas</h1>
            <p class="subtitle">Estrutura contábil da empresa</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../index.php" class="btn btn-secondary">← Voltar</a>
        </div>
    </div>
    
    <!-- Mensagem -->
    <?php if ($mensagem): ?>
    <div class="alert <?= $tipo_mensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>
    
    <!-- Submenu -->
    <div class="submenu">
        <a href="index.php" class="active">📋 Plano de Contas</a>
        <a href="../orcamento/">📊 Orçamento</a>
        <span class="divider"></span>
        <span class="label">🔑 Admin</span>
        <a href="?acao=criar_padrao" onclick="return confirm('Deseja recriar o plano de contas padrão? Isso irá limpar todas as contas existentes.');">🔄 Recriar Padrão</a>
        <a href="?acao=limpar" onclick="return confirm('Deseja limpar todas as contas? Esta ação não pode ser desfeita!');" style="color: #e74c3c;">🗑️ Limpar</a>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card ativo">
            <div class="number"><?= $totais['ativo'] ?></div>
            <div class="label">📊 Ativo</div>
        </div>
        <div class="stat-card passivo">
            <div class="number"><?= $totais['passivo'] ?></div>
            <div class="label">📊 Passivo</div>
        </div>
        <div class="stat-card receita">
            <div class="number"><?= $totais['receita'] ?></div>
            <div class="label">📈 Receita</div>
        </div>
        <div class="stat-card despesa">
            <div class="number"><?= $totais['despesa'] ?></div>
            <div class="label">📉 Despesa</div>
        </div>
        <div class="stat-card total">
            <div class="number"><?= $totais['total'] ?></div>
            <div class="label">📋 Total de Contas</div>
        </div>
    </div>
    
    <!-- Tabela -->
    <div class="table-responsive">
        <?php if (count($contas) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 120px;">Código</th>
                    <th>Nome da Conta</th>
                    <th style="width: 120px;">Tipo</th>
                    <th style="width: 80px;">Nível</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contas as $conta): ?>
                <tr>
                    <td class="codigo"><?= htmlspecialchars($conta['codigo']) ?></td>
                    <td class="nivel-<?= $conta['nivel'] ?>">
                        <?= htmlspecialchars($conta['nome']) ?>
                    </td>
                    <td>
                        <span class="tipo-badge <?= $conta['tipo'] ?>">
                            <?= ucfirst($conta['tipo']) ?>
                        </span>
                    </td>
                    <td style="text-align: center;"><?= $conta['nivel'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <span class="icon">📭</span>
            <h3>Nenhuma conta cadastrada</h3>
            <p>Clique em "Recriar Padrão" para criar o plano de contas automático.</p>
            <br>
            <a href="?acao=criar_padrao" class="btn btn-gold">📋 Criar Plano de Contas Padrão</a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Legenda -->
    <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; font-size: 12px; color: #94a3b8;">
        <strong>📌 Legenda:</strong>
        <span style="margin-left: 15px;">📊 <strong>Nível 1</strong> - Categoria Principal</span>
        <span style="margin-left: 15px;">📊 <strong>Nível 2</strong> - Subcategoria</span>
        <span style="margin-left: 15px;">📊 <strong>Nível 3</strong> - Conta Analítica</span>
    </div>
</div>

<?php
include '../../includes/footer_escola.php';
?>
</body>
</html>