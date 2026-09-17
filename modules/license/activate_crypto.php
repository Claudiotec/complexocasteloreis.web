<?php
// license/activate_crypto.php
// Ativação com validação criptográfica

require_once '../config/database.php';
require_once '../modules/license/CryptoLicenseClient.php';

session_start();

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    $chave = trim($_POST['chave_ativacao'] ?? '');
    $email = trim($_POST['email_cliente'] ?? '');
    $empresa = trim($_POST['empresa_cliente'] ?? '');
    
    if (empty($codigo) || empty($chave) || empty($email)) {
        $mensagem = '❌ Preencha todos os campos';
        $tipo_mensagem = 'error';
    } else {
        try {
            $client = new CryptoLicenseClient();
            
            // Gerar chave do cliente
            $chave_cliente = $client->gerarChaveCliente($email, $empresa);
            
            // Verificar se a chave de ativação corresponde
            $chave_esperada = $client->gerarChaveAtivacao($codigo, $chave_cliente);
            
            if ($chave !== $chave_esperada) {
                $mensagem = '❌ Chave de ativação inválida para este cliente';
                $tipo_mensagem = 'error';
            } else {
                // Validar licença na API
                $resultado = $client->validar($codigo, $chave, $chave_cliente);
                
                if ($resultado['valid']) {
                    // Salvar no banco
                    $stmt = $pdo->prepare("
                        INSERT INTO licencas (codigo_licenca, chave_ativacao, chave_cliente, status, cliente_email, data_ativacao, data_expiracao)
                        VALUES (?, ?, ?, 'ativa', ?, NOW, ?)
                    ");
                    $stmt->execute([
                        $codigo,
                        $chave,
                        $chave_cliente,
                        $email,
                        $resultado['dados']['data_expiracao'] ?? null
                    ]);
                    
                    $_SESSION['licenca_ativa'] = $codigo;
                    $_SESSION['chave_cliente'] = $chave_cliente;
                    
                    $mensagem = '✅ Licença ativada com sucesso!';
                    $tipo_mensagem = 'success';
                    
                    // Redirecionar após 2 segundos
                    echo "<script>setTimeout(function(){ window.location.href = '../index.php'; }, 2000);</script>";
                } else {
                    $mensagem = '❌ ' . $resultado['message'];
                    $tipo_mensagem = 'error';
                }
            }
            
        } catch (Exception $e) {
            $mensagem = '❌ Erro: ' . $e->getMessage();
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { font-size: 32px; background: linear-gradient(135deg, #c9a84c, #f5d76e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 14px; color: #1a2332; }
        .form-group input {
            width: 100%; padding: 12px 15px;
            border: 2px solid #e2e8f0; border-radius: 10px;
            font-size: 16px; transition: all 0.3s;
        }
        .form-group input:focus { border-color: #c9a84c; outline: none; box-shadow: 0 0 0 3px rgba(197,165,50,0.2); }
        .btn {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332; border: none; border-radius: 10px;
            font-size: 18px; font-weight: 700; cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover { transform: scale(1.02); box-shadow: 0 8px 25px rgba(197,165,50,0.3); }
        .mensagem { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .mensagem.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .mensagem.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .info-box { background: #f8fafc; padding: 15px; border-radius: 10px; margin-top: 20px; border-left: 4px solid #c9a84c; }
        .info-box p { font-size: 13px; color: #64748b; margin: 5px 0; }
        .info-box strong { color: #1a2332; }
        .hardware-id { background: #f1f5f9; padding: 8px 12px; border-radius: 6px; font-size: 11px; color: #64748b; margin-top: 15px; word-break: break-all; font-family: monospace; display: none; }
        .help-text { text-align: center; margin-top: 15px; font-size: 13px; color: #94a3b8; }
        .help-text a { color: #c9a84c; text-decoration: none; font-weight: 600; }
        .help-text a:hover { text-decoration: underline; }
        @media (max-width: 480px) { .container { padding: 25px; } }
    </style>
</head>
<body>
<div class="container">
    <div class="logo">
        <h1>🔐 SoftGest</h1>
        <p>Ativação de Licença com Criptografia</p>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="mensagem <?= $tipo_mensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>📝 Código da Licença</label>
            <input type="text" name="codigo" placeholder="Ex: SG-202601-ABC12345" required autofocus>
        </div>
        
        <div class="form-group">
            <label>🔑 Chave de Ativação</label>
            <input type="text" name="chave_ativacao" placeholder="XXXX-XXXX-XXXX-XXXX" required>
            <small style="color: #94a3b8; font-size: 12px;">A chave é única para cada cliente</small>
        </div>
        
        <div class="form-group">
            <label>📧 Email do Cliente</label>
            <input type="email" name="email_cliente" placeholder="cliente@email.com" required>
            <small style="color: #94a3b8; font-size: 12px;">Use o mesmo email usado na geração</small>
        </div>
        
        <div class="form-group">
            <label>🏢 Empresa (opcional)</label>
            <input type="text" name="empresa_cliente" placeholder="Nome da empresa">
        </div>
        
        <button type="submit" class="btn">🚀 Ativar Licença</button>
    </form>
    
    <div class="info-box">
        <p><strong>🔒 Como funciona?</strong></p>
        <p>• A licença é criptografada com sua chave</p>
        <p>• A chave de ativação é gerada baseada no seu email</p>
        <p>• Apenas você pode ativar sua licença</p>
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
</body>
</html>