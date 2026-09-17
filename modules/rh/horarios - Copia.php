<?php
require_once '../../config/database.php';

$action = $_GET['action'] ?? 'list';
$funcionarioId = $_GET['funcionario'] ?? null;

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $funcionario_id = $_POST['funcionario_id'];
        $dia_semana = $_POST['dia_semana'];
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
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO horarios_trabalho 
                (funcionario_id, dia_semana, hora_entrada, hora_saida, 
                hora_intervalo_inicio, hora_intervalo_fim, carga_horaria, turno) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$funcionario_id, $dia_semana, $hora_entrada, $hora_saida, 
                $hora_intervalo_inicio, $hora_intervalo_fim, $carga_horaria, $turno]);
        } else {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE horarios_trabalho SET 
                funcionario_id = ?, dia_semana = ?, hora_entrada = ?, hora_saida = ?, 
                hora_intervalo_inicio = ?, hora_intervalo_fim = ?, carga_horaria = ?, turno = ? 
                WHERE id = ?");
            $stmt->execute([$funcionario_id, $dia_semana, $hora_entrada, $hora_saida, 
                $hora_intervalo_inicio, $hora_intervalo_fim, $carga_horaria, $turno, $id]);
        }
        
        header('Location: horarios.php?success=1');
        exit;
    }
    
    if ($action === 'delete' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM horarios_trabalho WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: horarios.php?success=1');
        exit;
    }
}

// Buscar funcionários
$stmtFuncionarios = $pdo->query("SELECT id, nome_completo FROM forca_trabalho ORDER BY nome_completo");
$funcionarios = $stmtFuncionarios->fetchAll();

// Buscar horário para edição
$horarioEdit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM horarios_trabalho WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $horarioEdit = $stmt->fetch();
}

