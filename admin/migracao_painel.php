<?php
// ============================================
// admin/migracao_painel.php
// ============================================
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Migração MySQL → Neon</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
    min-height: 100vh; color: #fff; padding: 30px 20px;
}
.container { max-width: 1200px; margin: 0 auto; }

.header { text-align: center; margin-bottom: 30px; }
.header h1 {
    font-size: 32px; font-weight: 800;
    background: linear-gradient(135deg, #c9a84c, #f5d76e);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; margin-bottom: 8px;
}
.header p { color: #94a3b8; font-size: 15px; }

.card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; padding: 25px; margin-bottom: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.actions {
    display: flex; gap: 12px; flex-wrap: wrap;
    justify-content: center; margin-bottom: 25px;
}
.btn {
    padding: 14px 28px; border-radius: 10px; border: none;
    font-size: 15px; font-weight: 600; cursor: pointer;
    transition: all 0.3s; display: inline-flex; align-items: center;
    gap: 8px; font-family: inherit; text-decoration: none;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
.btn-primary { background: linear-gradient(135deg, #c9a84c, #f5d76e); color: #1a2332; }
.btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(201,168,76,0.4); }
.btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; }
.btn-danger:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(231,76,60,0.4); }
.btn-secondary { background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); }
.btn-secondary:hover:not(:disabled) { background: rgba(255,255,255,0.2); }
.btn-gerenciar { background: linear-gradient(135deg, #6c63ff, #a855f7); color: #fff; }
.btn-gerenciar:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(108,99,255,0.5); }
.btn-warning { background: linear-gradient(135deg, #f39c12, #e67e22); color: #fff; }
.btn-warning:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(243,156,18,0.4); }

.btn-gerenciar-topo {
    position: fixed; top: 20px; right: 20px;
    padding: 12px 22px;
    background: linear-gradient(135deg, #6c63ff, #a855f7);
    color: #fff; border-radius: 10px; text-decoration: none;
    font-size: 14px; font-weight: 700;
    display: inline-flex; align-items: center; gap: 8px;
    box-shadow: 0 4px 15px rgba(108,99,255,0.4);
    transition: all 0.3s; z-index: 100;
}
.btn-gerenciar-topo:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(108,99,255,0.6); }

.autosync-card {
    background: linear-gradient(135deg, rgba(46,204,113,0.08), rgba(52,152,219,0.08));
    border: 2px solid rgba(46,204,113,0.3);
}
.autosync-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
.autosync-toggle { display: flex; align-items: center; gap: 15px; cursor: pointer; user-select: none; flex: 1; min-width: 280px; }
.switch-wrap { position: relative; }
.switch-input { position: absolute; opacity: 0; width: 0; height: 0; }
.switch-track {
    width: 64px; height: 34px; background: #374151;
    border-radius: 17px; position: relative; transition: background 0.3s;
    box-shadow: inset 0 2px 5px rgba(0,0,0,0.3);
}
.switch-thumb {
    position: absolute; top: 4px; left: 4px;
    width: 26px; height: 26px; background: #fff; border-radius: 50%;
    transition: transform 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.3);
}
.switch-input:checked + .switch-track { background: linear-gradient(135deg, #2ecc71, #27ae60); }
.switch-input:checked + .switch-track .switch-thumb { transform: translateX(30px); }
.autosync-title {
    font-size: 16px; font-weight: 700; color: #2ecc71;
    display: flex; align-items: center; gap: 8px; margin-bottom: 4px;
}
.autosync-badge {
    font-size: 11px; padding: 2px 10px; border-radius: 10px;
    background: #374151; color: #94a3b8; font-weight: 700;
    text-transform: uppercase; transition: all 0.3s;
}
.autosync-badge.on { background: rgba(46,204,113,0.3); color: #2ecc71; }
.autosync-subtitle { font-size: 12px; color: #94a3b8; }
.autosync-controls { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.intervalo-label { font-size: 13px; color: #94a3b8; display: flex; align-items: center; gap: 6px; }
.intervalo-input {
    width: 70px; padding: 6px 10px; border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(0,0,0,0.3); color: #fff;
    font-size: 14px; text-align: center; font-family: inherit;
}
.btn-exec {
    padding: 8px 18px; border-radius: 8px;
    border: 1px solid rgba(52,152,219,0.5);
    background: rgba(52,152,219,0.2);
    color: #60a5fa; font-weight: 600; cursor: pointer;
    font-size: 13px; transition: all 0.3s; font-family: inherit;
}
.btn-exec:hover { background: rgba(52,152,219,0.35); }

.autosync-info {
    width: 100%; padding: 15px 18px; border-radius: 10px;
    background: rgba(0,0,0,0.25);
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px; margin-top: 18px;
}
.info-block .label {
    font-size: 11px; color: #94a3b8; text-transform: uppercase;
    font-weight: 600; margin-bottom: 4px;
}
.info-block .value { font-size: 15px; font-weight: 700; color: #c9a84c; }
.info-block .value.online { color: #2ecc71; }
.info-block .value.offline { color: #e74c3c; }

.progress-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 15px; flex-wrap: wrap; gap: 10px;
}
.progress-header h2 { font-size: 20px; color: #c9a84c; }
.status-badge {
    padding: 6px 16px; border-radius: 20px; font-size: 13px;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
}
.status-badge.iniciando,
.status-badge.conectando,
.status-badge.migrando { background: rgba(201,168,76,0.2); color: #c9a84c; animation: pulse 1.5s infinite; }
.status-badge.concluido { background: rgba(46,204,113,0.2); color: #2ecc71; }
.status-badge.erro,
.status-badge.cancelado { background: rgba(231,76,60,0.2); color: #e74c3c; }
.status-badge.aguardando { background: rgba(148,163,184,0.2); color: #94a3b8; }

.progress-bar-container {
    width: 100%; height: 30px;
    background: rgba(0,0,0,0.3); border-radius: 15px;
    overflow: hidden; position: relative; margin-bottom: 15px;
}
.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #c9a84c, #f5d76e);
    transition: width 0.5s ease; border-radius: 15px;
    position: relative; min-width: 0;
}
.progress-bar::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 2s infinite;
}
.progress-text {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    font-weight: 700; color: #fff;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    font-size: 14px; z-index: 2;
}

.tabela-atual {
    background: rgba(201,168,76,0.1);
    border-left: 4px solid #c9a84c;
    padding: 12px 18px; border-radius: 8px;
    font-size: 14px; margin-bottom: 15px;
}
.tabela-atual strong { color: #c9a84c; }

.metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}
.metric {
    background: rgba(0,0,0,0.2); border-radius: 10px;
    padding: 15px; text-align: center;
}
.metric .label {
    font-size: 11px; color: #94a3b8; text-transform: uppercase;
    font-weight: 600; margin-bottom: 6px;
}
.metric .value { font-size: 24px; font-weight: 800; color: #c9a84c; }
.metric.verde .value { color: #2ecc71; }
.metric.vermelho .value { color: #e74c3c; }
.metric.azul .value { color: #3498db; }

.log-container {
    background: #0d1520; border-radius: 10px; padding: 15px;
    max-height: 350px; overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 12px; line-height: 1.6;
    border: 1px solid rgba(255,255,255,0.05);
}
.log-line { padding: 3px 0; border-bottom: 1px solid rgba(255,255,255,0.03); }
.log-line.info { color: #94a3b8; }
.log-line.ok { color: #2ecc71; }
.log-line.warn { color: #f39c12; }
.log-line.error { color: #e74c3c; }

.table-result { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 15px; }
.table-result th {
    background: rgba(201,168,76,0.15); color: #c9a84c;
    padding: 10px; text-align: left; font-size: 11px;
    text-transform: uppercase; font-weight: 700;
    border-bottom: 2px solid rgba(201,168,76,0.3);
}
.table-result td {
    padding: 8px 10px; border-bottom: 1px solid rgba(255,255,255,0.05);
    color: #e2e8f0;
}
.table-result tr:hover td { background: rgba(255,255,255,0.03); }
.table-result .col-num { text-align: right; font-family: monospace; font-weight: 600; }
.table-result .col-ok { color: #2ecc71; }
.table-result .col-ign { color: #f39c12; }
.table-result .col-err { color: #e74c3c; }

.alerta-info {
    background: rgba(52,152,219,0.15);
    border-left: 4px solid #3498db;
    padding: 12px 18px; border-radius: 6px;
    color: #93c5fd; font-size: 13px; margin-bottom: 20px;
}

.alerta-erro {
    background: rgba(231,76,60,0.15);
    border-left: 4px solid #e74c3c;
    padding: 12px 18px; border-radius: 6px;
    color: #fca5a5; font-size: 13px; margin-bottom: 20px;
    display: none;
}

.voltar {
    position: fixed; top: 20px; left: 20px;
    background: rgba(255,255,255,0.1); color: #fff;
    padding: 10px 18px; border-radius: 8px;
    text-decoration: none; font-size: 14px; font-weight: 600;
    transition: all 0.3s; border: 1px solid rgba(255,255,255,0.2);
    z-index: 100;
}
.voltar:hover { background: rgba(255,255,255,0.2); transform: translateX(-3px); }

@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
@keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

@media (max-width: 768px) {
    .autosync-row { flex-direction: column; align-items: stretch; }
    .autosync-controls { justify-content: center; }
    .header h1 { font-size: 24px; }
    .btn { padding: 12px 20px; font-size: 14px; }
    .btn-gerenciar-topo {
        position: static; display: inline-flex;
        margin-bottom: 15px; width: 100%; justify-content: center;
    }
}
</style>
</head>
<body>

<a href="../index.php" class="voltar">← Voltar</a>
<a href="gerenciar_tabelas.php" class="btn-gerenciar-topo">🗄️ Gerenciar Tabelas</a>

<div class="container">

    <div class="header">
        <h1>🚀 Migração MySQL → Neon</h1>
        <p>Transferência inteligente — apenas dados novos são inseridos</p>
    </div>

    <div class="alerta-info">
        💡 <strong>Você pode continuar trabalhando no sistema enquanto a migração roda.</strong>
        A migração acontece em background. Esta tela pode ficar aberta ou ser fechada — o processo continua.
    </div>

    <!-- ============================================ -->
    <!-- AUTO-SYNC (opcional, reforço) -->
    <!-- ============================================ -->
    <div class="card autosync-card">
        <div class="autosync-row">

            <label class="autosync-toggle" for="chkAutoSync">
                <div class="switch-wrap">
                    <input type="checkbox" id="chkAutoSync" class="switch-input" onchange="toggleAutoSync()">
                    <div class="switch-track"><div class="switch-thumb"></div></div>
                </div>
                <div>
                    <div class="autosync-title">
                        <span>🔄 Auto-Sync (opcional)</span>
                        <span class="autosync-badge" id="autoStatus">OFF</span>
                    </div>
                    <div class="autosync-subtitle">
                        Se ativo, repete a migração a cada
                        <strong id="intervaloLabel">2</strong> min automaticamente.
                        <em>Não precisa estar ativo para o botão "Iniciar" funcionar.</em>
                    </div>
                </div>
            </label>

            <div class="autosync-controls">
                <label class="intervalo-label">
                    Intervalo:
                    <input type="number" id="intervaloMinutos" class="intervalo-input"
                           value="2" min="1" max="60">
                    min
                </label>
                <button class="btn-exec" onclick="executarAutoAgora()">▶️ Executar Agora</button>
            </div>

        </div>

        <div class="autosync-info">
            <div class="info-block">
                <div class="label">🌐 Internet</div>
                <div class="value" id="infoInternet">—</div>
            </div>
            <div class="info-block">
                <div class="label">⏱️ Última Execução</div>
                <div class="value" id="infoUltima">—</div>
            </div>
            <div class="info-block">
                <div class="label">⏳ Próxima</div>
                <div class="value" id="infoProxima">—</div>
            </div>
            <div class="info-block">
                <div class="label">📊 Ciclos Executados</div>
                <div class="value" id="infoCiclos">0</div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- AÇÕES MANUAIS -->
    <!-- ============================================ -->
    <div class="card">
        <div class="actions">
            <button id="btnIniciar" class="btn btn-primary" onclick="iniciar()">
                ▶️ Iniciar Migração
            </button>
            <button id="btnRodarDireto" class="btn btn-warning" onclick="rodarDireto()"
                    title="Roda o Python de forma síncrona (útil para debugar)">
                ⚡ Rodar Direto (Debug)
            </button>
            <button id="btnCancelar" class="btn btn-danger" onclick="cancelar()" style="display:none;">
                ⏹️ Cancelar
            </button>
            <button class="btn btn-secondary" onclick="resetar()">🔄 Resetar</button>
            <button class="btn btn-secondary" onclick="atualizarAgora()">🔃 Atualizar Agora</button>
            <button class="btn btn-secondary" onclick="diagnostico()">🩺 Diagnóstico</button>
            <a href="gerenciar_tabelas.php" class="btn btn-gerenciar">🗄️ Gerenciar Tabelas</a>
        </div>
        <div id="msgAcao" class="alerta-erro"></div>
    </div>

    <!-- ============================================ -->
    <!-- PROGRESSO -->
    <!-- ============================================ -->
    <div class="card">
        <div class="progress-header">
            <h2>📊 Progresso</h2>
            <span class="status-badge aguardando" id="statusBadge">Aguardando</span>
        </div>

        <div class="progress-bar-container">
            <div class="progress-bar" id="progressBar" style="width:0%;"></div>
            <span class="progress-text" id="progressText">0%</span>
        </div>

        <div class="tabela-atual" id="tabelaAtual" style="display:none;">
            🔄 Processando: <strong id="nomeTabela">—</strong>
            <span id="contadorTabelas" style="float:right;"></span>
        </div>

        <div class="metrics">
            <div class="metric verde"><div class="label">Inseridos</div><div class="value" id="metInseridos">0</div></div>
            <div class="metric azul"><div class="label">Já existiam</div><div class="value" id="metIgnorados">0</div></div>
            <div class="metric"><div class="label">Colunas Criadas</div><div class="value" id="metColunas" style="color:#c9a84c;">0</div></div>
            <div class="metric vermelho"><div class="label">Erros</div><div class="value" id="metErros">0</div></div>
            <div class="metric"><div class="label">Tabelas</div><div class="value" id="metTabelas">0/0</div></div>
            <div class="metric"><div class="label">Tempo</div><div class="value" id="metTempo">0s</div></div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- LOG -->
    <!-- ============================================ -->
    <div class="card">
        <h2 style="color:#c9a84c; margin-bottom:15px; font-size:18px;">📋 Log em Tempo Real</h2>
        <div class="log-container" id="logContainer">
            <div class="log-line info">Aguardando início da migração...</div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- RESULTADOS POR TABELA -->
    <!-- ============================================ -->
    <div class="card" id="cardResultados" style="display:none;">
        <h2 style="color:#c9a84c; margin-bottom:15px; font-size:18px;">📊 Resultados por Tabela</h2>
        <div style="overflow-x:auto;">
            <table class="table-result">
                <thead>
                    <tr>
                        <th>Tabela</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:right;">Inseridos</th>
                        <th style="text-align:right;">Já existiam</th>
                        <th style="text-align:right;">Colunas</th>
                        <th style="text-align:right;">Erros</th>
                    </tr>
                </thead>
                <tbody id="tabelaResultados"></tbody>
            </table>
        </div>
    </div>

</div>

<script>
// ============================================
// CONSTANTES
// ============================================
const API = 'migracao_control.php';
const API_CONFIG = 'auto_sync_config.php';

let pollInterval = null;
let ultimoLogCount = 0;
let autoSyncTimer = null;
let autoSyncContador = 0;

// ============================================
// UTILITÁRIOS DE UI
// ============================================
function toast(msg, tipo = 'info') {
    const cores = { info: '#3b82f6', success: '#16a34a', error: '#dc2626', warn: '#f39c12' };
    const div = document.createElement('div');
    div.style.cssText = `
        position: fixed; top: 80px; left: 50%; transform: translateX(-50%);
        background: ${cores[tipo] || cores.info}; color: #fff;
        padding: 14px 28px; border-radius: 10px; font-weight: 600;
        font-size: 14px; z-index: 100000;
        box-shadow: 0 10px 40px rgba(0,0,0,0.4);
    `;
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(() => { div.style.opacity = '0'; div.style.transition = 'opacity 0.3s'; }, 3500);
    setTimeout(() => div.remove(), 4000);
}

function mostrarErro(msg) {
    const el = document.getElementById('msgAcao');
    el.textContent = '❌ ' + msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 8000);
}

function setBadge(status) {
    const el = document.getElementById('statusBadge');
    const labels = {
        'aguardando': 'Aguardando', 'iniciando': 'Iniciando...',
        'conectando': 'Conectando...', 'migrando': 'Migrando...',
        'concluido': '✅ Concluído', 'cancelado': '⏹️ Cancelado',
        'erro': '❌ Erro'
    };
    el.className = 'status-badge ' + status;
    el.textContent = labels[status] || status;
}

function formatarTempo(segundos) {
    if (segundos < 60) return segundos + 's';
    const min = Math.floor(segundos / 60);
    const seg = segundos % 60;
    return min + 'm ' + seg + 's';
}

function atualizarStatusInternet() {
    const online = navigator.onLine;
    const el = document.getElementById('infoInternet');
    el.textContent = online ? '🟢 Online' : '🔴 Offline';
    el.className = 'value ' + (online ? 'online' : 'offline');
}

// ============================================
// INICIALIZAÇÃO
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // 1) Carrega config do auto-sync (opcional — não bloqueia)
    fetch(API_CONFIG + '?acao=ler')
        .then(r => r.text())
        .then(txt => {
            try {
                const data = JSON.parse(txt);
                if (data.success) {
                    document.getElementById('chkAutoSync').checked = !!data.ativo;
                    document.getElementById('intervaloMinutos').value = data.intervalo_minutos || 2;
                    document.getElementById('intervaloLabel').textContent = data.intervalo_minutos || 2;

                    if (data.ultima_execucao) {
                        document.getElementById('infoUltima').textContent =
                            new Date(data.ultima_execucao).toLocaleTimeString('pt-BR');
                    }
                    if (data.proxima_execucao) {
                        document.getElementById('infoProxima').textContent =
                            new Date(data.proxima_execucao).toLocaleTimeString('pt-BR');
                    }
                    atualizarVisualSwitch();
                    if (data.ativo) iniciarLoopAuto();
                }
            } catch(e) {
                console.warn('Config auto-sync não respondeu:', e);
            }
        })
        .catch(e => console.warn('Config auto-sync inacessível:', e));

    // 2) Status de internet
    atualizarStatusInternet();

    // 3) ⚡ SEMPRE inicia o polling — INDEPENDENTE de checkbox
    console.log('▶️ Iniciando polling de status...');
    consultarStatus();
    iniciarPolling();
});

// ============================================
// AUTO-SYNC (opcional)
// ============================================
function toggleAutoSync() {
    const ativo = document.getElementById('chkAutoSync').checked;
    salvarConfigAuto(ativo);
}

async function salvarConfigAuto(ativo) {
    const intervalo = parseInt(document.getElementById('intervaloMinutos').value) || 2;
    const formData = new FormData();
    formData.append('acao', 'salvar');
    formData.append('ativo', ativo ? '1' : '0');
    formData.append('intervalo_minutos', intervalo);

    try {
        const res = await fetch(API_CONFIG, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            atualizarVisualSwitch();
            if (ativo) {
                iniciarLoopAuto();
                setTimeout(executarAutoAgora, 500);
            } else {
                pararLoopAuto();
            }
        }
    } catch (e) {
        toast('Erro ao salvar config: ' + e.message, 'error');
    }
}

function atualizarVisualSwitch() {
    const ativo = document.getElementById('chkAutoSync').checked;
    const badge = document.getElementById('autoStatus');
    if (ativo) { badge.textContent = 'ON'; badge.classList.add('on'); }
    else { badge.textContent = 'OFF'; badge.classList.remove('on'); }
    document.getElementById('intervaloLabel').textContent =
        document.getElementById('intervaloMinutos').value;
}

function iniciarLoopAuto() {
    if (autoSyncTimer) clearInterval(autoSyncTimer);
    const intervalo = parseInt(document.getElementById('intervaloMinutos').value) || 2;
    autoSyncTimer = setInterval(executarAutoAgora, intervalo * 60 * 1000);
    console.log('🔄 Auto-Sync ativado — intervalo:', intervalo, 'min');
}

function pararLoopAuto() {
    if (autoSyncTimer) { clearInterval(autoSyncTimer); autoSyncTimer = null; }
    console.log('⏹️ Auto-Sync desativado');
}

async function executarAutoAgora() {
    atualizarStatusInternet();
    if (!navigator.onLine) { console.log('📴 Sem internet'); return; }

    try {
        const res = await fetch(API + '?acao=auto', { method: 'POST' });
        const data = await res.json();

        autoSyncContador++;
        document.getElementById('infoCiclos').textContent = autoSyncContador;
        document.getElementById('infoUltima').textContent =
            new Date().toLocaleTimeString('pt-BR');

        const intervalo = parseInt(document.getElementById('intervaloMinutos').value) || 2;
        document.getElementById('infoProxima').textContent =
            new Date(Date.now() + intervalo * 60 * 1000).toLocaleTimeString('pt-BR');

        if (data.success) {
            console.log('✅ Auto-Sync disparou migração');
            iniciarPolling();
        } else if (data.em_andamento) {
            console.log('⏳ Já tinha migração rodando');
        } else {
            console.warn('⚠️ ' + (data.message || 'erro'));
        }
    } catch (e) {
        console.warn('❌ Erro no auto-sync:', e);
    }
}

document.getElementById('intervaloMinutos')?.addEventListener('change', function() {
    if (document.getElementById('chkAutoSync').checked) {
        salvarConfigAuto(true);
    } else {
        document.getElementById('intervaloLabel').textContent = this.value;
    }
});

window.addEventListener('online', function() {
    atualizarStatusInternet();
    if (document.getElementById('chkAutoSync').checked) executarAutoAgora();
});
window.addEventListener('offline', atualizarStatusInternet);

// ============================================
// POLLING DE STATUS (sempre ativo)
// ============================================
function adicionarLog(log) {
    const container = document.getElementById('logContainer');
    const novos = log.slice(ultimoLogCount);
    for (const linha of novos) {
        const div = document.createElement('div');
        div.className = 'log-line ' + (linha.tipo || 'info');
        div.textContent = '[' + linha.hora + '] ' + linha.msg;
        container.appendChild(div);
    }
    ultimoLogCount = log.length;
    container.scrollTop = container.scrollHeight;
}

function renderizarTabelas(porTabela) {
    if (!porTabela || Object.keys(porTabela).length === 0) return;
    const tbody = document.getElementById('tabelaResultados');
    tbody.innerHTML = '';
    for (const [tabela, stats] of Object.entries(porTabela)) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${tabela}</strong></td>
            <td class="col-num">${stats.total || 0}</td>
            <td class="col-num col-ok">+${stats.inseridos || 0}</td>
            <td class="col-num col-ign">${stats.ignorados || 0}</td>
            <td class="col-num">${stats.colunas_criadas || 0}</td>
            <td class="col-num col-err">${stats.erros || 0}</td>
        `;
        tbody.appendChild(tr);
    }
    document.getElementById('cardResultados').style.display = 'block';
}

async function consultarStatus() {
    try {
        const res = await fetch(API + '?acao=status&t=' + Date.now());
        const txt = await res.text();

        let data;
        try {
            data = JSON.parse(txt);
        } catch (e) {
            console.error('Resposta não-JSON:', txt.substring(0, 200));
            return;
        }

        if (!data.success) return;

        setBadge(data.status);

        const prog = data.progresso || 0;
        document.getElementById('progressBar').style.width = prog + '%';
        document.getElementById('progressText').textContent = prog + '%';

        if (data.tabela_atual) {
            document.getElementById('tabelaAtual').style.display = 'block';
            document.getElementById('nomeTabela').textContent = data.tabela_atual;
            document.getElementById('contadorTabelas').textContent =
                (data.tabelas_processadas || 0) + ' / ' + (data.tabelas_total || 0);
        } else {
            document.getElementById('tabelaAtual').style.display = 'none';
        }

        document.getElementById('metInseridos').textContent = data.registros_inseridos || 0;
        document.getElementById('metIgnorados').textContent = data.registros_ignorados || 0;
        document.getElementById('metColunas').textContent = data.colunas_criadas || 0;
        document.getElementById('metErros').textContent = data.erros || 0;
        document.getElementById('metTabelas').textContent =
            (data.tabelas_processadas || 0) + '/' + (data.tabelas_total || 0);
        document.getElementById('metTempo').textContent =
            formatarTempo(data.tempo_decorrido || 0);

        if (data.log && data.log.length > 0) adicionarLog(data.log);
        renderizarTabelas(data.por_tabela);

        const migrando = ['iniciando', 'conectando', 'migrando'].includes(data.status);
        document.getElementById('btnIniciar').disabled = migrando;
        document.getElementById('btnIniciar').textContent =
            migrando ? '⏳ Migrando...' : '▶️ Iniciar Migração';
        document.getElementById('btnCancelar').style.display = migrando ? 'inline-flex' : 'none';

    } catch (e) {
        console.error('Erro no polling:', e);
    }
}

function iniciarPolling() {
    if (pollInterval) return;
    pollInterval = setInterval(consultarStatus, 1500);
    consultarStatus();
}

function pararPolling() {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
}

// ============================================
// AÇÕES MANUAIS
// ============================================
async function iniciar() {
    if (!confirm('Iniciar a migração inteligente?\n\nApenas dados novos serão inseridos.')) return;

    const btn = document.getElementById('btnIniciar');
    btn.disabled = true;
    btn.textContent = '⏳ Iniciando...';

    document.getElementById('logContainer').innerHTML =
        '<div class="log-line info">Iniciando...</div>';
    ultimoLogCount = 0;

    try {
        const res = await fetch(API + '?acao=iniciar', { method: 'POST' });
        const txt = await res.text();

        let data;
        try { data = JSON.parse(txt); }
        catch (e) {
            mostrarErro('Resposta inválida do servidor: ' + txt.substring(0, 200));
            btn.disabled = false;
            btn.textContent = '▶️ Iniciar Migração';
            return;
        }

        if (data.success) {
            toast('✅ ' + data.message, 'success');
            setBadge('iniciando');
            iniciarPolling();
            setTimeout(() => {
                btn.disabled = false;
                btn.textContent = '▶️ Iniciar Migração';
            }, 3000);
        } else {
            mostrarErro(data.message || 'erro desconhecido');
            btn.disabled = false;
            btn.textContent = '▶️ Iniciar Migração';
        }
    } catch (e) {
        mostrarErro('Erro de rede: ' + e.message);
        btn.disabled = false;
        btn.textContent = '▶️ Iniciar Migração';
    }
}

async function rodarDireto() {
    if (!confirm('Rodar o Python SINCRONAMENTE? A página vai travar até terminar.\n\nUse só para DEBUG.')) return;

    const btn = document.getElementById('btnRodarDireto');
    btn.disabled = true;
    btn.textContent = '⏳ Rodando...';
    document.getElementById('logContainer').innerHTML =
        '<div class="log-line info">Rodando sincronamente (aguarde)...</div>';

    try {
        const res = await fetch(API + '?acao=rodar_sincrono', { method: 'POST' });
        const data = await res.json();

        if (data.success) {
            toast('✅ Execução concluída', 'success');
            console.log('Saída do Python:', data.output);
            document.getElementById('logContainer').innerHTML =
                '<div class="log-line ok">' +
                (data.output || '(sem saída)').replace(/\n/g, '<br>') +
                '</div>';
            consultarStatus();
        } else {
            mostrarErro(data.message);
        }
    } catch (e) {
        mostrarErro('Erro: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = '⚡ Rodar Direto (Debug)';
    }
}

async function cancelar() {
    if (!confirm('Cancelar a migração em andamento?')) return;
    try {
        await fetch(API + '?acao=cancelar', { method: 'POST' });
        consultarStatus();
    } catch (e) {}
}

async function resetar() {
    if (!confirm('Resetar o status da migração?')) return;
    try {
        await fetch(API + '?acao=resetar', { method: 'POST' });
        location.reload();
    } catch (e) {}
}

function atualizarAgora() { consultarStatus(); }

async function diagnostico() {
    try {
        const res = await fetch(API + '?acao=diagnostico');
        const data = await res.json();
        alert(JSON.stringify(data, null, 2));
    } catch (e) {
        alert('Erro: ' + e.message);
    }
}
</script>

</body>
</html>