<?php
// ============================================
// modules/escola/turmas/vagas.php - Verificar Vagas (CORRIGIDO)
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

// ===== BUSCAR DADOS =====
$turmas = [];
try {
    // ✅ CORREÇÃO: Query sem subquery problemática
    $turmas = $pdo->query("
        SELECT t.*
        FROM turmas t
        ORDER BY t.classe, t.nome
    ")->fetchAll();
    
    // ✅ Buscar contagem de alunos separadamente
    foreach ($turmas as &$t) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE TURMA = ? AND status = 'ativo'");
            $stmt->execute([$t['nome']]);
            $t['total_alunos'] = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
            $t['total_alunos'] = 0;
        }
    }
    
} catch (Exception $e) {
    // Se a tabela turmas não existir, criar
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS turmas (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nome VARCHAR(50) NOT NULL,
                classe VARCHAR(10) NOT NULL,
                curso VARCHAR(50) NOT NULL,
                ano_letivo VARCHAR(10),
                turno ENUM('manha','tarde','noite','integral') DEFAULT 'manha',
                sala VARCHAR(20),
                limite INT DEFAULT 30,
                idades VARCHAR(20) DEFAULT '',
                disciplinas TEXT,
                professor_id INT,
                status ENUM('ativa','concluida','cancelada') DEFAULT 'ativa',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $turmas = [];
    } catch (Exception $e2) {
        $turmas = [];
    }
}


