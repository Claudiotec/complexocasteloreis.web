<?php
// =============================================
// configurar_passe.php - Configurações do Passe
// =============================================

require_once '../../config/database.php';
date_default_timezone_set('Africa/Luanda');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Buscar configurações existentes
$config = [];
try {
    $stmt = $pdo->query("SELECT * FROM config_passe LIMIT 1");
    $config = $stmt->fetch();
} catch (Exception $e) {
    // Tabela ainda não existe
}

// Salvar configurações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'] ?? 'COMPLEXO ESCOLAR Nº 4006 - JAMBONDO';
    $endereco = $_POST['endereco'] ?? 'Icolo e Bengo - Bom Jesus, Jambondo';
    $telefone = $_POST['telefone'] ?? '925 307 484 / 958 670 657 / 946 646 242';
    $email = $_POST['email'] ?? 'escola4006jambondo@gmail.com';
    $site = $_POST['site'] ?? 'sistemaescolar4006.onrender.com';
    $slogan = $_POST['slogan'] ?? 'EDUCAR HOJE, TRANSFORMAR AMANHÃ';
    $footer = $_POST['footer'] ?? 'JUNTOS CONSTRUÍMOS O FUTURO';
    $cor_primaria = $_POST['cor_primaria'] ?? '#c9a84c';
    
    try {
        // Criar tabela se não existir
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS config_passe (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nome VARCHAR(200) NOT NULL,
                endereco VARCHAR(200) NOT NULL,
                telefone VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                site VARCHAR(100) NOT NULL,
                slogan VARCHAR(100) NOT NULL,
                footer VARCHAR(100) NOT NULL,
                cor_primaria VARCHAR(10) DEFAULT '#c9a84c',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Verificar se já existe
        $stmt = $pdo->query("SELECT COUNT(*) FROM config_passe");
        if ($stmt->fetchColumn() > 0) {
            $stmt = $pdo->prepare("UPDATE config_passe SET 
                nome = ?, endereco = ?, telefone = ?, email = ?, 
                site = ?, slogan = ?, footer = ?, cor_primaria = ?
            ");
        } else {
            $stmt = $pdo->prepare("INSERT INTO config_passe 
                (nome, endereco, telefone, email, site, slogan, footer, cor_primaria) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }
        $stmt->execute([$nome, $endereco, $telefone, $email, $site, $slogan, $footer, $cor_primaria]);
        
        $sucesso = "✅ Configurações salvas com sucesso!";
        $config = ['nome' => $nome, 'endereco' => $endereco, 'telefone' => $telefone, 
                   'email' => $email, 'site' => $site, 'slogan' => $slogan, 
                   'footer' => $footer, 'cor_primaria' => $cor_primaria];
    } catch (Exception $e) {
        $erro = "❌ Erro ao salvar: " . $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Passe</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content { margin-left: 280px; flex: 1; padding: 20px; max-width: calc(100% - 280px); min-height: 100vh; }
        .container { max-width: 800px; margin: 0 auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 25px; }
        .page-title { font-size: 28px; font-weight: 800; color: #1a2332; }
        .page-title span { color: #c9a84c; }
        
        .form-card { background: white; border-radius: 12px; padding: 30px; border: 1px solid #eef2f7; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #1a2332; font-size: 13px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; transition: border-color .3s; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }
        .form-group textarea { min-height: 60px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn { padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; border: none; }
        .btn-gold { background: linear-gradient(135deg, #c9a84c, #f5d76e); color: #1a2332; }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197,165,50,0.3); }
        .btn-secondary { background: #eef2f7; color: #1a2332; }
        .btn-secondary:hover { background: #e2e8f0; transform: translateY(-2px); }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .cor-preview { width: 40px; height: 40px; border-radius: 8px; border: 2px solid #eef2f7; display: inline-block; vertical-align: middle; margin-left: 10px; }
        .btn-back { padding: 8px 20px; background: #f1f5f9; color: #4a5568; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-back:hover { background: #e2e8f0; }
        
        @media (max-width: 992px) { .main-content { margin-left: 0; max-width: 100%; padding: 15px; } }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } .page-header { flex-direction: column; align-items: flex-start; gap: 10px; } }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <div>
                    <h1 class="page-title">⚙️ <span>Configurar</span> Passe</h1>
                    <p style="color: #94a3b8; font-size: 14px;">Personalize as informações do passe</p>
                </div>
                <a href="gerar_passes.php" class="btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>

            <?php if (isset($sucesso)): ?>
                <div class="alert alert-success"><?= $sucesso ?></div>
            <?php endif; ?>
            <?php if (isset($erro)): ?>
                <div class="alert alert-error"><?= $erro ?></div>
            <?php endif; ?>

            <div class="form-card">
                <form method="POST">
                    <div class="form-group">
                        <label>Nome da Empresa/Escola</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($config['nome'] ?? 'COMPLEXO ESCOLAR Nº 4006 - JAMBONDO') ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Endereço</label>
                            <input type="text" name="endereco" value="<?= htmlspecialchars($config['endereco'] ?? 'Icolo e Bengo - Bom Jesus, Jambondo') ?>">
                        </div>
                        <div class="form-group">
                            <label>Telefone</label>
                            <input type="text" name="telefone" value="<?= htmlspecialchars($config['telefone'] ?? '925 307 484 / 958 670 657 / 946 646 242') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($config['email'] ?? 'escola4006jambondo@gmail.com') ?>">
                        </div>
                        <div class="form-group">
                            <label>Site</label>
                            <input type="text" name="site" value="<?= htmlspecialchars($config['site'] ?? 'sistemaescolar4006.onrender.com') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Slogan Principal</label>
                            <input type="text" name="slogan" value="<?= htmlspecialchars($config['slogan'] ?? 'EDUCAR HOJE, TRANSFORMAR AMANHÃ') ?>">
                        </div>
                        <div class="form-group">
                            <label>Rodapé</label>
                            <input type="text" name="footer" value="<?= htmlspecialchars($config['footer'] ?? 'JUNTOS CONSTRUÍMOS O FUTURO') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Cor Primária (Dourado)</label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="color" name="cor_primaria" value="<?= htmlspecialchars($config['cor_primaria'] ?? '#c9a84c') ?>" style="width: 60px; height: 40px; padding: 2px; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer;">
                            <span style="font-size: 13px; color: #94a3b8;">Escolha a cor principal do passe</span>
                        </div>
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> Salvar Configurações</button>
                        <a href="gerar_passes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php include '../../includes/footer.php'; ?>
</body>
</html>