<?php
// ============================================
// modules/escola/alunos/configurar_cartao.php - Configurar Cartão Escolar
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

// ===== DADOS DA EMPRESA =====
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'SoftGest Sistemas';

// ===== CONFIGURAÇÕES PADRÃO =====
$config_padrao = [
    'largura' => 90,
    'altura' => 95,
    'cor_fundo' => 'linear-gradient(135deg, #FF8C00, #FFA500)',
    'cor_texto' => '#1a2332',
    'cor_destaque' => '#FF8C00',
    'logo' => 'insignia_angola.png',
    'mostrar_controle_pagamentos' => true,
    'mostrar_codigo_barras' => true,
    'mostrar_ano_letivo' => true
];

// ===== CARREGAR CONFIGURAÇÕES SALVAS =====
$config_file = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/config_cartao.json';
$config = $config_padrao;

if (file_exists($config_file)) {
    $json = file_get_contents($config_file);
    $config_salva = json_decode($json, true);
    if ($config_salva) {
        $config = array_merge($config_padrao, $config_salva);
    }
}

// ===== SALVAR CONFIGURAÇÕES =====
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $config = [
        'largura' => intval($_POST['largura'] ?? 90),
        'altura' => intval($_POST['altura'] ?? 95),
        'cor_fundo' => $_POST['cor_fundo'] ?? 'linear-gradient(135deg, #FF8C00, #FFA500)',
        'cor_texto' => $_POST['cor_texto'] ?? '#1a2332',
        'cor_destaque' => $_POST['cor_destaque'] ?? '#FF8C00',
        'logo' => $_POST['logo'] ?? 'insignia_angola.png',
        'mostrar_controle_pagamentos' => isset($_POST['mostrar_controle_pagamentos']) ? true : false,
        'mostrar_codigo_barras' => isset($_POST['mostrar_codigo_barras']) ? true : false,
        'mostrar_ano_letivo' => isset($_POST['mostrar_ano_letivo']) ? true : false
    ];
    
    // Validar tamanhos
    if ($config['largura'] < 50 || $config['largura'] > 150) {
        $erro = 'A largura deve estar entre 50mm e 150mm.';
    } elseif ($config['altura'] < 50 || $config['altura'] > 150) {
        $erro = 'A altura deve estar entre 50mm e 150mm.';
    } else {
        // Salvar configuração
        $pasta = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/';
        if (!file_exists($pasta)) {
            mkdir($pasta, 0777, true);
        }
        
        if (file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT))) {
            $mensagem = 'Configurações salvas com sucesso!';
        } else {
            $erro = 'Erro ao salvar as configurações. Verifique as permissões da pasta.';
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
    
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #c0392b;
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
    
    .form-container {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 800px;
    }
    
    .form-section {
        margin-bottom: 25px;
    }
    
    .form-section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1a2332;
        padding-bottom: 8px;
        border-bottom: 2px solid #eef2f7;
        margin-bottom: 15px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #1a2332;
        font-size: 13px;
    }
    
    .form-group label .help {
        font-weight: 400;
        color: #94a3b8;
        font-size: 11px;
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        transition: border-color 0.3s;
        font-family: inherit;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
    }
    
    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #c9a84c;
    }
    
    .checkbox-group label {
        font-weight: 500;
        color: #1a2332;
        cursor: pointer;
        font-size: 13px;
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
    
    .color-picker {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 5px;
    }
    
    .color-picker .color-option {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid transparent;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .color-picker .color-option:hover {
        transform: scale(1.1);
    }
    
    .color-picker .color-option.active {
        border-color: #1a2332;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.3);
    }
    
    .color-picker .color-option input {
        display: none;
    }
    
    .preview-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid #eef2f7;
        margin-top: 20px;
        text-align: center;
    }
    
    .preview-card .card-preview {
        width: 120px;
        height: 130px;
        margin: 0 auto;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 10px;
        font-size: 10px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #FF8C00, #FFA500);
        color: #1a2332;
    }
    
    .preview-card .card-preview .logo-preview {
        font-size: 20px;
        font-weight: 700;
    }
    
    .preview-card .card-preview .nome-preview {
        font-weight: 600;
        margin: 5px 0;
        font-size: 11px;
    }
    
    .preview-card .card-preview .info-preview {
        font-size: 8px;
        color: rgba(255,255,255,0.7);
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
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .form-row-3 {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .form-container {
            padding: 15px;
        }
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
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            justify-content: center;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>⚙️ Configurar Cartão Escolar</h1>
        <p class="subtitle">Personalize o layout e as informações do cartão escolar</p>
    </div>
    <a href="cartoes_escolares.php" class="btn btn-secondary">← Voltar</a>
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
    <a href="configurar_cartao.php" class="active">⚙️ Configurar Cartão</a>
</div>

<?php if ($mensagem): ?>
    <div class="alert alert-success">✅ <?= $mensagem ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error">❌ <?= $erro ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <!-- Tamanho do Cartão -->
        <div class="form-section">
            <div class="form-section-title">📐 Tamanho do Cartão</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Largura (mm) <span class="help">(50-150mm)</span></label>
                    <input type="number" name="largura" value="<?= $config['largura'] ?>" min="50" max="150" required>
                </div>
                <div class="form-group">
                    <label>Altura (mm) <span class="help">(50-150mm)</span></label>
                    <input type="number" name="altura" value="<?= $config['altura'] ?>" min="50" max="150" required>
                </div>
            </div>
        </div>
        
        <!-- Cores -->
        <div class="form-section">
            <div class="form-section-title">🎨 Cores do Cartão</div>
            
            <div class="form-group">
                <label>Cor de Fundo</label>
                <div class="color-picker">
                    <?php 
                    $cores = [
                        'linear-gradient(135deg, #FF8C00, #FFA500)' => 'Laranja',
                        'linear-gradient(135deg, #1e3c72, #2a5298)' => 'Azul Escuro',
                        'linear-gradient(135deg, #56ab2f, #a8e063)' => 'Verde',
                        'linear-gradient(135deg, #ff416c, #ff4b2b)' => 'Vermelho',
                        'linear-gradient(135deg, #8e2de2, #4a00e0)' => 'Roxo',
                        'linear-gradient(135deg, #f5af19, #f12711)' => 'Dourado',
                        'linear-gradient(135deg, #00b4db, #0083b0)' => 'Turquesa',
                        'linear-gradient(135deg, #ff7e5f, #feb47b)' => 'Pôr do Sol',
                        'linear-gradient(135deg, #0F2027, #203A43, #2C5364)' => 'Twilight',
                        '#1a2332' => 'Azul Escuro Sólido',
                        '#2ecc71' => 'Verde Sólido',
                        '#e74c3c' => 'Vermelho Sólido',
                        '#3498db' => 'Azul Sólido',
                        '#9b59b6' => 'Roxo Sólido'
                    ];
                    foreach($cores as $cor => $nome): 
                        $is_active = ($config['cor_fundo'] == $cor);
                    ?>
                    <label class="color-option <?= $is_active ? 'active' : '' ?>" style="background: <?= $cor ?>;" title="<?= $nome ?>">
                        <input type="radio" name="cor_fundo" value="<?= htmlspecialchars($cor) ?>" <?= $is_active ? 'checked' : '' ?>>
                    </label>
                    <?php endforeach; ?>
                </div>
                <span class="help">Selecione uma cor ou gradiente para o fundo do cartão</span>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Cor do Texto</label>
                    <div class="color-picker">
                        <?php 
                        $cores_texto = [
                            '#1a2332' => 'Preto',
                            '#ffffff' => 'Branco',
                            '#FF8C00' => 'Laranja',
                            '#2ecc71' => 'Verde',
                            '#e74c3c' => 'Vermelho',
                            '#3498db' => 'Azul'
                        ];
                        foreach($cores_texto as $cor => $nome): 
                            $is_active = ($config['cor_texto'] == $cor);
                        ?>
                        <label class="color-option <?= $is_active ? 'active' : '' ?>" style="background: <?= $cor ?>; border: 2px solid <?= $cor == '#ffffff' ? '#ccc' : $cor ?>;" title="<?= $nome ?>">
                            <input type="radio" name="cor_texto" value="<?= htmlspecialchars($cor) ?>" <?= $is_active ? 'checked' : '' ?>>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Cor de Destaque</label>
                    <div class="color-picker">
                        <?php 
                        $cores_destaque = [
                            '#FF8C00' => 'Laranja',
                            '#e74c3c' => 'Vermelho',
                            '#3498db' => 'Azul',
                            '#2ecc71' => 'Verde',
                            '#9b59b6' => 'Roxo',
                            '#f39c12' => 'Amarelo'
                        ];
                        foreach($cores_destaque as $cor => $nome): 
                            $is_active = ($config['cor_destaque'] == $cor);
                        ?>
                        <label class="color-option <?= $is_active ? 'active' : '' ?>" style="background: <?= $cor ?>;" title="<?= $nome ?>">
                            <input type="radio" name="cor_destaque" value="<?= htmlspecialchars($cor) ?>" <?= $is_active ? 'checked' : '' ?>>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Opções -->
        <div class="form-section">
            <div class="form-section-title">⚙️ Opções do Cartão</div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="mostrar_controle_pagamentos" id="mostrar_controle_pagamentos" <?= $config['mostrar_controle_pagamentos'] ? 'checked' : '' ?>>
                <label for="mostrar_controle_pagamentos">📊 Mostrar Controle de Pagamentos (verso)</label>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="mostrar_codigo_barras" id="mostrar_codigo_barras" <?= $config['mostrar_codigo_barras'] ? 'checked' : '' ?>>
                <label for="mostrar_codigo_barras">📱 Mostrar Código de Barras</label>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="mostrar_ano_letivo" id="mostrar_ano_letivo" <?= $config['mostrar_ano_letivo'] ? 'checked' : '' ?>>
                <label for="mostrar_ano_letivo">📅 Mostrar Ano Letivo</label>
            </div>
        </div>
        
        <!-- Pré-visualização -->
        <div class="form-section">
            <div class="form-section-title">👁️ Pré-visualização</div>
            <div class="preview-card">
                <div class="card-preview" id="cardPreview">
                    <div class="logo-preview"><?= htmlspecialchars($nomeEmpresa) ?></div>
                    <div class="nome-preview">Nome do Aluno</div>
                    <div class="info-preview">ID: 12345 | Classe: 6ª</div>
                </div>
                <p style="color: #94a3b8; font-size: 12px; margin-top: 10px;">
                    Pré-visualização do cartão com as configurações atuais
                </p>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Salvar Configurações</button>
            <a href="cartoes_escolares.php" class="btn btn-secondary">Cancelar</a>
            <button type="button" class="btn btn-danger" onclick="resetarConfiguracoes()">🔄 Restaurar Padrão</button>
        </div>
    </form>
</div>

<script>
    // Atualizar pré-visualização ao mudar as cores
    document.querySelectorAll('input[name="cor_fundo"]').forEach(function(input) {
        input.addEventListener('change', function() {
            document.getElementById('cardPreview').style.background = this.value;
        });
    });
    
    document.querySelectorAll('input[name="cor_texto"]').forEach(function(input) {
        input.addEventListener('change', function() {
            document.getElementById('cardPreview').style.color = this.value;
        });
    });
    
    // Atualizar pré-visualização ao mudar tamanho
    document.querySelectorAll('input[name="largura"], input[name="altura"]').forEach(function(input) {
        input.addEventListener('change', function() {
            var largura = document.querySelector('input[name="largura"]').value || 90;
            var altura = document.querySelector('input[name="altura"]').value || 95;
            var preview = document.getElementById('cardPreview');
            var scale = Math.min(120 / largura, 130 / altura);
            preview.style.width = (largura * scale) + 'px';
            preview.style.height = (altura * scale) + 'px';
        });
    });
    
    function resetarConfiguracoes() {
        if (confirm('Tem certeza que deseja restaurar as configurações padrão?')) {
            document.querySelector('input[name="largura"]').value = 90;
            document.querySelector('input[name="altura"]').value = 95;
            document.querySelector('input[name="cor_fundo"][value="linear-gradient(135deg, #FF8C00, #FFA500)"]').checked = true;
            document.querySelector('input[name="cor_texto"][value="#1a2332"]').checked = true;
            document.querySelector('input[name="cor_destaque"][value="#FF8C00"]').checked = true;
            document.getElementById('mostrar_controle_pagamentos').checked = true;
            document.getElementById('mostrar_codigo_barras').checked = true;
            document.getElementById('mostrar_ano_letivo').checked = true;
            
            // Atualizar pré-visualização
            document.getElementById('cardPreview').style.background = 'linear-gradient(135deg, #FF8C00, #FFA500)';
            document.getElementById('cardPreview').style.color = '#1a2332';
            
            alert('Configurações restauradas para o padrão. Clique em "Salvar" para confirmar.');
        }
    }
</script>

<?php include '../includes/footer_escola.php'; ?>