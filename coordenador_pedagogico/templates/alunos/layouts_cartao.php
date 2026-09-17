<?php
// ============================================
// modules/escola/alunos/layouts_cartao.php - Layouts para Cartões Escolares
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'editar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== LAYOUTS PRÉ-DEFINIDOS =====
$layouts = [
    'classico' => [
        'nome' => 'Clássico',
        'descricao' => 'Layout tradicional com fundo laranja gradiente',
        'cor_fundo' => 'linear-gradient(135deg, #FF8C00, #FFA500)',
        'cor_texto' => '#1a2332',
        'cor_destaque' => '#FF8C00',
        'fonte' => 'Arial',
        'estilo_foto' => 'circle',
        'borda' => '3mm solid #FF8C00',
        'preview' => '🎓'
    ],
    'moderno' => [
        'nome' => 'Moderno',
        'descricao' => 'Layout clean com fundo azul escuro e detalhes em dourado',
        'cor_fundo' => 'linear-gradient(135deg, #1a2332, #2c3e50)',
        'cor_texto' => '#ffffff',
        'cor_destaque' => '#f5d76e',
        'fonte' => 'Segoe UI',
        'estilo_foto' => 'rounded',
        'borda' => '3mm solid #f5d76e',
        'preview' => '✨'
    ],
    'elegante' => [
        'nome' => 'Elegante',
        'descricao' => 'Layout sofisticado com fundo gradiente roxo e dourado',
        'cor_fundo' => 'linear-gradient(135deg, #2d1b69, #11998e)',
        'cor_texto' => '#ffffff',
        'cor_destaque' => '#f5d76e',
        'fonte' => 'Georgia',
        'estilo_foto' => 'circle',
        'borda' => '3mm solid #f5d76e',
        'preview' => '👑'
    ],
    'infantil' => [
        'nome' => 'Infantil',
        'descricao' => 'Layout colorido e divertido para alunos mais novos',
        'cor_fundo' => 'linear-gradient(135deg, #f093fb, #f5576c)',
        'cor_texto' => '#ffffff',
        'cor_destaque' => '#ffeaa7',
        'fonte' => 'Comic Sans MS',
        'estilo_foto' => 'rounded',
        'borda' => '3mm solid #ffeaa7',
        'preview' => '🌈'
    ],
    'corporativo' => [
        'nome' => 'Corporativo',
        'descricao' => 'Layout profissional com fundo escuro e detalhes prateados',
        'cor_fundo' => 'linear-gradient(135deg, #0f2027, #203a43, #2c5364)',
        'cor_texto' => '#ffffff',
        'cor_destaque' => '#a8c0d0',
        'fonte' => 'Tahoma',
        'estilo_foto' => 'square',
        'borda' => '3mm solid #a8c0d0',
        'preview' => '🏢'
    ],
    'natureza' => [
        'nome' => 'Natureza',
        'descricao' => 'Layout inspirado na natureza com tons verdes',
        'cor_fundo' => 'linear-gradient(135deg, #11998e, #38ef7d)',
        'cor_texto' => '#1a2332',
        'cor_destaque' => '#2d3436',
        'fonte' => 'Arial',
        'estilo_foto' => 'circle',
        'borda' => '3mm solid #2d3436',
        'preview' => '🌿'
    ],
    'luxo' => [
        'nome' => 'Luxo',
        'descricao' => 'Layout premium com fundo escuro e detalhes dourados',
        'cor_fundo' => 'linear-gradient(135deg, #0c0c1d, #1a1a2e, #16213e)',
        'cor_texto' => '#f5d76e',
        'cor_destaque' => '#f5d76e',
        'fonte' => 'Times New Roman',
        'estilo_foto' => 'circle',
        'borda' => '3mm solid #f5d76e',
        'preview' => '💎'
    ],
    'esportivo' => [
        'nome' => 'Esportivo',
        'descricao' => 'Layout dinâmico com cores vibrantes para alunos atletas',
        'cor_fundo' => 'linear-gradient(135deg, #f7971e, #ffd200)',
        'cor_texto' => '#1a2332',
        'cor_destaque' => '#e74c3c',
        'fonte' => 'Impact',
        'estilo_foto' => 'rounded',
        'borda' => '3mm solid #e74c3c',
        'preview' => '⚽'
    ],
    'tecnologia' => [
        'nome' => 'Tecnologia',
        'descricao' => 'Layout futurista com fundo escuro e detalhes ciano',
        'cor_fundo' => 'linear-gradient(135deg, #0f0c29, #302b63, #24243e)',
        'cor_texto' => '#00d2ff',
        'cor_destaque' => '#00d2ff',
        'fonte' => 'Courier New',
        'estilo_foto' => 'square',
        'borda' => '3mm solid #00d2ff',
        'preview' => '💻'
    ]
];

