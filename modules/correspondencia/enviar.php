<?php
require_once '../../config/database.php';

$empresa = getEmpresa();
$mensagem = '';
$tipoMensagem = '';

// Buscar clientes para sugestão
$clientes = $pdo->query("SELECT nome, email, telefone FROM clientes ORDER BY nome")->fetchAll();

// Verificar se a tabela modelos_mensagem existe
try {
    $modelos = $pdo->query("SELECT * FROM modelos_mensagem ORDER BY nome")->fetchAll();
} catch(PDOException $e) {
    $modelos = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $destinatario = $_POST['destinatario'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $endereco = $_POST['endereco'];
    $assunto = $_POST['assunto'];
    $mensagem_texto = $_POST['mensagem'];
    $tipo = $_POST['tipo'];
    $enviar_agora = isset($_POST['enviar_agora']) ? true : false;
    
    // Inserir correspondência
    $stmt = $pdo->prepare("INSERT INTO correspondencias (destinatario, email, telefone, endereco, assunto, mensagem, tipo, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $status = $enviar_agora ? 'enviado' : 'rascunho';
    $stmt->execute([$destinatario, $email, $telefone, $endereco, $assunto, $mensagem_texto, $tipo, $status]);
    
    if ($enviar_agora && $tipo == 'whatsapp') {
        $numero = preg_replace('/[^0-9]/', '', $telefone);
        if (strlen($numero) == 10 || strlen($numero) == 11) {
            $numero = '55' . $numero;
        }
        $link = 'https://wa.me/' . $numero . '?text=' . urlencode($mensagem_texto);
        echo "<script>window.open('$link', '_blank');</script>";
    }
    
    if ($enviar_agora) {
        $mensagem = 'Mensagem enviada com sucesso!';
        $tipoMensagem = 'success';
    } else {
        $mensagem = 'Rascunho salvo com sucesso!';
        $tipoMensagem = 'info';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Correspondência - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.06);
            padding: 40px 45px;
            position: relative;
            overflow: hidden;
        }
        
        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #2c3e50, #3498db, #2ecc71);
        }
        
        .form-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f2f5;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .form-header h2 {
            font-size: 24px;
            color: #1e293b;
            margin: 0;
        }
        
        .form-header .subtitle {
            color: #94a3b8;
            font-size: 14px;
            margin: 0;
        }
        
        .form-group-modern {
            margin-bottom: 22px;
        }
        
        .form-group-modern label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }
        
        .form-group-modern label .required {
            color: #ef4444;
            margin-left: 3px;
        }
        
        .form-group-modern select,
        .form-group-modern input,
        .form-group-modern textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            color: #1e293b;
            background: #fafbfc;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group-modern select:focus,
        .form-group-modern input:focus,
        .form-group-modern textarea:focus {
            border-color: #3498db;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
            outline: none;
        }
        
        .form-group-modern select:hover,
        .form-group-modern input:hover {
            border-color: #94a3b8;
        }
        
        .form-group-modern textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-row-modern {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .tipo-selector {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        
        .tipo-option {
            flex: 1;
            min-width: 120px;
            padding: 15px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fafbfc;
        }
        
        .tipo-option:hover {
            border-color: #94a3b8;
            background: #f1f5f9;
        }
        
        .tipo-option.active {
            border-color: #3498db;
            background: #ebf5fb;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
        }
        
        .tipo-option .icon {
            font-size: 32px;
            display: block;
            margin-bottom: 5px;
        }
        
        .tipo-option .label {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #f0f2f5;
            flex-wrap: wrap;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 204, 113, 0.3);
        }
        
        .btn-save-draft {
            background: #f1f5f9;
            color: #64748b;
            padding: 14px 35px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-save-draft:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .btn-cancel {
            background: #fef2f2;
            color: #dc2626;
            padding: 14px 35px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-cancel:hover {
            background: #fee2e2;
        }
        
        .cliente-sugestao {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .cliente-sugestao .tag {
            background: #f1f5f9;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: #475569;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .cliente-sugestao .tag:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .info-box {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #3498db;
            margin-bottom: 20px;
        }
        
        .info-box .info-icon {
            font-size: 20px;
        }
        
        @media (max-width: 768px) {
            .form-card {
                padding: 25px 20px;
            }
            .form-row-modern {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .form-actions {
                flex-direction: column;
            }
            .btn-save, .btn-save-draft, .btn-cancel {
                width: 100%;
                justify-content: center;
            }
            .tipo-selector {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <div>
                    <h2>✉️ Nova Correspondência</h2>
                    <p class="subtitle">Envie mensagens por Email, WhatsApp ou SMS</p>
                </div>
            </div>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <!-- TIPO DE ENVIO -->
                <div class="form-group-modern">
                    <label>Tipo de Envio <span class="required">*</span></label>
                    <div class="tipo-selector" id="tipoSelector">
                        <div class="tipo-option active" data-tipo="email">
                            <span class="icon">📧</span>
                            <span class="label">Email</span>
                        </div>
                        <div class="tipo-option" data-tipo="whatsapp">
                            <span class="icon">💬</span>
                            <span class="label">WhatsApp</span>
                        </div>
                        <div class="tipo-option" data-tipo="sms">
                            <span class="icon">📱</span>
                            <span class="label">SMS</span>
                        </div>
                    </div>
                    <input type="hidden" name="tipo" id="tipoSelecionado" value="email">
                </div>
                
                <!-- DADOS DO DESTINATÁRIO -->
                <div class="info-box">
                    <span class="info-icon">💡</span>
                    <strong>Dica:</strong> Clique em um cliente abaixo para preencher automaticamente os dados
                </div>
                
                <div class="cliente-sugestao">
                    <?php foreach($clientes as $cliente): ?>
                        <span class="tag" onclick="preencherCliente('<?= htmlspecialchars($cliente['nome']) ?>', '<?= htmlspecialchars($cliente['email']) ?>', '<?= htmlspecialchars($cliente['telefone']) ?>')">
                            <?= htmlspecialchars($cliente['nome']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-row-modern">
                    <div class="form-group-modern">
                        <label>Destinatário <span class="required">*</span></label>
                        <input type="text" name="destinatario" id="destinatario" required>
                    </div>
                    <div class="form-group-modern">
                        <label>Telefone</label>
                        <input type="tel" name="telefone" id="telefone" placeholder="(00) 00000-0000">
                    </div>
                </div>
                
                <div class="form-row-modern">
                    <div class="form-group-modern">
                        <label>Email</label>
                        <input type="email" name="email" id="email" placeholder="email@exemplo.com">
                    </div>
                    <div class="form-group-modern">
                        <label>Endereço</label>
                        <input type="text" name="endereco" id="endereco" placeholder="Rua, número, bairro, cidade">
                    </div>
                </div>
                
                <div class="form-group-modern">
                    <label>Assunto <span class="required">*</span></label>
                    <input type="text" name="assunto" id="assunto" required>
                </div>
                
                <div class="form-group-modern">
                    <label>Mensagem <span class="required">*</span></label>
                    <textarea name="mensagem" id="mensagem" required placeholder="Digite sua mensagem aqui..."></textarea>
                </div>
                
                <!-- AÇÕES -->
                <div class="form-actions">
                    <button type="submit" name="enviar_agora" value="1" class="btn-save">📤 Enviar Agora</button>
                    <button type="submit" class="btn-save-draft">💾 Salvar Rascunho</button>
                    <a href="index.php" class="btn-cancel">✕ Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // ===== SELETOR DE TIPO =====
        document.querySelectorAll('.tipo-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.tipo-option').forEach(el => el.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tipoSelecionado').value = this.dataset.tipo;
            });
        });

        // ===== PREENCHER CLIENTE =====
        function preencherCliente(nome, email, telefone) {
            document.getElementById('destinatario').value = nome;
            document.getElementById('email').value = email || '';
            document.getElementById('telefone').value = telefone || '';
        }
    </script>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>