<?php
// ============================================
// modules/escola/index.php - Dashboard da Escola
// ============================================

require_once '../../config/database.php';
require_once '../../config/app_modes.php';
require_once 'includes/contadores.php';

// ============================================
// 🚀 GERAR RELATÓRIO AGT AUTOMATICAMENTE
// ============================================
// Sempre que o dashboard for acessado, gera o relatório em background
// Isso não bloqueia o carregamento da página
try {
    // Incluir o gerador automático
    require_once 'financeiro/relatorios/auto_agt.php';
    // O relatório é gerado automaticamente na inclusão do arquivo
} catch (Exception $e) {
    // Não interrompe o carregamento do dashboard se houver erro
    error_log("⚠️ Erro ao gerar relatório AGT: " . $e->getMessage());
}

// ============================================
// CONTINUAÇÃO DO DASHBOARD
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ... resto do código ...







if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ============================================
// TOTAIS
// ============================================
$totalAlunos = contarAlunos();
$totalProfessores = contarProfessores();
$totalTurmas = contarTurmas();
$totalDisciplinas = contarDisciplinas();

// Últimos alunos
$ultimosAlunos = [];
try {
    global $pdo;
    $ultimosAlunos = $pdo->query("SELECT * FROM alunos WHERE status = 'ativo' OR status IS NULL ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {}

// Alunos por Turma
$alunosPorTurma = [];
try {
    global $pdo;
    $alunosPorTurma = $pdo->query("
        SELECT TURMA as nome, COUNT(*) as total
        FROM alunos
        WHERE (status = 'ativo' OR status IS NULL) 
        AND TURMA IS NOT NULL AND TURMA != ''
        GROUP BY TURMA ORDER BY TURMA
    ")->fetchAll();
} catch (Exception $e) {}

// ============================================
// STATUS DO SISTEMA
// ============================================

// Verificar status da conexão
$statusConexao = 'online';
$tempoAtivo = 0;
$ultimoPing = 'Nunca';

try {
    global $pdo;
    if ($pdo) {
        $pdo->query("SELECT 1");
        $statusConexao = 'online';
    }
} catch (Exception $e) {
    $statusConexao = 'offline';
}

// Tempo de atividade do sistema
$arquivo_status = __DIR__ . '/../../temp/status.json';
if (file_exists($arquivo_status)) {
    $dados = json_decode(file_get_contents($arquivo_status), true);
    if ($dados && isset($dados['inicio'])) {
        $tempoAtivo = time() - $dados['inicio'];
        $ultimoPing = isset($dados['ultimo_ping']) ? date('d/m/Y H:i:s', $dados['ultimo_ping']) : 'Nunca';
    }
}

// Contar usuários online (sessões ativas)
$usuariosOnline = 0;
try {
    // Tentar contar sessões ativas (se tiver tabela de sessões)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT session_id) as total FROM sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $result = $stmt->fetch();
    $usuariosOnline = $result['total'] ?? 0;
} catch (Exception $e) {
    // Se não tiver tabela, estimar por usuários logados
    $usuariosOnline = 1; // Pelo menos o usuário atual
}

// ============================================
// CONTADOR DE CHAMADAS PENDENTES
// ============================================
$chamadasPendentes = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM chamadas_internas WHERE usuario_destino = ? AND status = 'pendente'");
    $stmt->execute([$_SESSION['usuario_id']]);
    $result = $stmt->fetch();
    $chamadasPendentes = $result['total'] ?? 0;
} catch (Exception $e) {
    // Tabela pode não existir ainda
}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

include 'includes/header_escola.php';
?>

<style>
/* ============================================
   ESTILOS DO DASHBOARD
   ============================================ */

/* Cards de estatísticas */
.stat-card {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.stat-card .icon-bg {
    position: absolute;
    right: -10px;
    bottom: -10px;
    font-size: 80px;
    opacity: 0.1;
    pointer-events: none;
}

/* Indicador de status do sistema */
#statusSistema {
    transition: all 0.3s ease;
    font-family: 'Segoe UI', Arial, sans-serif;
    cursor: default;
    user-select: none;
}

#statusSistema:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

#statusSistema .pulsar {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 8px;
    animation: pulsar 2s ease-in-out infinite;
}

#statusSistema .pulsar.online {
    background: #2ecc71;
    box-shadow: 0 0 10px rgba(46, 204, 113, 0.5);
}

