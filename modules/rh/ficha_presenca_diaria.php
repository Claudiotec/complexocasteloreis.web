<?php
// ficha_presenca_diaria.php - Ficha Diária de Presença para Assinatura
require_once '../../config/database.php';

// =============================================
// CONFIGURAÇÃO DE FUSO HORÁRIO - ANGOLA (UTC+1)
// =============================================
date_default_timezone_set('Africa/Luanda');
putenv('TZ=Africa/Luanda');

if (date_default_timezone_get() !== 'Africa/Luanda') {
    date_default_timezone_set('Etc/GMT-1');
}

function getDataAngola() {
    $timestamp = time() + 3600;
    return date('Y-m-d', $timestamp);
}

// Processar salvamento da ficha preenchida manualmente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_ficha'])) {
    $data = getDataAngola();
    $salvos = 0;
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'entrada_') === 0) {
            $funcionario_id = str_replace('entrada_', '', $key);
            $hora_entrada = $value;
            $hora_saida = $_POST['saida_' . $funcionario_id] ?? null;
            $status = $_POST['status_' . $funcionario_id] ?? 'presente';
            $obs = $_POST['obs_' . $funcionario_id] ?? '';
            
            if (!empty($hora_entrada)) {
                // Verificar se já existe registro
                $stmt = $pdo->prepare("SELECT id FROM presenca_qr WHERE funcionario_id = ? AND data = ?");
                $stmt->execute([$funcionario_id, $data]);
                $existente = $stmt->fetch();
                
                if ($existente) {
                    // Atualizar registro existente
                    $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                        hora_entrada = ?,
                        hora_saida = ?,
                        status = ?,
                        observacao = ?,
                        qr_scanned = 0,
                        qr_scan_time = NOW()
                        WHERE id = ?");
                    $stmt->execute([$hora_entrada, $hora_saida, $status, $obs, $existente['id']]);
                } else {
                    // Inserir novo registro
                    $stmt = $pdo->prepare("INSERT INTO presenca_qr 
                        (funcionario_id, data, hora_entrada, hora_saida, status, observacao, qr_scanned, qr_scan_time) 
                        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
                    $stmt->execute([$funcionario_id, $data, $hora_entrada, $hora_saida, $status, $obs]);
                }
                $salvos++;
            }
        }
    }
    
    if ($salvos > 0) {
        $mensagem_sucesso = "✅ $salvos registros de presença salvos com sucesso!";
    } else {
        $mensagem_erro = "⚠️ Nenhum registro foi salvo. Preencha pelo menos a hora de entrada.";
    }
}

$dataHoje = getDataAngola();
$dataHojeFormatada = date('d/m/Y', strtotime($dataHoje));
$dia_semana = date('l', strtotime('today'));
$dias_semana_pt = [
    'Monday' => 'Segunda-feira',
    'Tuesday' => 'Terça-feira',
    'Wednesday' => 'Quarta-feira',
    'Thursday' => 'Quinta-feira',
    'Friday' => 'Sexta-feira',
    'Saturday' => 'Sábado',
    'Sunday' => 'Domingo'
];
$dia_semana_pt = $dias_semana_pt[$dia_semana];

