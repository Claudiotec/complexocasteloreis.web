<?php
// admin/sync_modes.php
// Sincronizar dados entre modos (Público ↔ Local)

require_once '../config/app_modes.php';

// Verificar se é admin
if (!isset($_SESSION['usuario_perfil']) || $_SESSION['usuario_perfil'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$mensagem = '';
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tabela = $_POST['tabela'] ?? '';
    $direcao = $_POST['direcao'] ?? 'local_para_publico';
    
    if ($tabela) {
        try {
            $origem = ($direcao === 'local_para_publico') ? conectarLocal() : conectarPublico();
            $destino = ($direcao === 'local_para_publico') ? conectarPublico() : conectarLocal();
            
            // Buscar dados
            $stmt = $origem->query("SELECT * FROM $tabela");
            $dados = $stmt->fetchAll();
            
            if (empty($dados)) {
                $mensagem = "Nenhum dado encontrado na tabela '$tabela'";
            } else {
                $inseridos = 0;
                foreach ($dados as $row) {
                    $campos = array_keys($row);
                    $placeholders = implode(', ', array_fill(0, count($campos), '?'));
                    $sql = "INSERT INTO $tabela (" . implode(', ', $campos) . ") VALUES ($placeholders)";
                    $stmt = $destino->prepare($sql);
                    $stmt->execute(array_values($row));
                    $inseridos++;
                }
                $mensagem = "✅ $inseridos registros sincronizados de " . ($direcao === 'local_para_publico' ? 'Local' : 'Público') . " para " . ($direcao === 'local_para_publico' ? 'Público' : 'Local');
            }
        } catch (Exception $e) {
            $mensagem = "❌ Erro: " . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronizar Modos - SoftGest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #1a2332, #2c3e50); color: white; padding: 20px 30px; border-radius: 12px; margin-bottom: 25px; }
        .header h1 { font-size: 28px; }
        .header h1 span { color: #f5d76e; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; }
        .card h2 { font-size: 20px; color: #1a2332; margin-bottom: 20px; border-bottom: 2px solid #f5d76e; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; font-size: 14px; color: #1a2332; margin-bottom: 5px; }
        .form-group select { width: 100%; padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
        .btn { padding: 10px 25px; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); }
        .btn-success { background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; }
        .btn-success:hover { box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3); }
        .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .info-box { background: #f8fafc; padding: 15px; border-radius: 8px; margin-top: 15px; border: 1px solid #e2e8f0; }
        .info-box .modo { display: flex; align-items: center; gap: 10px; padding: 8px 0; }
        .info-box .modo .icon { font-size: 20px; }
        .info-box .modo .nome { font-weight: 600; }
        .info-box .modo .db { font-size: 13px; color: #94a3b8; }
        @media (max-width: 768px) { .header { flex-direction: column; align-items: flex-start; gap: 10px; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔄 <span>Sincronizar Modos</span></h1>
        <p style="color: #94a3b8; margin-top: 5px;">Sincronize dados entre Local e Público</p>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= strpos($mensagem, '✅') !== false ? 'success' : 'error' ?>">
            <?= nl2br(htmlspecialchars($mensagem)) ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>📋 Modos Disponíveis</h2>
        <div class="info-box">
            <div class="modo">
                <span class="icon">💻</span>
                <span class="nome">Modo Local</span>
                <span class="db">MySQL | <?= DB_HOST ?>/<?= DB_NAME ?></span>
            </div>
            <div class="modo">
                <span class="icon">☁️</span>
                <span class="nome">Modo Público</span>
                <span class="db">PostgreSQL | <?= DB_HOST ?>/<?= DB_NAME ?></span>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2>🔄 Sincronizar</h2>
        <form method="POST">
            <div class="form-group">
                <label>📋 Tabela</label>
                <select name="tabela" required>
                    <option value="">Selecione uma tabela...</option>
                    <?php
                    try {
                        $pdo = conectarBanco();
                        $stmt = $pdo->query("SHOW TABLES");
                        while ($row = $stmt->fetch(PDO::FETCH_COLUMN)) {
                            echo "<option value='$row'>$row</option>";
                        }
                    } catch (Exception $e) {}
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>🔄 Direção</label>
                <select name="direcao" required>
                    <option value="local_para_publico">💻 Local → ☁️ Público</option>
                    <option value="publico_para_local">☁️ Público → 💻 Local</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-success">🚀 Sincronizar</button>
        </form>
    </div>
</div>
</body>
</html>

<?php include '../includes/footer.php'; ?>