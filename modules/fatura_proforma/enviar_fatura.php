<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

// Buscar dados da fatura
$stmt = $pdo->prepare("SELECT * FROM faturas_proforma WHERE id = ?");
$stmt->execute([$id]);
$fatura = $stmt->fetch();

if (!$fatura) {
    header("Location: index.php");
    exit;
}

// Buscar cliente
$stmtCli = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmtCli->execute([$fatura['cliente_id']]);
$cliente = $stmtCli->fetch();

// Buscar dados da empresa
$empresa = getEmpresa();

// Processar envio
$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo = $_POST['tipo'];
    $destinatario = $_POST['destinatario'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $assunto = $_POST['assunto'];
    $mensagem_texto = $_POST['mensagem'];
    $enviar_agora = isset($_POST['enviar_agora']) ? true : false;
    
    if ($enviar_agora) {
        // Atualizar status da fatura para enviada
        $stmt = $pdo->prepare("UPDATE faturas_proforma SET status = 'enviada', data_envio = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        // Registrar na tabela de correspondências (sem enviado_em)
        $stmt = $pdo->prepare("INSERT INTO correspondencias (destinatario, email, telefone, assunto, mensagem, tipo, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'enviado', NOW())");
        $stmt->execute([$destinatario, $email, $telefone, $assunto, $mensagem_texto, $tipo]);
        
        // Se for WhatsApp, abrir link
        if ($tipo == 'whatsapp') {
            $numero = preg_replace('/[^0-9]/', '', $telefone);
            if (strlen($numero) == 10 || strlen($numero) == 11) {
                $numero = '55' . $numero;
            }
            $link = 'https://wa.me/' . $numero . '?text=' . urlencode($mensagem_texto);
            echo "<script>window.open('$link', '_blank');</script>";
        }
        
        $mensagem = 'Fatura enviada com sucesso!';
        $tipoMensagem = 'success';
        
        // Redirecionar após 2 segundos
        echo "<script>setTimeout(function(){ window.location.href = 'index.php?enviada=1'; }, 2000);</script>";
    } else {
        // Salvar como rascunho
        $stmt = $pdo->prepare("INSERT INTO correspondencias (destinatario, email, telefone, assunto, mensagem, tipo, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'rascunho', NOW())");
        $stmt->execute([$destinatario, $email, $telefone, $assunto, $mensagem_texto, $tipo]);
        
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
    <title>Enviar Fatura - SoftGest Web</title>
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
        
        .fatura-info {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #3498db;
            margin-bottom: 25px;
        }
        
        .fatura-info .info-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .fatura-info .info-item {
            font-size: 14px;
            color: #475569;
        }
        
        .fatura-info .info-item strong {
            color: #1e293b;
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
        
        .btn-template {
            background: #f59e0b;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-template:hover {
            background: #d97706;
        }
        
        .template-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            border: 1px solid #e2e8f0;
            display: none;
        }
        
        .template-box.show {
            display: block;
        }
        
        .template-item {
            padding: 10px 15px;
            margin-bottom: 8px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .template-item:hover {
            border-color: #3498db;
            background: #ebf5fb;
        }
        
        .template-item .template-name {
            font-weight: 600;
            color: #1e293b;
        }
        
        .template-item .template-preview {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
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
                    <h2>📤 Enviar Fatura</h2>
                    <p class="subtitle">Envie a fatura por Email, WhatsApp ou SMS</p>
                </div>
                <span style="font-size: 14px; color: #94a3b8;">Fatura: <strong><?= $fatura['numero'] ?></strong></span>
            </div>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
            <?php endif; ?>
            
            <!-- Informações da Fatura -->
            <div class="fatura-info">
                <div class="info-row">
                    <div class="info-item"><strong>Nº:</strong> <?= $fatura['numero'] ?></div>
                    <div class="info-item"><strong>Cliente:</strong> <?= htmlspecialchars($cliente['nome'] ?? 'N/A') ?></div>
                    <div class="info-item"><strong>Total:</strong> R$ <?= number_format($fatura['total'], 2, ',', '.') ?></div>
                    <div class="info-item"><strong>Status:</strong> <?= ucfirst($fatura['status']) ?></div>
                </div>
            </div>
            
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
                
                <!-- TEMPLATES -->
                <div class="form-group-modern">
                    <label>Modelos de Mensagem</label>
                    <button type="button" class="btn-template" onclick="toggleTemplates()">📋 Mostrar Templates</button>
                    
                    <div class="template-box" id="templateBox">
                        <div class="template-item" onclick="aplicarTemplate('email', 'Fatura Proforma {{numero}}', 'Prezado(a) {{cliente}},\n\nSegue em anexo a fatura proforma nº {{numero}} no valor de R$ {{total}}.\n\nData de vencimento: {{vencimento}}\n\nAtenciosamente,\n{{empresa}}')">
                            <div class="template-name">📧 Template Email Padrão</div>
                            <div class="template-preview">Prezado(a) {{cliente}}, segue fatura nº {{numero}}...</div>
                        </div>
                        <div class="template-item" onclick="aplicarTemplate('whatsapp', 'Fatura Proforma {{numero}}', 'Olá {{cliente}}!\n\nSua fatura nº {{numero}} no valor de R$ {{total}} está disponível.\n\nData de vencimento: {{vencimento}}\n\nAtenciosamente,\n{{empresa}}')">
                            <div class="template-name">💬 Template WhatsApp</div>
                            <div class="template-preview">Olá {{cliente}}! Sua fatura nº {{numero}}...</div>
                        </div>
                        <div class="template-item" onclick="aplicarTemplate('sms', 'Fatura {{numero}}', 'Fatura {{numero}} - R$ {{total}} - Venc: {{vencimento}} - {{empresa}}')">
                            <div class="template-name">📱 Template SMS</div>
                            <div class="template-preview">Fatura {{numero}} - R$ {{total}} - Venc: {{vencimento}}...</div>
                        </div>
                        <div class="template-item" onclick="aplicarTemplate('email', 'Pagamento Fatura {{numero}}', 'Prezado(a) {{cliente}},\n\nConfirmamos o recebimento do pagamento da fatura nº {{numero}} no valor de R$ {{total}}.\n\nAtenciosamente,\n{{empresa}}')">
                            <div class="template-name">✅ Template Pagamento</div>
                            <div class="template-preview">Confirmamos o recebimento do pagamento da fatura...</div>
                        </div>
                    </div>
                </div>
                
                <!-- DADOS DO DESTINATÁRIO -->
                <div class="form-row-modern">
                    <div class="form-group-modern">
                        <label>Destinatário <span class="required">*</span></label>
                        <input type="text" name="destinatario" id="destinatario" value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>" required>
                    </div>
                    <div class="form-group-modern">
                        <label>Telefone</label>
                        <input type="tel" name="telefone" id="telefone" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-row-modern">
                    <div class="form-group-modern">
                        <label>Email</label>
                        <input type="email" name="email" id="email" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                    </div>
                    <div class="form-group-modern">
                        <label>Endereço</label>
                        <input type="text" name="endereco" id="endereco" value="<?= htmlspecialchars($cliente['endereco'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group-modern">
                    <label>Assunto <span class="required">*</span></label>
                    <input type="text" name="assunto" id="assunto" value="Fatura Proforma <?= $fatura['numero'] ?>" required>
                </div>
                
                <div class="form-group-modern">
                    <label>Mensagem <span class="required">*</span></label>
                    <textarea name="mensagem" id="mensagem" required><?php
                        $dados = [
                            'numero' => $fatura['numero'],
                            'cliente' => $cliente['nome'] ?? 'Cliente',
                            'total' => number_format($fatura['total'], 2, ',', '.'),
                            'vencimento' => date('d/m/Y', strtotime($fatura['data_validade'])),
                            'empresa' => $empresa['nome_fantasia'] ?? 'SoftGest Web'
                        ];
                        echo "Prezado(a) {$dados['cliente']},\n\n";
                        echo "Segue em anexo a fatura proforma nº {$dados['numero']} no valor de R$ {$dados['total']}.\n\n";
                        echo "Data de vencimento: {$dados['vencimento']}\n\n";
                        echo "Atenciosamente,\n{$dados['empresa']}";
                    ?></textarea>
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

        // ===== MOSTRAR/OCULTAR TEMPLATES =====
        function toggleTemplates() {
            const box = document.getElementById('templateBox');
            box.classList.toggle('show');
        }

        // ===== APLICAR TEMPLATE =====
        function aplicarTemplate(tipo, assunto, mensagem) {
            // Selecionar o tipo correspondente
            document.querySelectorAll('.tipo-option').forEach(el => {
                el.classList.remove('active');
                if (el.dataset.tipo === tipo) {
                    el.classList.add('active');
                }
            });
            document.getElementById('tipoSelecionado').value = tipo;
            
            // Preencher campos
            document.getElementById('assunto').value = assunto;
            document.getElementById('mensagem').value = mensagem;
            
            // Fechar o box de templates
            document.getElementById('templateBox').classList.remove('show');
            
            // Mostrar feedback
            alert('✅ Template aplicado! Revise a mensagem antes de enviar.');
        }
    </script>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>