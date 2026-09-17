<?php
// ============================================
// modules/escola/horarios/print.php
// Impressão da Grade Horária com Cabeçalho da Empresa
// Mostra apenas os tempos que têm aulas cadastradas
// Formato: A4 Paisagem (landscape)
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

$pdo = conectarBanco();
$pdo->exec("SET NAMES utf8mb4");

// ===== FILTROS =====
$selectedClasse = isset($_GET['classe']) ? $_GET['classe'] : '';
$selectedTurma  = isset($_GET['turma']) ? intval($_GET['turma']) : 0;

// ===== BUSCAR DADOS DA EMPRESA =====
function buscarDadosEmpresa($pdo) {
    $dados = [
        'nome' => 'Sistema Escolar',
        'endereco' => '',
        'telefone' => '',
        'celular' => '',
        'email' => '',
        'nif' => '',
        'logo' => ''
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM empresa WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            $dados['nome'] = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Sistema Escolar';
            
            $endereco_parts = [];
            if (!empty($empresa['endereco'])) $endereco_parts[] = $empresa['endereco'];
            if (!empty($empresa['numero']))   $endereco_parts[] = $empresa['numero'];
            if (!empty($empresa['bairro']))   $endereco_parts[] = $empresa['bairro'];
            if (!empty($empresa['cidade']))   $endereco_parts[] = $empresa['cidade'];
            if (!empty($empresa['estado']))   $endereco_parts[] = $empresa['estado'];
            if (!empty($empresa['cep']))      $endereco_parts[] = 'CEP: ' . $empresa['cep'];
            
            $dados['endereco']  = implode(', ', $endereco_parts);
            $dados['telefone']  = $empresa['telefone'] ?? '';
            $dados['celular']   = $empresa['celular'] ?? '';
            $dados['email']     = $empresa['email'] ?? '';
            $dados['nif']       = $empresa['cnpj'] ?? $empresa['inscricao_estadual'] ?? '';
            $dados['logo']      = $empresa['logo'] ?? '';
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar empresa: " . $e->getMessage());
    }
    
    return $dados;
}

$dados_empresa = buscarDadosEmpresa($pdo);

// ===== BUSCAR TURMA =====
$turma = null;
if ($selectedTurma > 0) {
    $stmt = $pdo->prepare("SELECT * FROM turmas WHERE id = ? LIMIT 1");
    $stmt->execute([$selectedTurma]);
    $turma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($turma && empty($selectedClasse)) {
        $selectedClasse = $turma['classe'] ?? '';
    }
}

// ===== BUSCAR TODOS OS TEMPOS =====
$tempos = [];
try {
    $stmt = $pdo->query("SELECT * FROM tempos WHERE status = 'ativo' ORDER BY ordem, hora_inicio");
    $tempos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tempos as &$t) {
        if (!isset($t['is_intervalo'])) {
            $t['is_intervalo'] = (stripos($t['nome'], 'intervalo') !== false) ? 1 : 0;
        }
    }
    unset($t);
} catch (Exception $e) {
    $tempos = [];
}

// ===== BUSCAR HORÁRIOS DA TURMA =====
$horarios = [];
if ($selectedTurma > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT h.*, 
                   f.nome as professor_nome,
                   f.cargo as professor_cargo,
                   tp.nome as tempo_nome,
                   tp.ordem as tempo_ordem
            FROM horarios h
            LEFT JOIN funcionarios f ON h.funcionario_id = f.id
            LEFT JOIN tempos tp ON h.tempo_id = tp.id
            WHERE h.turma_id = ?
            ORDER BY FIELD(h.dia_semana, 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'), 
                     COALESCE(tp.ordem, 999), h.hora_inicio
        ");
        $stmt->execute([$selectedTurma]);
        $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $horarios = [];
    }
}

// ===== AGRUPAR HORÁRIOS POR TEMPO_ID E POR HORA =====
$horariosPorTempo = [];
foreach ($horarios as $h) {
    $tid = intval($h['tempo_id'] ?? 0);
    $keyHora = $h['hora_inicio'] . '|' . $h['hora_fim'];
    
    if ($tid > 0) {
        $horariosPorTempo[$tid][$h['dia_semana']] = $h;
    }
    if (!isset($horariosPorTempo[$keyHora])) {
        $horariosPorTempo[$keyHora] = [];
    }
    $horariosPorTempo[$keyHora][$h['dia_semana']] = $h;
}

$diasSemana = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