// ===== LAYOUTS DA INTERNET (EXEMPLOS) =====
$layouts_internet = [
    'minimalista' => [
        'nome' => 'Minimalista (Web)',
        'descricao' => 'Layout limpo e minimalista com tons pastéis',
        'cor_fundo' => 'linear-gradient(135deg, #f5f7fa, #c3cfe2)',
        'cor_texto' => '#2d3436',
        'cor_destaque' => '#6c5ce7',
        'fonte' => 'Montserrat',
        'estilo_foto' => 'circle',
        'borda' => '3mm solid #6c5ce7',
        'preview' => '◐',
        'url' => 'https://example.com/layouts/minimalista'
    ],
    'neon' => [
        'nome' => 'Neon (Web)',
        'descricao' => 'Layout vibrante com efeitos neon',
        'cor_fundo' => 'linear-gradient(135deg, #0a0a0a, #1a1a2e)',
        'cor_texto' => '#ff6b6b',
        'cor_destaque' => '#ff6b6b',
        'fonte' => 'Orbitron',
        'estilo_foto' => 'rounded',
        'borda' => '3mm solid #ff6b6b',
        'preview' => '💡',
        'url' => 'https://example.com/layouts/neon'
    ],
    'vintage' => [
        'nome' => 'Vintage (Web)',
        'descricao' => 'Layout retrô com tons sépia',
        'cor_fundo' => 'linear-gradient(135deg, #d4a373, #faedcd)',
        'cor_texto' => '#4a3728',
        'cor_destaque' => '#b5835a',
        'fonte' => 'Georgia',
        'estilo_foto' => 'circle',
        'borda' => '3mm solid #b5835a',
        'preview' => '📜',
        'url' => 'https://example.com/layouts/vintage'
    ]
];

// ===== SALVAR CONFIGURAÇÃO =====
$erro = '';
$mensagem = '';
$layout_selecionado = $_GET['layout'] ?? 'classico';
$layout_atual = $layouts[$layout_selecionado] ?? $layouts['classico'];

// Se for layout da internet
if (isset($layouts_internet[$layout_selecionado])) {
    $layout_atual = $layouts_internet[$layout_selecionado];
}

// ===== CARREGAR CONFIGURAÇÃO SALVA =====
$config_file = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/config_cartao.json';
$config_atual = [];

if (file_exists($config_file)) {
    $json = file_get_contents($config_file);
    $config_atual = json_decode($json, true);
}

// ===== SALVAR LAYOUT SELECIONADO =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['layout'])) {
    $layout_escolhido = $_POST['layout'];
    
    // Verificar se é layout pré-definido ou da internet
    if (isset($layouts[$layout_escolhido])) {
        $layout = $layouts[$layout_escolhido];
    } elseif (isset($layouts_internet[$layout_escolhido])) {
        $layout = $layouts_internet[$layout_escolhido];
    } else {
        $erro = 'Layout não encontrado!';
    }
    
    if (empty($erro)) {
        // Salvar configurações
        $config = [
            'largura' => $config_atual['largura'] ?? 90,
            'altura' => $config_atual['altura'] ?? 95,
            'cor_fundo' => $layout['cor_fundo'],
            'cor_texto' => $layout['cor_texto'],
            'cor_destaque' => $layout['cor_destaque'],
            'fonte' => $layout['fonte'],
            'estilo_foto' => $layout['estilo_foto'],
            'borda' => $layout['borda'],
            'layout_selecionado' => $layout_escolhido,
            'mostrar_controle_pagamentos' => $config_atual['mostrar_controle_pagamentos'] ?? true,
            'mostrar_codigo_barras' => $config_atual['mostrar_codigo_barras'] ?? true,
            'mostrar_ano_letivo' => $config_atual['mostrar_ano_letivo'] ?? true
        ];
        
        $pasta = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/';
        if (!file_exists($pasta)) {
            mkdir($pasta, 0777, true);
        }
        
        if (file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT))) {
            $mensagem = 'Layout "' . $layout['nome'] . '" aplicado com sucesso!';
            $layout_atual = $layout;
        } else {
            $erro = 'Erro ao salvar as configurações.';
        }
    }
}

