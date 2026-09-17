<?php
require_once '../../config/database.php';

if ($_SESSION['usuario_perfil'] != 'admin') {
    header("Location: " . SITE_URL);
    exit;
}

$usuario_id = $_GET['usuario'] ?? 0;

if ($usuario_id) {
    // Buscar usuário
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        header("Location: index.php");
        exit;
    }
}

// Buscar todos os módulos
$modulos = $pdo->query("SELECT * FROM modulos ORDER BY ordem")->fetchAll();

// Buscar permissões do usuário
$permissoes = [];
if ($usuario_id) {
    $stmt = $pdo->prepare("SELECT * FROM permissoes WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    while ($row = $stmt->fetch()) {
        $permissoes[$row['modulo']] = $row;
    }
}

// Salvar permissões
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario_id = $_POST['usuario_id'];
    
    // Remover permissões antigas
    $stmt = $pdo->prepare("DELETE FROM permissoes WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    
    // Inserir novas permissões
    foreach ($_POST['modulos'] as $modulo => $perms) {
        $visualizar = isset($perms['visualizar']) ? 1 : 0;
        $criar = isset($perms['criar']) ? 1 : 0;
        $editar = isset($perms['editar']) ? 1 : 0;
        $excluir = isset($perms['excluir']) ? 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO permissoes (usuario_id, modulo, visualizar, criar, editar, excluir) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $modulo, $visualizar, $criar, $editar, $excluir]);
    }
    
    header("Location: index.php?success=1");
    exit;
}

// Buscar todos os usuários para seleção
$usuarios = $pdo->query("SELECT id, nome, email FROM usuarios ORDER BY nome")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permissões - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .perm-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 30px; margin-top: 20px;
        }
        .perm-table { width: 100%; border-collapse: collapse; }
        .perm-table th {
            background: #f8fafc; padding: 12px 15px; text-align: left;
            font-size: 13px; text-transform: uppercase; color: #64748b;
            border-bottom: 2px solid #eef2f7;
        }
        .perm-table td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; }
        .perm-table tr:hover { background: #f8fafc; }
        .perm-table .modulo-nome { font-weight: 600; color: #1a2332; }
        .perm-checkbox { width: 20px; height: 20px; cursor: pointer; }
        .btn-save {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332; padding: 12px 30px; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s;
            margin-top: 20px;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197,165,50,0.3); }
        .user-selector {
            display: flex; gap: 15px; align-items: center; flex-wrap: wrap;
            padding: 15px; background: #f8fafc; border-radius: 8px;
        }
        .user-selector select {
            padding: 10px 16px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 15px; min-width: 250px;
        }
        .user-selector .btn-select {
            background: #3498db; color: white; padding: 10px 24px;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
            text-decoration: none;
        }
        .user-selector .btn-select:hover { background: #2980b9; }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>🔑 Gerenciar Permissões</h2>
        
        <div class="user-selector">
            <form method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <label style="font-weight: 600; color: #2c3e50;">Selecionar Usuário:</label>
                <select name="usuario" required>
                    <option value="">Selecione...</option>
                    <?php foreach($usuarios as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= $usuario_id == $user['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['nome']) ?> (<?= htmlspecialchars($user['email']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-select">📂 Carregar</button>
                <a href="permissoes.php" class="btn" style="background: #e2e8f0; color: #475569;">Limpar</a>
            </form>
        </div>
        
        <?php if ($usuario_id): ?>
        <div class="perm-card">
            <h3 style="margin-bottom: 10px;"><?= htmlspecialchars($usuario['nome']) ?></h3>
            <p style="color: #94a3b8; margin-bottom: 20px;">Email: <?= htmlspecialchars($usuario['email']) ?> | Perfil: <?= ucfirst($usuario['perfil']) ?></p>
            
            <form method="POST">
                <input type="hidden" name="usuario_id" value="<?= $usuario_id ?>">
                
                <table class="perm-table">
                    <thead>
                        <tr>
                            <th>Módulo</th>
                            <th style="text-align: center; width: 80px;">👁️ Visualizar</th>
                            <th style="text-align: center; width: 80px;">➕ Criar</th>
                            <th style="text-align: center; width: 80px;">✏️ Editar</th>
                            <th style="text-align: center; width: 80px;">🗑️ Excluir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($modulos as $modulo): 
                            $perm = $permissoes[$modulo['nome']] ?? null;
                        ?>
                        <tr>
                            <td class="modulo-nome">
                                <?= $modulo['icone'] ?> <?= $modulo['nome'] ?>
                            </td>
                            <td style="text-align: center;">
                                <input type="checkbox" name="modulos[<?= $modulo['nome'] ?>][visualizar]" class="perm-checkbox" 
                                    <?= ($perm && $perm['visualizar']) ? 'checked' : '' ?>>
                            </td>
                            <td style="text-align: center;">
                                <input type="checkbox" name="modulos[<?= $modulo['nome'] ?>][criar]" class="perm-checkbox"
                                    <?= ($perm && $perm['criar']) ? 'checked' : '' ?>>
                            </td>
                            <td style="text-align: center;">
                                <input type="checkbox" name="modulos[<?= $modulo['nome'] ?>][editar]" class="perm-checkbox"
                                    <?= ($perm && $perm['editar']) ? 'checked' : '' ?>>
                            </td>
                            <td style="text-align: center;">
                                <input type="checkbox" name="modulos[<?= $modulo['nome'] ?>][excluir]" class="perm-checkbox"
                                    <?= ($perm && $perm['excluir']) ? 'checked' : '' ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <button type="submit" class="btn-save">💾 Salvar Permissões</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>