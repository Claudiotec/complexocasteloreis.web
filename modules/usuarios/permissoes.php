<?php
// modules/usuarios/gerenciar_permissoes.php
// Gerenciamento de Permissões de Usuários - SUBMÓDULOS ATIVOS

session_start();
require_once '../../config/app_modes.php';
require_once '../../config/database.php';

// Verificar se está logado e é admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] != 'admin') {
    header('Location: ../../login.php');
    exit;
}

// Conectar ao banco
try {
    $pdo = conectarBanco();
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// ============================================
// LISTA DE MÓDULOS E SUBMÓDULOS
// ============================================

$modulos = [
    'Dashboard' => ['icone' => '📊', 'submodulos' => []],
    'Clientes' => ['icone' => '👤', 'submodulos' => []],
    'Produtos' => ['icone' => '📦', 'submodulos' => []],
    'Estoque' => ['icone' => '📊', 'submodulos' => []],
    'Faturas' => ['icone' => '📄', 'submodulos' => []],
    'Recibos' => ['icone' => '🧾', 'submodulos' => []],
    'Fluxo de Caixa' => ['icone' => '💰', 'submodulos' => []],
    'RH' => ['icone' => '👥', 'submodulos' => []],
    'Marketing' => ['icone' => '📈', 'submodulos' => []],
    'Correspondência' => ['icone' => '✉️', 'submodulos' => []],
    'Empresa' => ['icone' => '⚙️', 'submodulos' => []],
    'Usuários' => ['icone' => '🔐', 'submodulos' => []],
    
    // ============================================
    // MÓDULO FINANCEIRO
    // ============================================
    'Financeiro' => [
        'icone' => '💳',
        'submodulos' => [
            'Dashboard Financeiro' => '📊',
            'Contas a Pagar' => '📤',
            'Contas a Receber' => '📥',
            'Fluxo de Caixa' => '💰',
            'Bancos' => '🏦',
            'Extratos Bancários' => '📋',
            'Conciliação Bancária' => '🔄',
            'Transferências' => '↔️',
            'Investimentos' => '📈',
            'Relatórios Financeiros' => '📄',
            'Orçamentos' => '📑',
            'Despesas' => '💸',
            'Receitas' => '💹',
            'Carteiras' => '👛',
            'Câmbio' => '🌍',
            'Impostos' => '📝',
            'Auditoria' => '🔍'
        ]
    ],
    
    // ============================================
    // MÓDULO PEDAGÓGICO
    // ============================================
    'Pedagógico' => [
        'icone' => '📚',
        'submodulos' => [
            'Dashboard Pedagógico' => '📊',
            'Planejamento' => '📋',
            'Currículo' => '📖',
            'Projetos' => '📐',
            'Avaliações' => '📝',
            'Recuperação' => '🔄',
            'Relatórios Pedagógicos' => '📄',
            'Metodologias' => '📚',
            'Materiais Didáticos' => '📎',
            'Provas' => '📃',
            'Gabaritos' => '✅',
            'Cronogramas' => '📅',
            'Eventos' => '🎪',
            'Formação Continuada' => '🎓',
            'Observações' => '👀',
            'Pareceres' => '📋'
        ]
    ],
    
    // ============================================
    // MÓDULO ESCOLA
    // ============================================
    'Escola' => [
        'icone' => '🎓',
        'submodulos' => [
            'Dashboard Escola' => '📊',
            'Alunos' => '👨‍🎓',
            'Professores' => '👨‍🏫',
            'Turmas' => '🏫',
            'Matrículas' => '📝',
            'Disciplinas' => '📚',
            'Notas' => '📈',
            'Frequência' => '✅',
            'Horários' => '🕐',
            'Boletins' => '📄',
            'Mensalidades' => '💰',
            'Ocorrências' => '📋',
            'Biblioteca' => '📖'
        ]
    ]
];

// ============================================
// FUNÇÕES
// ============================================

function getUsuarios($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, nome, email, perfil FROM usuarios ORDER BY nome");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getPermissoes($pdo, $usuario_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM permissoes WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $permissoes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissoes[$row['modulo']] = $row;
        }
        return $permissoes;
    } catch (PDOException $e) {
        return [];
    }
}

