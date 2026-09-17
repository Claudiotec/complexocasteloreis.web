<?php
// license/activate.php
require_once '../config/database.php';
require_once '../modules/license/LicenseClient.php';

session_start();

// Buscar licença ativa no banco local
function getLicencaAtiva($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM licencas WHERE status = 'ativa' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    return $stmt->fetch();
}

$licenca_ativa = getLicencaAtiva($pdo);
$mensagem = '';
$tipo_mensagem = '';

// Processar ativação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    $chave = trim($_POST['chave_ativacao'] ?? '');
    
    if (empty($codigo) || empty($chave)) {
        $mensagem = 'Preencha todos os campos';
        $tipo_mensagem = 'error';
    } else {
        try {
            $client = new LicenseClient();
            
            // Gerar hardware ID (baseado em características do servidor)
            $hardware_id = hash('sha256', 
                $_SERVER['SERVER_NAME'] . 
                $_SERVER['SERVER_ADDR'] . 
                $_SERVER['DOCUMENT_ROOT']
            );
            
            // Validar/Ativar licença
            $resultado = $client->ativar($codigo, $chave, $hardware_id);
            
            if ($resultado['success']) {
                // Salvar no banco local
                $stmt = $pdo->prepare("
                    INSERT INTO licencas (codigo_licenca, chave_ativacao, status, data_ativacao)
                    VALUES (?, ?, 'ativa', NOW())
                ");
                $stmt->execute([$codigo, $chave]);
                
                $_SESSION['licenca_ativa'] = $codigo;
                $mensagem = '✅ ' . $resultado['message'];
                $tipo_mensagem = 'success';
                
                // Redirecionar após 2 segundos
                echo "<script>setTimeout(function(){ window.location.href = '../index.php'; }, 2000);</script>";
            } else {
                $mensagem = '❌ ' . $resultado['message'];
                $tipo_mensagem = 'error';
            }
            
        } catch (Exception $e) {
            $mensagem = '❌ Erro ao ativar licença: ' . $e->getMessage();
            $tipo_mensagem = 'error';
        }
    }
}

// Verificar se já tem licença ativa
if ($licenca_ativa) {
    header('Location: ../index.php');
    exit;
}

include '../includes/header.php';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ativação de Licença - SoftGest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .license-container {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            font-size: 32px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .logo p {
            color: #94a3b8;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1a2332;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            border-color: #c9a84c;
            outline: none;
        }
        .btn-activate {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-activate:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(197, 165, 50, 0.3);
        }
        .btn-activate:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .mensagem {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .mensagem.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .mensagem.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .info-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid #c9a84c;
        }
        .info-box p {
            font-size: 13px;
            color: #64748b;
            margin: 5px 0;
        }
        .info-box strong {
            color: #1a2332;
        }
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #c9a84c;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<div class="license-container">
    <div class="logo">
        <h1>🔐 SoftGest</h1>
        <p>Ativação de Licença</p>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="mensagem <?= $tipo_mensagem ?>">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" id="formAtivacao">
        <div class="form-group">
            <label>📝 Código da Licença</label>
            <input type="text" name="codigo" placeholder="Ex: SG-202601-ABC12345" required>
        </div>
        
        <div class="form-group">
            <label>🔑 Chave de Ativação</label>
            <input type="text" name="chave_ativacao" placeholder="Digite a chave de ativação" required>
        </div>
        
        <button type="submit" class="btn-activate" id="btnAtivar">
            🚀 Ativar Licença
        </button>
        <div class="spinner" id="spinner"></div>
    </form>
    
    <div class="info-box">
        <p><strong>ℹ️ Como obter sua licença?</strong></p>
        <p>• Entre em contato com o suporte para adquirir sua licença</p>
        <p>• Você receberá um código e uma chave de ativação</p>
        <p>• Insira os dados acima para ativar o sistema</p>
    </div>
</div>

<script>
document.getElementById('formAtivacao').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnAtivar');
    const spinner = document.getElementById('spinner');
    btn.disabled = true;
    btn.style.opacity = '0.6';
    spinner.style.display = 'block';
});
</script>

</body>
</html>

<?php include '../includes/footer.php'; ?>