// ===== FILTRAR APENAS TEMPOS QUE TÊM AULAS NESTA TURMA =====
$temposComAulas = [];
foreach ($tempos as $tempo) {
    $tid = intval($tempo['id']);
    $keyHora = $tempo['hora_inicio'] . '|' . $tempo['hora_fim'];
    
    $temAula = false;
    foreach ($diasSemana as $dia) {
        if (isset($horariosPorTempo[$tid][$dia]) || isset($horariosPorTempo[$keyHora][$dia])) {
            $temAula = true;
            break;
        }
    }
    
    if ($temAula) {
        $temposComAulas[] = $tempo;
    }
}

// Ordenar por ordem
usort($temposComAulas, function($a, $b) {
    $oA = intval($a['ordem'] ?? 999);
    $oB = intval($b['ordem'] ?? 999);
    if ($oA === $oB) {
        return strcmp($a['hora_inicio'], $b['hora_inicio']);
    }
    return $oA - $oB;
});

// ===== SE NÃO HOUVER TEMPOS MAS HOUVER HORÁRIOS, CRIAR TEMPOS VIRTUAIS =====
if (empty($temposComAulas) && !empty($horarios)) {
    $temposMap = [];
    foreach ($horarios as $h) {
        $key = $h['hora_inicio'] . '|' . $h['hora_fim'];
        if (!isset($temposMap[$key])) {
            $temposMap[$key] = [
                'id' => intval($h['tempo_id'] ?? 0),
                'nome' => $h['tempo_nome'] ?? (date('H:i', strtotime($h['hora_inicio'])) . ' - ' . date('H:i', strtotime($h['hora_fim']))),
                'hora_inicio' => $h['hora_inicio'],
                'hora_fim' => $h['hora_fim'],
                'ordem' => intval($h['tempo_ordem'] ?? 999),
                'is_intervalo' => $h['is_intervalo'] ?? 0
            ];
        }
    }
    $temposComAulas = array_values($temposMap);
    usort($temposComAulas, function($a, $b) {
        return $a['ordem'] - $b['ordem'];
    });
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Grade Horária - <?= htmlspecialchars($dados_empresa['nome']) ?></title>
<style>
    /* ===== A4 PAISAGEM ===== */
    @page {
        size: A4 landscape;
        margin: 8mm;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        background: #f0f0f0;
        color: #1a2332;
    }
    .page {
        max-width: 297mm;
        width: 100%;
        margin: 0 auto;
        background: #fff;
        padding: 8mm;
    }

    /* ===== CABEÇALHO DA EMPRESA ===== */
    .header-empresa {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 6px;
        border-bottom: 3px double #1a2332;
        margin-bottom: 8px;
        gap: 12px;
    }
    .header-empresa .logo-area {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 0;
    }
    .header-empresa .logo-icon {
        width: 50px;
        height: 50px;
        background: #1a2332;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #c9a84c;
        font-size: 24px;
        font-weight: bold;
        border: 3px solid #c9a84c;
        flex-shrink: 0;
    }
    .header-empresa .logo-img {
        width: 50px;
        height: 50px;
        object-fit: contain;
        flex-shrink: 0;
    }
    .header-empresa .empresa-info { min-width: 0; flex: 1; }
    .header-empresa .empresa-info h1 {
        font-size: 15px;
        font-weight: 800;
        color: #1a2332;
        line-height: 1.2;
    }
    .header-empresa .empresa-info .endereco {
        font-size: 9px;
        color: #555;
        margin-top: 2px;
    }
    .header-empresa .empresa-info .contactos {
        font-size: 9px;
        color: #555;
        margin-top: 1px;
    }
    .header-empresa .doc-info {
        text-align: right;
        flex-shrink: 0;
        max-width: 170px;
    }
    .header-empresa .doc-info h2 {
        font-size: 13px;
        font-weight: 800;
        color: #c9a84c;
        letter-spacing: 0.5px;
        line-height: 1.2;
    }
    .header-empresa .doc-info .nif-box {
        background: #1a2332;
        color: #fff;
        padding: 2px 8px;
        border-radius: 3px;
        font-weight: bold;
        font-size: 9px;
        display: inline-block;
        margin-top: 3px;
    }
    .header-empresa .doc-info .data-emissao {
        font-size: 8px;
        color: #666;
        margin-top: 3px;
    }

    /* ===== INFO DA TURMA ===== */
    .info-turma {
        background: #f8fafc;
        border: 1px solid #ddd;
        border-left: 4px solid #c9a84c;
        padding: 6px 12px;
        border-radius: 4px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 10px;
        color: #4a5568;
    }
    .info-turma strong { color: #1a2332; }

    /* ===== TABELA ===== */
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5px;
        margin-bottom: 6px;
        table-layout: fixed;
    }
    thead th {
        background: #1a2332;
        color: #fff;
        padding: 6px 4px;
        text-align: center;
        border: 1px solid #1a2332;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    thead th:first-child { width: 80px; text-align: left; padding-left: 8px; }
    tbody td {
        padding: 5px 4px;
        border: 1px solid #d1d5db;
        vertical-align: top;
        text-align: center;
        font-size: 8px;
        height: 55px;
        word-wrap: break-word;
    }
    .time-col {
        background: #f1f5f9;
        font-weight: 700;
        color: #1a2332;
        font-size: 8.5px;
        text-align: left;
        padding: 5px 8px !important;
        vertical-align: middle !important;
    }
    .time-col .tempo-nome {
        font-size: 7.5px;
        color: #64748b;
        display: block;
        margin-top: 2px;
        font-weight: 600;
    }
    
    /* ===== CÉLULA DE AULA ===== */
    .aula-cell {
        background: #fafbfc;
        border-left: 3px solid #c9a84c !important;
        text-align: left !important;
        padding: 5px 6px !important;
    }
    .aula-cell.intervalo {
        background: #fef9e7;
        border-left-color: #d4a843 !important;
    }
    .aula-cell .subject {
        font-weight: 700;
        color: #0f172a;
        display: block;
        font-size: 9px;
        line-height: 1.15;
        margin-bottom: 2px;
    }
    .aula-cell.intervalo .subject { color: #d4a843; }
    .aula-cell .teacher {
        font-size: 7.5px;
        color: #64748b;
        display: block;
        line-height: 1.2;
    }
    .aula-cell .room {
        display: inline-block;
        background: #e2e8f0;
        padding: 0 5px;
        border-radius: 6px;
        font-size: 7px;
        font-weight: 600;
        color: #475569;
        margin-top: 2px;
    }
    .empty-cell {
        color: #cbd5e1;
        font-size: 14px;
        font-style: italic;
    }

    /* ===== RODAPÉ ===== */
    .rodape {
        margin-top: 12px;
        padding-top: 8px;
        border-top: 1px solid #1a2332;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 8px;
        color: #666;
    }
    .rodape .assinatura {
        text-align: center;
        min-width: 160px;
    }
    .rodape .assinatura .linha {
        width: 150px;
        border-top: 1px solid #333;
        margin: 20px auto 3px;
    }
    .rodape .info-doc {
        text-align: right;
        font-size: 7.5px;
        color: #888;
    }

    /* ===== BOTÕES ===== */
    .no-print {
        text-align: center;
        padding: 15px;
        max-width: 297mm;
        margin: 0 auto;
    }
    .btn {
        padding: 11px 32px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        margin: 0 5px;
        transition: all 0.3s;
    }
    .btn-print { background: #1a2332; color: #fff; }
    .btn-print:hover { background: #2d3748; }
    .btn-close { background: #e74c3c; color: #fff; }
    .btn-close:hover { background: #c0392b; }

    /* ===== IMPRESSÃO ===== */
    @media print {
        body { background: #fff !important; }
        .page { padding: 0 !important; max-width: 100% !important; }
        .no-print { display: none !important; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        tr { page-break-inside: avoid; }
    }
</style>
<script>
    function imprimir() {
        setTimeout(function() { window.print(); }, 500);
    }
    window.onload = function() {
        if (window.location.search.indexOf("print=1") > -1) {
            imprimir();
        }
    };
</script>
</head>
<body>
<div class="page">

    <!-- ===== CABEÇALHO DA EMPRESA ===== -->
    <div class="header-empresa">
        <div class="logo-area">
            <?php 
            $logo_path = '../../../' . ($dados_empresa['logo'] ?? '');
            if (!empty($dados_empresa['logo']) && file_exists($logo_path)): 
            ?>
                <img src="<?= $logo_path ?>" alt="Logo" class="logo-img">
            <?php else: ?>
                <div class="logo-icon">🎓</div>
            <?php endif; ?>
            <div class="empresa-info">
                <h1><?= htmlspecialchars($dados_empresa['nome'], ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if (!empty($dados_empresa['endereco'])): ?>
                    <div class="endereco">📍 <?= htmlspecialchars($dados_empresa['endereco'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <div class="contactos">
                    <?php if (!empty($dados_empresa['telefone'])): ?>
                        📞 <?= htmlspecialchars($dados_empresa['telefone'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                    <?php if (!empty($dados_empresa['celular'])): ?>
                        | 📱 <?= htmlspecialchars($dados_empresa['celular'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                    <?php if (!empty($dados_empresa['email'])): ?>
                        | ✉️ <?= htmlspecialchars($dados_empresa['email'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="doc-info">
            <h2>GRADE HORÁRIA</h2>
            <?php if (!empty($dados_empresa['nif'])): ?>
                <div class="nif-box">NIF: <?= htmlspecialchars($dados_empresa['nif'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="data-emissao">Emitido em: <?= date('d/m/Y H:i') ?></div>
        </div>
    </div>

    <!-- ===== INFO DA TURMA ===== -->
    <div class="info-turma">
        <div>
            <strong>📖 Classe:</strong> <?= htmlspecialchars($selectedClasse ?: '-') ?>
            &nbsp;|&nbsp;
            <strong>🏫 Turma:</strong> <?= htmlspecialchars($turma['nome'] ?? '-') ?>
            <?php if (!empty($turma['ano_letivo'])): ?>
                &nbsp;|&nbsp; <strong>Ano Letivo:</strong> <?= htmlspecialchars($turma['ano_letivo']) ?>
            <?php endif; ?>
            <?php if (!empty($turma['turno'])): ?>
                &nbsp;|&nbsp; <strong>Turno:</strong> <?= ucfirst(htmlspecialchars($turma['turno'])) ?>
            <?php endif; ?>
            <?php if (!empty($turma['sala'])): ?>
                &nbsp;|&nbsp; <strong>Sala:</strong> <?= htmlspecialchars($turma['sala']) ?>
            <?php endif; ?>
            <?php if (!empty($turma['curso'])): ?>
                &nbsp;|&nbsp; <strong>Curso:</strong> <?= htmlspecialchars($turma['curso']) ?>
            <?php endif; ?>
        </div>
        <div>
            <strong>📅 Período:</strong> <?= date('d/m/Y') ?>
        </div>
    </div>

    <!-- ===== TABELA ===== -->
    <?php if (!empty($temposComAulas)): ?>
    <table>
        <thead>
            <tr>
                <th>Horário</th>
                <?php foreach ($diasSemana as $dia): ?>
                    <th><?= substr($dia, 0, -4) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($temposComAulas as $tempo): 
                $tid = intval($tempo['id']);
                $keyHora = $tempo['hora_inicio'] . '|' . $tempo['hora_fim'];
            ?>
                <tr>
                    <td class="time-col">
                        <?= date('H:i', strtotime($tempo['hora_inicio'])) ?> - 
                        <?= date('H:i', strtotime($tempo['hora_fim'])) ?>
                        <span class="tempo-nome"><?= htmlspecialchars($tempo['nome']) ?></span>
                    </td>
                    <?php foreach ($diasSemana as $dia): 
                        $aula = $horariosPorTempo[$tid][$dia] ?? $horariosPorTempo[$keyHora][$dia] ?? null;
                        $isIntervalo = $aula && ($aula['is_intervalo'] ?? 0) == 1;
                    ?>
                        <td class="<?= $aula ? 'aula-cell' . ($isIntervalo ? ' intervalo' : '') : '' ?>">
                            <?php if ($aula): ?>
                                <?php if ($isIntervalo): ?>
                                    <span class="subject">☕ INTERVALO</span>
                                <?php else: ?>
                                    <span class="subject"><?= htmlspecialchars($aula['disciplina']) ?></span>
                                    <span class="teacher">👨‍🏫 <?= htmlspecialchars($aula['professor_nome'] ?? '-') ?></span>
                                    <?php if (!empty($aula['sala'])): ?>
                                        <span class="room">🏠 <?= htmlspecialchars($aula['sala']) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="empty-cell">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div style="text-align:center; padding:40px; color:#94a3b8; font-size:12px;">
            📭 Nenhum horário cadastrado para esta turma.
        </div>
    <?php endif; ?>

    <!-- ===== RODAPÉ ===== -->
    <div class="rodape">
        <div>
            <div><strong><?= htmlspecialchars($dados_empresa['nome'], ENT_QUOTES, 'UTF-8') ?></strong></div>
            <?php if (!empty($dados_empresa['nif'])): ?>
                <div>NIF: <?= htmlspecialchars($dados_empresa['nif'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div>Documento gerado eletronicamente em <?= date('d/m/Y \à\s H:i:s') ?></div>
        </div>
        <div class="assinatura">
            <div class="linha"></div>
            <div>Assinatura do Responsável</div>
        </div>
        <div class="info-doc">
            <div>Sistema de Gestão Escolar</div>
            <div>Página 1</div>
        </div>
    </div>

</div>

<!-- ===== BOTÕES ===== -->
<div class="no-print">
    <button onclick="imprimir()" class="btn btn-print">🖨️ IMPRIMIR</button>
    <button onclick="window.close()" class="btn btn-close">✕ FECHAR</button>
</div>

</body>
</html>