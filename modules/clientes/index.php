<?php
// ============================================
// modules/clientes/index.php
// Listagem de Clientes
// ============================================

// ===== CONFIGURAÇÕES INICIAIS =====
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== INCLUIR CONFIGURAÇÃO =====
require_once '../../config/database.php';

// ===== INICIAR SESSÃO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== VERIFICAR SE O USUÁRIO ESTÁ LOGADO =====
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

// ===== BUSCAR CLIENTES =====
function getClientes($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM clientes ORDER BY id DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// ===== EXECUTAR BUSCA =====
$clientes = getClientes($pdo);
$totalClientes = count($clientes);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        /* ===== RESET E ESTILOS GERAIS ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 30px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-header h2 {
            font-size: 28px;
            color: #1a2332;
        }
        
        .page-header h2 span {
            color: #c9a84c;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        /* ===== BOTÕES ===== */
        .btn-add {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
            color: white;
        }
        
        .btn-small {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
            margin: 2px;
            border: none;
            cursor: pointer;
        }
        
        .btn-small:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }
        
        .btn-edit {
            background: #f59e0b;
        }
        
        .btn-view {
            background: #3498db;
        }
        
        .btn-delete {
            background: #ef4444;
        }
        
        /* ===== CARDS DE RESUMO ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .card-resumo {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border-left: 4px solid #3498db;
            transition: all 0.3s;
        }
        
        .card-resumo:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        
        .card-resumo .numero {
            font-size: 32px;
            font-weight: 700;
            color: #1a2332;
            margin: 0;
        }
        
        .card-resumo .label {
            font-size: 14px;
            color: #94a3b8;
            margin-top: 5px;
        }
        
        .card-resumo .icone {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        /* ===== ALERTAS ===== */
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
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        
        /* ===== TABELA ===== */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table thead {
            background: #f8fafc;
        }
        
        .table th {
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #eef2f7;
        }
        
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #1a2332;
        }
        
        .table tbody tr:hover {
            background: #f8fafc;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* ===== STATUS ===== */
        .status-ativo {
            background: #d1fae5;
            color: #065f46;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-inativo {
            background: #fee2e2;
            color: #991b1b;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        /* ===== VAZIO ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-state .icone {
            font-size: 64px;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            color: #1a2332;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        /* ===== RESPONSIVO ===== */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .actions {
                width: 100%;
            }
            
            .actions .btn-add {
                width: 100%;
                justify-content: center;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .table {
                min-width: 600px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-small {
                padding: 3px 8px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <!-- ===== HEADER ===== -->
        <div class="page-header">
            <h2>👤 <span>Clientes</span></h2>
            <div class="actions">
                <a href="add.php" class="btn-add">
                    ➕ Novo Cliente
                </a>
            </div>
        </div>
        
        <!-- ===== CARDS DE RESUMO ===== -->
        <div class="stats-grid">
            <div class="card-resumo">
                <div class="icone">👤</div>
                <div class="numero"><?= $totalClientes ?></div>
                <div class="label">Total de Clientes</div>
            </div>
            
            <?php if ($totalClientes > 0): ?>
            <div class="card-resumo" style="border-left-color: #2ecc71;">
                <div class="icone">🟢</div>
                <div class="numero"><?= $totalClientes ?></div>
                <div class="label">Clientes Ativos</div>
            </div>
            
            <div class="card-resumo" style="border-left-color: #c9a84c;">
                <div class="icone">📅</div>
                <div class="numero"><?= date('d/m/Y') ?></div>
                <div class="label">Última Atualização</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- ===== ALERTAS ===== -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ Cliente cadastrado com sucesso!</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">✅ Cliente atualizado com sucesso!</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">✅ Cliente excluído com sucesso!</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>
        
        <!-- ===== TABELA DE CLIENTES ===== -->
        <div class="table-container">
            <?php if (count($clientes) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Documento</th>
                            <th>Status</th>
                            <th>Data Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($clientes as $cliente): ?>
                        <tr>
                            <td>#<?= $cliente['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($cliente['nome']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($cliente['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($cliente['telefone'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($cliente['documento'] ?? '-') ?></td>
                            <td>
                                <?php if (isset($cliente['status']) && $cliente['status'] == 'ativo'): ?>
                                    <span class="status-ativo">✅ Ativo</span>
                                <?php else: ?>
                                    <span class="status-inativo">⛔ Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($cliente['created_at'])) ?></td>
                            <td>
                                <a href="edit.php?id=<?= $cliente['id'] ?>" class="btn-small btn-edit">✏️ Editar</a>
                                <a href="view.php?id=<?= $cliente['id'] ?>" class="btn-small btn-view">👁️ Ver</a>
                                <a href="delete.php?id=<?= $cliente['id'] ?>" class="btn-small btn-delete" onclick="return confirm('Tem certeza que deseja excluir este cliente?')">🗑️ Excluir</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icone">👤</div>
                    <h3>Nenhum cliente cadastrado</h3>
                    <p>Comece cadastrando seu primeiro cliente no sistema.</p>
                    <a href="add.php" class="btn-add" style="display: inline-flex; margin-top: 10px;">
                        ➕ Cadastrar Cliente
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ===== RODAPÉ DA PÁGINA ===== -->
        <?php if (count($clientes) > 0): ?>
        <div style="margin-top: 15px; text-align: right; font-size: 13px; color: #94a3b8;">
            Mostrando <strong><?= count($clientes) ?></strong> cliente(s)
        </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>