include '../includes/header_escola.php';
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
    
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .btn-secondary:hover {
        background: #e2e8f0;
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
    
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    
    .btn-success:hover {
        background: #27ae60;
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
    
    .btn-import {
        background: #8e44ad;
        color: #fff;
    }
    
    .btn-import:hover {
        background: #732d91;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(142, 68, 173, 0.3);
    }
    
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .alert-info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    
    .layouts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .layout-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        border: 2px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        transition: all 0.3s;
        cursor: pointer;
        position: relative;
    }
    
    .layout-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    }
    
    .layout-card.active {
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.2);
    }
    
    .layout-card .preview {
        width: 100%;
        height: 100px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        margin-bottom: 12px;
        transition: all 0.3s;
        overflow: hidden;
        position: relative;
    }
    
    .layout-card .preview .preview-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        padding: 10px;
    }
    
    .layout-card .preview .preview-content .preview-nome {
        font-size: 14px;
        font-weight: 700;
        color: #fff;
        text-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }
    
    .layout-card .preview .preview-content .preview-detalhe {
        font-size: 10px;
        color: rgba(255,255,255,0.8);
        margin-top: 2px;
    }
    
    .layout-card .info h3 {
        font-size: 16px;
        font-weight: 700;
        color: #1a2332;
        margin: 0 0 4px;
    }
    
    .layout-card .info p {
        font-size: 13px;
        color: #94a3b8;
        margin: 0 0 10px;
    }
    
    .layout-card .info .badge {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        background: #e0f2fe;
        color: #0369a1;
    }
    
    .layout-card .info .badge-web {
        background: #fef3c7;
        color: #92400e;
    }
    
    .layout-card .btn-aplicar {
        width: 100%;
        margin-top: 10px;
        justify-content: center;
    }
    
    .nav-alunos {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 25px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .nav-alunos a {
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
        color: #4a5568;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .nav-alunos a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .nav-alunos a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .modal.active {
        display: flex;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    
    .modal-content h2 {
        color: #1a2332;
        margin: 0 0 15px;
    }
    
    .modal-content .form-group {
        margin-bottom: 15px;
    }
    
    .modal-content .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #1a2332;
        font-size: 13px;
    }
    
    .modal-content .form-group input,
    .modal-content .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        transition: border-color 0.3s;
        font-family: inherit;
    }
    
    .modal-content .form-group input:focus,
    .modal-content .form-group textarea:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    
    .modal-content .form-group textarea {
        min-height: 60px;
        resize: vertical;
    }
    
    .modal-content .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .nav-alunos {
            flex-direction: column;
            align-items: stretch;
        }
        .nav-alunos a {
            text-align: center;
            justify-content: center;
        }
        .layouts-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>🎨 Layouts para Cartões Escolares</h1>
        <p class="subtitle">Escolha um layout ou importe da internet para personalizar os cartões</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="cartoes_escolares.php" class="btn btn-secondary">← Voltar</a>
        <a href="configurar_cartao.php" class="btn btn-info">⚙️ Configurar</a>
    </div>
</div>

<!-- Navegação -->
<div class="nav-alunos">
    <a href="index.php">📋 Lista de Alunos</a>
    <a href="add.php">➕ Cadastrar Aluno</a>
    <a href="reconfirmar.php">🔄 Reconfirmação</a>
    <a href="consulta.php">🔍 Consulta</a>
    <a href="relatorio.php">📈 Relatório</a>
    <a href="relatorio_idade.php">📊 Relatório por Idade</a>
    <a href="listas_nominais.php">📋 Listas Nominais</a>
    <a href="cartoes_escolares.php">🪪 Cartões Escolares</a>
    <a href="configurar_cartao.php">⚙️ Configurar Cartão</a>
    <a href="layouts_cartao.php" class="active">🎨 Layouts</a>
</div>

<?php if ($mensagem): ?>
    <div class="alert alert-success">✅ <?= $mensagem ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error">❌ <?= $erro ?></div>
<?php endif; ?>

