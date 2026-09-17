<?php
require_once '../../config/database.php';

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $endereco = $_POST['endereco'];
    $documento = $_POST['documento'];
    $tipo_pessoa = $_POST['tipo_pessoa'];
    $rg = $_POST['rg'];
    $data_nascimento = $_POST['data_nascimento'];
    $cep = $_POST['cep'];
    $cidade = $_POST['cidade'];
    $estado = $_POST['estado'];
    $observacoes = $_POST['observacoes'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, endereco, documento) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $email, $telefone, $endereco, $documento]);
        
        // Atualizar com campos extras (se a tabela tiver)
        $id = $pdo->lastInsertId();
        
        $mensagem = 'Cliente cadastrado com sucesso!';
        $tipoMensagem = 'success';
        
        // Redirecionar após 2 segundos
        echo "<script>setTimeout(function(){ window.location.href = 'index.php?success=1'; }, 1500);</script>";
    } catch(PDOException $e) {
        $mensagem = 'Erro ao cadastrar cliente: ' . $e->getMessage();
        $tipoMensagem = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Cliente - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .form-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 30px;
        }
        .form-card h2 {
            margin-bottom: 25px;
            color: #2c3e50;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 15px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .form-group label .required {
            color: #e74c3c;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-actions { display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap; }
        .btn-actions .btn { padding: 12px 30px; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .btn-actions { flex-direction: column; }
            .btn-actions .btn { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="form-container">
        <div class="form-card">
            <h2>➕ Novo Cliente</h2>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <!-- Dados Pessoais -->
                <h3 style="color: #2c3e50; margin-bottom: 15px;">📋 Dados Pessoais</h3>
                
                <div class="form-group">
                    <label>Nome Completo <span class="required">*</span></label>
                    <input type="text" name="nome" required placeholder="Nome completo do cliente">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Pessoa</label>
                        <select name="tipo_pessoa">
                            <option value="fisica">Pessoa Física</option>
                            <option value="juridica">Pessoa Jurídica</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Data de Nascimento</label>
                        <input type="date" name="data_nascimento">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>CPF/CNPJ</label>
                        <input type="text" name="documento" placeholder="000.000.000-00 ou 00.000.000/0001-00">
                    </div>
                    <div class="form-group">
                        <label>RG</label>
                        <input type="text" name="rg" placeholder="Número do RG">
                    </div>
                </div>
                
                <!-- Contato -->
                <h3 style="color: #2c3e50; margin: 25px 0 15px 0;">📞 Contato</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="email@exemplo.com">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="tel" name="telefone" placeholder="(00) 00000-0000">
                    </div>
                </div>
                
                <!-- Endereço -->
                <h3 style="color: #2c3e50; margin: 25px 0 15px 0;">📍 Endereço</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" name="cep" placeholder="00000-000">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Endereço</label>
                    <input type="text" name="endereco" placeholder="Rua, número, complemento">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" name="cidade" placeholder="Cidade">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="">Selecione</option>
                            <option value="AC">AC</option><option value="AL">AL</option>
                            <option value="AP">AP</option><option value="AM">AM</option>
                            <option value="BA">BA</option><option value="CE">CE</option>
                            <option value="DF">DF</option><option value="ES">ES</option>
                            <option value="GO">GO</option><option value="MA">MA</option>
                            <option value="MT">MT</option><option value="MS">MS</option>
                            <option value="MG">MG</option><option value="PA">PA</option>
                            <option value="PB">PB</option><option value="PR">PR</option>
                            <option value="PE">PE</option><option value="PI">PI</option>
                            <option value="RJ">RJ</option><option value="RN">RN</option>
                            <option value="RS">RS</option><option value="RO">RO</option>
                            <option value="RR">RR</option><option value="SC">SC</option>
                            <option value="SP">SP</option><option value="SE">SE</option>
                            <option value="TO">TO</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Observações</label>
                    <textarea name="observacoes" placeholder="Informações adicionais sobre o cliente..."></textarea>
                </div>
                
                <div class="btn-actions">
                    <button type="submit" class="btn btn-primary">💾 Salvar Cliente</button>
                    <a href="index.php" class="btn">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>