?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .page-header .subtitle {
        color: #94a3b8;
        font-size: 14px;
        margin: 2px 0 0;
    }
    
    .btn {
        padding: 8px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        cursor: pointer;
    }
    
    .btn-primary {
        background: #c9a84c;
        color: #1a2332;
    }
    
    .btn-primary:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201,168,76,0.3);
    }
    
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .btn-secondary:hover {
        background: #e2e8f0;
    }
    
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    
    .btn-success:hover {
        background: #27ae60;
    }
    
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #c0392b;
    }
    
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    
    .btn-warning:hover {
        background: #d68910;
    }
    
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    
    .btn-info:hover {
        background: #2980b9;
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 11px;
        border-radius: 6px;
    }
    
    .actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-card {
        background: white;
        padding: 18px 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        border-left: 4px solid #c9a84c;
        transition: all 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .stat-card .number {
        font-size: 26px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .stat-card .label {
        font-size: 12px;
        color: #94a3b8;
        margin: 3px 0 0;
    }
    
    .stat-card .icon {
        font-size: 24px;
        display: block;
        margin-bottom: 5px;
    }
    
    .stat-card.total { border-left-color: #c9a84c; }
    .stat-card.ocupadas { border-left-color: #3498db; }
    .stat-card.disponiveis { border-left-color: #2ecc71; }
    .stat-card.lotadas { border-left-color: #e74c3c; }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        min-width: 900px;
    }
    
    .table th {
        background: #f8fafc;
        padding: 10px 12px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    
    .table tr:hover {
        background: #fafbfc;
    }
    
    /* ===== ESTILOS DE ALERTA PARA VAGAS ===== */
    .alerta-emergencia {
        background: #fee2e2 !important;
        border-left: 4px solid #e74c3c !important;
        animation: alerta-pisca 1.5s ease-in-out infinite;
    }
    
    .alerta-emergencia td {
        background: #fee2e2 !important;
    }
    
    .alerta-critico {
        background: #fffbeb !important;
        border-left: 4px solid #f39c12 !important;
    }
    
    .alerta-critico td {
        background: #fffbeb !important;
    }
    
    .alerta-atencao {
        background: #f0f9ff !important;
        border-left: 4px solid #3498db !important;
    }
    
    .alerta-atencao td {
        background: #f0f9ff !important;
    }
    
    @keyframes alerta-pisca {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.85; background: #fecaca !important; }
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .turno-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
    }
    
    .turno-manha { background: #fef3c7; color: #92400e; }
    .turno-tarde { background: #dbeafe; color: #1e40af; }
    .turno-noite { background: #e0e7ff; color: #3730a3; }
    .turno-integral { background: #d1fae5; color: #065f46; }
    
    .classe-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        background: #e0f2fe;
        color: #0369a1;
    }
    
    .vagas-badge {
        display: inline-block;
        padding: 4px 14px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-align: center;
        min-width: 40px;
    }
    
    .vagas-disponivel { 
        background: #d1fae5; 
        color: #065f46; 
    }
    
    .vagas-poucas { 
        background: #fef3c7; 
        color: #92400e; 
    }
    
    .vagas-lotado { 
        background: #fee2e2; 
        color: #991b1b;
        animation: pulse 1s ease-in-out infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .situacao-emergencia {
        background: #e74c3c !important;
        color: #fff !important;
        font-weight: 700;
        padding: 4px 16px;
        border-radius: 20px;
        animation: pulse 1s ease-in-out infinite;
    }
    
    .situacao-critico {
        background: #f39c12 !important;
        color: #fff !important;
        font-weight: 700;
        padding: 4px 16px;
        border-radius: 20px;
    }
    
    .situacao-atencao {
        background: #3498db !important;
        color: #fff !important;
        font-weight: 600;
        padding: 4px 16px;
        border-radius: 20px;
    }
    
    .situacao-normal {
        background: #2ecc71 !important;
        color: #fff !important;
        font-weight: 600;
        padding: 4px 16px;
        border-radius: 20px;
    }
    
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
        color: #4a5568;
        margin: 0 0 5px;
    }
    
    .acoes-rapidas {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 20px;
        justify-content: center;
        padding: 15px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #eef2f7;
    }
    
    /* ===== BANNER DE EMERGÊNCIA ===== */
    .banner-emergencia {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: #fff;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: none;
        align-items: center;
        gap: 15px;
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        animation: slideDown 0.5s ease;
    }
    
    .banner-emergencia.ativo {
        display: flex;
    }
    
    .banner-emergencia .icon {
        font-size: 30px;
    }
    
    .banner-emergencia .conteudo {
        flex: 1;
    }
    
    .banner-emergencia .titulo {
        font-size: 16px;
        font-weight: 700;
        margin: 0;
    }
    
    .banner-emergencia .descricao {
        font-size: 13px;
        opacity: 0.9;
        margin: 2px 0 0;
    }
    
    .banner-emergencia .contador {
        background: rgba(255,255,255,0.2);
        padding: 8px 20px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 20px;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* ===== BARRA DE PROGRESSO ===== */
    .progress-bar-container {
        width: 80px;
        height: 8px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        display: inline-block;
        vertical-align: middle;
        margin-right: 5px;
    }
    
    .progress-bar {
        height: 100%;
        border-radius: 10px;
        transition: width 0.5s ease;
    }
    
    .progress-bar.emergencia { background: #e74c3c; }
    .progress-bar.critico { background: #f39c12; }
    .progress-bar.atencao { background: #3498db; }
    .progress-bar.normal { background: #2ecc71; }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .actions {
            flex-direction: column;
        }
        .actions .btn {
            justify-content: center;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .stat-card {
            padding: 14px 16px;
        }
        .stat-card .number {
            font-size: 22px;
        }
        .table {
            font-size: 12px;
            min-width: 750px;
        }
        .table th, .table td {
            padding: 6px 8px;
        }
        .acoes-rapidas {
            flex-direction: column;
            align-items: stretch;
        }
        .acoes-rapidas .btn {
            justify-content: center;
        }
        .progress-bar-container {
            width: 50px;
        }
        .banner-emergencia {
            flex-direction: column;
            text-align: center;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📊 Verificar Vagas</h1>
        <p class="subtitle">Relatório de vagas disponíveis por turma</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php
// ===== CALCULAR ESTATÍSTICAS =====
$totalVagas = 0;
$totalOcupadas = 0;
$totalDisponiveis = 0;
$totalLotadas = 0;
$totalCriticas = 0;
$totalAtencao = 0;

foreach($turmas as $t) {
    $limite = $t['limite'] ?? 30;
    $alunos = $t['total_alunos'] ?? 0;
    $vagas = $limite - $alunos;
    
    $totalVagas += $limite;
    $totalOcupadas += $alunos;
    $totalDisponiveis += max(0, $vagas);
    
    if ($vagas <= 0) {
        $totalLotadas++;
    } elseif ($vagas <= 3) {
        $totalCriticas++;
    } elseif ($vagas <= 5) {
        $totalAtencao++;
    }
}

// Verificar se há emergência
$temEmergencia = $totalLotadas > 0;
?>

<!-- ===== BANNER DE EMERGÊNCIA ===== -->
<div class="banner-emergencia <?= $temEmergencia ? 'ativo' : '' ?>">
    <span class="icon">🚨</span>
    <div class="conteudo">
        <p class="titulo">ALERTA DE EMERGÊNCIA - TURMAS LOTADAS</p>
        <p class="descricao">Existem <?= $totalLotadas ?> turma(s) com capacidade esgotada. Ação imediata necessária!</p>
    </div>
    <div class="contador"><?= $totalLotadas ?></div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card total">
        <span class="icon">🏫</span>
        <div class="number"><?= $totalVagas ?></div>
        <div class="label">Total de Vagas</div>
    </div>
    <div class="stat-card ocupadas">
        <span class="icon">👨‍🎓</span>
        <div class="number"><?= $totalOcupadas ?></div>
        <div class="label">Vagas Ocupadas</div>
    </div>
    <div class="stat-card disponiveis">
        <span class="icon">✅</span>
        <div class="number"><?= $totalDisponiveis ?></div>
        <div class="label">Vagas Disponíveis</div>
    </div>
    <div class="stat-card lotadas">
        <span class="icon">🔴</span>
        <div class="number"><?= $totalLotadas ?></div>
        <div class="label">Turmas Lotadas</div>
    </div>
</div>

<!-- Filtros -->
<div class="filtros" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
    <select id="filtroSituacao" onchange="filtrarVagas()" style="padding: 8px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; background: white; outline: none;">
        <option value="todos">📊 Todos</option>
        <option value="emergencia">🚨 Lotados (0 vagas)</option>
        <option value="critico">⚠️ Crítico (1-3 vagas)</option>
        <option value="atencao">⚡ Atenção (4-5 vagas)</option>
        <option value="normal">✅ Disponível (>5 vagas)</option>
    </select>
    <input type="text" id="filtroBusca" placeholder="🔍 Buscar turma..." onkeyup="filtrarVagas()" style="padding: 8px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; flex: 1; min-width: 200px;">
</div>

<!-- Tabela -->
<div class="table-responsive">
    <table class="table" id="tabelaVagas">
        <thead>
            <tr>
                <th>#</th>
                <th>Turma</th>
                <th>Classe</th>
                <th>Curso</th>
                <th>Turno</th>
                <th>Sala</th>
                <th>Idades</th>
                <th>Limite</th>
                <th>Matriculados</th>
                <th>Vagas</th>
                <th>Ocupação</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($turmas) > 0): ?>
                <?php 
                $contador = 0;
                foreach($turmas as $t): 
                    $contador++;
                    $limite = $t['limite'] ?? 30;
                    $alunos = $t['total_alunos'] ?? 0;
                    $vagas = $limite - $alunos;
                    $percentual = $limite > 0 ? round(($alunos / $limite) * 100, 1) : 0;
                    
                    // Determinar nível de alerta
                    if ($vagas <= 0) {
                        $alerta = 'emergencia';
                        $classeAlerta = 'alerta-emergencia';
                        $vagasClass = 'vagas-lotado';
                        $situacao = '🚨 LOTADO';
                        $situacaoClass = 'situacao-emergencia';
                        $progressClass = 'emergencia';
                    } elseif ($vagas <= 3) {
                        $alerta = 'critico';
                        $classeAlerta = 'alerta-critico';
                        $vagasClass = 'vagas-poucas';
                        $situacao = '⚠️ Crítico';
                        $situacaoClass = 'situacao-critico';
                        $progressClass = 'critico';
                    } elseif ($vagas <= 5) {
                        $alerta = 'atencao';
                        $classeAlerta = 'alerta-atencao';
                        $vagasClass = 'vagas-poucas';
                        $situacao = '⚡ Atenção';
                        $situacaoClass = 'situacao-atencao';
                        $progressClass = 'atencao';
                    } else {
                        $alerta = 'normal';
                        $classeAlerta = '';
                        $vagasClass = 'vagas-disponivel';
                        $situacao = '✅ Disponível';
                        $situacaoClass = 'situacao-normal';
                        $progressClass = 'normal';
                    }
                ?>
                <tr class="<?= $classeAlerta ?>" data-alerta="<?= $alerta ?>" data-nome="<?= strtolower($t['nome']) ?>">
                    <td><?= $contador ?></td>
                    <td><strong><?= htmlspecialchars($t['nome']) ?></strong></td>
                    <td><span class="classe-badge"><?= htmlspecialchars($t['classe'] ?? '-') ?></span></td>
                    <td><?= htmlspecialchars($t['curso'] ?? '-') ?></td>
                    <td><span class="turno-badge turno-<?= $t['turno'] ?? 'manha' ?>"><?= ucfirst($t['turno'] ?? 'Manhã') ?></span></td>
                    <td><?= htmlspecialchars($t['sala'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($t['idades'] ?? '-') ?></td>
                    <td><strong><?= $limite ?></strong></td>
                    <td><strong><?= $alunos ?></strong></td>
                    <td>
                        <span class="vagas-badge <?= $vagasClass ?>">
                            <?= max(0, $vagas) ?>
                        </span>
                    </td>
                    <td>
                        <div class="progress-bar-container">
                            <div class="progress-bar <?= $progressClass ?>" style="width: <?= $percentual ?>%;"></div>
                        </div>
                        <span style="font-size: 11px; color: #4a5568;"><?= $percentual ?>%</span>
                    </td>
                    <td>
                        <span class="vagas-badge <?= $situacaoClass ?>">
                            <?= $situacao ?>
                        </span>
                        <?php if ($alerta == 'emergencia'): ?>
                            <br><small style="color: #e74c3c; font-weight: 700;">🚨 SEM VAGAS!</small>
                        <?php elseif ($alerta == 'critico'): ?>
                            <br><small style="color: #f39c12; font-weight: 600;">⚠️ Últimas vagas!</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhuma turma cadastrada</h3>
                            <p>Cadastre turmas para verificar as vagas disponíveis.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Ações Rápidas -->
<div class="acoes-rapidas">
    <span style="color: #94a3b8; font-size: 13px; font-weight: 500;">⚡ Ações:</span>
    <a href="relatorio_vagas.php" class="btn btn-success">📄 Gerar Relatório de Vagas</a>
    <a href="exportar_vagas.php" class="btn btn-primary">📤 Exportar Dados</a>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<script>
// ===== FUNÇÃO DE FILTRAGEM =====
function filtrarVagas() {
    const filtroSituacao = document.getElementById('filtroSituacao').value;
    const filtroBusca = document.getElementById('filtroBusca').value.toLowerCase();
    const linhas = document.querySelectorAll('#tabelaVagas tbody tr');
    
    linhas.forEach(linha => {
        const alerta = linha.dataset.alerta || 'normal';
        const nome = linha.dataset.nome || '';
        
        let mostrar = true;
        
        // Filtrar por situação
        if (filtroSituacao !== 'todos' && alerta !== filtroSituacao) {
            mostrar = false;
        }
        
        // Filtrar por busca
        if (filtroBusca && !nome.includes(filtroBusca)) {
            mostrar = false;
        }
        
        linha.style.display = mostrar ? '' : 'none';
    });
}

// ===== NOTIFICAÇÃO DE EMERGÊNCIA =====
document.addEventListener('DOMContentLoaded', function() {
    const emergencias = document.querySelectorAll('.alerta-emergencia');
    if (emergencias.length > 0) {
        // Criar notificação flutuante
        const notificacao = document.createElement('div');
        notificacao.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #e74c3c;
            color: #fff;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(231, 76, 60, 0.4);
            z-index: 9999;
            font-weight: 600;
            animation: slideDown 0.5s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        `;
        notificacao.innerHTML = `
            <span style="font-size: 24px;">🚨</span>
            <div>
                <div style="font-weight: 700;">EMERGÊNCIA</div>
                <div style="font-size: 13px; opacity: 0.9;">${emergencias.length} turma(s) lotada(s)</div>
            </div>
        `;
        notificacao.onclick = function() {
            document.querySelector('.alerta-emergencia').scrollIntoView({ behavior: 'smooth' });
        };
        document.body.appendChild(notificacao);
        
        // Remover após 10 segundos
        setTimeout(() => {
            notificacao.style.opacity = '0';
            notificacao.style.transition = 'opacity 0.5s';
            setTimeout(() => notificacao.remove(), 500);
        }, 10000);
    }
});
</script>

<?php include '../includes/footer_escola.php'; ?>