<!-- Layouts Pré-definidos -->
<h3 style="color: #1a2332; margin: 20px 0 10px;">📦 Layouts Pré-definidos</h3>
<div class="layouts-grid">
    <?php foreach($layouts as $key => $layout): 
        $is_active = ($layout_selecionado == $key);
    ?>
    <div class="layout-card <?= $is_active ? 'active' : '' ?>">
        <div class="preview" style="background: <?= $layout['cor_fundo'] ?>; border: <?= $layout['borda'] ?>;">
            <div class="preview-content">
                <span style="font-size: 28px;"><?= $layout['preview'] ?></span>
                <div class="preview-nome"><?= htmlspecialchars($layout['nome']) ?></div>
                <div class="preview-detalhe">Fonte: <?= htmlspecialchars($layout['fonte']) ?></div>
            </div>
        </div>
        <div class="info">
            <h3><?= htmlspecialchars($layout['nome']) ?></h3>
            <p><?= htmlspecialchars($layout['descricao']) ?></p>
            <span class="badge">Pré-definido</span>
        </div>
        <form method="POST">
            <input type="hidden" name="layout" value="<?= $key ?>">
            <button type="submit" class="btn btn-<?= $is_active ? 'success' : 'primary' ?> btn-aplicar">
                <?= $is_active ? '✅ Ativo' : '📥 Aplicar Layout' ?>
            </button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<!-- Layouts da Internet -->
<h3 style="color: #1a2332; margin: 30px 0 10px;">🌐 Layouts da Internet</h3>
<div class="layouts-grid">
    <?php foreach($layouts_internet as $key => $layout): 
        $is_active = ($layout_selecionado == $key);
    ?>
    <div class="layout-card <?= $is_active ? 'active' : '' ?>">
        <div class="preview" style="background: <?= $layout['cor_fundo'] ?>; border: <?= $layout['borda'] ?>;">
            <div class="preview-content">
                <span style="font-size: 28px;"><?= $layout['preview'] ?></span>
                <div class="preview-nome"><?= htmlspecialchars($layout['nome']) ?></div>
                <div class="preview-detalhe">Fonte: <?= htmlspecialchars($layout['fonte']) ?></div>
            </div>
        </div>
        <div class="info">
            <h3><?= htmlspecialchars($layout['nome']) ?></h3>
            <p><?= htmlspecialchars($layout['descricao']) ?></p>
            <span class="badge badge-web">🌐 Web</span>
            <?php if (isset($layout['url'])): ?>
            <span style="font-size: 10px; color: #94a3b8; display: block; margin-top: 2px;">
                Fonte: <?= htmlspecialchars($layout['url']) ?>
            </span>
            <?php endif; ?>
        </div>
        <form method="POST">
            <input type="hidden" name="layout" value="<?= $key ?>">
            <button type="submit" class="btn btn-<?= $is_active ? 'success' : 'btn-import' ?> btn-aplicar">
                <?= $is_active ? '✅ Ativo' : '📥 Importar Layout' ?>
            </button>
        </form>
    </div>
    <?php endforeach; ?>
    
    <!-- Card para importar novo layout -->
    <div class="layout-card" style="border: 2px dashed #d1d5db; cursor: pointer;" onclick="abrirModalImportar()">
        <div class="preview" style="background: #f8fafc; border: 2px dashed #d1d5db; display: flex; align-items: center; justify-content: center;">
            <div style="text-align: center; color: #94a3b8;">
                <span style="font-size: 48px; display: block;">➕</span>
                <span style="font-size: 14px; font-weight: 600;">Importar Novo Layout</span>
                <span style="font-size: 12px;">Da internet ou personalizado</span>
            </div>
        </div>
        <div class="info" style="text-align: center; padding: 10px 0;">
            <p style="color: #94a3b8; font-size: 13px;">Clique para importar um layout da internet ou criar um personalizado</p>
        </div>
        <button class="btn btn-import btn-aplicar" onclick="abrirModalImportar()">🌐 Importar Layout</button>
    </div>
</div>

<!-- Layout Atual -->
<div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid #eef2f7;">
    <h4 style="color: #1a2332; margin: 0 0 5px;">📌 Layout Atual</h4>
    <p style="color: #94a3b8; margin: 0;">
        <strong><?= htmlspecialchars($layout_atual['nome'] ?? 'Clássico') ?></strong> - 
        <?= htmlspecialchars($layout_atual['descricao'] ?? 'Layout padrão do sistema') ?>
    </p>
    <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
        <span style="display: inline-block; padding: 2px 12px; background: <?= $layout_atual['cor_fundo'] ?? '#FF8C00' ?>; border-radius: 4px; color: <?= $layout_atual['cor_texto'] ?? '#fff' ?>; font-size: 12px; font-weight: 600;">
            Fundo
        </span>
        <span style="display: inline-block; padding: 2px 12px; background: <?= $layout_atual['cor_destaque'] ?? '#FF8C00' ?>; border-radius: 4px; color: #fff; font-size: 12px; font-weight: 600;">
            Destaque
        </span>
        <span style="display: inline-block; padding: 2px 12px; background: #f1f5f9; border-radius: 4px; font-size: 12px;">
            Fonte: <?= htmlspecialchars($layout_atual['fonte'] ?? 'Arial') ?>
        </span>
        <span style="display: inline-block; padding: 2px 12px; background: #f1f5f9; border-radius: 4px; font-size: 12px;">
            Foto: <?= htmlspecialchars($layout_atual['estilo_foto'] ?? 'circle') ?>
        </span>
    </div>
