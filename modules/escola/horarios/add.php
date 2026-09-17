<?php
// ============================================
// modules/escola/horarios/add.php - Adicionar Horário
// CORRIGIDO: Dia da semana opcional quando "aplicar todos" está marcado
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ============================================
// BUSCAR DADOS PARA OS SELECTS
// ============================================
$turmas = [];
$funcionarios = [];
$disciplinas_lista = [];
$tempos = [];
$erro = '';
$mensagem = '';

try {
    $pdo = conectarBanco();
    
    // Buscar turmas
    $stmt = $pdo->query("SELECT id, nome, classe, curso, disciplinas FROM turmas WHERE status = 'ativa' ORDER BY classe, nome");
    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar funcionários (professores)
    $stmt = $pdo->query("SELECT id, nome, cargo FROM funcionarios WHERE cargo = 'Professor' OR cargo LIKE '%Professor%' ORDER BY nome");
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se não houver funcionários com cargo Professor, buscar todos
    if (empty($funcionarios)) {
        $stmt = $pdo->query("SELECT id, nome, cargo FROM funcionarios ORDER BY nome");
        $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Buscar tempos cadastrados - com tratamento para is_intervalo
    try {
        $stmt = $pdo->query("SELECT * FROM tempos WHERE status = 'ativo' ORDER BY ordem");
        $tempos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback sem is_intervalo
        try {
            $stmt = $pdo->query("SELECT id, nome, hora_inicio, hora_fim, ordem, turno, status FROM tempos WHERE status = 'ativo' ORDER BY ordem");
            $tempos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tempos as &$t) {
                $t['is_intervalo'] = (stripos($t['nome'], 'intervalo') !== false) ? 1 : 0;
            }
            unset($t);
        } catch (Exception $e2) {
            $tempos = [];
        }
    }
    
    // Garantir que is_intervalo existe em cada tempo
    foreach ($tempos as &$t) {
        if (!isset($t['is_intervalo'])) {
            $t['is_intervalo'] = (stripos($t['nome'], 'intervalo') !== false) ? 1 : 0;
        }
    }
    unset($t);
    
    // Extrair disciplinas únicas da tabela turmas
    $disciplinas_set = [];
    foreach ($turmas as $turma) {
        if (!empty($turma['disciplinas'])) {
            $discs = explode(',', $turma['disciplinas']);
            foreach ($discs as $disc) {
                $disc = trim($disc);
                if (!empty($disc) && !in_array($disc, $disciplinas_set)) {
                    $disciplinas_set[] = $disc;
                }
            }
        }
    }
    sort($disciplinas_set);
    $disciplinas_lista = $disciplinas_set;
    
} catch (Exception $e) {
    $erro = 'Erro ao carregar dados: ' . $e->getMessage();
}