// Buscar todos os funcionários ativos com suas presenças de hoje
$stmt = $pdo->prepare("
    SELECT 
        f.id,
        f.numero_agente,
        f.nome_completo,
        f.categoria_actual,
        f.funcao,
        f.contacto,
        f.data_inicio_funcao,
        p.id as presenca_id,
        p.hora_entrada,
        p.hora_saida,
        p.status as presenca_status,
        p.qr_scanned,
        p.horas_trabalhadas,
        p.observacao
    FROM forca_trabalho f
    LEFT JOIN presenca_qr p ON f.id = p.funcionario_id AND p.data = ?
    WHERE f.status = 'ativo'
    ORDER BY f.nome_completo ASC
");
$stmt->execute([$dataHoje]);
$funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar resumo do banco
$total_funcionarios = count($funcionarios);
$presentes_db = 0;
$ausentes_db = 0;

foreach ($funcionarios as $func) {
    if ($func['presenca_status'] === 'presente') {
        $presentes_db++;
    } else {
        $ausentes_db++;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha Diária de Presença - <?= $dataHojeFormatada ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', Courier, monospace;
            background: #f0f0f0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        
        .header h1 {
            font-size: 24px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 5px;
            color: #1a2332;
        }
        
        .header h2 {
            font-size: 18px;
            font-weight: normal;
            margin-bottom: 5px;
            color: #333;
        }
        
        .header .info {
            font-size: 14px;
            color: #555;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 5px;
        }
        
        .header .info span {
            background: #f8f9fa;
            padding: 3px 12px;
            border-radius: 4px;
        }
        
        .resumo {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 15px 0 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            flex-wrap: wrap;
            border: 1px solid #e9ecef;
        }
        
        .resumo-item {
            text-align: center;
            padding: 5px 20px;
            min-width: 100px;
        }
        
        .resumo-item .numero {
            font-size: 28px;
            font-weight: bold;
        }
        
        .resumo-item .label {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
        
        .resumo-item.presentes .numero { color: #27ae60; }
        .resumo-item.ausentes .numero { color: #e74c3c; }
        .resumo-item.total .numero { color: #2c3e50; }
        .resumo-item.manual .numero { color: #f59e0b; }
        
        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        
        .table-container {
            overflow-x: auto;
            margin: 20px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        table thead {
            background: #2c3e50;
            color: white;
        }
        
        table th {
            padding: 10px 6px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #34495e;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        table td {
            padding: 8px 6px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        
        table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        table tbody tr:hover {
            background: #f0f8ff;
        }
        
        .assinatura {
            min-height: 35px;
            border-bottom: 1px solid #333;
            margin-top: 5px;
        }
        
        .obs {
            font-size: 9px;
            color: #999;
            font-style: italic;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #333;
            font-size: 12px;
            color: #555;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .footer .assinatura-footer {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
        }
        
        .footer .assinatura-footer div {
            text-align: center;
        }
        
        .footer .assinatura-footer .linha {
            width: 150px;
            border-bottom: 1px solid #333;
            margin-top: 30px;
        }
        
        .btn-print {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-print:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-voltar {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-voltar:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .status-badge.presente {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.ausente {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.justificado {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.atraso {
            background: #fff3cd;
            color: #856404;
        }
        
        .hora-input {
            width: 100%;
            padding: 6px 4px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 12px;
            font-family: 'Courier New', monospace;
            background: white;
            text-align: center;
        }
        
        .hora-input:focus {
            border-color: #3498db;
            outline: none;
            background: #f0f8ff;
        }
        
        .hora-input.com-registro {
            background: #e8f5e9;
            border-color: #4CAF50;
            font-weight: bold;
        }
        
        .hora-input.sem-registro {
            background: white;
            border-color: #ddd;
        }
        
        .hora-input.preenchido-manual {
            background: #fff3cd;
            border-color: #f59e0b;
        }
        
        .legenda {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            font-size: 12px;
            border: 1px solid #e9ecef;
        }
        
        .legenda-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legenda-item .cor {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            border: 1px solid #ddd;
        }
        
        .legenda-item .cor.verde { background: #e8f5e9; border-color: #4CAF50; }
        .legenda-item .cor.amarelo { background: #fff3cd; border-color: #f59e0b; }
        .legenda-item .cor.branco { background: white; border-color: #ddd; }
        
        .info-presenca {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
        }
        
        .info-presenca .qr { color: #8b5cf6; }
        .info-presenca .manual { color: #f59e0b; }
        .info-presenca .pendente { color: #999; }
        
        .select-status {
            width: 100%;
            padding: 4px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 11px;
            background: white;
        }
        
        @media print {
            body {
                background: white;
                padding: 10px;
            }
            
            .container {
                box-shadow: none;
                padding: 15px;
            }
            
            .btn-print, .btn-voltar, .btn-success, .btn-warning, .no-print, .actions {
                display: none !important;
            }
            
            table {
                font-size: 10px;
            }
            
            table th {
                padding: 6px 4px;
                font-size: 9px;
            }
            
            table td {
                padding: 5px 4px;
            }
            
            .footer .assinatura-footer .linha {
                width: 120px;
            }
            
            .header h1 {
                font-size: 20px;
            }
            
            .hora-input {
                border: 1px solid #999 !important;
                background: white !important;
                font-weight: normal;
                padding: 3px;
            }
            
            .hora-input.com-registro {
                background: #e8f5e9 !important;
                border-color: #4CAF50 !important;
            }
            
            .hora-input.preenchido-manual {
                background: #fff3cd !important;
                border-color: #f59e0b !important;
            }
            
            .resumo {
                background: #f8f9fa;
                border: 1px solid #ddd;
            }
            
            .legenda {
                background: #f8f9fa;
                border: 1px solid #ddd;
            }
            
            .select-status {
                border: 1px solid #999;
                background: white;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            table {
                font-size: 10px;
            }
            
            table th, table td {
                padding: 4px 3px;
            }
            
            .header h1 {
                font-size: 18px;
            }
            
            .header .info {
                flex-direction: column;
                gap: 5px;
                align-items: center;
            }
            
            .header .info span {
                display: block;
                width: 100%;
            }
            
            .footer {
                flex-direction: column;
                gap: 15px;
            }
            
            .footer .assinatura-footer {
                flex-direction: column;
                gap: 15px;
                width: 100%;
            }
            
            .footer .assinatura-footer .linha {
                width: 100%;
            }
            
            .resumo {
                flex-direction: column;
                gap: 5px;
                align-items: center;
            }
            
            .legenda {
                flex-direction: column;
                gap: 5px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .actions button, .actions a {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }
            
            table {
                font-size: 8px;
            }
            
            table th, table td {
                padding: 3px 2px;
            }
            
            .hora-input {
                font-size: 9px;
                padding: 2px 3px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Botões de ação (não aparecem na impressão) -->
        <div class="actions no-print">
            <button onclick="window.print()" class="btn-print">🖨️ Imprimir Ficha</button>
            <a href="presenca_qr.php" class="btn-voltar">⬅️ Voltar</a>
            <button onclick="exportarCSV()" class="btn-success">📊 Exportar CSV</button>
            <button onclick="limparCampos()" class="btn-warning">🗑️ Limpar Campos</button>
        </div>
        
        <?php if (isset($mensagem_sucesso)): ?>
            <div class="alert alert-success"><?= $mensagem_sucesso ?></div>
        <?php endif; ?>
        
        <?php if (isset($mensagem_erro)): ?>
            <div class="alert alert-error"><?= $mensagem_erro ?></div>
        <?php endif; ?>
        
        <!-- Cabeçalho -->
        <div class="header">
            <h1>COMPLEXO ESCOLAR CASTELO REIS</h1>
            <h2>FICHA DIÁRIA DE PRESENÇA</h2>
            <div class="info">
                <span>📅 Data: <strong><?= $dataHojeFormatada ?></strong></span>
                <span>📆 Dia: <strong><?= $dia_semana_pt ?></strong></span>
                <span>👥 Total: <strong><?= $total_funcionarios ?></strong> funcionários</span>
                <span>🏫 Ano Lectivo: <strong>2026</strong></span>
            </div>
        </div>
        
        <!-- Resumo com estatísticas -->
        <div class="resumo" id="resumo">
            <div class="resumo-item total">
                <div class="numero" id="totalCount"><?= $total_funcionarios ?></div>
                <div class="label">👥 Total</div>
            </div>
            <div class="resumo-item presentes">
                <div class="numero" id="presentesCount"><?= $presentes_db ?></div>
                <div class="label">✅ Presentes</div>
            </div>
            <div class="resumo-item ausentes">
                <div class="numero" id="ausentesCount"><?= $ausentes_db ?></div>
                <div class="label">❌ Ausentes</div>
            </div>
            <div class="resumo-item manual">
                <div class="numero" id="manualCount">0</div>
                <div class="label">✏️ Preenchidos Manual</div>
            </div>
        </div>
        
        <form method="POST" id="fichaForm">
            <!-- Tabela de Presença -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th width="25">#</th>
                            <th width="70">Nº Agente</th>
                            <th width="170">Nome Completo</th>
                            <th width="100">Categoria</th>
                            <th width="130">Função</th>
                            <th width="80">Contacto</th>
                            <th width="90">Entrada</th>
                            <th width="90">Saída</th>
                            <th width="90">Status</th>
                            <th width="70">Assinatura</th>
                            <th width="70">Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $contador = 1;
                        foreach ($funcionarios as $func): 
                            $hora_entrada = $func['hora_entrada'] ? date('H:i', strtotime($func['hora_entrada'])) : '';
                            $hora_saida = $func['hora_saida'] ? date('H:i', strtotime($func['hora_saida'])) : '';
                            $status = $func['presenca_status'] ?? 'ausente';
                            $qr_scanned = $func['qr_scanned'] ?? 0;
                            $obs = $func['observacao'] ?? '';
                            
                            $classeEntrada = $hora_entrada ? 'com-registro' : 'sem-registro';
                            $classeSaida = $hora_saida ? 'com-registro' : 'sem-registro';
                            
                            $infoExtra = '';
                            if ($hora_entrada) {
                                $infoExtra = $qr_scanned == 1 ? '📱 QR' : '✏️ Sistema';
                            }
                        ?>
                        <tr>
                            <td style="text-align: center;"><?= $contador++ ?></td>
                            <td><?= htmlspecialchars($func['numero_agente']) ?></td>
                            <td><strong><?= htmlspecialchars($func['nome_completo']) ?></strong></td>
                            <td><?= htmlspecialchars($func['categoria_actual']) ?></td>
                            <td><?= htmlspecialchars($func['funcao']) ?></td>
                            <td><?= htmlspecialchars($func['contacto']) ?></td>
                            <td>
                                <input type="time" 
                                       name="entrada_<?= $func['id'] ?>" 
                                       class="hora-input <?= $classeEntrada ?>"
                                       value="<?= $hora_entrada ?>"
                                       placeholder="--:--"
                                       onchange="atualizarResumo()">
                                <div class="info-presenca">
                                    <?php if ($hora_entrada): ?>
                                        <span class="manual">✅ Registrado</span>
                                    <?php else: ?>
                                        <span class="pendente">⬜ Aguardando</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <input type="time" 
                                       name="saida_<?= $func['id'] ?>" 
                                       class="hora-input <?= $classeSaida ?>"
                                       value="<?= $hora_saida ?>"
                                       placeholder="--:--"
                                       onchange="atualizarResumo()">
                                <div class="info-presenca">
                                    <?php if ($hora_saida): ?>
                                        <span class="manual">✅ Registrado</span>
                                    <?php else: ?>
                                        <span class="pendente">⬜ Aguardando</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <select name="status_<?= $func['id'] ?>" class="select-status" onchange="atualizarResumo()">
                                    <option value="presente" <?= $status == 'presente' ? 'selected' : '' ?>>✅ Presente</option>
                                    <option value="ausente" <?= $status == 'ausente' ? 'selected' : '' ?>>❌ Ausente</option>
                                    <option value="atraso" <?= $status == 'atraso' ? 'selected' : '' ?>>⚠️ Atraso</option>
                                    <option value="justificado" <?= $status == 'justificado' ? 'selected' : '' ?>>📋 Justificado</option>
                                </select>
                                <?php if ($infoExtra): ?>
                                    <br><span style="font-size: 9px; color: #666;"><?= $infoExtra ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="assinatura"></div>
                                <span class="obs">Assinatura</span>
                            </td>
                            <td>
                                <input type="text" 
                                       name="obs_<?= $func['id'] ?>" 
                                       style="width: 100%; padding: 4px; border: 1px solid #ddd; border-radius: 3px; font-size: 10px;" 
                                       placeholder="Obs..."
                                       value="<?= htmlspecialchars($obs) ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Botão Salvar (não aparece na impressão) -->
            <div class="no-print" style="text-align: center; margin: 20px 0;">
                <button type="submit" name="salvar_ficha" class="btn-success" style="padding: 15px 50px; font-size: 16px;">
                    💾 Salvar Ficha Preenchida
                </button>
            </div>
        </form>
        
        <!-- Legenda -->
        <div class="legenda">
            <div class="legenda-item">
                <div class="cor verde"></div>
                <span><strong>Fundo Verde</strong> - Registro do sistema</span>
            </div>
            <div class="legenda-item">
                <div class="cor amarelo"></div>
                <span><strong>Fundo Amarelo</strong> - Preenchimento manual</span>
            </div>
            <div class="legenda-item">
                <div class="cor branco"></div>
                <span><strong>Fundo Branco</strong> - Aguardando preenchimento</span>
            </div>
            <div class="legenda-item">
                <span>📱 <strong>QR</strong> - Registrado via QR Code</span>
            </div>
            <div class="legenda-item">
                <span>✏️ <strong>Sistema</strong> - Registrado no sistema</span>
            </div>
            <div class="legenda-item">
                <span>✏️ <strong>Manual</strong> - Preenchido na ficha</span>
            </div>
        </div>
        
        <!-- Rodapé -->
        <div class="footer">
            <div>
                <p><strong>Instruções:</strong></p>
                <p style="font-size: 11px; color: #777; line-height: 1.6;">
                    1. Campos com fundo <strong style="color: #4CAF50;">verde</strong> já possuem registro no sistema<br>
                    2. Preencha os campos com fundo <strong>branco</strong> manualmente<br>
                    3. Selecione o status de presença para cada funcionário<br>
                    4. Clique em <strong>"Salvar Ficha Preenchida"</strong> para registrar os dados<br>
                    5. As estatísticas são atualizadas automaticamente
                </p>
            </div>
            
            <div class="assinatura-footer">
                <div>
                    <div class="linha"></div>
                    <p style="font-size: 11px; margin-top: 5px;"><strong>Responsável</strong></p>
                    <p style="font-size: 10px; color: #777;">Nome e Assinatura</p>
                </div>
                <div>
                    <div class="linha"></div>
                    <p style="font-size: 11px; margin-top: 5px;"><strong>Diretor Pedagógico</strong></p>
                    <p style="font-size: 10px; color: #777;">Nome e Assinatura</p>
                </div>
                <div>
                    <div class="linha"></div>
                    <p style="font-size: 11px; margin-top: 5px;"><strong>Carimbo</strong></p>
                    <p style="font-size: 10px; color: #777;">Data: ___/___/______</p>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 15px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px;">
            Documento gerado automaticamente em <?= date('d/m/Y H:i:s') ?> - Sistema de Gestão de Presenças
        </div>
    </div>
    
    <script>
        // Função para atualizar o resumo/estatísticas
        function atualizarResumo() {
            const linhas = document.querySelectorAll('table tbody tr');
            let total = linhas.length;
            let presentes = 0;
            let ausentes = 0;
            let manual = 0;
            
            linhas.forEach(linha => {
                const entrada = linha.querySelector('input[name^="entrada_"]');
                const status = linha.querySelector('select[name^="status_"]');
                
                if (entrada && entrada.value) {
                    manual++;
                    if (status && status.value === 'presente') {
                        presentes++;
                    } else if (status && status.value === 'ausente') {
                        ausentes++;
                    }
                } else {
                    // Se não tem entrada, verifica se tem registro no sistema
                    const statusBadge = linha.querySelector('.status-badge');
                    if (statusBadge && statusBadge.classList.contains('presente')) {
                        presentes++;
                    } else {
                        ausentes++;
                    }
                }
            });
            
            // Atualizar os números
            document.getElementById('totalCount').textContent = total;
            document.getElementById('presentesCount').textContent = presentes;
            document.getElementById('ausentesCount').textContent = ausentes;
            document.getElementById('manualCount').textContent = manual;
            
            // Destacar campos preenchidos manualmente
            document.querySelectorAll('input[name^="entrada_"]').forEach(input => {
                if (input.value && !input.classList.contains('com-registro')) {
                    input.classList.add('preenchido-manual');
                    const parent = input.closest('td');
                    if (parent) {
                        const info = parent.querySelector('.info-presenca .manual');
                        if (info) {
                            info.textContent = '✏️ Manual';
                            info.style.color = '#f59e0b';
                        }
                    }
                }
            });
        }
        
        // Função para imprimir
        function imprimirFicha() {
            window.print();
        }
        
        // Função para exportar CSV
        function exportarCSV() {
            const tabela = document.querySelector('table');
            const linhas = tabela.querySelectorAll('tr');
            let csv = [];
            
            const cabecalho = ['#', 'Nº Agente', 'Nome Completo', 'Categoria', 'Função', 'Contacto', 'Entrada', 'Saída', 'Status', 'Observação'];
            csv.push(cabecalho.join(','));
            
            linhas.forEach((linha, index) => {
                if (index === 0) return;
                
                const colunas = linha.querySelectorAll('td');
                if (colunas.length > 0) {
                    const linhaDados = [];
                    
                    linhaDados.push(colunas[0]?.textContent.trim() || '');
                    linhaDados.push(colunas[1]?.textContent.trim() || '');
                    linhaDados.push(colunas[2]?.textContent.trim() || '');
                    linhaDados.push(colunas[3]?.textContent.trim() || '');
                    linhaDados.push(colunas[4]?.textContent.trim() || '');
                    linhaDados.push(colunas[5]?.textContent.trim() || '');
                    
                    const entrada = colunas[6]?.querySelector('input')?.value || '';
                    const saida = colunas[7]?.querySelector('input')?.value || '';
                    const status = colunas[8]?.querySelector('select')?.value || 'ausente';
                    const obs = colunas[10]?.querySelector('input')?.value || '';
                    
                    linhaDados.push(entrada);
                    linhaDados.push(saida);
                    linhaDados.push(status);
                    linhaDados.push(obs);
                    
                    csv.push(linhaDados.join(','));
                }
            });
            
            const blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'ficha_presenca_<?= date('Y-m-d') ?>.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Função para limpar campos
        function limparCampos() {
            if (confirm('Deseja limpar todos os campos para preenchimento manual?')) {
                document