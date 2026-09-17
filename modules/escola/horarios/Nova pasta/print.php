<?php
// ============================================
// modules/escola/horarios/print.php - Imprimir Grade de Horários
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

// ===== FILTROS =====
$turma_id = isset($_GET['turma_id']) ? $_GET['turma_id'] : null;

// ===== BUSCAR DADOS DA EMPRESA =====
$empresa = [];
try {
    $pdo = conectarBanco();
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEscola = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';
$endereco = $empresa['endereco'] ?? '';
$cidade = $empresa['cidade'] ?? '';
$telefone = $empresa['telefone'] ?? $empresa['celular'] ?? '';
$email = $empresa['email'] ?? '';
$site = $empresa['site'] ?? '';
$logo = $empresa['logo'] ?? '';

// ===== BUSCAR HORÁRIOS =====
$horarios = [];
$turmaSelecionada = '';
try {
    $pdo = conectarBanco();
    $sql = "
        SELECT h.*, 
               t.nome as turma_nome,
               t.classe as turma_classe,
               f.nome as professor_nome,
               f.cargo as professor_cargo,
               tp.nome as tempo_nome
        FROM horarios h
        LEFT JOIN turmas t ON h.turma_id = t.id
        LEFT JOIN funcionarios f ON h.funcionario_id = f.id
        LEFT JOIN tempos tp ON h.tempo_id = tp.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($turma_id) {
        $sql .= " AND h.turma_id = ?";
        $params[] = $turma_id;
        // Buscar nome da turma
        $stmt = $pdo->prepare("SELECT nome, classe FROM turmas WHERE id = ?");
        $stmt->execute([$turma_id]);
        $t = $stmt->fetch();
        $turmaSelecionada = ($t['classe'] ?? '') . ' - ' . ($t['nome'] ?? '');
    }
    
    $sql .= " ORDER BY t.classe, t.nome, h.dia_semana, h.hora_inicio";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $horarios = $stmt->fetchAll();
    
} catch (Exception $e) {
    $erro = 'Erro ao carregar horários: ' . $e->getMessage();
}

// ===== DIAS DA SEMANA =====
$diasSemana = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

// ===== BUSCAR HORÁRIOS ÚNICOS =====
$horariosUnicos = [];
foreach ($horarios as $h) {
    $key = $h['hora_inicio'] . '|' . $h['hora_fim'];
    $horariosUnicos[$key] = [
        'hora_inicio' => $h['hora_inicio'],
        'hora_fim' => $h['hora_fim'],
        'tempo_id' => $h['tempo_id'],
        'tempo_nome' => $h['tempo_nome'] ?? ''
    ];
}
ksort($horariosUnicos);

// ===== AGRUPAR HORÁRIOS POR DIA =====
$horariosPorDia = [];
foreach ($horarios as $h) {
    $dia = $h['dia_semana'] ?? 'Segunda-feira';
    if (!isset($horariosPorDia[$dia])) {
        $horariosPorDia[$dia] = [];
    }
    $horariosPorDia[$dia][] = $h;
}

// ===== CALCULAR ANO LETIVO =====
$mes_atual = date('m');
$ano_atual = date('Y');
if ($mes_atual >= 9) {
    $ano_letivo = $ano_atual . ' / ' . ($ano_atual + 1);
} else {
    $ano_letivo = ($ano_atual - 1) . ' / ' . $ano_atual;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Horária - <?= htmlspecialchars($turmaSelecionada ?: 'Todas as Turmas') ?></title>
    <style>
        /* ============================================
           ESTILOS PARA IMPRESSÃO PROFISSIONAL
           ============================================ */
        @page {
            size: A4 portrait;
            margin: 10mm 8mm 10mm 8mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            background: white;
            color: #1a2332;
            font-size: 9pt;
            line-height: 1.3;
        }
        
        .print-container {
            max-width: 100%;
            padding: 0;
        }
        
        /* ===== CABEÇALHO ===== */
        .header-print {
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 3px double #d4a843;
            margin-bottom: 12px;
        }
        
        .header-print .logo-area {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 2px;
        }
        
        .header-print .logo-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #d4a843;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 900;
            color: white;
            flex-shrink: 0;
        }
        
        .header-print .escola-nome {
            font-size: 14pt;
            font-weight: 800;
            color: #1a2332;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .header-print .escola-nome small {
            display: block;
            font-size: 7.5pt;
            font-weight: 400;
            color: #64748b;
            text-transform: none;
            letter-spacing: 0;
        }
        
        .header-print .escola-info {
            font-size: 7pt;
            color: #475569;
            margin-top: 2px;
        }
        
        .header-print .escola-info span {
            margin: 0 4px;
        }
        
        .header-print .titulo-print {
            font-size: 13pt;
            font-weight: 700;
            color: #1a2332;
            margin-top: 6px;
            letter-spacing: 2px;
        }
        
        .header-print .subtitulo-print {
            font-size: 8.5pt;
            color: #64748b;
            margin-top: 2px;
        }
        
        .header-print .ano-letivo {
            font-size: 8pt;
            color: #d4a843;
            font-weight: 600;
            margin-top: 2px;
        }
        
        /* ===== TABELA ===== */
        .table-wrapper {
            margin-top: 8px;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        
        .schedule-table th {
            background: #1a2a3a;
            color: white;
            padding: 5px 4px;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 6.5pt;
            letter-spacing: 0.3px;
            border: 1px solid #1a2a3a;
        }
        
        .schedule-table th:first-child {
            border-radius: 6px 0 0 0;
        }
        
        .schedule-table th:last-child {
            border-radius: 0 6px 0 0;
        }
        
        .schedule-table td {
            padding: 4px 4px;
            border: 1px solid #d1d5db;
            vertical-align: middle;
            text-align: center;
        }
        
        .schedule-table tr:nth-child(even) td {
            background: #fafbfc;
        }
        
        .time-col {
            font-weight: 700;
            color: #475569;
            background: #f8fafc !important;
            white-space: nowrap;
            font-size: 6.5pt;
        }
        
        .time-col .tempo-nome {
            display: block;
            font-size: 5.5pt;
            font-weight: 400;
            color: #94a3b8;
        }
        
        .class-cell {
            min-width: 60px;
            padding: 3px 3px;
        }
        
        .class-cell .subject {
            font-weight: 700;
            color: #0f172a;
            display: block;
            font-size: 7pt;
        }
        
        .class-cell .teacher {
            font-size: 5.5pt;
            color: #64748b;
            display: block;
        }
        
        .class-cell .room {
            display: inline-block;
            background: #e2e8f0;
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 5pt;
            font-weight: 600;
            color: #475569;
            margin-top: 1px;
        }
        
        .class-cell .badge-free {
            color: #94a3b8;
            font-style: italic;
            font-size: 6.5pt;
        }
        
        .class-cell.has-class {
            background-color: #f8fafc;
            border-left: 2px solid #d4a843;
        }
        
        .class-cell.intervalo-cell {
            background: #fef9e7 !important;
            border-left: 2px solid #d4a843;
        }
        
        .class-cell.intervalo-cell .subject {
            color: #d4a843;
        }
        
        /* ===== RODAPÉ ===== */
        .footer-print {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 6.5pt;
            color: #94a3b8;
        }
        
        .footer-print .assinatura {
            display: flex;
            gap: 25px;
        }
        
        .footer-print .assinatura-item {
            text-align: center;
        }
        
        .footer-print .assinatura-item .linha {
            width: 80px;
            border-top: 1px solid #1a2332;
            margin: 12px auto 2px;
        }
        
        .footer-print .assinatura-item .cargo {
            font-size: 6pt;
            color: #64748b;
        }
        
        .footer-print .info-rodape {
            text-align: right;
        }
        
        .footer-print .info-rodape .data-impressao {
            font-size: 6pt;
            color: #94a3b8;
        }
        
        /* ===== LEGENDA ===== */
        .legenda-print {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            padding: 5px 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 6.5pt;
            color: #475569;
        }
        
        .legenda-print .item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .legenda-print .cor {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            border: 1px solid #d1d5db;
        }
        
        .legenda-print .cor.aula {
            background: white;
            border-left: 2px solid #d4a843;
        }
        
        .legenda-print .cor.intervalo {
            background: #fef9e7;
            border-left: 2px solid #d4a843;
        }
        
        .legenda-print .cor.livre {
            background: white;
        }
        
        .legenda-print .total-info {
            margin-left: auto;
            font-weight: 600;
            color: #1a2332;
        }
        
        /* ===== ESTADO VAZIO ===== */
        .empty-print {
            text-align: center;
            padding: 30px 20px;
            color: #94a3b8;
        }
        
        .empty-print .icon {
            font-size: 36px;
            display: block;
            margin-bottom: 8px;
        }
        
        .empty-print h3 {
            color: #4a5568;
            font-size: 13pt;
            margin: 0 0 5px;
        }
        
        /* ===== IMPRESSÃO ===== */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .schedule-table th {
                background: #1a2a3a !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .class-cell.intervalo-cell {
                background: #fef9e7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .class-cell.has-class {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .time-col {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .header-print .logo-placeholder {
                background: #d4a843 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .legenda-print {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .footer-print .assinatura-item .linha {
                border-top: 1px solid #1a2332 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .schedule-table {
                page-break-inside: avoid;
            }
            .schedule-table tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        
        <!-- ===== CABEÇALHO ===== -->
        <div class="header-print">
            <div class="logo-area">
                <div class="logo-placeholder">CR</div>
                <div>
                    <div class="escola-nome">
                        <?= htmlspecialchars($nomeEscola) ?>
                        <small>Educar para Transformar, Formar para a Vida</small>
                    </div>
                </div>
            </div>
            <div class="escola-info">
                <?php if ($endereco): ?>
                    <span>📍 <?= htmlspecialchars($endereco) ?></span>
                <?php endif; ?>
                <?php if ($cidade): ?>
                    <span>🏙️ <?= htmlspecialchars($cidade) ?></span>
                <?php endif; ?>
                <?php if ($telefone): ?>
                    <span>📞 <?= htmlspecialchars($telefone) ?></span>
                <?php endif; ?>
                <?php if ($email): ?>
                    <span>✉️ <?= htmlspecialchars($email) ?></span>
                <?php endif; ?>
                <?php if ($site): ?>
                    <span>🌐 <?= htmlspecialchars($site) ?></span>
                <?php endif; ?>
            </div>
            <div class="titulo-print">📚 GRADE HORÁRIA</div>
            <div class="subtitulo-print">
                <?php if ($turmaSelecionada): ?>
                    Turma: <?= htmlspecialchars($turmaSelecionada) ?>
                <?php else: ?>
                    Todas as Turmas
                <?php endif; ?>
            </div>
            <div class="ano-letivo">Ano Lectivo: <?= $ano_letivo ?></div>
        </div>
        
        <!-- ===== GRADE HORÁRIA ===== -->
        <?php if (count($horarios) > 0): ?>
            
            <div class="table-wrapper">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Horário</th>
                            <?php foreach ($diasSemana as $dia): ?>
                                <th><?= substr($dia, 0, -4) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($horariosUnicos)): ?>
                            <?php foreach ($horariosUnicos as $key => $horario): ?>
                                <tr>
                                    <td class="time-col">
                                        <?= date('H:i', strtotime($horario['hora_inicio'])) ?> - 
                                        <?= date('H:i', strtotime($horario['hora_fim'])) ?>
                                        <?php if (!empty($horario['tempo_nome'])): ?>
                                            <span class="tempo-nome"><?= htmlspecialchars($horario['tempo_nome']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($diasSemana as $dia): 
                                        $aula = null;
                                        foreach ($horarios as $h) {
                                            if ($h['dia_semana'] == $dia && 
                                                $h['hora_inicio'] == $horario['hora_inicio'] && 
                                                $h['hora_fim'] == $horario['hora_fim']) {
                                                $aula = $h;
                                                break;
                                            }
                                        }
                                        $isIntervalo = $aula && $aula['is_intervalo'] == 1;
                                    ?>
                                        <td class="class-cell <?= $aula ? ($isIntervalo ? 'intervalo-cell' : 'has-class') : '' ?>">
                                            <?php if ($aula): ?>
                                                <?php if ($isIntervalo): ?>
                                                    <span class="subject" style="color:#d4a843;">☕ INTERVALO</span>
                                                <?php else: ?>
                                                    <span class="subject"><?= htmlspecialchars($aula['disciplina']) ?></span>
                                                    <span class="teacher"><?= htmlspecialchars($aula['professor_nome'] ?? $aula['funcionario_nome'] ?? '-') ?></span>
                                                    <?php if (!empty($aula['sala'])): ?>
                                                        <span class="room">🏠 <?= htmlspecialchars($aula['sala']) ?></span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge-free">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:20px; color:#94a3b8;">
                                    Nenhum horário cadastrado para esta turma.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ===== LEGENDA ===== -->
            <div class="legenda-print">
                <span class="item">
                    <span class="cor aula"></span>
                    Aula
                </span>
                <span class="item">
                    <span class="cor intervalo"></span>
                    Intervalo
                </span>
                <span class="item">
                    <span class="cor livre"></span>
                    Horário Livre
                </span>
                <span class="total-info">
                    Total: <?= count($horarios) ?> horários
                </span>
            </div>
            
        <?php else: ?>
            <div class="empty-print">
                <span class="icon">📭</span>
                <h3>Nenhum horário cadastrado</h3>
                <p>Não há horários definidos para esta turma.</p>
            </div>
        <?php endif; ?>
        
        <!-- ===== RODAPÉ ===== -->
        <div class="footer-print">
            <div class="assinatura">
                <div class="assinatura-item">
                    <div class="linha"></div>
                    <div><strong>Diretor(a) Pedagógico</strong></div>
                    <div class="cargo">Direcção da Escola</div>
                </div>
                <div class="assinatura-item">
                    <div class="linha"></div>
                    <div><strong>Coordenador(a) Pedagógico</strong></div>
                    <div class="cargo">Coordenação Pedagógica</div>
                </div>
            </div>
            <div class="info-rodape">
                <div class="data-impressao">
                    Documento gerado em: <?= date('d/m/Y \à\s H:i') ?>
                </div>
                <div style="font-size: 6pt; color: #94a3b8; margin-top: 2px;">
                    <?= htmlspecialchars($nomeEscola) ?> - Todos os direitos reservados
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- ===== BOTÃO DE IMPRESSÃO ===== -->
    <div class="no-print" style="text-align: center; padding: 12px; background: #f8fafc; position: fixed; bottom: 0; left: 0; right: 0; border-top: 2px solid #e2e8f0; z-index: 1000;">
        <button onclick="window.print()" style="padding: 8px 30px; background: #d4a843; color: #1a2332; border: none; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer;">
            🖨️ Imprimir Grade Horária
        </button>
        <button onclick="window.close()" style="padding: 8px 20px; background: #f1f5f9; color: #4a5568; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; margin-left: 8px;">
            ✖ Fechar
        </button>
        <div style="margin-top: 4px; font-size: 9px; color: #94a3b8;">
            Use <strong>Ctrl+P</strong> ou o botão acima para imprimir em folha A4
        </div>
    </div>
    
    <script>
        // Fechar com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
</body>
</html>