<?php
// ============================================
// modules/escola/alunos/view.php - Visualizar Aluno (COM FOTO CORRIGIDO)
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verifica permissão
if (function_exists('temPermissao')) {
    if (!temPermissao('Escola', 'visualizar')) {
        header('Location: ' . SITE_URL);
        exit;
    }
}

$id = $_GET['id'] ?? 0;
$aluno = null;

if ($id) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
        $stmt->execute([$id]);
        $aluno = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Erro ao buscar aluno: " . $e->getMessage());
    }
}

if (!$aluno) {
    header('Location: index.php');
    exit;
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
    
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    
    .btn-warning:hover {
        background: #d68910;
    }
    
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #c0392b;
    }
    
    .btn-success {
        background: #10b981;
        color: #fff;
    }
    
    .btn-success:hover {
        background: #059669;
    }
    
    .card-detalhes {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 900px;
    }
    
    .card-detalhes .header-info {
        display: flex;
        align-items: center;
        gap: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #eef2f7;
        margin-bottom: 15px;
    }
    
    .card-detalhes .header-info .foto-container {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        border: 4px solid #c9a84c;
        box-shadow: 0 4px 20px rgba(201,168,76,0.3);
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    
    .card-detalhes .header-info .foto-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .card-detalhes .header-info .foto-container .sem-foto {
        font-size: 48px;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        background: #f1f5f9;
    }
    
    .card-detalhes .header-info .info h2 {
        font-size: 22px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .card-detalhes .header-info .info .sub {
        color: #94a3b8;
        font-size: 14px;
        margin-top: 4px;
    }
    
    .card-detalhes .header-info .info .sub .separator {
        margin: 0 10px;
        color: #e2e8f0;
    }
    
    .card-detalhes .header-info .info .sub .id-aluno {
        font-weight: 600;
        color: #c9a84c;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-Matrícula {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .status-Confirmação {
        background: #d1fae5;
        color: #065f46;
    }
    
    .sexo-badge {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .sexo-M {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .sexo-F {
        background: #fce7f3;
        color: #9d174d;
    }
    
    .card-detalhes .grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 30px;
    }
    
    .card-detalhes .item {
        display: flex;
        padding: 6px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .card-detalhes .item:last-child {
        border-bottom: none;
    }
    
    .card-detalhes .item .label {
        width: 130px;
        font-weight: 600;
        color: #4a5568;
        flex-shrink: 0;
        font-size: 13px;
    }
    
    .card-detalhes .item .valor {
        color: #1a2332;
        font-weight: 500;
        font-size: 13px;
    }
    
    .section-title {
        font-size: 14px;
        font-weight: 700;
        color: #1a2332;
        margin: 15px 0 10px;
        padding-bottom: 5px;
        border-bottom: 1px solid #eef2f7;
    }
    
    .acoes {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 20px;
    }
    
    .btn-acoes-wrapper {
        display: inline-flex;
    }
    
    .btn-enviar-email {
        background: #3b82f6;
        color: #fff;
    }
    
    .btn-enviar-email:hover {
        background: #2563eb;
    }
    
    .btn-enviar-whatsapp {
        background: #25d366;
        color: #fff;
    }
    
    .btn-enviar-whatsapp:hover {
        background: #1da851;
    }
    
    .btn-enviar-sms {
        background: #f59e0b;
        color: #fff;
    }
    
    .btn-enviar-sms:hover {
        background: #d97706;
    }
    
    /* ESTILOS PARA OS BOTÕES DE ENVIO - SEMPRE VISÍVEIS */
    .btn-envio {
        padding: 8px 16px;
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
    
    .btn-envio:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    
    .btn-email {
        background: #3b82f6;
        color: #fff;
    }
    
    .btn-email:hover {
        background: #2563eb;
    }
    
    .btn-whatsapp {
        background: #25d366;
        color: #fff;
    }
    
    .btn-whatsapp:hover {
        background: #1da851;
    }
    
    .btn-sms {
        background: #f59e0b;
        color: #fff;
    }
    
    .btn-sms:hover {
        background: #d97706;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .card-detalhes .grid {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .card-detalhes .item .label {
            width: 100px;
        }
        .card-detalhes .header-info {
            flex-direction: column;
            text-align: center;
        }
        .card-detalhes .header-info .foto-container {
            width: 100px;
            height: 100px;
        }
        .acoes {
            flex-direction: column;
            align-items: stretch;
        }
        .acoes .btn {
            justify-content: center;
        }
        .btn-acoes-wrapper {
            width: 100%;
        }
        .btn-acoes-wrapper .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<?php
// ============================================================
// PROCESSAR ENVIO DE NOTIFICAÇÕES
// ============================================================

$mensagem_envio = '';
$tipo_envio = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar se a função de envio existe
    if (function_exists('enviarNotificacao')) {
        
        // Enviar Email
        if (isset($_POST['enviar_email'])) {
            $destinatario = !empty($aluno['email']) ? $aluno['email'] : $aluno['Contacto_do_Aluno'] . '@email.com';
            $assunto = 'Dados do Aluno - ' . $aluno['nome'];
            
            $mensagem = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #c9a84c; color: #1a2332; padding: 15px; text-align: center; }
                    .content { padding: 20px; background: #f8fafc; }
                    .dados { margin: 15px 0; }
                    .dados p { margin: 8px 0; }
                    .footer { background: #1a2332; color: #fff; padding: 10px; text-align: center; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>📋 Dados do Aluno</h2>
                    </div>
                    <div class='content'>
                        <div class='dados'>
                            <p><strong>👤 Nome:</strong> {$aluno['nome']}</p>
                            <p><strong>🎂 Data Nascimento:</strong> " . sprintf('%02d/%02d/%04d', $aluno['dia'] ?? 0, $aluno['mes'] ?? 0, $aluno['Ano'] ?? 0) . "</p>
                            <p><strong>🏫 Classe:</strong> {$aluno['Classe']}</p>
                            <p><strong>📚 Curso:</strong> {$aluno['Curso']}</p>
                            <p><strong>📞 Contacto:</strong> {$aluno['Contacto_do_Aluno']}</p>
                            <p><strong>📌 Situação:</strong> {$aluno['Situacao_Cadastro']}</p>
                            <hr>
                            <p><strong>👨 Nome do Pai:</strong> {$aluno['Nome_do_Pai']}</p>
                            <p><strong>👩 Nome da Mãe:</strong> {$aluno['Nome_da_mae']}</p>
                        </div>
                    </div>
                    <div class='footer'>
                        <p>Enviado por " . SITE_NAME . " - " . date('d/m/Y H:i') . "</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $resultado = enviarEmail($destinatario, $assunto, $mensagem);
            
            if ($resultado) {
                $mensagem_envio = "✅ Email enviado com sucesso para " . htmlspecialchars($destinatario);
                $tipo_envio = 'success';
                logEnvio('email', $destinatario, $assunto, true);
            } else {
                $mensagem_envio = "❌ Erro ao enviar email para " . htmlspecialchars($destinatario);
                $tipo_envio = 'error';
                logEnvio('email', $destinatario, $assunto, false, 'Erro no envio');
            }
        }
        
        // Enviar WhatsApp
        if (isset($_POST['enviar_whatsapp'])) {
            $numero = !empty($aluno['Contacto_do_Pai']) ? $aluno['Contacto_do_Pai'] : $aluno['Contacto_do_Aluno'];
            $mensagem = "📋 *DADOS DO ALUNO*\n\n";
            $mensagem .= "👤 Nome: " . $aluno['nome'] . "\n";
            $mensagem .= "🏫 Classe: " . $aluno['Classe'] . "\n";
            $mensagem .= "📚 Curso: " . $aluno['Curso'] . "\n";
            $mensagem .= "📞 Contacto: " . $aluno['Contacto_do_Aluno'] . "\n";
            $mensagem .= "📌 Situação: " . $aluno['Situacao_Cadastro'] . "\n\n";
            $mensagem .= "👨 Pai: " . $aluno['Nome_do_Pai'] . "\n";
            $mensagem .= "👩 Mãe: " . $aluno['Nome_da_mae'] . "\n\n";
            $mensagem .= "📅 Enviado em " . date('d/m/Y H:i');
            
            // Redirecionar para o WhatsApp
            $link = enviarWhatsApp($numero, $mensagem);
            header('Location: ' . $link);
            exit;
        }
        
        // Enviar SMS
        if (isset($_POST['enviar_sms'])) {
            $numero = !empty($aluno['Contacto_do_Pai']) ? $aluno['Contacto_do_Pai'] : $aluno['Contacto_do_Aluno'];
            $mensagem = "DADOS DO ALUNO: " . $aluno['nome'] . " - Classe: " . $aluno['Classe'] . " - " . SITE_NAME;
            
            $resultado = enviarSMS($numero, $mensagem);
            
            if ($resultado) {
                $mensagem_envio = "✅ SMS enviado com sucesso para " . htmlspecialchars($numero);
                $tipo_envio = 'success';
                logEnvio('sms', $numero, 'SMS Aluno', true);
            } else {
                $mensagem_envio = "❌ Erro ao enviar SMS para " . htmlspecialchars($numero);
                $tipo_envio = 'error';
                logEnvio('sms', $numero, 'SMS Aluno', false, 'Erro no envio');
            }
        }
        
    } else {
        $mensagem_envio = "⚠️ Funções de envio não disponíveis. Verifique o arquivo funcoes_envio.php";
        $tipo_envio = 'warning';
    }
}
?>

<div class="page-header">
    <h1>👤 Visualizar Aluno</h1>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if (!empty($mensagem_envio)): ?>
    <div class="alert alert-<?= $tipo_envio ?>" style="padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; 
         <?= $tipo_envio == 'success' ? 'background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;' : '' ?>
         <?= $tipo_envio == 'error' ? 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;' : '' ?>
         <?= $tipo_envio == 'warning' ? 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;' : '' ?>
         ">
        <?= $mensagem_envio ?>
    </div>
<?php endif; ?>

<div class="card-detalhes">
    <!-- Header com Foto -->
    <div class="header-info">
        <div class="foto-container">
            <?php 
            // ============================================
            // FUNÇÃO PARA ENCONTRAR A FOTO DO ALUNO
            // ============================================
            function encontrarFotoAluno($aluno) {
                $id_aluno = $aluno['id'] ?? 0;
                
                // 1. Tentar pelo campo 'foto' no banco
                if (!empty($aluno['foto'])) {
                    $nome_foto = $aluno['foto'];
                    
                    $caminhos = [
                        $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/alunos/' . $nome_foto,
                        $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/' . $nome_foto,
                        __DIR__ . '/../../../uploads/alunos/' . $nome_foto,
                        __DIR__ . '/../../../uploads/fotos_alunos/' . $nome_foto,
                        'C:/xampp/htdocs/softgest_web/uploads/alunos/' . $nome_foto,
                        'C:/xampp/htdocs/softgest_web/uploads/fotos_alunos/' . $nome_foto
                    ];
                    
                    foreach ($caminhos as $caminho) {
                        if (file_exists($caminho)) {
                            $url = str_replace($_SERVER['DOCUMENT_ROOT'], '', $caminho);
                            $url = str_replace('\\', '/', $url);
                            return $url;
                        }
                    }
                }
                
                // 2. Tentar buscar por ID com diferentes extensões
                $extensoes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $diretorios = [
                    $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/alunos/',
                    $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/',
                    __DIR__ . '/../../../uploads/alunos/',
                    __DIR__ . '/../../../uploads/fotos_alunos/',
                    'C:/xampp/htdocs/softgest_web/uploads/alunos/',
                    'C:/xampp/htdocs/softgest_web/uploads/fotos_alunos/'
                ];
                
                foreach ($diretorios as $dir) {
                    if (!is_dir($dir)) continue;
                    
                    foreach ($extensoes as $ext) {
                        $caminho = $dir . $id_aluno . '.' . $ext;
                        if (file_exists($caminho)) {
                            $url = str_replace($_SERVER['DOCUMENT_ROOT'], '', $caminho);
                            $url = str_replace('\\', '/', $url);
                            return $url;
                        }
                    }
                    
                    foreach ($extensoes as $ext) {
                        $caminho = $dir . 'aluno_' . $id_aluno . '.' . $ext;
                        if (file_exists($caminho)) {
                            $url = str_replace($_SERVER['DOCUMENT_ROOT'], '', $caminho);
                            $url = str_replace('\\', '/', $url);
                            return $url;
                        }
                    }
                    
                    $pattern = $dir . 'aluno_' . $id_aluno . '_*';
                    $arquivos = glob($pattern);
                    if (!empty($arquivos)) {
                        $url = str_replace($_SERVER['DOCUMENT_ROOT'], '', $arquivos[0]);
                        $url = str_replace('\\', '/', $url);
                        return $url;
                    }
                }
                
                // 3. Tentar buscar por qualquer arquivo que comece com o ID
                foreach ($diretorios as $dir) {
                    if (!is_dir($dir)) continue;
                    
                    $arquivos = glob($dir . $id_aluno . '.*');
                    if (!empty($arquivos)) {
                        $url = str_replace($_SERVER['DOCUMENT_ROOT'], '', $arquivos[0]);
                        $url = str_replace('\\', '/', $url);
                        return $url;
                    }
                }
                
                return null;
            }
            
            $foto_url = encontrarFotoAluno($aluno);
            $tem_foto = !is_null($foto_url);
            ?>
            
            <?php if ($tem_foto): ?>
                <img src="<?= $foto_url ?>" alt="Foto de <?= htmlspecialchars($aluno['nome'] ?? '') ?>" id="fotoAluno">
            <?php else: ?>
                <div class="sem-foto">👤</div>
            <?php endif; ?>
        </div>
        <div class="info">
            <h2><?= htmlspecialchars($aluno['nome'] ?? '') ?></h2>
            <div class="sub">
                <span class="sexo-badge sexo-<?= $aluno['Sexo'] ?? 'M' ?>">
                    <?= $aluno['Sexo'] ?? 'M' ?>
                </span>
                <span class="separator">|</span>
                <span class="id-aluno">ID: <?= htmlspecialchars($aluno['id'] ?? '') ?></span>
                <span class="separator">|</span>
                <span class="status-badge status-<?= $aluno['Situacao_Cadastro'] ?? 'Matrícula' ?>">
                    <?= $aluno['Situacao_Cadastro'] ?? 'Matrícula' ?>
                </span>
                <?php if ($tem_foto): ?>
                    <span class="separator">|</span>
                    <span style="color: #2ecc71; font-size: 12px;">📸 Com foto</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Dados Pessoais -->
    <div class="section-title">📌 Dados Pessoais</div>
    <div class="grid">
        <div class="item">
            <span class="label">Nº Processo:</span>
            <span class="valor"><?= htmlspecialchars($aluno['id'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Nome Completo:</span>
            <span class="valor"><?= htmlspecialchars($aluno['nome'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Sexo:</span>
            <span class="valor"><?= $aluno['Sexo'] ?? '-' ?></span>
        </div>
        <div class="item">
            <span class="label">Data Nascimento:</span>
            <span class="valor">
                <?php 
                $dia = $aluno['dia'] ?? 0;
                $mes = $aluno['mes'] ?? 0;
                $ano = $aluno['Ano'] ?? 0;
                if ($dia > 0 && $mes > 0 && $ano > 0) {
                    echo sprintf('%02d/%02d/%04d', $dia, $mes, $ano);
                } else {
                    echo '-';
                }
                ?>
            </span>
        </div>
        <div class="item">
            <span class="label">Idade:</span>
            <span class="valor"><?= ($aluno['Idade'] ?? 0) > 0 ? $aluno['Idade'] . ' anos' : '-' ?></span>
        </div>
        <div class="item">
            <span class="label">Morada:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Morada'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Transporte:</span>
            <span class="valor"><?= $aluno['Cadastro_Transporte'] ?? 'Não' ?></span>
        </div>
        <div class="item">
            <span class="label">Contacto:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Contacto_do_Aluno'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Naturalidade:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Naturalidade'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Município:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Municipio'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Província:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Provincia'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Nº BI:</span>
            <span class="valor"><?= htmlspecialchars($aluno['N_BI'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Debilidade:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Debilidade'] ?? 'Nenhuma') ?></span>
        </div>
        <div class="item">
            <span class="label">Ocupação do Aluno:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Ocupacao_do_Aluno'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Data Emissão BI:</span>
            <span class="valor">
                <?php 
                $data_bi = $aluno['Data_Emissao_do_BI'] ?? '';
                if (!empty($data_bi) && $data_bi != '0000-00-00' && $data_bi != '1970-01-01') {
                    echo date('d/m/Y', strtotime($data_bi));
                } else {
                    echo '-';
                }
                ?>
            </span>
        </div>
        <div class="item">
            <span class="label">Arq. Identificação:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Arq_identificacao'] ?? '-') ?></span>
        </div>
    </div>

    <!-- Dados Escolares -->
    <div class="section-title">🏫 Dados Escolares</div>
    <div class="grid">
        <div class="item">
            <span class="label">Classe:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Classe'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Curso:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Curso'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Turma:</span>
            <span class="valor"><?= htmlspecialchars($aluno['TURMA'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Sala:</span>
            <span class="valor"><?= htmlspecialchars($aluno['SALA'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Período:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Periodo'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Data Matrícula:</span>
            <span class="valor">
                <?php 
                $data_mat = $aluno['Data_Matricula'] ?? '';
                if (!empty($data_mat) && $data_mat != '0000-00-00' && $data_mat != '1970-01-01') {
                    echo date('d/m/Y', strtotime($data_mat));
                } else {
                    echo '-';
                }
                ?>
            </span>
        </div>
        <div class="item">
            <span class="label">Situação:</span>
            <span class="valor">
                <span class="status-badge status-<?= $aluno['Situacao_Cadastro'] ?? 'Matrícula' ?>">
                    <?= $aluno['Situacao_Cadastro'] ?? 'Matrícula' ?>
                </span>
            </span>
        </div>
    </div>

    <!-- Dados dos Pais -->
    <div class="section-title">👨‍👩‍👦 Dados dos Pais</div>
    <div class="grid">
        <div class="item">
            <span class="label">Nome do Pai:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Nome_do_Pai'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Contacto do Pai:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Contacto4'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Ocupação do Pai:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Ocupacao'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Local de Trabalho:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Local_de_Trabalho'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Nome da Mãe:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Nome_da_mae'] ?? '-') ?></span>
        </div>
        <div class="item">
            <span class="label">Contacto da Mãe:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Contacto_Mae'] ?? '-') ?></span>
        </div>
    </div>

    <!-- Ações com Verificação de Permissão - BOTÃO EXCLUIR ESCONDIDO SE NÃO TIVER PERMISSÃO -->
    <div class="acoes">
        <!-- Botão Editar - Só aparece se tiver permissão -->
        <?php if (function_exists('temPermissao') && temPermissao('Escola', 'editar')): ?>
            <a href="edit.php?id=<?= $aluno['id'] ?>" class="btn btn-primary">✏️ Editar Aluno</a>
        <?php endif; ?>
        

        <!-- FIM DO BOTÃO EXCLUIR - ESCONDIDO SE NÃO TIVER PERMISSÃO -->
        
        <!-- Botões de Envio - SEMPRE VISÍVEIS -->
        <form method="POST" style="display: inline;">
            <button type="submit" name="enviar_email" class="btn btn-envio btn-email" 
                    title="Enviar dados do aluno por email">
                📧 Email
            </button>
        </form>
        
        <form method="POST" style="display: inline;">
            <button type="submit" name="enviar_whatsapp" class="btn btn-envio btn-whatsapp"
                    title="Enviar dados do aluno por WhatsApp">
                💬 WhatsApp
            </button>
        </form>
        
        <form method="POST" style="display: inline;">
            <button type="submit" name="enviar_sms" class="btn btn-envio btn-sms"
                    title="Enviar dados do aluno por SMS">
                📱 SMS
            </button>
        </form>
        
        <a href="gerar_ficha.php?id=<?= $aluno['id'] ?>" class="btn btn-warning">📄 Gerar Ficha</a>
        <a href="gerar_comprovante.php?id=<?= $aluno['id'] ?>" class="btn btn-success">📄 Comprovante</a>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<script>
    // Função de confirmação de exclusão melhorada
    function confirmarExclusao(id, nome) {
        if (confirm('⚠️ TEM CERTEZA QUE DESEJA EXCLUIR ESTE ALUNO?\n\n' +
                   '📌 Nome: ' + nome + '\n' +
                   '🆔 ID: ' + id + '\n\n' +
                   'Esta ação é irreversível!\n' +
                   'Todos os dados relacionados serão removidos permanentemente.')) {
            
            return confirm('🔴 ÚLTIMA CONFIRMAÇÃO:\n\n' +
                          'Deseja realmente excluir o aluno "' + nome + '"?\n' +
                          'Esta ação não pode ser desfeita.');
        }
        return false;
    }
    
    // Se a imagem não carregar, mostrar o placeholder
    document.addEventListener('DOMContentLoaded', function() {
        const img = document.getElementById('fotoAluno');
        if (img) {
            img.addEventListener