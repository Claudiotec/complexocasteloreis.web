<?php
require_once '../../config/database.php';

// Criar tabela de presenças se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS presencas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    funcionario_id INT,
    data DATE,
    entrada TIME,
    saida TIME,
    status ENUM('presente','ausente','justificado') DEFAULT 'presente',
    observacao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id)
)");

// Buscar funcionários
$funcionarios = $pdo->query("SELECT * FROM funcionarios WHERE status = 'ativo' ORDER BY nome")->fetchAll();

// Buscar presenças de hoje
$dataHoje = date('Y-m-d');
$presencasHoje = [];
$stmt = $pdo->prepare("SELECT * FROM presencas WHERE data = ?");
$stmt->execute([$dataHoje]);
while ($row = $stmt->fetch()) {
    $presencasHoje[$row['funcionario_id']] = $row;
}

// Registrar presença
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['marcar_presenca'])) {
    $funcionario_id = $_POST['funcionario_id'];
    $status = $_POST['status'];
    $observacao = $_POST['observacao'] ?? '';
    $hora = date('H:i:s');
    
    // Verificar se já existe presença hoje
    $stmt = $pdo->prepare("SELECT * FROM presencas WHERE funcionario_id = ? AND data = ?");
    $stmt->execute([$funcionario_id, $dataHoje]);
    $existente = $stmt->fetch();
    
    if ($existente) {
        // Atualizar
        $stmt = $pdo->prepare("UPDATE presencas SET status = ?, saida = ?, observacao = ? WHERE id = ?");
        $stmt->execute([$status, $hora, $observacao, $existente['id']]);
    } else {
        // Inserir
        $stmt = $pdo->prepare("INSERT INTO presencas (funcionario_id, data, entrada, status, observacao) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$funcionario_id, $dataHoje, $hora, $status, $observacao]);
    }
    
    header("Location: presenca.php?success=1");
    exit;
}

// Buscar presenças do mês
$mesAtual = date('m');
$anoAtual = date('Y');
$stmt = $pdo->prepare("SELECT funcionario_id, COUNT(*) as total FROM presencas WHERE MONTH(data) = ? AND YEAR(data) = ? AND status = 'presente' GROUP BY funcionario_id");
$stmt->execute([$mesAtual, $anoAtual]);
$presencasMes = [];
while ($row = $stmt->fetch()) {
    $presencasMes[$row['funcionario_id']] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Presença - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .presenca-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .presenca-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #eef2f7;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            transition: all 0.3s;
        }
        .presenca-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        .presenca-card .nome {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
        }
        .presenca-card .cargo {
            font-size: 14px;
            color: #94a3b8;
        }
        .presenca-card .status-atual {
            margin: 10px 0;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
        }
        .status-presente {
            background: #d1fae5;
            color: #065f46;
        }
        .status-ausente {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-justificado {
            background: #fef3c7;
            color: #92400e;
        }
        .presenca-card .btn-marcar {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-presente {
            background: #10b981;
            color: white;
        }
        .btn-presente:hover {
            background: #059669;
        }
        .btn-ausente {
            background: #ef4444;
            color: white;
        }
        .btn-ausente:hover {
            background: #dc2626;
        }
        .btn-justificado {
            background: #f59e0b;
            color: white;
        }
        .btn-justificado:hover {
            background: #d97706;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>📌 Mapa de Presença</h2>
        <p style="color: #94a3b8;"><?= date('d/m/Y') ?> - Registro de presença dos funcionários</p>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ Presença registrada com sucesso!</div>
        <?php endif; ?>
        
        <div class="presenca-grid">
            <?php foreach($funcionarios as $func): 
                $status = isset($presencasHoje[$func['id']]) ? $presencasHoje[$func['id']]['status'] : 'ausente';
                $presencasMesCount = $presencasMes[$func['id']] ?? 0;
            ?>
            <div class="presenca-card">
                <div class="nome"><?= htmlspecialchars($func['nome']) ?></div>
                <div class="cargo"><?= htmlspecialchars($func['cargo']) ?> - <?= htmlspecialchars($func['departamento']) ?></div>
                <div class="cargo">📊 Presenças no mês: <strong><?= $presencasMesCount ?></strong></div>
                
                <div class="status-atual status-<?= $status ?>">
                    <?php if ($status == 'presente'): ?>
                        ✅ Presente
                    <?php elseif ($status == 'justificado'): ?>
                        ⚠️ Justificado
                    <?php else: ?>
                        ❌ Ausente
                    <?php endif; ?>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="funcionario_id" value="<?= $func['id'] ?>">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="submit" name="marcar_presenca" value="1" class="btn-marcar btn-presente" style="flex:1;">✅ Presente</button>
                        <button type="submit" name="marcar_presenca" value="1" class="btn-marcar btn-justificado" style="flex:1;" formaction="?status=justificado">⚠️ Justificado</button>
                        <button type="submit" name="marcar_presenca" value="1" class="btn-marcar btn-ausente" style="flex:1;" formaction="?status=ausente">❌ Ausente</button>
                    </div>
                    <input type="hidden" name="status" value="<?= $status == 'presente' ? 'ausente' : 'presente' ?>">
                    <input type="hidden" name="observacao" value="">
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="btn">← Voltar ao RH</a>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>