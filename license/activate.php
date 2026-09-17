<?php
// ============================================
// license/activate.php - Ativação de Licença
// Página independente sem header duplicado
// ============================================

require_once '../config/database.php';
require_once '../modules/license/LicenseClient.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se já tem licença ativa
function getLicencaAtiva($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM licencas WHERE status = 'ativa' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    return $stmt->fetch();
}

$licenca_ativa = getLicencaAtiva($pdo);
$mensagem = '';
$tipo_mensagem = '';

// Se já tem licença ativa, redirecionar
if ($licenca_ativa) {
    header('Location: ../index.php');
    exit;
}

// Processar ativação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    $chave = trim($_POST['chave_ativacao'] ?? '');
    
    if (empty($codigo) || empty($chave)) {
        $mensagem = '❌ Preencha todos os campos';
        $tipo_mensagem = 'error';
    } else {
        try {
            $client = new LicenseClient();
            
            // Gerar hardware ID
            $hardware_id = hash('sha256', 
                $_SERVER['SERVER_NAME'] . 
                $_SERVER['SERVER_ADDR'] . 
                $_SERVER['DOCUMENT_ROOT']
            );
            
            // Ativar licença
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativação de Licença - SoftGest</title>
    <style>
        /* ===== RESET E ESTILOS ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
        }
        
        .license-container {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            margin-top: 5px;
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
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus {
            border-color: #c9a84c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(197, 165, 50, 0.2);
        }
        
        .form-group input::placeholder {
            color: #94a3b8;
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
            transform: none;
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
            width: 24px;
            height: 24px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #c9a84c;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 15px auto 0;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .help-text {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
            color: #94a3b8;
        }
        
        .help-text a {
            color: #c9a84c;
            text-decoration: none;
            font-weight: 600;
        }
        
        .help-text a:hover {
            text-decoration: underline;
        }
        
        .hardware-id {
            background: #f1f5f9;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            color: #64748b;
            margin-top: 15px;
            word-break: break-all;
            font-family: monospace;
            display: none;
        }
        
        @media (max-width: 480px) {
            .license-container {
                padding: 25px;
            }
            
            .logo h1 {
                font-size: 26px;
            }
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
            <label for="codigo">📝 Código da Licença</label>
            <input 
                type="text" 
                id="codigo" 
                name="codigo" 
                placeholder="Ex: SG-202601-ABC12345" 
                required
                autofocus
            >
        </div>
        
        <div class="form-group">
            <label for="chave_ativacao">🔑 Chave de Ativação</label>
            <input 
                type="text" 
                id="chave_ativacao" 
                name="chave_ativacao" 
                placeholder="Digite a chave de ativação" 
                required
            >
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
    
    <div class="help-text">
        <p>Já tem uma licença? <a href="#" onclick="document.getElementById('hardwareInfo').style.display='block'">Mostrar ID do Hardware</a></p>
        <div id="hardwareInfo" class="hardware-id">
            <strong>Hardware ID:</strong><br>
            <?php 
            $hardware_id = hash('sha256', 
                $_SERVER['SERVER_NAME'] . 
                $_SERVER['SERVER_ADDR'] . 
                $_SERVER['DOCUMENT_ROOT']
            );
            echo $hardware_id;
            ?>
        </div>
    </div>
</div>

<script>
    // Prevenir duplo clique no botão
    document.getElementById('formAtivacao').addEventListener('submit', function(e) {
        const btn = document.getElementById('btnAtivar');
        const spinner = document.getElementById('spinner');
        
        btn.disabled = true;
        btn.textContent = '⏳ Ativando...';
        spinner.style.display = 'block';
        
        // Reativar após 10 segundos (fallback)
        setTimeout(function() {
            btn.disabled = false;
            btn.textContent = '🚀 Ativar Licença';
            spinner.style.display = 'none';
        }, 10000);
    });
    
    // Mostrar/ocultar hardware ID
    document.querySelector('.help-text a').addEventListener('click', function(e) {
        e.preventDefault();
        const info = document.getElementById('hardwareInfo');
        if (info.style.display === 'block') {
            info.style.display = 'none';
        } else {
            info.style.display = 'block';
        }
    });
</script>

</body>
</html>