function salvarPermissoes($pdo, $usuario_id, $modulo, $visualizar, $criar, $editar, $excluir) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissoes WHERE usuario_id = ? AND modulo = ?");
        $stmt->execute([$usuario_id, $modulo]);
        $existe = $stmt->fetchColumn();
        
        if ($existe > 0) {
            $stmt = $pdo->prepare("
                UPDATE permissoes 
                SET visualizar = ?, criar = ?, editar = ?, excluir = ? 
                WHERE usuario_id = ? AND modulo = ?
            ");
            return $stmt->execute([$visualizar, $criar, $editar, $excluir, $usuario_id, $modulo]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO permissoes (usuario_id, modulo, visualizar, criar, editar, excluir) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$usuario_id, $modulo, $visualizar, $criar, $editar, $excluir]);
        }
    } catch (PDOException $e) {
        return false;
    }
}

// ============================================
// PROCESSAR AÇÕES
// ============================================

$mensagem = '';
$tipo_mensagem = '';
$usuario_selecionado = null;
$permissoes_usuario = [];

$usuarios = getUsuarios($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'carregar') {
        $usuario_id = $_POST['usuario_id'] ?? 0;
        if ($usuario_id > 0) {
            $usuario_selecionado = array_filter($usuarios, function($u) use ($usuario_id) {
                return $u['id'] == $usuario_id;
            });
            $usuario_selecionado = reset($usuario_selecionado);
            $permissoes_usuario = getPermissoes($pdo, $usuario_id);
        }
    }
    
    if ($acao === 'salvar') {
        $usuario_id = $_POST['usuario_id'] ?? 0;
        if ($usuario_id > 0) {
            $usuario_selecionado = array_filter($usuarios, function($u) use ($usuario_id) {
                return $u['id'] == $usuario_id;
            });
            $usuario_selecionado = reset($usuario_selecionado);
            
            $salvos = 0;
            $erros = 0;
            
            foreach ($modulos as $modulo => $dados) {
                // Módulo principal
                $modulo_key = str_replace(' ', '_', $modulo);
                $visualizar = isset($_POST['visualizar_' . $modulo_key]) ? 1 : 0;
                $criar = isset($_POST['criar_' . $modulo_key]) ? 1 : 0;
                $editar = isset($_POST['editar_' . $modulo_key]) ? 1 : 0;
                $excluir = isset($_POST['excluir_' . $modulo_key]) ? 1 : 0;
                
                if (salvarPermissoes($pdo, $usuario_id, $modulo, $visualizar, $criar, $editar, $excluir)) {
                    $salvos++;
                } else {
                    $erros++;
                }
                
                // Submódulos
                if (!empty($dados['submodulos'])) {
                    foreach ($dados['submodulos'] as $submodulo => $icone) {
                        $sub_key = str_replace(' ', '_', $submodulo);
                        $visualizar = isset($_POST['visualizar_' . $sub_key]) ? 1 : 0;
                        $criar = isset($_POST['criar_' . $sub_key]) ? 1 : 0;
                        $editar = isset($_POST['editar_' . $sub_key]) ? 1 : 0;
                        $excluir = isset($_POST['excluir_' . $sub_key]) ? 1 : 0;
                        
                        if (salvarPermissoes($pdo, $usuario_id, $submodulo, $visualizar, $criar, $editar, $excluir)) {
                            $salvos++;
                        } else {
                            $erros++;
                        }
                    }
                }
            }
            
            if ($erros == 0) {
                $mensagem = "✅ $salvos permissões salvas com sucesso!";
                $tipo_mensagem = 'success';
            } else {
                $mensagem = "⚠️ $salvos permissões salvas, $erros erros encontrados.";
                $tipo_mensagem = 'warning';
            }
            
            $permissoes_usuario = getPermissoes($pdo, $usuario_id);
        }
    }
}

