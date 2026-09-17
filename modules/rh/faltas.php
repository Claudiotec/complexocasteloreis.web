<?php
require_once '../../config/database.php';

// Criar tabela de faltas se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS faltas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    funcionario_id INT,
    data DATE,
    tipo ENUM('falta','atraso','licenca') DEFAULT 'falta',
    justificativa TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id)
)");

// Buscar funcionários
$funcionarios = $pdo->query("SELECT * FROM funcionarios WHERE status = 'ativo' ORDER BY nome")->fetchAll();

// Buscar faltas do mês
$mesAtual = date('m');
$anoAtual = date('Y');
$stmt = $pdo->prepare("SELECT f.*, func.nome as funcionario_nome FROM faltas f JOIN funcionarios func ON f.funcionario_id = func.id WHERE MONTH(f.data) = ? AND YEAR(f.data) = ? ORDER BY f.data DESC");
$stmt->execute([$mesAtual, $anoAtual]);
$faltas = $stmt->fetchAll();

// Registrar falta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_falta'])) {
    $funcionario_id = $_POST['funcionario_id'];
    $data = $_POST['data'];
    $tipo = $_POST['tipo'];
    $justificativa = $_POST['justificativa'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO faltas (funcionario_id, data, tipo, justificativa) VALUES (?, ?, ?, ?)");
    $stmt->execute([$funcionario_id, $data, $tipo, $justificativa]);
    
    header("Location: faltas.php?success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Faltas - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .form-falta {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            margin-bottom: 25px;
            max-width: 600px;
        }
        .form-falta h3 {
            margin-bottom: 15px;
            color: #1a2332;
        }
        .form-falta .form-group {
            margin-bottom: 15px;
        }
        .form-falta label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .form-falta input, .form-falta select, .form-falta textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-falta input:focus, .form-falta select:focus, .form-falta textarea:focus {
            border-color: #c9a84c;
            outline: none;
        }
        .form-falta textarea {
            resize: vertical;
            min-height: 80px;
        }
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(197,165,50,0.3);
        }
        .status-falta {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-falta.falta {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-falta.atraso {
            background: #fef3c7;
            color: #92400e;
        }
        .status-falta.licenca {
            background: #dbeafe;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>⚠️ Registrar Faltas</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ Falta registrada com sucesso!</div>
        <?php endif; ?>
        
        <div class="form-falta">
            <h3>📝 Nova Falta</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Funcionário *</label>
                    <select name="funcionario_id" required>
                        <option value="">Selecione</option>
                        <?php foreach($funcionarios as $func): ?>
                        <option value="<?= $func['id'] ?>"><?= htmlspecialchars($func['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Data *</label>
                    <input type="date" name="data" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="tipo" required>
                        <option value="falta">Falta</option>
                        <option value="atraso">Atraso</option>
                        <option value="licenca">Licença</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Justificativa</label>
                    <textarea name="justificativa" placeholder="Descreva o motivo..."></textarea>
                </div>
                <button type="submit" name="registrar_falta" value="1" class="btn-gold">📝 Registrar</button>
            </form>
        </div>
        
        <h3>📋 Histórico de Faltas - <?= date('m/Y') ?></h3>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Justificativa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($faltas) > 0): ?>
                        <?php foreach($faltas as $falta): ?>
                        <tr>
                            <td><?= htmlspecialchars($falta['funcionario_nome']) ?></td>
                            <td><?= date('d/m/Y', strtotime($falta['data'])) ?></td>
                            <td><span class="status-falta <?= $falta['tipo'] ?>"><?= ucfirst($falta['tipo']) ?></span></td>
                            <td><?= htmlspecialchars($falta['justificativa'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px; color: #999;">
                                Nenhuma falta registrada neste mês.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="index.php" class="btn">← Voltar ao RH</a>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>