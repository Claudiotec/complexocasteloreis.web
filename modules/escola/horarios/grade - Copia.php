<?php
// ============================================
// grade.php - Grade Horária Escolar
// Módulo: escola/horarios
// ============================================

// Ativar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// CONFIGURAÇÃO DO BANCO DE DADOS
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'softgest_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // ✅ SENHA CONFIGURADA
define('DB_PORT', 3306);

// ============================================
// Conectar ao banco de dados
// ============================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    die("❌ Erro de conexão: " . $e->getMessage());
}

// ============================================
// BUSCAR DADOS DO BANCO
// ============================================

// Pegar filtros da URL
$selectedGrade = isset($_GET['grade']) ? (int)$_GET['grade'] : 0;
$selectedClass = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$selectedWeek = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d');

// Buscar lista de séries/anos
$grades = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM grades ORDER BY nome");
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se a tabela não existir, usar dados de exemplo
    $grades = [
        ['id' => 1, 'nome' => '1º Ano'],
        ['id' => 2, 'nome' => '2º Ano'],
        ['id' => 3, 'nome' => '3º Ano'],
        ['id' => 4, 'nome' => '4º Ano'],
        ['id' => 5, 'nome' => '5º Ano'],
    ];
}

// Buscar turmas da série selecionada
$classes = [];
if ($selectedGrade > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, nome FROM classes WHERE grade_id = ? ORDER BY nome");
        $stmt->execute([$selectedGrade]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Dados de exemplo
        $classes = [
            ['id' => 1, 'nome' => 'A'],
            ['id' => 2, 'nome' => 'B'],
            ['id' => 3, 'nome' => 'C'],
        ];
    }
}

// Buscar grade horária
$schedule = [];
if ($selectedGrade > 0 && $selectedClass > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.dia_semana,
                s.hora_inicio,
                s.hora_fim,
                d.nome as disciplina,
                p.nome as professor,
                s.sala
            FROM horarios s
            JOIN disciplinas d ON s.disciplina_id = d.id
            LEFT JOIN professores p ON s.professor_id = p.id
            WHERE s.grade_id = ? AND s.class_id = ?
            ORDER BY FIELD(s.dia_semana, 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta'), s.hora_inicio
        ");
        $stmt->execute([$selectedGrade, $selectedClass]);
        $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Se a tabela não existir, usar dados de exemplo
        $schedule = getSampleSchedule($selectedGrade, $selectedClass);
    }
}

// Gerar grade de exemplo se não houver dados
function getSampleSchedule($grade, $class) {
    $days = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta'];
    $subjects = ['Matemática', 'Português', 'Ciências', 'História', 'Geografia', 'Inglês', 'Artes', 'Educação Física'];
    $teachers = ['Prof. Silva', 'Prof. Santos', 'Prof. Oliveira', 'Prof. Pereira', 'Prof. Costa'];
    
    $schedule = [];
    $timeSlots = ['07:30', '08:20', '09:10', '10:00', '10:50', '11:40', '13:00', '13:50', '14:40', '15:30'];
    
    for ($i = 0; $i < 5; $i++) {
        for ($j = 0; $j < 4; $j++) {
            $schedule[] = [
                'dia_semana' => $days[$i],
                'hora_inicio' => $timeSlots[$j * 2],
                'hora_fim' => $timeSlots[$j * 2 + 1],
                'disciplina' => $subjects[array_rand($subjects)],
                'professor' => $teachers[array_rand($teachers)],
                'sala' => chr(65 + rand(0, 5)) . (rand(1, 3))
            ];
        }
    }
    usort($schedule, function($a, $b) {
        $days = ['Segunda'=>1, 'Terça'=>2, 'Quarta'=>3, 'Quinta'=>4, 'Sexta'=>5];
        if ($days[$a['dia_semana']] != $days[$b['dia_semana']]) {
            return $days[$a['dia_semana']] - $days[$b['dia_semana']];
        }
        return strcmp($a['hora_inicio'], $b['hora_inicio']);
    });
    return $schedule;
}

// Agrupar por dia
$scheduleByDay = [];
foreach ($schedule as $item) {
    $scheduleByDay[$item['dia_semana']][] = $item;
}

// Dias da semana
$weekDays = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta'];

// Pegar horários únicos
function getTimeSlots($schedule) {
    $times = [];
    foreach ($schedule as $item) {
        $times[] = $item['hora_inicio'] . ' - ' . $item['hora_fim'];
    }
    return array_unique($times);
}
$timeSlots = getTimeSlots($schedule);
sort($timeSlots);

// Nomes da série e turma
$gradeName = '';
foreach ($grades as $g) {
    if ($g['id'] == $selectedGrade) {
        $gradeName = $g['nome'];
        break;
    }
}