</div>

<!-- ===== MODAL PARA IMPORTAR LAYOUT ===== -->
<div class="modal" id="modalImportar">
    <div class="modal-content">
        <h2>🌐 Importar Layout da Internet</h2>
        <p style="color: #94a3b8; margin-bottom: 15px;">Cole o código do layout ou preencha os dados manualmente</p>
        
        <form method="POST" id="formImportar">
            <div class="form-group">
                <label>Nome do Layout</label>
                <input type="text" name="nome_layout" placeholder="Ex: Meu Layout Personalizado" required>
            </div>
            
            <div class="form-group">
                <label>Descrição</label>
                <textarea name="descricao_layout" placeholder="Breve descrição do layout"></textarea>
            </div>
            
            <div class="form-group">
                <label>URL do Layout (opcional)</label>
                <input type="url" name="url_layout" placeholder="https://exemplo.com/layout.json">
            </div>
            
            <div class="form-group">
                <label>Cor de Fundo (CSS)</label>
                <input type="text" name="cor_fundo" placeholder="linear-gradient(135deg, #FF8C00, #FFA500)" value="linear-gradient(135deg, #1a2332, #2c3e50)">
            </div>
            
            <div class="form-group">
                <label>Cor do Texto</label>
                <input type="color" name="cor_texto" value="#ffffff">
            </div>
            
            <div class="form-group">
                <label>Cor de Destaque</label>
                <input type="color" name="cor_destaque" value="#f5d76e">
            </div>
            
            <div class="form-group">
                <label>Fonte</label>
                <select name="fonte">
                    <option value="Arial">Arial</option>
                    <option value="Segoe UI">Segoe UI</option>
                    <option value="Georgia">Georgia</option>
                    <option value="Tahoma">Tahoma</option>
                    <option value="Times New Roman">Times New Roman</option>
                    <option value="Impact">Impact</option>
                    <option value="Courier New">Courier New</option>
                    <option value="Comic Sans MS">Comic Sans MS</option>
                    <option value="Montserrat">Montserrat</option>
                    <option value="Orbitron">Orbitron</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Estilo da Foto</label>
                <select name="estilo_foto">
                    <option value="circle">Circular</option>
                    <option value="rounded">Arredondado</option>
                    <option value="square">Quadrado</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Borda</label>
                <input type="text" name="borda" placeholder="3mm solid #f5d76e" value="3mm solid #f5d76e">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">✅ Importar Layout</button>
                <button type="button" class="btn btn-secondary" onclick="fecharModalImportar()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModalImportar() {
        document.getElementById('modalImportar').classList.add('active');
    }
    
    function fecharModalImportar() {
        document.getElementById('modalImportar').classList.remove('active');
    }
    
    // Fechar modal ao clicar fora
    document.getElementById('modalImportar').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalImportar();
        }
    });
    
    // Processar formulário de importação
    document.getElementById('formImportar').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Coletar dados
        var dados = {
            layout: 'personalizado_' + Date.now(),
            nome: document.querySelector('input[name="nome_layout"]').value,
            descricao: document.querySelector('textarea[name="descricao_layout"]').value || 'Layout personalizado',
            url: document.querySelector('input[name="url_layout"]').value || '',
            cor_fundo: document.querySelector('input[name="cor_fundo"]').value,
            cor_texto: document.querySelector('input[name="cor_texto"]').value,
            cor_destaque: document.querySelector('input[name="cor_destaque"]').value,
            fonte: document.querySelector('select[name="fonte"]').value,
            estilo_foto: document.querySelector('select[name="estilo_foto"]').value,
            borda: document.querySelector('input[name="borda"]').value,
            preview: '🎨'
        };
        
        // Salvar via AJAX
        fetch('salvar_layout_personalizado.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(dados)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Layout importado com sucesso!');
                location.reload();
            } else {
                alert('❌ Erro ao importar layout: ' + data.error);
            }
        })
        .catch(error => {
            alert('❌ Erro ao importar layout: ' + error);
        });
    });
</script>

<?php include '../includes/footer_escola.php'; ?>