if (isset($_GET['usuario_id']) && empty($usuario_selecionado)) {
    $usuario_id = $_GET['usuario_id'];
    $usuario_selecionado = array_filter($usuarios, function($u) use ($usuario_id) {
        return $u['id'] == $usuario_id;
    });
    $usuario_selecionado = reset($usuario_selecionado);
    if ($usuario_selecionado) {
        $permissoes_usuario = getPermissoes($pdo, $usuario_id);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Permissões - SoftGest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #1a2332, #2c3e50);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 { font-size: 26px; }
        .header h1 span { color: #f5d76e; }
        .header a {
            color: #f5d76e;
            text-decoration: none;
            padding: 8px 20px;
            background: rgba(245, 215, 110, 0.2);
            border-radius: 8px;
            transition: all 0.3s;
        }
        .header a:hover {
            background: rgba(245, 215, 110, 0.3);
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
        }
        .card h2 {
            font-size: 20px;
            color: #1a2332;
            margin-bottom: 20px;
            border-bottom: 2px solid #f5d76e;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: #1a2332;
            margin-bottom: 5px;
        }
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }
        .form-group select:focus, .form-group input:focus {
            border-color: #c9a84c;
            outline: none;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 15px;
            align-items: end;
        }
        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
        }
        .btn-primary:hover {
            box-shadow: 0 4px 15px rgba(197, 165, 50, 0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        .btn-success:hover {
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        .btn-danger:hover {
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-sm:hover {
            transform: translateY(-1px);
        }
        .btn-sm-primary {
            background: #c9a84c;
            color: #1a2332;
        }
        .btn-sm-secondary {
            background: #e2e8f0;
            color: #1a2332;
        }
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 15px;
            max-height: 600px;
            overflow-y: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        thead th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #1a2332;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tbody td {
            padding: 10px 15px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        tbody tr:hover {
            background: #f8fafc;
        }
        .modulo-nome {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        .modulo-icone {
            font-size: 18px;
        }
        .submodulo {
            padding-left: 30px !important;
            font-size: 13px;
            color: #64748b;
            background: #fafafa;
        }
        .submodulo .modulo-nome {
            font-weight: 400;
        }
        .submodulo .modulo-icone {
            font-size: 16px;
        }
        .submodulo:hover {
            background: #f0f0f0 !important;
        }
        .checkbox-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            cursor: pointer;
            font-weight: normal;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #c9a84c;
        }
        .checkbox-group input[type="checkbox"]:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-admin {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-usuario {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-gerente {
            background: #fef3c7;
            color: #92400e;
        }
        .user-info {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #eef2f7;
        }
        .user-info .nome {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
        }
        .user-info .email {
            color: #94a3b8;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .empty-state .icon {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
        }
        
        /* Cores dos módulos principais */
        .modulo-dashboard td:first-child .modulo-nome { color: #3498db; }
        .modulo-clientes td:first-child .modulo-nome { color: #2ecc71; }
        .modulo-produtos td:first-child .modulo-nome { color: #f39c12; }
        .modulo-estoque td:first-child .modulo-nome { color: #e67e22; }
        .modulo-faturas td:first-child .modulo-nome { color: #9b59b6; }
        .modulo-recibos td:first-child .modulo-nome { color: #1abc9c; }
        .modulo-fluxo-caixa td:first-child .modulo-nome { color: #27ae60; }
        .modulo-rh td:first-child .modulo-nome { color: #e74c3c; }
        .modulo-marketing td:first-child .modulo-nome { color: #f1c40f; }
        .modulo-correspondencia td:first-child .modulo-nome { color: #3498db; }
        .modulo-empresa td:first-child .modulo-nome { color: #7f8c8d; }
        .modulo-usuarios td:first-child .modulo-nome { color: #e74c3c; }
        .modulo-financeiro td:first-child .modulo-nome { color: #2ecc71; }
        .modulo-pedagogico td:first-child .modulo-nome { color: #e67e22; }
        .modulo-escola td:first-child .modulo-nome { color: #8e44ad; }
        
        .submodulo .modulo-nome { color: #64748b !important; }
        
        /* Headers dos módulos com submódulos */
        .modulo-header {
            background: #f8fafc !important;
            border-left: 3px solid #c9a84c;
            font-weight: 700;
        }
        .modulo-header .modulo-nome {
            font-weight: 700 !important;
        }
        .modulo-financeiro-header { border-left-color: #2ecc71 !important; }
        .modulo-pedagogico-header { border-left-color: #e67e22 !important; }
        .modulo-escola-header { border-left-color: #8e44ad !important; }
        
        /* Destaque para submódulos */
        .submodulo-financeiro { border-left: 2px solid #2ecc71; }
        .submodulo-pedagogico { border-left: 2px solid #e67e22; }
        .submodulo-escola { border-left: 2px solid #8e44ad; }
        
        .toggle-btn {
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 0 8px;
            cursor: pointer;
            font-size: 14px;
            color: #64748b;
            transition: all 0.3s;
        }
        .toggle-btn:hover {
            background: #e2e8f0;
        }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .checkbox-group { flex-direction: column; gap: 8px; }
            .header { flex-direction: column; text-align: center; }
            .header h1 { font-size: 22px; }
            .btn-group { justify-content: center; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <h1>🔑 <span>Gerenciar Permissões</span></h1>
        <div>
            <a href="../../index.php">📊 Dashboard</a>
            <a href="listar.php" style="margin-left: 10px;">👥 Usuários</a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_mensagem ?>"><?= $mensagem ?></div>
    <?php endif; ?>

    <!-- Selecionar Usuário -->
    <div class="card">
        <h2>👤 Selecionar Usuário</h2>
        <form method="POST">
            <input type="hidden" name="acao" value="carregar">
            <div class="form-row">
                <div class="form-group">
                    <label>Selecione um usuário para gerenciar permissões</label>
                    <select name="usuario_id" required>
                        <option value="">Selecione um usuário...</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <?php 
                            $selected = ($usuario_selecionado && $usuario_selecionado['id'] == $usuario['id']) ? 'selected' : '';
                            ?>
                            <option value="<?= $usuario['id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($usuario['nome']) ?> 
                                (<?= htmlspecialchars($usuario['email']) ?>)
                                - <?= ucfirst($usuario['perfil']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display: flex; gap: 10px; align-items: end;">
                    <button type="submit" class="btn btn-primary">📂 Carregar</button>
                    <a href="?limpar=1" class="btn btn-danger">Limpar</a>
                </div>
            </div>
        </form>
    </div>

    <?php if ($usuario_selecionado): ?>
    <!-- Gerenciar Permissões -->
    <div class="card">
        <h2>🔑 Permissões - <?= htmlspecialchars($usuario_selecionado['nome']) ?></h2>
        
        <div class="user-info">
            <div class="nome"><?= htmlspecialchars($usuario_selecionado['nome']) ?></div>
            <div class="email">
                📧 <?= htmlspecialchars($usuario_selecionado['email']) ?> 
                | Perfil: 
                <span class="badge badge-<?= $usuario_selecionado['perfil'] ?>">
                    <?= ucfirst($usuario_selecionado['perfil']) ?>
                </span>
            </div>
        </div>

        <?php if ($usuario_selecionado['perfil'] == 'admin'): ?>
            <div class="alert alert-info">
                ℹ️ <strong>Administradores</strong> têm acesso total a todos os módulos automaticamente.
                As permissões abaixo podem ser ajustadas conforme necessidade.
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="usuario_id" value="<?= $usuario_selecionado['id'] ?>">
            
            <div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn-sm btn-sm-primary" onclick="selecionarTodos(true)">✅ Marcar Todos</button>
                <button type="button" class="btn-sm btn-sm-secondary" onclick="selecionarTodos(false)">❌ Desmarcar Todos</button>
                <button type="button" class="btn-sm btn-sm-primary" onclick="marcarVisualizar(true)">👁️ Marcar Visualizar</button>
                <button type="button" class="btn-sm btn-sm-secondary" onclick="marcarVisualizar(false)">🔒 Desmarcar Visualizar</button>
                <button type="button" class="btn-sm btn-sm-primary" onclick="expandirTodos(true)">📂 Expandir Todos</button>
                <button type="button" class="btn-sm btn-sm-secondary" onclick="expandirTodos(false)">📂 Recolher Todos</button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="min-width: 220px;">Módulo / Submódulo</th>
                            <th style="text-align: center; min-width: 100px;">👁️ Visualizar</th>
                            <th style="text-align: center; min-width: 100px;">➕ Criar</th>
                            <th style="text-align: center; min-width: 100px;">✏️ Editar</th>
                            <th style="text-align: center; min-width: 100px;">🗑️ Excluir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // REMOVIDO: $is_admin = $usuario_selecionado['perfil'] == 'admin';
                        $disabled = ''; // CHECKBOXES SEMPRE HABILITADOS
                        $expandido = true;
                        
                        foreach ($modulos as $modulo => $dados):
                            $modulo_class = 'modulo-' . strtolower(str_replace(' ', '-', $modulo));
                            $modulo_key = str_replace(' ', '_', $modulo);
                            $perm = $permissoes_usuario[$modulo] ?? ['visualizar' => 0, 'criar' => 0, 'editar' => 0, 'excluir' => 0];
                            $tem_submodulos = !empty($dados['submodulos']);
                            $header_class = $tem_submodulos ? 'modulo-header ' . $modulo_class . '-header' : '';
                        ?>
                            <!-- Módulo Principal -->
                            <tr class="<?= $modulo_class ?> <?= $header_class ?>">
                                <td>
                                    <div class="modulo-nome">
                                        <span class="modulo-icone"><?= $dados['icone'] ?></span>
                                        <?= $modulo ?>
                                        <?php if ($tem_submodulos): ?>
                                            <span style="font-size: 11px; color: #94a3b8; font-weight: 400;">
                                                (<?= count($dados['submodulos']) ?> submódulos)
                                            </span>
                                            <button type="button" 
                                                    class="toggle-btn" 
                                                    onclick="toggleSubmodulos('<?= $modulo_key ?>', this)"
                                                    title="Expandir/Recolher submódulos">
                                                −
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" 
                                           name="visualizar_<?= $modulo_key ?>" 
                                           value="1" 
                                           <?= $perm['visualizar'] ? 'checked' : '' ?>
                                           <?= $disabled ?>
                                           class="check-visualizar check-<?= $modulo_key ?>">
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" 
                                           name="criar_<?= $modulo_key ?>" 
                                           value="1" 
                                           <?= $perm['criar'] ? 'checked' : '' ?>
                                           <?= $disabled ?>
                                           class="check-criar check-<?= $modulo_key ?>">
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" 
                                           name="editar_<?= $modulo_key ?>" 
                                           value="1" 
                                           <?= $perm['editar'] ? 'checked' : '' ?>
                                           <?= $disabled ?>
                                           class="check-editar check-<?= $modulo_key ?>">
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" 
                                           name="excluir_<?= $modulo_key ?>" 
                                           value="1" 
                                           <?= $perm['excluir'] ? 'checked' : '' ?>
                                           <?= $disabled ?>
                                           class="check-excluir check-<?= $modulo_key ?>">
                                </td>
                            </tr>
                            
                            <!-- Submódulos -->
                            <?php if ($tem_submodulos): ?>
                                <?php foreach ($dados['submodulos'] as $submodulo => $icone): ?>
                                    <?php 
                                    $sub_key = str_replace(' ', '_', $submodulo);
                                    $sub_perm = $permissoes_usuario[$submodulo] ?? ['visualizar' => 0, 'criar' => 0, 'editar' => 0, 'excluir' => 0];
                                    $sub_class = 'submodulo-' . strtolower(str_replace(' ', '-', $modulo));
                                    ?>
                                    <tr class="submodulo submodulo-<?= $sub_key ?> <?= $sub_class ?> submodulo-container-<?= $modulo_key ?>" 
                                        style="display: <?= $expandido ? 'table-row' : 'none' ?>;">
                                        <td>
                                            <div class="modulo-nome">
                                                <span class="modulo-icone"><?= $icone ?></span>
                                                <?= $submodulo ?>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="checkbox" 
                                                   name="visualizar_<?= $sub_key ?>" 
                                                   value="1" 
                                                   <?= $sub_perm['visualizar'] ? 'checked' : '' ?>
                                                   <?= $disabled ?>
                                                   class="check-visualizar check-<?= $sub_key ?> sub-check">
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="checkbox" 
                                                   name="criar_<?= $sub_key ?>" 
                                                   value="1" 
                                                   <?= $sub_perm['criar'] ? 'checked' : '' ?>
                                                   <?= $disabled ?>
                                                   class="check-criar check-<?= $sub_key ?> sub-check">
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="checkbox" 
                                                   name="editar_<?= $sub_key ?>" 
                                                   value="1" 
                                                   <?= $sub_perm['editar'] ? 'checked' : '' ?>
                                                   <?= $disabled ?>
                                                   class="check-editar check-<?= $sub_key ?> sub-check">
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="checkbox" 
                                                   name="excluir_<?= $sub_key ?>" 
                                                   value="1" 
                                                   <?= $sub_perm['excluir'] ? 'checked' : '' ?>
                                                   <?= $disabled ?>
                                                   class="check-excluir check-<?= $sub_key ?> sub-check">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Botão Salvar SEMPRE VISÍVEL -->
            <div class="btn-group">
                <button type="submit" class="btn btn-success">💾 Salvar Permissões</button>
                <button type="reset" class="btn btn-danger">🔄 Resetar</button>
            </div>
            
            <?php if ($usuario_selecionado['perfil'] == 'admin'): ?>
                <div class="alert alert-info" style="margin-top: 15px;">
                    ℹ️ Administradores têm acesso total, mas você pode ajustar permissões específicas conforme necessário.
                </div>
            <?php endif; ?>
        </form>
    </div>
    <?php else: ?>
    <!-- Estado vazio -->
    <div class="card">
        <div class="empty-state">
            <span class="icon">👤</span>
            <h3>Selecione um usuário</h3>
            <p>Escolha um usuário acima para gerenciar suas permissões</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Selecionar todos os checkboxes
function selecionarTodos(marcar) {
    document.querySelectorAll('table input[type="checkbox"]').forEach(cb => {
        cb.checked = marcar;
    });
}

// Marcar/Desmarcar apenas Visualizar
function marcarVisualizar(marcar) {
    document.querySelectorAll('table input[type="checkbox"].check-visualizar').forEach(cb => {
        cb.checked = marcar;
    });
}

// Expandir/Recolher submódulos de um módulo específico
function toggleSubmodulos(modulo, btn) {
    const submodulos = document.querySelectorAll('.submodulo-container-' + modulo);
    const visiveis = submodulos[0]?.style.display !== 'none';
    
    submodulos.forEach(el => {
        el.style.display = visiveis ? 'none' : 'table-row';
    });
    
    btn.textContent = visiveis ? '+' : '−';
}

// Expandir/Recolher todos os submódulos
function expandirTodos(expandir) {
    const submodulos = document.querySelectorAll('.submodulo');
    submodulos.forEach(el => {
        el.style.display = expandir ? 'table-row' : 'none';
    });
    
    // Atualizar todos os botões toggle
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.textContent = expandir ? '−' : '+';
    });
}

// Desabilitar/habilitar Criar, Editar, Excluir baseado no Visualizar
document.addEventListener('DOMContentLoaded', function() {
    const visualizarChecks = document.querySelectorAll('.check-visualizar');
    
    visualizarChecks.forEach(function(check) {
        check.addEventListener('change', function() {
            const row = this.closest('tr');
            const criar = row.querySelector('.check-criar');
            const editar = row.querySelector('.check-editar');
            const excluir = row.querySelector('.check-excluir');
            
            if (!this.checked) {
                if (criar) { 
                    criar.checked = false; 
                    criar.disabled = true; 
                }
                if (editar) { 
                    editar.checked = false; 
                    editar.disabled = true; 
                }
                if (excluir) { 
                    excluir.checked = false; 
                    excluir.disabled = true; 
                }
            } else {
                if (criar) criar.disabled = false;
                if (editar) editar.disabled = false;
                if (excluir) excluir.disabled = false;
            }
        });
        
        // Inicializar estado
        if (!check.checked) {
            const row = check.closest('tr');
            const criar = row.querySelector('.check-criar');
            const editar = row.querySelector('.check-editar');
            const excluir = row.querySelector('.check-excluir');
            
            if (criar && !criar.disabled) {
                criar.disabled = true;
                editar.disabled = true;
                excluir.disabled = true;
            }
        }
    });
});
</script>

</body>
</html>