// Buscar horários existentes
$stmtHorarios = $pdo->query("
    SELECT h.*, f.nome_completo, f.numero_agente 
    FROM horarios_trabalho h 
    JOIN forca_trabalho f ON h.funcionario_id = f.id 
    ORDER BY f.nome_completo, FIELD(h.dia_semana, 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo')
");
$horarios = $stmtHorarios->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Horários - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f4f8; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197, 165, 50, 0.3); }
        
        .btn-gold-outline {
            background: transparent;
            color: #c9a84c;
            padding: 10px 24px;
            border: 2px solid #c9a84c;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-gold-outline:hover { background: rgba(197, 165, 50, 0.1); transform: translateY(-2px); }
        
        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-danger:hover { background: #dc2626; }
        
        .form-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            margin-bottom: 30px;
        }
        .form-card h3 { margin-bottom: 20px; color: #1a2332; }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #1a2332; font-size: 14px; }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 10px 15px; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            font-size: 14px; 
            background: white;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #c9a84c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(197, 165, 50, 0.1);
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eef2f7;
            overflow-x: auto;
        }
        .table-container table { width: 100%; border-collapse: collapse; }
        .table-container th { 
            background: #f8fafc; 
            padding: 12px 18px; 
            text-align: left; 
            font-size: 12px; 
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table-container td { 
            padding: 10px 18px; 
            border-bottom: 1px solid #f1f5f9; 
            font-size: 14px;
            color: #1e293b;
        }
        .table-container tr:hover { background: #f8fafc; }
        
        .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            background: #eef2f7;
            color: #64748b;
        }
        .badge-manha { background: #dbeafe; color: #1e40af; }
        .badge-tarde { background: #fef3c7; color: #92400e; }
        .badge-noite { background: #e0e7ff; color: #3730a3; }
        .badge-integral { background: #d1fae5; color: #065f46; }
        
        .btn-small {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            margin: 2px;
        }
        .btn-small.edit { background: #f59e0b; color: white; }
        .btn-small.edit:hover { background: #d97706; }
        .btn-small.delete { background: #ef4444; color: white; }
        .btn-small.delete:hover { background: #dc2626; }
        
        .actions-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .actions-bar { flex-direction: column; }
            .actions-bar a { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>🕐 Gerenciar Horários</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ Operação realizada com sucesso!</div>
        <?php endif; ?>
        
        <div class="actions-bar">
            <a href="presenca_qr.php" class="btn-gold">📱 Presença QR</a>
            <a href="index.php" class="btn-gold-outline">📊 Dashboard</a>
        </div>
        
        <!-- Formulário -->
        <div class="form-card">
            <h3><?= $action === 'edit' ? '✏️ Editar Horário' : '➕ Novo Horário' ?></h3>
            
            <form method="POST">
                <?php if ($action === 'edit' && $horarioEdit): ?>
                    <input type="hidden" name="id" value="<?= $horarioEdit['id'] ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="funcionario_id">Funcionário</label>
                        <select name="funcionario_id" id="funcionario_id" required>
                            <option value="">Selecione um funcionário</option>
                            <?php foreach($funcionarios as $func): ?>
                                <option value="<?= $func['id'] ?>" 
                                    <?= ($action === 'edit' && $horarioEdit && $horarioEdit['funcionario_id'] == $func['id']) || 
                                       ($funcionarioId && $funcionarioId == $func['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($func['nome_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="dia_semana">Dia da Semana</label>
                        <select name="dia_semana" id="dia_semana" required>
                            <option value="">Selecione</option>
                            <?php 
                            $dias = ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'];
                            foreach($dias as $dia):
                                $selected = ($action === 'edit' && $horarioEdit && $horarioEdit['dia_semana'] == $dia) ? 'selected' : '';
                            ?>
                                <option value="<?= $dia ?>" <?= $selected ?>><?= ucfirst($dia) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="hora_entrada">Hora de Entrada</label>
                        <input type="time" name="hora_entrada" id="hora_entrada" 
                               value="<?= $action === 'edit' && $horarioEdit ? date('H:i', strtotime($horarioEdit['hora_entrada'])) : '08:00' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="hora_saida">Hora de Saída</label>
                        <input type="time" name="hora_saida" id="hora_saida" 
                               value="<?= $action === 'edit' && $horarioEdit ? date('H:i', strtotime($horarioEdit['hora_saida'])) : '17:00' ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="hora_intervalo_inicio">Início do Intervalo</label>
                        <input type="time" name="hora_intervalo_inicio" id="hora_intervalo_inicio" 
                               value="<?= $action === 'edit' && $horarioEdit ? date('H:i', strtotime($horarioEdit['hora_intervalo_inicio'])) : '12:00' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="hora_intervalo_fim">Fim do Intervalo</label>
                        <input type="time" name="hora_intervalo_fim" id="hora_intervalo_fim" 
                               value="<?= $action === 'edit' && $horarioEdit ? date('H:i', strtotime($horarioEdit['hora_intervalo_fim'])) : '13:00' ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="turno">Turno</label>
                    <select name="turno" id="turno" required>
                        <option value="manha" <?= $action === 'edit' && $horarioEdit && $horarioEdit['turno'] == 'manha' ? 'selected' : '' ?>>Manhã</option>
                        <option value="tarde" <?= $action === 'edit' && $horarioEdit && $horarioEdit['turno'] == 'tarde' ? 'selected' : '' ?>>Tarde</option>
                        <option value="noite" <?= $action === 'edit' && $horarioEdit && $horarioEdit['turno'] == 'noite' ? 'selected' : '' ?>>Noite</option>
                        <option value="integral" <?= $action === 'edit' && $horarioEdit && $horarioEdit['turno'] == 'integral' ? 'selected' : '' ?>>Integral</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-gold"><?= $action === 'edit' ? 'Atualizar' : 'Cadastrar' ?></button>
                    <a href="horarios.php" class="btn-gold-outline">Cancelar</a>
                </div>
            </form>
        </div>
        
        <!-- Lista de Horários -->
        <h3>📋 Horários Cadastrados</h3>
        
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
                            <td><strong><?= htmlspecialchars($h['nome_completo']) ?></strong></td>
                            <td><span class="badge"><?= ucfirst($h['dia_semana']) ?></span></td>
                            <td><?= date('H:i', strtotime($h['hora_entrada'])) ?></td>
                            <td><?= date('H:i', strtotime($h['hora_saida'])) ?></td>
                            <td><?= date('H:i', strtotime($h['hora_intervalo_inicio'])) ?> - <?= date('H:i', strtotime($h['hora_intervalo_fim'])) ?></td>
                            <td><?= number_format($h['carga_horaria'], 2, ',', '.') ?>h</td>
                            <td><span class="badge badge-<?= $h['turno'] ?>"><?= ucfirst($h['turno']) ?></span></td>
                            <td style="text-align: center;">
                                <a href="horarios.php?action=edit&id=<?= $h['id'] ?>" class="btn-small edit">✏️</a>
                                <a href="horarios.php?action=delete&id=<?= $h['id'] ?>" class="btn-small delete" onclick="return confirm('Tem certeza que deseja excluir este horário?')">🗑️</a>
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
    </div>
</body>
</html>