// ============================================
// PROCESSAR FORMULÁRIO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $turma_id = intval($_POST['turma_id'] ?? 0);
    $disciplina = trim($_POST['disciplina'] ?? '');
    $funcionario_id = intval($_POST['funcionario_id'] ?? 0);
    $dia_semana = $_POST['dia_semana'] ?? '';
    $tempo_id = intval($_POST['tempo_id'] ?? 0);
    $sala = trim($_POST['sala'] ?? '');
    $aplicar_todos_dias = isset($_POST['aplicar_todos_dias']) ? 1 : 0;
    $is_intervalo = isset($_POST['is_intervalo']) ? intval($_POST['is_intervalo']) : 0;
    
    // Validar
    if ($turma_id <= 0 || $tempo_id <= 0) {
        $erro = 'Turma e Tempo são obrigatórios.';
    } elseif ($is_intervalo == 0 && (empty($disciplina) || $funcionario_id <= 0)) {
        $erro = 'Disciplina e Professor são obrigatórios para aulas normais.';
    } elseif (!$aplicar_todos_dias && empty($dia_semana)) {
        $erro = 'Selecione o dia da semana ou marque "Aplicar para todos os dias".';
    } else {
        try {
            $pdo = conectarBanco();
            
            // Buscar dados do tempo
            $stmt_tempo = $pdo->prepare("SELECT * FROM tempos WHERE id = ?");
            $stmt_tempo->execute([$tempo_id]);
            $tempo = $stmt_tempo->fetch(PDO::FETCH_ASSOC);
            
            if (!$tempo) {
                throw new Exception('Tempo não encontrado.');
            }
            
            // Definir dias a serem inseridos
            $dias_para_inserir = [];
            if ($aplicar_todos_dias == 1) {
                $dias_para_inserir = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
            } else {
                $dias_para_inserir = [$dia_semana];
            }
            
            $contador = 0;
            
            foreach ($dias_para_inserir as $dia) {
                // Verificar se já existe horário para esta turma/tempo/dia
                $stmt_check = $pdo->prepare("
                    SELECT COUNT(*) as total FROM horarios 
                    WHERE turma_id = ? AND tempo_id = ? AND dia_semana = ?
                ");
                $stmt_check->execute([$turma_id, $tempo_id, $dia]);
                $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);
                
                if ($existe['total'] > 0) {
                    continue;
                }
                
                // Inserir horário - APENAS colunas que existem na tabela
                $stmt = $pdo->prepare("
                    INSERT INTO horarios (
                        turma_id, 
                        disciplina, 
                        funcionario_id, 
                        dia_semana, 
                        tempo_id,
                        hora_inicio, 
                        hora_fim, 
                        sala,
                        is_intervalo,
                        status,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo', NOW())
                ");
                
                $stmt->execute([
                    $turma_id,
                    $is_intervalo == 1 ? 'INTERVALO' : $disciplina,
                    $is_intervalo == 1 ? null : $funcionario_id,
                    $dia,
                    $tempo_id,
                    $tempo['hora_inicio'] ?? '',
                    $tempo['hora_fim'] ?? '',
                    $is_intervalo == 1 ? '' : $sala,
                    $is_intervalo
                ]);
                
                $contador++;
            }
            
            if ($contador == 0) {
                $erro = 'Nenhum horário foi adicionado. Verifique se já não existem horários para este dia e tempo.';
            } else {
                $mensagem_tipo = $is_intervalo == 1 ? 'Intervalo' : 'Horário';
                $_SESSION['sucesso'] = $mensagem_tipo . ' adicionado com sucesso para ' . $contador . ' dia(s)!';
                header('Location: index.php');
                exit;
            }
            
        } catch (Exception $e) {
            $erro = 'Erro ao adicionar: ' . $e->getMessage();
        }
    }
}

$dias_semana = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Horário</title>
    <style>
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .header h1 { font-size: 22px; color: #1a2a3a; }
        .btn-voltar {
            padding: 8px 20px;
            background: #f1f5f9;
            color: #4a5568;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-voltar:hover { background: #e2e8f0; }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #4a5568;
            font-size: 14px;
        }
        .form-group .obrigatorio { color: #e53e3e; }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s, opacity 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #d4a843;
            outline: none;
        }
        .form-group select:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .btn-salvar {
            padding: 12px 30px;
            background: #d4a843;
            color: #1a2a3a;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-salvar:hover {
            background: #c9a84c;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212,168,67,0.3);
        }
        .btn-cancelar {
            padding: 12px 30px;
            background: #f1f5f9;
            color: #4a5568;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-left: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-cancelar:hover { background: #e2e8f0; }
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .alert-info .icon { font-size: 20px; margin-right: 8px; }
        .acoes { display: flex; align-items: center; margin-top: 10px; }
        .info-extra { font-size: 12px; color: #94a3b8; margin-top: 3px; }
        .info-extra a { color: #c9a84c; font-weight: 600; text-decoration: none; }
        .info-extra a:hover { text-decoration: underline; }
        .badge-tempo {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-intervalo {
            background: #fef9e7;
            color: #d4a843;
            border: 1px solid #d4a843;
        }
        .badge-tempo-normal { background: #e2e8f0; color: #475569; }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #d4a843;
            cursor: pointer;
        }
        .checkbox-group label {
            font-weight: 600;
            color: #1a2a3a;
            cursor: pointer;
            font-size: 14px;
        }
        .checkbox-group .descricao {
            font-weight: normal;
            color: #94a3b8;
            font-size: 13px;
        }
        .campo-desativado {
            opacity: 0.4;
        }
        .aviso-dia {
            display: none;
            font-size: 12px;
            color: #c9a84c;
            font-weight: 600;
            margin-top: 4px;
        }
        .aviso-dia.ativo { display: block; }
        .campos-ocultos { display: none; }
        .campos-visiveis { display: block; }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .header { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>📚 Adicionar Horário</h1>
        <a href="index.php" class="btn-voltar">← Voltar</a>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['sucesso'])): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['sucesso']) ?></div>
        <?php unset($_SESSION['sucesso']); ?>
    <?php endif; ?>

    <form method="POST" action="" id="formHorario">
        <div class="form-row">
            <div class="form-group">
                <label>Turma <span class="obrigatorio">*</span></label>
                <select name="turma_id" id="turma_id" required onchange="carregarDisciplinas()">
                    <option value="">Selecione a turma</option>
                    <?php foreach ($turmas as $turma): ?>
                        <option value="<?= $turma['id'] ?>" data-disciplinas="<?= htmlspecialchars($turma['disciplinas'] ?? '') ?>">
                            <?= htmlspecialchars($turma['classe'] ?? '') ?> - <?= htmlspecialchars($turma['nome'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Tempo <span class="obrigatorio">*</span></label>
                <select name="tempo_id" id="tempo_id" required onchange="verificarIntervalo()">
                    <option value="">Selecione o tempo</option>
                    <?php foreach ($tempos as $tempo): ?>
                        <option value="<?= $tempo['id'] ?>" data-is-intervalo="<?= $tempo['is_intervalo'] ?>">
                            <?= htmlspecialchars($tempo['nome']) ?> 
                            (<?= date('H:i', strtotime($tempo['hora_inicio'])) ?> - <?= date('H:i', strtotime($tempo['hora_fim'])) ?>)
                            <?php if ($tempo['is_intervalo'] == 1): ?>
                                ☕ INTERVALO
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="info-extra">
                    <?php if (empty($tempos)): ?>
                        <span style="color: #e53e3e;">⚠️ Nenhum tempo cadastrado! 
                        <a href="tempo_add.php">➕ Cadastrar agora</a></span>
                    <?php else: ?>
                        <?= count($tempos) ?> tempos cadastrados
                        <a href="tempos.php">⚙️ Gerenciar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ALERTA DE INTERVALO -->
        <div id="alertaIntervalo" class="alert-info" style="display: none;">
            <span class="icon">☕</span>
            <strong>Intervalo selecionado!</strong> 
            Apenas a Turma e o Tempo serão salvos. Disciplina e Professor não são necessários.
        </div>

        <!-- CAMPOS PARA AULA NORMAL -->
        <div id="camposAula" class="campos-visiveis">
            <div class="form-row">
                <div class="form-group">
                    <label>Disciplina <span class="obrigatorio" id="labelDisciplina">*</span></label>
                    <select name="disciplina" id="disciplina" required>
                        <option value="">Selecione a disciplina</option>
                        <?php foreach ($disciplinas_lista as $disc): ?>
                            <option value="<?= htmlspecialchars($disc) ?>"><?= htmlspecialchars($disc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Professor <span class="obrigatorio" id="labelProfessor">*</span></label>
                    <select name="funcionario_id" id="funcionario_id" required>
                        <option value="">Selecione o professor</option>
                        <?php foreach ($funcionarios as $funcionario): ?>
                            <option value="<?= $funcionario['id'] ?>">
                                <?= htmlspecialchars($funcionario['nome']) ?> 
                                <?= !empty($funcionario['cargo']) ? '('.htmlspecialchars($funcionario['cargo']).')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Sala</label>
                <input type="text" name="sala" id="sala" placeholder="Ex: Sala 101">
            </div>
        </div>

        <!-- DIA DA SEMANA -->
        <div class="form-group" id="campoDia">
            <label id="labelDia">Dia da Semana <span class="obrigatorio" id="obrigatorioDia">*</span></label>
            <select name="dia_semana" id="dia_semana" required>
                <option value="">Selecione o dia</option>
                <?php foreach ($dias_semana as $dia): ?>
                    <option value="<?= $dia ?>"><?= $dia ?></option>
                <?php endforeach; ?>
            </select>
            <div class="aviso-dia" id="avisoDia">
                ℹ️ O campo "Dia da Semana" foi desativado porque marcou "Aplicar para todos os dias".
            </div>
        </div>

        <!-- APLICAR PARA TODOS OS DIAS -->
        <div class="checkbox-group">
            <input type="checkbox" name="aplicar_todos_dias" id="aplicar_todos_dias" value="1"
                   onchange="toggleDiaSemana()">
            <label for="aplicar_todos_dias">
                📅 Aplicar para todos os dias da semana
                <span class="descricao">(Segunda a Sábado)</span>
            </label>
        </div>

        <!-- CAMPO OCULTO -->
        <input type="hidden" name="is_intervalo" id="is_intervalo" value="0">

        <?php if (!empty($tempos)): ?>
        <div class="form-group" style="background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; margin-top: 10px;">
            <label style="font-size: 12px; color: #94a3b8;">📋 Tempos disponíveis:</label>
            <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px;">
                <?php foreach ($tempos as $tempo): ?>
                    <span class="badge-tempo <?= $tempo['is_intervalo'] == 1 ? 'badge-intervalo' : 'badge-tempo-normal' ?>">
                        <?= htmlspecialchars($tempo['nome']) ?>
                        <?php if ($tempo['is_intervalo'] == 1): ?> ☕<?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="acoes">
            <button type="submit" class="btn-salvar">💾 Salvar</button>
            <a href="index.php" class="btn-cancelar">Cancelar</a>
        </div>
    </form>
</div>

<script>
    // ============================================
    // TOGGLE DIA DA SEMANA
    // ============================================
    function toggleDiaSemana() {
        var checkbox = document.getElementById('aplicar_todos_dias');
        var selectDia = document.getElementById('dia_semana');
        var labelDia = document.getElementById('labelDia');
        var obrigatorioDia = document.getElementById('obrigatorioDia');
        var avisoDia = document.getElementById('avisoDia');
        
        if (checkbox.checked) {
            // Desativar o select do dia
            selectDia.removeAttribute('required');
            selectDia.disabled = true;
            selectDia.value = '';
            selectDia.classList.add('campo-desativado');
            
            // Esconder asterisco e mostrar aviso
            if (obrigatorioDia) obrigatorioDia.style.display = 'none';
            if (avisoDia) avisoDia.classList.add('ativo');
            if (labelDia) labelDia.classList.add('campo-desativado');
        } else {
            // Reativar
            selectDia.setAttribute('required', 'required');
            selectDia.disabled = false;
            selectDia.classList.remove('campo-desativado');
            
            // Mostrar asterisco e esconder aviso
            if (obrigatorioDia) obrigatorioDia.style.display = 'inline';
            if (avisoDia) avisoDia.classList.remove('ativo');
            if (labelDia) labelDia.classList.remove('campo-desativado');
        }
    }

    // ============================================
    // VERIFICAR INTERVALO
    // ============================================
    function verificarIntervalo() {
        var selectTempo = document.getElementById('tempo_id');
        var selectedOption = selectTempo.options[selectTempo.selectedIndex];
        var isIntervalo = selectedOption.getAttribute('data-is-intervalo') == '1';
        
        var camposAula = document.getElementById('camposAula');
        var alertaIntervalo = document.getElementById('alertaIntervalo');
        var isIntervaloInput = document.getElementById('is_intervalo');
        var disciplinaSelect = document.getElementById('disciplina');
        var funcionarioSelect = document.getElementById('funcionario_id');
        var labelDisciplina = document.getElementById('labelDisciplina');
        var labelProfessor = document.getElementById('labelProfessor');
        
        if (isIntervalo) {
            camposAula.style.display = 'none';
            alertaIntervalo.style.display = 'block';
            isIntervaloInput.value = '1';
            
            disciplinaSelect.removeAttribute('required');
            funcionarioSelect.removeAttribute('required');
            
            labelDisciplina.textContent = '';
            labelProfessor.textContent = '';
        } else {
            camposAula.style.display = 'block';
            alertaIntervalo.style.display = 'none';
            isIntervaloInput.value = '0';
            
            disciplinaSelect.setAttribute('required', 'required');
            funcionarioSelect.setAttribute('required', 'required');
            
            labelDisciplina.textContent = '*';
            labelProfessor.textContent = '*';
        }
    }
    
    // ============================================
    // CARREGAR DISCIPLINAS DA TURMA
    // ============================================
    function carregarDisciplinas() {
        var selectTurma = document.getElementById('turma_id');
        var selectDisciplina = document.getElementById('disciplina');
        var selectedOption = selectTurma.options[selectTurma.selectedIndex];
        var disciplinasData = selectedOption.getAttribute('data-disciplinas');
        
        selectDisciplina.innerHTML = '<option value="">Selecione a disciplina</option>';
        
        if (disciplinasData) {
            var disciplinas = disciplinasData.split(',').map(function(item) {
                return item.trim();
            });
            
            disciplinas.forEach(function(disciplina) {
                if (disciplina) {
                    var option = document.createElement('option');
                    option.value = disciplina;
                    option.textContent = disciplina;
                    selectDisciplina.appendChild(option);
                }
            });
        }
    }
    
    // ============================================
    // INICIALIZAÇÃO
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        verificarIntervalo();
        
        // Se o formulário foi submetido com "aplicar todos" marcado, manter o estado
        var checkbox = document.getElementById('aplicar_todos_dias');
        if (checkbox && checkbox.checked) {
            toggleDiaSemana();
        }
    });
</script>

</body>
</html>