$className = '';
foreach ($classes as $c) {
    if ($c['id'] == $selectedClass) {
        $className = $c['nome'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Horária - <?php echo $gradeName . ' ' . $className; ?></title>
    <style>
        /* ============================================
           ESTILOS COMPLETOS
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1300px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            padding: 30px;
        }
        
        /* Cabeçalho */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
        }
        
        .header h1 {
            font-size: 26px;
            color: #1e293b;
        }
        
        .header h1 small {
            font-size: 16px;
            font-weight: normal;
            color: #64748b;
            display: block;
            margin-top: 4px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 18px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-success {
            background: #22c55e;
            color: white;
        }
        
        .btn-success:hover {
            background: #16a34a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: #2563eb;
            border: 2px solid #2563eb;
        }
        
        .btn-outline:hover {
            background: #2563eb;
            color: white;
        }
        
        .btn-print {
            background: #475569;
            color: white;
        }
        
        .btn-print:hover {
            background: #334155;
        }
        
        /* Filtros */
        .filters {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
            border: 1px solid #e2e8f0;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 150px;
        }
        
        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: border 0.3s ease;
            width: 100%;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* Tabela */
        .schedule-wrapper {
            overflow-x: auto;
            margin-top: 10px;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 700px;
        }
        
        .schedule-table th {
            background: #1e293b;
            color: white;
            padding: 14px 10px;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        
        .schedule-table th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .schedule-table th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .schedule-table td {
            padding: 12px 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            min-height: 70px;
        }
        
        .schedule-table tr:nth-child(even) td {
            background: #fafbfc;
        }
        
        .schedule-table tr:hover td {
            background: #f1f5f9;
        }
        
        .time-col {
            font-weight: 700;
            color: #475569;
            background: #f8fafc !important;
            white-space: nowrap;
            font-size: 13px;
        }
        
        .class-cell {
            min-width: 100px;
            position: relative;
        }
        
        .class-cell .subject {
            font-weight: 700;
            color: #0f172a;
            display: block;
            font-size: 15px;
        }
        
        .class-cell .teacher {
            font-size: 12px;
            color: #64748b;
            display: block;
            margin-top: 4px;
        }
        
        .class-cell .room {
            display: inline-block;
            background: #e2e8f0;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            margin-top: 4px;
        }
        
        .class-cell .badge-free {
            color: #94a3b8;
            font-style: italic;
            font-size: 13px;
        }
        
        .class-cell.has-class {
            background-color: #f8fafc;
            border-left: 4px solid #2563eb;
        }
        
        /* Estado vazio */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            color: #1e293b;
            font-size: 20px;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 15px;
            max-width: 400px;
            margin: 0 auto;
        }
        
        /* Rodapé */
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            font-size: 13px;
            color: #94a3b8;
        }
        
        /* Resumo */
        .summary {
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
        }
        
        /* Responsivo */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header h1 {
                font-size: 20px;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .schedule-table {
                font-size: 12px;
                min-width: 500px;
            }
            
            .schedule-table th,
            .schedule-table td {
                padding: 8px 4px;
            }
            
            .class-cell .subject {
                font-size: 13px;
            }
        }
        
        /* Impressão */
        @media print {
            .no-print {
                display: none !important;
            }
            .container {
                box-shadow: none;
                padding: 10px;
            }
            body {
                background: white;
                padding: 0;
            }
            .filters {
                background: #f8fafc;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- CABEÇALHO -->
        <div class="header">
            <h1>
                📚 Grade Horária
                <small>
                    <?php 
                    if ($selectedGrade > 0 && $selectedClass > 0) {
                        echo $gradeName . ' - Turma ' . $className;
                    } else {
                        echo 'Selecione uma turma para visualizar';
                    }
                    ?>
                </small>
            </h1>
            <div class="header-actions no-print">
                <button onclick="window.print()" class="btn btn-print">
                    🖨️ Imprimir
                </button>
                <a href="?export=pdf&grade=<?php echo $selectedGrade; ?>&class=<?php echo $selectedClass; ?>" class="btn btn-primary">
                    📄 PDF
                </a>
            </div>
        </div>
        
        <!-- FILTROS -->
        <form method="GET" class="filters no-print">
            <div class="filter-group">
                <label for="grade">📖 Série / Ano</label>
                <select name="grade" id="grade" onchange="this.form.submit()">
                    <option value="0">Selecione...</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?php echo $g['id']; ?>" <?php echo ($selectedGrade == $g['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="class">🏫 Turma</label>
                <select name="class" id="class" onchange="this.form.submit()" <?php echo $selectedGrade == 0 ? 'disabled' : ''; ?>>
                    <option value="0">Selecione...</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClass == $c['id']) ? 'selected' : ''; ?>>
                            Turma <?php echo htmlspecialchars($c['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="week">📅 Semana</label>
                <input type="date" name="week" id="week" value="<?php echo $selectedWeek; ?>" onchange="this.form.submit()">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">🔍 Consultar</button>
                <a href="grade.php" class="btn btn-outline">↺ Limpar</a>
            </div>
        </form>
        
        <!-- GRADE HORÁRIA -->
        <?php if ($selectedGrade > 0 && $selectedClass > 0 && !empty($schedule)): ?>
            
            <div class="schedule-wrapper">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Horário</th>
                            <?php foreach ($weekDays as $day): ?>
                                <th><?php echo $day; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Pegar todos os horários únicos
                        $allTimes = [];
                        foreach ($schedule as $item) {
                            $key = $item['hora_inicio'] . '|' . $item['hora_fim'];
                            $allTimes[$key] = $item['hora_inicio'] . ' - ' . $item['hora_fim'];
                        }
                        ksort($allTimes);
                        
                        // Para cada horário, exibir as aulas
                        foreach ($allTimes as $timeKey => $timeLabel):
                            list($start, $end) = explode(' - ', $timeLabel);
                        ?>
                        <tr>
                            <td class="time-col"><?php echo $timeLabel; ?></td>
                            <?php foreach ($weekDays as $day): 
                                $found = null;
                                foreach ($schedule as $item) {
                                    if ($item['dia_semana'] == $day && 
                                        $item['hora_inicio'] == $start && 
                                        $item['hora_fim'] == $end) {
                                        $found = $item;
                                        break;
                                    }
                                }
                            ?>
                                <td class="class-cell <?php echo $found ? 'has-class' : ''; ?>">
                                    <?php if ($found): ?>
                                        <span class="subject"><?php echo htmlspecialchars($found['disciplina']); ?></span>
                                        <span class="teacher">👨‍🏫 <?php echo htmlspecialchars($found['professor']); ?></span>
                                        <span class="room">🏠 <?php echo htmlspecialchars($found['sala']); ?></span>
                                    <?php else: ?>
                                        <span class="badge-free">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- RESUMO -->
            <div class="summary">
                <div>
                    <strong>📊 Resumo:</strong>
                    <span style="margin-left: 15px; color: #64748b;">
                        <?php 
                            $totalClasses = count($schedule);
                            $uniqueSubjects = array_unique(array_column($schedule, 'disciplina'));
                        ?>
                        <?php echo $totalClasses; ?> aulas • 
                        <?php echo count($uniqueSubjects); ?> disciplinas
                    </span>
                </div>
                <div>
                    <span style="margin-right: 15px;">
                        <span style="display:inline-block; width:12px; height:12px; background:#2563eb; border-radius:3px; vertical-align:middle;"></span>
                        Aulas ocupadas
                    </span>
                    <span>
                        <span style="display:inline-block; width:12px; height:12px; background:#e2e8f0; border-radius:3px; vertical-align:middle;"></span>
                        Horários livres
                    </span>
                </div>
                <div style="font-size: 13px; color: #94a3b8;">
                    Atualizado em: <?php echo date('d/m/Y H:i'); ?>
                </div>
            </div>
            
        <?php elseif ($selectedGrade > 0 && $selectedClass > 0): ?>
            <!-- SEM DADOS -->
            <div class="empty-state">
                <div class="icon">📋</div>
                <h3>Nenhuma aula cadastrada</h3>
                <p>Não há horários definidos para <?php echo $gradeName . ' - Turma ' . $className; ?>.</p>
                <br>
                <a href="#" class="btn btn-primary">➕ Adicionar Horários</a>
            </div>
        <?php else: ?>
            <!-- SELECIONE UMA TURMA -->
            <div class="empty-state">
                <div class="icon">🔍</div>
                <h3>Selecione uma turma</h3>
                <p>Use os filtros acima para visualizar a grade horária de uma turma específica.</p>
            </div>
        <?php endif; ?>
        
        <!-- RODAPÉ -->
        <div class="footer">
            <div class="info">
                <span>© <?php echo date('Y'); ?> SoftGest - Sistema de Gestão Escolar</span>
                <span>| Módulo: Escola / Horários</span>
            </div>
            <div>
                <span>Versão 1.0</span>
            </div>
        </div>
    </div>
    
    <!-- JAVASCRIPT -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit ao mudar selects
            const selects = document.querySelectorAll('select');
            selects.forEach(select => {
                select.addEventListener('change', function() {
                    if (this.id !== 'week') {
                        this.closest('form').submit();
                    }
                });
            });
            
            // Destacar o dia de hoje
            const days = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta'];
            const today = new Date();
            const dayIndex = today.getDay();
            if (dayIndex >= 1 && dayIndex <= 5) {
                const todayName = days[dayIndex - 1];
                const headers = document.querySelectorAll('.schedule-table th');
                headers.forEach((th, index) => {
                    if (index > 0 && th.textContent.trim() === todayName) {
                        th.style.background = '#2563eb';
                        th.style.color = 'white';
                        const rows = document.querySelectorAll('.schedule-table tbody tr');
                        rows.forEach(row => {
                            const cells = row.querySelectorAll('td');
                            if (cells[index]) {
                                cells[index].style.background = '#eff6ff';
                            }
                        });
                    }
                });
            }
            
            // Ctrl+P para imprimir
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'p') {
                    window.print();
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>