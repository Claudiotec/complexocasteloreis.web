<?php
require_once '../../config/database.php';

$empresa = getEmpresa();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $data_emissao = $_POST['data_emissao'];
    $data_vencimento = $_POST['data_vencimento'];
    $subtotal = $_POST['subtotal'];
    $desconto = $_POST['desconto'] ?? 0;
    $total = $subtotal - $desconto;
    $observacoes = $_POST['observacoes'] ?? '';
    
    // Gerar número do recibo
    $numero = 'FR-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("INSERT INTO faturas_recibo (numero, cliente_id, data_emissao, data_vencimento, subtotal, desconto, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')");
    $stmt->execute([$numero, $cliente_id, $data_emissao, $data_vencimento, $subtotal, $desconto, $total]);
    
    $recibo_id = $pdo->lastInsertId();
    
    // Verificar se deve enviar
    if (isset($_POST['enviar_agora'])) {
        header("Location: enviar_recibo.php?id=" . $recibo_id . "&enviar=1");
        exit;
    }
    
    header("Location: index.php?success=1");
    exit;
}

// Buscar clientes
$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Recibo - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .form-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        .form-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 5px; background: linear-gradient(90deg, #c9a84c, #f5d76e);
        }
        .form-card h2 {
            color: #1a2332; border-bottom: 2px solid #f0f2f5;
            padding-bottom: 15px; margin-bottom: 25px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; font-weight: 600; color: #2c3e50; margin-bottom: 5px;
        }
        .form-group label .required { color: #e74c3c; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0;
            border-radius: 8px; font-size: 14px; transition: all 0.3s;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #c9a84c; outline: none; box-shadow: 0 0 0 3px rgba(197,165,50,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-actions { display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap; }
        .btn-save {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332; padding: 14px 30px; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197,165,50,0.3); }
        .btn-enviar {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white; padding: 14px 30px; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s;
        }
        .btn-enviar:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(139,92,246,0.3); }
        .total-box {
            background: #f8fafc; padding: 15px 20px; border-radius: 8px;
            border-left: 4px solid #c9a84c; margin: 10px 0;
        }
        .total-box .total-value {
            font-size: 24px; font-weight: 700; color: #c9a84c;
        }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .btn-actions { flex-direction: column; }
            .btn-actions .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="form-container">
        <div class="form-card">
            <h2>🧾 Novo Recibo de Pagamento</h2>
            
            <form method="POST" id="formRecibo">
                <!-- Cliente -->
                <div class="form-group">
                    <label>Cliente <span class="required">*</span></label>
                    <select name="cliente_id" required>
                        <option value="">Selecione um cliente</option>
                        <?php foreach($clientes as $cliente): ?>
                        <option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Datas -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Data de Emissão <span class="required">*</span></label>
                        <input type="date" name="data_emissao" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Data de Vencimento <span class="required">*</span></label>
                        <input type="date" name="data_vencimento" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>
                </div>
                
                <!-- Valores -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Subtotal <span class="required">*</span></label>
                        <input type="number" step="0.01" name="subtotal" id="subtotal" required oninput="calcularTotal()">
                    </div>
                    <div class="form-group">
                        <label>Desconto</label>
                        <input type="number" step="0.01" name="desconto" id="desconto" value="0" oninput="calcularTotal()">
                    </div>
                </div>
                
                <!-- Total -->
                <div class="total-box">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 600; color: #1a2332;">Total do Recibo</span>
                        <span class="total-value" id="totalDisplay">R$ 0,00</span>
                    </div>
                    <input type="hidden" name="total" id="totalInput">
                </div>
                
                <!-- Observações -->
                <div class="form-group">
                    <label>Observações</label>
                    <textarea name="observacoes" placeholder="Informações adicionais sobre o recibo..."></textarea>
                </div>
                
                <!-- Ações -->
                <div class="btn-actions">
                    <button type="submit" class="btn-save">💾 Salvar Recibo</button>
                    <button type="submit" name="enviar_agora" value="1" class="btn-enviar">📤 Salvar e Enviar</button>
                    <a href="index.php" class="btn">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function calcularTotal() {
            const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
            const desconto = parseFloat(document.getElementById('desconto').value) || 0;
            const total = subtotal - desconto;
            
            document.getElementById('totalDisplay').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
            document.getElementById('totalInput').value = total;
        }
    </script>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>