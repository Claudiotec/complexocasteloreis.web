<?php
// ============================================
// sidebar.php - Menu Lateral do Módulo Escola
// ============================================

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/contadores.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CALCULAR TOTAIS
// ============================================
$totalAlunos = contarAlunos();
$totalProfessores = contarProfessores();  // ← 1! ✅
$totalTurmas = contarTurmas();
$totalDisciplinas = contarDisciplinas();
$totalMensagens = 0;

// Verificar se está sendo carregado dentro do módulo ou standalone
$isEmbedded = strpos($_SERVER['REQUEST_URI'], 'modules/escola/') !== false && 
              strpos($_SERVER['REQUEST_URI'], 'modules/escola/includes/') === false;
?>

<!-- ===== SIDEBAR DO MÓDULO ESCOLA ===== -->
<aside class="sidebar-escola">
    <div class="sidebar-header-escola">
        <h2>🎓 <?= SISTEMA_NOME ?? 'SoftGest' ?></h2>
        <p class="sidebar-subtitle-escola">Módulo Acadêmico</p>
    </div>

    <nav class="sidebar-nav-escola">
        <ul>
            <li class="sidebar-item-escola sidebar-title-escola">📊 Visão Geral</li>
            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">📊</span>
                    <span class="text-escola">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item-escola sidebar-title-escola">💬 Comunicação</li>
            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/comunicacao/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">💬</span>
                    <span class="text-escola">Mensagens</span>
                    <span class="badge-escola"><?= $totalMensagens ?></span>
                </a>
            </li>

            <li class="sidebar-item-escola sidebar-title-escola">👥 Gestão Acadêmica</li>
            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/alunos/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">👨‍🎓</span>
                    <span class="text-escola">Alunos</span>
                    <span class="badge-escola"><?= $totalAlunos ?></span>
                </a>
            </li>

            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/professores/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">👨‍🏫</span>
                    <span class="text-escola">Professores</span>
                    <span class="badge-escola badge-professores-escola"><?= $totalProfessores ?></span>
                </a>
            </li>

            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/turmas/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">🏫</span>
                    <span class="text-escola">Turmas</span>
                    <span class="badge-escola"><?= $totalTurmas ?></span>
                </a>
            </li>

            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/disciplinas/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">📚</span>
                    <span class="text-escola">Disciplinas</span>
                    <span class="badge-escola"><?= $totalDisciplinas ?></span>
                </a>
            </li>

            <li class="sidebar-item-escola sidebar-title-escola">📋 Controle</li>
            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/frequencia/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">✅</span>
                    <span class="text-escola">Frequência</span>
                </a>
            </li>

            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/notas/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">📊</span>
                    <span class="text-escola">Notas</span>
                </a>
            </li>

            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/horarios/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">⏰</span>
                    <span class="text-escola">Horários</span>
                </a>
            </li>

            <li class="sidebar-item-escola sidebar-title-escola">📖 Pedagógico</li>
            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/pedagogico/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">📋</span>
                    <span class="text-escola">Pedagógico</span>
                </a>
            </li>

            <li class="sidebar-item-escola sidebar-title-escola">💰 Financeiro</li>
            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/financeiro/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">💰</span>
                    <span class="text-escola">Financeiro</span>
                </a>
            </li>

            <li class="sidebar-item-escola sidebar-title-escola">📈 Relatórios</li>
            <li class="sidebar-item-escola">
                <a href="<?= SITE_URL ?? '/softgest_web/' ?>modules/escola/relatorios/index.php" class="sidebar-link-escola">
                    <span class="icon-escola">📈</span>
                    <span class="text-escola">Relatórios</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<style>
/* ============================================
   SIDEBAR DO MÓDULO ESCOLA
   ============================================ */
.sidebar-escola {
    width: 260px;
    min-height: 100vh;
    background: #1a2332;
    color: #e2e8f0;
    padding: 20px 0;
    /* REMOVER position: fixed para não sobrepor o sidebar principal */
    position: relative;
    top: auto;
    left: auto;
    overflow-y: auto;
    z-index: 1;
    flex-shrink: 0;
}

.sidebar-header-escola {
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    margin-bottom: 15px;
}

.sidebar-header-escola h2 {
    font-size: 20px;
    font-weight: 700;
    color: #f5d76e;
    margin: 0;
}

.sidebar-subtitle-escola {
    font-size: 12px;
    color: #94a3b8;
    margin: 2px 0 0;
}

.sidebar-nav-escola {
    padding: 0 10px;
}

.sidebar-nav-escola ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-item-escola {
    margin-bottom: 2px;
}

.sidebar-title-escola {
    font-size: 11px;
    text-transform: uppercase;
    color: #64748b;
    padding: 12px 12px 6px;
    font-weight: 700;
    letter-spacing: 1px;
}

.sidebar-link-escola {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-radius: 8px;
    color: #cbd5e1;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s;
}

.sidebar-link-escola:hover {
    background: rgba(255,255,255,0.08);
    color: #fff;
}

.sidebar-link-escola .icon-escola {
    font-size: 18px;
    width: 24px;
    text-align: center;
}

.sidebar-link-escola .text-escola {
    flex: 1;
}

.sidebar-link-escola .badge-escola {
    background: #c9a84c;
    color: #1a2332;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    min-width: 22px;
    text-align: center;
}

.sidebar-link-escola .badge-professores-escola {
    background: #2ecc71;
    color: #fff;
}

/* ===== PARA USO EMBUTIDO (dentro do sistema principal) ===== */
.sidebar-escola-embedded {
    width: 100% !important;
    min-height: auto !important;
    background: transparent !important;
    padding: 0 !important;
}

.sidebar-escola-embedded .sidebar-header-escola {
    display: none !important;
}

.sidebar-escola-embedded .sidebar-link-escola {
    padding: 8px 12px !important;
    font-size: 13px !important;
}

.sidebar-escola-embedded .sidebar-title-escola {
    font-size: 10px !important;
    padding: 8px 12px 4px !important;
}

/* ============================================
   LAYOUT COM SIDEBAR + CONTEÚDO
   ============================================ */
.escola-layout {
    display: flex;
    min-height: 100vh;
}

.escola-content {
    flex: 1;
    padding: 20px 25px;
    background: #f0f2f5;
    overflow-x: hidden;
}

/* ============================================
   RESPONSIVIDADE
   ============================================ */
@media (max-width: 768px) {
    .sidebar-escola {
        width: 200px;
    }
    .sidebar-link-escola {
        font-size: 13px;
        padding: 8px 12px;
    }
    .sidebar-header-escola h2 {
        font-size: 17px;
    }
    
    .escola-content {
        padding: 15px;
    }
}

@media (max-width: 576px) {
    .sidebar-escola {
        width: 100% !important;
        min-height: auto !important;
        position: relative !important;
    }
    .escola-layout {
        flex-direction: column !important;
    }
}
</style>