#statusSistema .pulsar.offline {
    background: #e74c3c;
    box-shadow: 0 0 10px rgba(231, 76, 60, 0.5);
    animation: pulsar 1s ease-in-out infinite;
}

#statusSistema .pulsar.manutencao {
    background: #f39c12;
    box-shadow: 0 0 10px rgba(243, 156, 18, 0.5);
}

@keyframes pulsar {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.3); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

/* Cards de status */
.status-card {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid #eef2f7;
    transition: all 0.3s ease;
    flex: 1;
    min-width: 150px;
}

.status-card:hover {
    border-color: #c9a84c;
    transform: translateY(-2px);
}

.status-card .label {
    font-size: 12px;
    color: #94a3b8;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-card .value {
    font-size: 18px;
    font-weight: 700;
    color: #1a2332;
    margin-top: 4px;
}

.status-card .value .online {
    color: #2ecc71;
}

.status-card .value .offline {
    color: #e74c3c;
}

/* Badge de chamadas */
.badge-chamada-header {
    background: #e74c3c;
    color: white;
    border-radius: 50%;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: bold;
    animation: pulse 1.5s infinite;
    display: inline-block;
    margin-left: 5px;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Animação de slide para mensagens */
@keyframes slideDown {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.alert-slide {
    animation: slideDown 0.5s ease;
}

/* Botão AGT */
.btn-agt {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    background: linear-gradient(135deg, #1a2332, #2c3e50);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(26, 35, 50, 0.3);
}

.btn-agt:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(26, 35, 50, 0.4);
    background: linear-gradient(135deg, #2c3e50, #1a2332);
}

.btn-agt i {
    font-style: normal;
}

/* Responsivo */
@media (max-width: 768px) {
    .status-cards {
        flex-direction: column;
    }
    .status-card {
        min-width: auto;
    }
    .stat-card .icon-bg {
        font-size: 50px;
    }
    .btn-agt {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="escola-content" style="padding: 25px 35px 50px; max-width: 100%;">

    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="../../index.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #f1f5f9; color: #4a5568; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.3s;">
                <span>←</span> Voltar
            </a>
            <div>
                <h1 style="font-size: 28px; font-weight: 800; color: #1a2332; margin: 0;">📊 Dashboard Escolar</h1>
                <p style="color: #94a3b8; font-size: 16px; margin: 6px 0 0;">Visão geral do sistema de gestão escolar</p>
            </div>
        </div>
        
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <!-- ============================================ -->
            <!-- BOTÃO AGT - CONFIGURAÇÃO -->
            <!-- ============================================ -->
            <a href="agt/config_agt.php" class="btn-agt" title="Configurar emissão de faturas AGT">
                <i>⚙️</i> AGT
            </a>
            
            <!-- Badge de chamadas pendentes -->
            <?php if ($chamadasPendentes > 0): ?>
            <a href="chamada.php" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #e74c3c; color: white; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 13px; animation: pulse 1.5s infinite;">
                📞 Chamadas
                <span style="background: white; color: #e74c3c; border-radius: 50%; padding: 0 8px; font-size: 12px; font-weight: 700;">
                    <?= $chamadasPendentes ?>
                </span>
            </a>
            <?php endif; ?>
            
            <div style="display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 6px 16px 6px 12px; border-radius: 30px; border: 1px solid #eef2f7;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #c9a84c, #f5d76e); display: flex; align-items: center; justify-content: center; font-size: 14px; color: #1a2332; font-weight: 700;">
                    <?= strtoupper(substr($usuario_nome, 0, 2)) ?>
                </div>
                <span style="font-size: 14px; font-weight: 600; color: #1a2332;"><?= htmlspecialchars($usuario_nome) ?></span>
            </div>
            <a href="../../logout.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #e74c3c; color: white; border: none; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 2px 10px rgba(231, 76, 60, 0.3); transition: all 0.3s;">
                Sair
            </a>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- STATUS DO SISTEMA - KEEP ALIVE -->
    <!-- ============================================ -->
    <div style="background: white; border-radius: 14px; padding: 20px 26px; margin-bottom: 24px; border: 1px solid #eef2f7;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h3 style="margin: 0; color: #1a2332; font-size: 16px; font-weight: 700;">⚡ Status do Sistema</h3>
                <p style="margin: 4px 0 0; color: #94a3b8; font-size: 13px;">Monitoramento em tempo real</p>
            </div>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;" class="status-cards">
                <!-- Conexão -->
                <div class="status-card">
                    <div class="label">🔌 Conexão</div>
                    <div class="value">
                        <span class="<?= $statusConexao ?>">
                            <?= $statusConexao == 'online' ? '🟢 Online' : '🔴 Offline' ?>
                        </span>
                    </div>
                </div>
                
                <!-- Tempo Ativo -->
                <div class="status-card">
                    <div class="label">⏱️ Tempo Ativo</div>
                    <div class="value">
                        <?= gmdate('H:i:s', $tempoAtivo) ?>
                    </div>
                </div>
                
                <!-- Último Ping -->
                <div class="status-card">
                    <div class="label">📡 Último Ping</div>
                    <div class="value" style="font-size: 14px;">
                        <?= $ultimoPing ?>
                    </div>
                </div>
                
                <!-- Usuários Online -->
                <div class="status-card">
                    <div class="label">👥 Usuários Online</div>
                    <div class="value">
                        <?= $usuariosOnline ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 18px; margin-bottom: 30px;">
        <div class="stat-card" style="background: white; padding: 22px 24px; border-radius: 14px; text-align: center; border: 1px solid #eef2f7; border-left: 5px solid #c9a84c;">
            <span class="icon-bg">👨‍🎓</span>
            <span style="font-size: 34px; display: block; margin-bottom: 8px;">👨‍🎓</span>
            <div style="font-size: 32px; font-weight: 700; color: #1a2332;"><?= $totalAlunos ?></div>
            <div style="font-size: 14px; color: #94a3b8; font-weight: 500;">Alunos</div>
        </div>
        
        <div class="stat-card" style="background: white; padding: 22px 24px; border-radius: 14px; text-align: center; border: 1px solid #eef2f7; border-left: 5px solid #2ecc71;">
            <span class="icon-bg">👨‍🏫</span>
            <span style="font-size: 34px; display: block; margin-bottom: 8px;">👨‍🏫</span>
            <div style="font-size: 32px; font-weight: 700; color: #1a2332; background: #d1fae5; padding: 0 15px; border-radius: 8px; display: inline-block;"><?= $totalProfessores ?></div>
            <div style="font-size: 14px; color: #94a3b8; font-weight: 500; margin-top: 5px;">Professores</div>
        </div>
        
        <div class="stat-card" style="background: white; padding: 22px 24px; border-radius: 14px; text-align: center; border: 1px solid #eef2f7; border-left: 5px solid #f39c12;">
            <span class="icon-bg">🏫</span>
            <span style="font-size: 34px; display: block; margin-bottom: 8px;">🏫</span>
            <div style="font-size: 32px; font-weight: 700; color: #1a2332;"><?= $totalTurmas ?></div>
            <div style="font-size: 14px; color: #94a3b8; font-weight: 500;">Turmas</div>
        </div>
        
        <div class="stat-card" style="background: white; padding: 22px 24px; border-radius: 14px; text-align: center; border: 1px solid #eef2f7; border-left: 5px solid #3498db;">
            <span class="icon-bg">📚</span>
            <span style="font-size: 34px; display: block; margin-bottom: 8px;">📚</span>
            <div style="font-size: 32px; font-weight: 700; color: #1a2332;"><?= $totalDisciplinas ?></div>
            <div style="font-size: 14px; color: #94a3b8; font-weight: 500;">Disciplinas</div>
        </div>
    </div>

    <!-- Alunos por Turma -->
    <div style="background: white; border-radius: 14px; padding: 22px 26px; margin-top: 24px; border: 1px solid #eef2f7;">
        <h3 style="margin-bottom: 16px; color: #1a2332; font-size: 19px; font-weight: 700;">📊 Alunos por Turma</h3>
        <?php if (count($alunosPorTurma) > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px;">
            <?php foreach($alunosPorTurma as $turma): ?>
            <div style="background: #f8fafc; padding: 16px; border-radius: 12px; text-align: center; border-top: 4px solid #c9a84c; transition: all 0.3s;">
                <div style="font-weight: 600; font-size: 16px; color: #1a2332;"><?= htmlspecialchars($turma['nome']) ?></div>
                <div style="font-size: 28px; font-weight: 700; color: #c9a84c;"><?= $turma['total'] ?></div>
                <div style="font-size: 13px; color: #94a3b8;">alunos</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color: #94a3b8; text-align: center; padding: 20px;">Nenhum aluno cadastrado em turmas.</p>
        <?php endif; ?>
    </div>

    <!-- Últimos Alunos -->
    <div style="background: white; border-radius: 14px; padding: 22px 26px; margin-top: 24px; border: 1px solid #eef2f7;">
        <h3 style="margin-bottom: 16px; color: #1a2332; font-size: 19px; font-weight: 700;">👨‍🎓 Últimos Alunos Cadastrados</h3>
        <?php if (count($ultimosAlunos) > 0): ?>
            <?php foreach($ultimosAlunos as $aluno): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                <div>
                    <div style="font-weight: 600; color: #1a2332; font-size: 16px;"><?= htmlspecialchars($aluno['nome']) ?></div>
                    <div style="font-size: 14px; color: #94a3b8;">
                        📅 <?= date('d/m/Y', strtotime($aluno['created_at'])) ?>
                        <?php if (!empty($aluno['TURMA'])): ?>
                            | 🏫 <?= htmlspecialchars($aluno['TURMA']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <span style="display: inline-block; padding: 4px 16px; border-radius: 14px; font-size: 12px; font-weight: 600; background: #d1fae5; color: #065f46;">
                    <?= ucfirst($aluno['status'] ?? 'ativo') ?>
                </span>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <p style="color: #94a3b8; text-align: center; padding: 20px;">Nenhum aluno cadastrado.</p>
        <?php endif; ?>
    </div>

</div>

<!-- ============================================ -->
<!-- INDICADOR DE STATUS FLUTUANTE -->
<!-- ============================================ -->
<div id="statusSistema" style="position: fixed; bottom: 15px; right: 15px; z-index: 9999; padding: 8px 18px; border-radius: 25px; font-size: 12px; font-weight: 600; background: #d1fae5; color: #2ecc71; box-shadow: 0 2px 15px rgba(0,0,0,0.1); font-family: 'Segoe UI', Arial, sans-serif; display: flex; align-items: center; gap: 8px; border: 1px solid rgba(46, 204, 113, 0.2);">
    <span class="pulsar online" id="statusDot"></span>
    <span id="statusTexto">Sistema Online</span>
    <span style="font-size: 10px; color: #94a3b8; margin-left: 5px;" id="statusTempo">⏱️ <?= gmdate('H:i:s', $tempoAtivo) ?></span>
</div>

<!-- ============================================ -->
<!-- KEEP ALIVE - MANTER SISTEMA ATIVO -->
<!-- ============================================ -->
<script>
// ============================================
// HEARTBEAT - MANTÉM O SISTEMA ATIVO
// ============================================

(function() {
    'use strict';

    // ============================================
    // CONFIGURAÇÕES
    // ============================================
    const CONFIG = {
        intervalo: 30000, // 30 segundos
        url: '/softgest_web/keep_alive.php',
        timeoutMax: 120000, // 2 minutos
        maxTentativas: 5
    };

    // ============================================
    // VARIÁVEIS
    // ============================================
    let tentativas = 0;
    let ultimoPing = Date.now();
    let intervaloId = null;
    let timeoutId = null;
    let tempoAtivo = <?= $tempoAtivo ?>;

    // ============================================
    // ELEMENTOS DO INDICADOR
    // ============================================
    const statusDot = document.getElementById('statusDot');
    const statusTexto = document.getElementById('statusTexto');
    const statusTempo = document.getElementById('statusTempo');

    // ============================================
    // FUNÇÃO PRINCIPAL - PING
    // ============================================
    function enviarPing() {
        fetch(CONFIG.url, {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Resposta do servidor: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            tentativas = 0;
            ultimoPing = Date.now();
            atualizarIndicador(true);
            
            // Atualizar tempo
            if (data.timestamp) {
                tempoAtivo = data.timestamp - <?= time() - $tempoAtivo ?>;
                if (statusTempo) {
                    statusTempo.textContent = '⏱️ ' + formatarTempo(tempoAtivo);
                }
            }
            
            console.log('💓 Keep Alive: OK - ' + new Date().toLocaleTimeString());
        })
        .catch(error => {
            console.error('❌ Erro no Keep Alive:', error);
            tentativas++;
            
            if (tentativas >= CONFIG.maxTentativas) {
                console.warn('⚠️ Muitas tentativas falhas. Tentando reconectar...');
                tentativas = 0;
                reconectar();
            }
            
            atualizarIndicador(false);
        });
    }

    // ============================================
    // RECONEXÃO
    // ============================================
    function reconectar() {
        console.log('🔄 Tentando reconectar ao sistema...');
        
        fetch('/softgest_web/index.php', {
            method: 'HEAD',
            credentials: 'same-origin'
        })
        .then(() => {
            console.log('✅ Reconexão bem-sucedida!');
            setTimeout(enviarPing, 1000);
        })
        .catch(() => {
            console.error('❌ Falha na reconexão. Tentando novamente em 30 segundos...');
            setTimeout(reconectar, 30000);
        });
    }

    // ============================================
    // ATUALIZAR INDICADOR VISUAL
    // ============================================
    function atualizarIndicador(conectado) {
        if (!statusDot || !statusTexto) return;
        
        if (conectado) {
            statusDot.className = 'pulsar online';
            statusTexto.textContent = 'Sistema Online';
            statusTexto.style.color = '#2ecc71';
            document.getElementById('statusSistema').style.background = '#d1fae5';
            document.getElementById('statusSistema').style.borderColor = 'rgba(46, 204, 113, 0.2)';
        } else {
            statusDot.className = 'pulsar offline';
            statusTexto.textContent = '⚠️ Reconectando...';
            statusTexto.style.color = '#e74c3c';
            document.getElementById('statusSistema').style.background = '#fee2e2';
            document.getElementById('statusSistema').style.borderColor = 'rgba(231, 76, 60, 0.2)';
        }
    }

    // ============================================
    // FORMATAR TEMPO
    // ============================================
    function formatarTempo(segundos) {
        if (segundos < 0) segundos = 0;
        const horas = Math.floor(segundos / 3600);
        const minutos = Math.floor((segundos % 3600) / 60);
        const segs = Math.floor(segundos % 60);
        return String(horas).padStart(2, '0') + ':' + 
               String(minutos).padStart(2, '0') + ':' + 
               String(segs).padStart(2, '0');
    }

    // ============================================
    // INICIAR
    // ============================================
    function iniciar() {
        // Enviar ping imediato
        setTimeout(enviarPing, 1000);
        
        // Configurar intervalo
        if (intervaloId) {
            clearInterval(intervaloId);
        }
        intervaloId = setInterval(enviarPing, CONFIG.intervalo);
        
        // Verificar timeout
        if (timeoutId) {
            clearInterval(timeoutId);
        }
        timeoutId = setInterval(function() {
            const tempoSemResposta = Date.now() - ultimoPing;
            if (tempoSemResposta > CONFIG.timeoutMax) {
                console.warn('⚠️ Tempo sem resposta excedido. Tentando reconectar...');
                reconectar();
                ultimoPing = Date.now();
            }
        }, CONFIG.intervalo);
        
        console.log('💓 Sistema Keep Alive iniciado!');
    }

    // ============================================
    // PARAR
    // ============================================
    function parar() {
        if (intervaloId) {
            clearInterval(intervaloId);
            intervaloId = null;
        }
        if (timeoutId) {
            clearInterval(timeoutId);
            timeoutId = null;
        }
        console.log('💤 Keep Alive parado.');
    }

    // ============================================
    // EVENTOS
    // ============================================
    
    document.addEventListener('DOMContentLoaded', iniciar);
    
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            console.log('👀 Página reativada, verificando conexão...');
            enviarPing();
        }
    });
    
    window.addEventListener('beforeunload', parar);

    // ============================================
    // EXPOR FUNÇÕES
    // ============================================
    window.KeepAlive = {
        iniciar: iniciar,
        parar: parar,
        ping: enviarPing,
        status: function() {
            return {
                conectado: tentativas < CONFIG.maxTentativas,
                ultimoPing: new Date(ultimoPing).toLocaleTimeString(),
                tentativas: tentativas
            };
        }
    };

    console.log('💓 Keep Alive carregado. Versão 1.0');
})();

// ============================================
// ATUALIZAR TEMPO ATIVO A CADA SEGUNDO
// ============================================
(function() {
    let segundos = <?= $tempoAtivo ?>;
    const tempoEl = document.getElementById('statusTempo');
    
    if (tempoEl) {
        setInterval(function() {
            segundos++;
            const horas = Math.floor(segundos / 3600);
            const minutos = Math.floor((segundos % 3600) / 60);
            const segs = Math.floor(segundos % 60);
            tempoEl.textContent = '⏱️ ' + 
                String(horas).padStart(2, '0') + ':' + 
                String(minutos).padStart(2, '0') + ':' + 
                String(segs).padStart(2, '0');
        }, 1000);
    }
})();
</script>

<?php include 'includes/footer_escola.php'; ?>