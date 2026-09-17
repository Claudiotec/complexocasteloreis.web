<?php
// horarios.php - Gerenciar Horários de Trabalho
require_once '../../config/database.php';

// =============================================
// CONFIGURAÇÃO
// =============================================
date_default_timezone_set('Africa/Luanda');

// =============================================
// FUNÇÃO PARA EXPORTAR EXCEL
// =============================================
if (isset($_GET['exportar_excel'])) {
    // Buscar todos os horários
    $stmt = $pdo->query("
        SELECT h.*, f.nome as nome_funcionario, f.cargo, f.departamento
        FROM horarios_trabalho h 
        JOIN funcionarios f ON h.funcionario_id = f.id 
        ORDER BY f.nome, FIELD(h.dia_semana, 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo')
    ");
    $dados = $stmt->fetchAll();
    
    // Buscar dados da empresa
    $stmtEmpresa = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmtEmpresa->fetch();
    
    if (!$empresa) {
        $empresa = [
            'nome_fantasia' => 'SoftGest Web',
            'endereco' => 'Rua Principal, 123',
            'telefone' => '(11) 9999-9999',
            'email' => 'contato@softgest.com',
            'cnpj' => '00.000.000/0001-00'
        ];
    }
    
    $diasSemana = [
        'segunda' => 'Segunda-feira',
        'terca' => 'Terça-feira',
        'quarta' => 'Quarta-feira',
        'quinta' => 'Quinta-feira',
        'sexta' => 'Sexta-feira',
        'sabado' => 'Sábado',
        'domingo' => 'Domingo'
    ];
    
    $turnos = [
        'manha' => 'Manhã',
        'tarde' => 'Tarde',
        'noite' => 'Noite',
        'integral' => 'Integral'
    ];
    
    // Nome do arquivo
    $nome_arquivo = 'Horarios_Trabalho_' . date('Y-m-d') . '.xls';
    
    // Cabeçalhos para Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Relatório de Horários</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; }
            .header-empresa { border-bottom: 2px solid #c9a84c; padding-bottom: 10px; margin-bottom: 15px; }
            .header-empresa h1 { color: #1a2332; font-size: 18px; }
            .header-empresa p { color: #666; font-size: 11px; }
            .titulo { font-size: 16px; font-weight: bold; color: #c9a84c; margin: 15px 0; text-align: center; }
            .info { background: #f8fafc; padding: 8px 12px; margin-bottom: 15px; border: 1px solid #eef2f7; font-size: 10px; }
            .info span { margin-right: 20px; }
            table { width: 100%; border-collapse: collapse; }
            table thead { background: #1a2332; color: white; }
            table th, table td { padding: 6px 10px; border: 1px solid #ccc; text-align: left; }
            table tr:nth-child(even) { background: #f9f9f9; }
            .footer { margin-top: 15px; padding-top: 10px; border-top: 1px solid #ccc; font-size: 9px; color: #666; text-align: center; }
            .badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
            .badge-manha { background: #dbeafe; color: #1e40af; }
            .badge-tarde { background: #fef3c7; color: #92400e; }
            .badge-noite { background: #e0e7ff; color: #3730a3; }
            .badge-integral { background: #d1fae5; color: #065f46; }
        </style>
    </head>
    <body>
        <div class="header-empresa">
            <h1><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest Web') ?></h1>
            <p><?= htmlspecialchars($empresa['endereco'] ?? 'Rua Principal, 123') ?> | 
               Tel: <?= htmlspecialchars($empresa['telefone'] ?? '(11) 9999-9999') ?> | 
               Email: <?= htmlspecialchars($empresa['email'] ?? 'contato@softgest.com') ?></p>
        </div>
        
        <div class="titulo">📋 RELATÓRIO DE HORÁRIOS DE TRABALHO</div>
        
        <div class="info">
            <span><strong>Data:</strong> <?= date('d/m/Y H:i:s') ?></span>
            <span><strong>Total de Horários:</strong> <?= count($dados) ?></span>
            <span><strong>Funcionários:</strong> <?= count(array_unique(array_column($dados, 'funcionario_id'))) ?></span>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Cargo</th>
                    <th>Departamento</th>
                    <th>Dia da Semana</th>
                    <th>Entrada</th>
                    <th>Saída</th>
                    <th>Intervalo</th>
                    <th>Carga Horária</th>
                    <th>Turno</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($dados) > 0): ?>
                    <?php foreach($dados as $h): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($h['nome_funcionario']) ?></strong></td>
                        <td><?= htmlspecialchars($h['cargo'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($h['departamento'] ?? '—') ?></td>
                        <td><?= $diasSemana[$h['dia_semana']] ?? $h['dia_semana'] ?></td>
                        <td><?= date('H:i', strtotime($h['hora_entrada'])) ?></td>
                        <td><?= date('H:i', strtotime($h['hora_saida'])) ?></td>
                        <td><?= date('H:i', strtotime($h['hora_intervalo_inicio'])) ?> - <?= date('H:i', strtotime($h['hora_intervalo_fim'])) ?></td>
                        <td><?= number_format($h['carga_horaria'], 2, ',', '.') ?>h</td>
                        <td><?= $turnos[$h['turno']] ?? $h['turno'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" style="text-align:center;">Nenhum horário cadastrado</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="footer">
            Relatório gerado em <?= date('d/m/Y H:i:s') ?> | SoftGest Web - Sistema de Gestão Empresarial
        </div>
    </body>
    </html>
    <?php
    exit;
}

// =============================================
// PROCESSAR AÇÕES
// =============================================

$action = $_GET['action'] ?? 'list';
$funcionarioId = $_GET['funcionario'] ?? null;
$id = $_GET['id'] ?? null;

// Processar formulário (Cadastrar/Editar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $funcionario_id = $_POST['funcionario_id'];
    $dias_selecionados = $_POST['dias_semana'] ?? [];
    $hora_entrada = $_POST['hora_entrada'];
    $hora_saida = $_POST['hora_saida'];
    $hora_intervalo_inicio = $_POST['hora_intervalo_inicio'];
    $hora_intervalo_fim = $_POST['hora_intervalo_fim'];
    $turno = $_POST['turno'];
    
    // Calcular carga horária
    $entrada = new DateTime($hora_entrada);
    $saida = new DateTime($hora_saida);
    $intervaloInicio = new DateTime($hora_intervalo_inicio);
    $intervaloFim = new DateTime($hora_intervalo_fim);
    
    $total = $entrada->diff($saida);
    $intervalo = $intervaloInicio->diff($intervaloFim);
    
    $horasTotal = $total->h + ($total->i / 60);
    $horasIntervalo = $intervalo->h + ($intervalo->i / 60);
    $carga_horaria = $horasTotal - $horasIntervalo;
    
    // Verificar se é edição ou novo
    $isEdit = isset($_POST['id']) && !empty($_POST['id']);
    
    // Verificar se o funcionário existe
    $checkFunc = $pdo->prepare("SELECT id FROM funcionarios WHERE id = ? AND status = 'ativo'");
    $checkFunc->execute([$funcionario_id]);
    if (!$checkFunc->fetch()) {
        $error = "Funcionário selecionado não existe ou está inativo!";
    } else {
        if ($isEdit) {
            // Atualizar (edição simples - apenas um dia)
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE horarios_trabalho SET 
                funcionario_id = ?, dia_semana = ?, hora_entrada = ?, hora_saida = ?, 
                hora_intervalo_inicio = ?, hora_intervalo_fim = ?, carga_horaria = ?, turno = ? 
                WHERE id = ?");
            $stmt->execute([$funcionario_id, $dias_selecionados[0] ?? '', $hora_entrada, $hora_saida, 
                $hora_intervalo_inicio, $hora_intervalo_fim, $carga_horaria, $turno, $id]);
            $success = "Horário atualizado com sucesso!";
        } else {
            // Cadastro múltiplo
            $cadastrados = 0;
            $erros = 0;
            
            foreach ($dias_selecionados as $dia) {
                // Verificar se já existe horário para este dia
                $check = $pdo->prepare("SELECT id FROM horarios_trabalho WHERE funcionario_id = ? AND dia_semana = ?");
                $check->execute([$funcionario_id, $dia]);
                if ($check->fetch()) {
                    $erros++;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO horarios_trabalho 
                        (funcionario_id, dia_semana, hora_entrada, hora_saida, 
                        hora_intervalo_inicio, hora_intervalo_fim, carga_horaria, turno) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$funcionario_id, $dia, $hora_entrada, $hora_saida, 
                        $hora_intervalo_inicio, $hora_intervalo_fim, $carga_horaria, $turno]);
                    $cadastrados++;
                }
            }
            
            if ($cadastrados > 0) {
                $success = "✅ $cadastrados horário(s) cadastrado(s) com sucesso!";
                if ($erros > 0) {
                    $success .= " ( $erros dia(s) já existiam e foram ignorados)";
                }
            } else {
                $error = "Nenhum horário foi cadastrado. Verifique se os dias selecionados já não existem.";
            }
        }
    }
}

// Processar exclusão
if ($action === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM horarios_trabalho WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: horarios.php?success=Horário excluído com sucesso!');
    exit;
}

// Buscar funcionários para o select
$stmtFuncionarios = $pdo->query("SELECT id, nome FROM funcionarios WHERE status = 'ativo' ORDER BY nome");
$funcionarios = $stmtFuncionarios->fetchAll();

// Buscar horário para edição
$horarioEdit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM horarios_trabalho WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $horarioEdit = $stmt->fetch();
    if (!$horarioEdit) {
        header('Location: horarios.php?error=Horário não encontrado!');
        exit;
    }
}

// Buscar horários existentes
$stmtHorarios = $pdo->query("
    SELECT h.*, f.nome as nome_funcionario, f.cargo, f.departamento
    FROM horarios_trabalho h 
    JOIN funcionarios f ON h.funcionario_id = f.id 
    ORDER BY f.nome, FIELD(h.dia_semana, 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo')
");
$horarios = $stmtHorarios->fetchAll();

// Mensagens
$success = $_GET['success'] ?? $success ?? null;
$error = $_GET['error'] ?? $error ?? null;

// Dias da semana
$diasSemana = [
    'segunda' => 'Segunda-feira',
    'terca' => 'Terça-feira',
    'quarta' => 'Quarta-feira',
    'quinta' => 'Quinta-feira',
    'sexta' => 'Sexta-feira',
    'sabado' => 'Sábado',
    'domingo' => 'Domingo'
];

// Turnos
$turnos = [
    'manha' => 'Manhã',
    'tarde' => 'Tarde',
    'noite' => 'Noite',
    'integral' => 'Integral'
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Horários - SoftGest Web</title>
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
        
        .container { max-width: 1200px; margin: 0 auto; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        .page-title { font-size: 28px; font-weight: 800; color: #1a2332; }
        .page-title span { color: #c9a84c; }
        .page-subtitle { color: #64748b; font-size: 14px; margin-top: 4px; }
        
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
        
        .btn-export {
            background: #10b981;
            color: white;
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
        .btn-export:hover { background: #059669; transform: translateY(-2px); }
        
        .btn-print {
            background: #8b5cf6;
            color: white;
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
        .btn-print:hover { background: #7c3aed; transform: translateY(-2px); }
        
        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
        
        .btn-edit {
            background: #f59e0b;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }
        .btn-edit:hover { background: #d97706; transform: translateY(-2px); }
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #eef2f7;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            margin-bottom: 30px;
        }
        
        .form-card h3 {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #1a2332;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .form-group label .required {
            color: #e74c3c;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
            width: 100%;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: #c9a84c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(197, 165, 50, 0.1);
        }
        
        .dias-checkbox {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 8px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #eef2f7;
        }
        
        .dias-checkbox label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #1a2332;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .dias-checkbox label:hover {
            background: rgba(197, 165, 50, 0.1);
        }
        
        .dias-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #c9a84c;
            cursor: pointer;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eef2f7;
            overflow-x: auto;
            margin-top: 15px;
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
        
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-manha { background: #dbeafe; color: #1e40af; }
        .badge-tarde { background: #fef3c7; color: #92400e; }
        .badge-noite { background: #e0e7ff; color: #3730a3; }
        .badge-integral { background: #d1fae5; color: #065f46; }
        
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .actions-top {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .info-cadastro {
            background: #fef3c7;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 12px;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
        }
        
        /* ===== ESTILOS PARA IMPRESSÃO ===== */
        @media print {
            .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; max-width: 100% !important; padding: 10px !important; }
            .container { max-width: 100% !important; }
            .form-card { display: none !important; }
            .actions-top { display: none !important; }
            .page-header .btn-outline { display: none !important; }
            .table-container { border: 1px solid #ccc !important; }
            .table-container thead { background: #1a2332 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body { background: white !important; }
        }
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; max-width: 100%; padding: 15px; }
            .form-row { grid-template-columns: 1fr; }
            .dias-checkbox { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); }
        }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .form-actions { flex-direction: column; }
            .form-actions button,
            .form-actions a { width: 100%; justify-content: center; }
            .actions-top { flex-direction: column; }
            .actions-top a { width: 100%; justify-content: center; }
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
                    <h1 class="page-title">🕐 <span>Gerenciar</span> Horários</h1>
                    <p class="page-subtitle">Cadastre os horários de trabalho dos funcionários (múltiplos dias)</p>
                </div>
                <div>
                    <a href="index.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Voltar ao RH</a>
                </div>
            </div>
            
            <!-- Ações Topo -->
            <div class="actions-top">
                <a href="presenca_qr.php" class="btn-gold"><i class="fas fa-qrcode"></i> Presença QR</a>
                <a href="horarios.php" class="btn-outline"><i class="fas fa-sync"></i> Recarregar</a>
                <a href="horarios.php?exportar_excel=1" class="btn-export" target="_blank"><i class="fas fa-file-excel"></i> Exportar Excel</a>
                <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Imprimir</button>
            </div>
            
            <!-- Mensagens -->
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <!-- Formulário -->
            <div class="form-card no-print">
                <h3><?= $action === 'edit' ? '✏️ Editar Horário' : '➕ Novo Horário (Múltiplos Dias)' ?></h3>
                
                <?php if ($action !== 'edit'): ?>
                <div class="info-cadastro">
                    <i class="fas fa-info-circle"></i>
                    Selecione um ou mais dias da semana para cadastrar o mesmo horário.
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <?php if ($action === 'edit' && $horarioEdit): ?>
                        <input type="hidden" name="id" value="<?= $horarioEdit['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Funcionário <span class="required">*</span></label>
                            <select name="funcionario_id" required>
                                <option value="">Selecione um funcionário</option>
                                <?php foreach($funcionarios as $func): ?>
                                    <option value="<?= $func['id'] ?>" 
                                        <?= ($action === 'edit' && $horarioEdit && $horarioEdit['funcionario_id'] == $func['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($func['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Dia(s) da Semana <span class="required">*</span></label>
                            <?php if ($action === 'edit'): ?>
                                <select name="dias_semana[]" required>
                                    <?php foreach($diasSemana as $key => $dia): ?>
                                        <option value="<?= $key ?>" 
                                            <?= ($horarioEdit && $horarioEdit['dia_semana'] == $key) ? 'selected' : '' ?>>
                                            <?= $dia ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="dias-checkbox">
                                    <?php foreach($diasSemana as $key => $dia): ?>
                                        <label>
                                            <input type="checkbox" name="dias_semana[]" value="<?= $key ?>">
                                            <?= $dia ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Hora de Entrada <span class="required">*</span></label>
                            <input type="time" name="hora_entrada" 
                                value="<?= $action === 'edit' && $horarioEdit ? date('H:i', strtotime($horarioEdit['hora_entrada'])) : '08:00' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Hora de Saída <span class="required">*</span></label>
                            <input type="time" name="hora_saida" 
                                value="<?= $action === 'edit' && $horarioEdit ? date('H:i', strtotime($horarioEdit['hora_saida'])) : '17:00' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Início do Intervalo <span class="required">*</span></label>
                            <input type="time" name="hora_intervalo_inicio" 
                                value="<?= $action === 'edit' && $horarioEdit ? date('H:i', strtotime($horarioEdit['hora_intervalo_inicio'])) : '12:00' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Fim do Intervalo <span class="required">*</span></label>
                            <input type="time" name="hora_intervalo_fim" 
                                value="<?= $action === 'edit' && $horarioEdit ? date('H:i', strtotime($horarioEdit['hora_intervalo_fim'])) : '13:00' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Turno <span class="required">*</span></label>
                            <select name="turno" required>
                                <option value="manha" <?= $action === 'edit' && $horarioEdit && $horarioEdit['turno'] == 'manha' ? 'selected' : '' ?>>Manhã</option>
                                <option value="tarde" <?= $action === 'edit' && $horarioEdit && $horarioEdit['turno'] == 'tarde' ? 'selected' : '' ?>>Tarde</option>
                                <option value="noite" <?= $action === 'edit' && $horarioEdit && $horarioEdit['turno'] == 'noite' ? 'selected' : '' ?>>Noite</option>
                                <option value="integral" <?= $action === 'edit' && $horarioEdit && $horarioEdit['turno'] == 'integral' ? 'selected' : '' ?>>Integral</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="justify-content: flex-end;">
                            <div style="background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid #eef2f7; width: 100%;">
                                <span style="font-size: 12px; color: #64748b;">
                                    <i class="fas fa-info-circle"></i> Carga horária calculada automaticamente
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-gold">
                            <i class="fas fa-save"></i> <?= $action === 'edit' ? 'Atualizar' : 'Cadastrar Múltiplos Dias' ?>
                        </button>
                        <a href="horarios.php" class="btn-outline">Cancelar</a>
                    </div>
                </form>
            </div>
            
            <!-- Lista de Horários -->
            <h3 style="margin-top: 30px; color: #1a2332;">📋 Horários Cadastrados</h3>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Dia</th>
                            <th>Entrada</th>
                            <th>Saída</th>
                            <th>Intervalo</th>
                            <th>Carga</th>
                            <th>Turno</th>
                            <th style="text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($horarios) > 0): ?>
                            <?php foreach($horarios as $h): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($h['nome_funcionario']) ?></strong></td>
                                <td><span class="badge"><?= ucfirst($h['dia_semana']) ?></span></td>
                                <td><?= date('H:i', strtotime($h['hora_entrada'])) ?></td>
                                <td><?= date('H:i', strtotime($h['hora_saida'])) ?></td>
                                <td><?= date('H:i', strtotime($h['hora_intervalo_inicio'])) ?> - <?= date('H:i', strtotime($h['hora_intervalo_fim'])) ?></td>
                                <td><?= number_format($h['carga_horaria'], 2, ',', '.') ?>h</td>
                                <td><span class="badge badge-<?= $h['turno'] ?>"><?= ucfirst($h['turno']) ?></span></td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <a href="horarios.php?action=edit&id=<?= $h['id'] ?>" class="btn-edit"><i class="fas fa-edit"></i> Editar</a>
                                    <a href="horarios.php?action=delete&id=<?= $h['id'] ?>" class="btn-danger" onclick="return confirm('Tem certeza que deseja excluir este horário?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                    <p style="font-size: 48px; margin-bottom: 10px;">🕐</p>
                                    <p>Nenhum horário cadastrado.</p>
                                    <p style="margin-top: 10px;">
                                        <a href="horarios.php?action=add" class="btn-gold">Cadastrar Horário</a>
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Informações -->
            <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 12px; border: 1px solid #eef2f7;">
                <div style="display: flex; flex-wrap: wrap; gap: 20px; font-size: 13px; color: #64748b;">
                    <div>
                        <strong>Total de horários:</strong> <?= count($horarios) ?>
                    </div>
                    <div>
                        <strong>Funcionários com horário:</strong> 
                        <?= count(array_unique(array_column($horarios, 'funcionario_id'))) ?>
                    </div>
                    <div>
                        <strong>Dias cadastrados:</strong>
                        <?php 
                        $dias = array_unique(array_column($horarios, 'dia_semana'));
                        echo count($dias);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Função para selecionar todos os dias
        function selecionarTodosDias() {
            const checkboxes = document.querySelectorAll('.dias-checkbox input[type="checkbox"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        }
        
        console.log('Horários carregados: <?= count($horarios) ?>');
    </script>
</body>
</html>