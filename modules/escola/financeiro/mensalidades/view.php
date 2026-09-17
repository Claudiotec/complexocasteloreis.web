<?php
// modules/escola/financeiro/mensalidades/index.php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../../login.php');
    exit;
}

require_once '../../../../config/database.php';

if (!isset($pdo)) {
    die('Erro: Conexão com o banco de dados não estabelecida.');
}

// Verificar se foi selecionado um aluno
$aluno_id = isset($_GET['aluno_id']) ? intval($_GET['aluno_id']) : 0;
$aluno_nome = '';

try {
    // Buscar lista de alunos para o filtro
    $query_alunos = "SELECT id, nome FROM alunos ORDER BY nome ASC";
    $stmt_alunos = $pdo->query($query_alunos);
    $lista_alunos = $stmt_alunos->fetchAll(PDO::FETCH_ASSOC);
    
    // Se tem aluno_id, buscar o nome do aluno
    if ($aluno_id > 0) {
        $query_nome = "SELECT nome FROM alunos WHERE id = ?";
        $stmt_nome = $pdo->prepare($query_nome);
        $stmt_nome->execute([$aluno_id]);
        $aluno = $stmt_nome->fetch(PDO::FETCH_ASSOC);
        $aluno_nome = $aluno ? $aluno['nome'] : '';
    }
    
    // Construir a query com filtro
    $query = "SELECT 
                p.*,
                a.nome AS aluno_nome,
                e.nome AS emolumento_nome
              FROM pagamentos p
              INNER JOIN alunos a ON p.aluno_id = a.id
              LEFT JOIN emolumentos e ON p.emolumento_id = e.id";
    
    // Adicionar filtro por aluno se selecionado
    if ($aluno_id > 0) {
        $query .= " WHERE p.aluno_id = :aluno_id";
    }
    
    $query .= " ORDER BY p.data_pagamento DESC";
    
    $stmt = $pdo->prepare($query);
    if ($aluno_id > 0) {
        $stmt->execute([':aluno_id' => $aluno_id]);
    } else {
        $stmt->execute();
    }
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Erro ao buscar dados: ' . $e->getMessage());
}

function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function getStatusBadge($status) {
    $statuses = [
        'confirmado' => '<span class="badge bg-success">Confirmado</span>',
        'pendente' => '<span class="badge bg-warning">Pendente</span>',
        'cancelado' => '<span class="badge bg-danger">Cancelado</span>'
    ];
    return $statuses[$status] ?? '<span class="badge bg-secondary">' . $status . '</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamentos - SoftGest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px 25px;
            border: none;
        }
        .card-header h4 {
            margin: 0;
            font-weight: 600;
        }
        .btn-action {
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .table-pagamentos {
            font-size: 0.95rem;
        }
        .table-pagamentos th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .total-info {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<div class="main-container">
    
    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold" style="color: #2d3436;">
                <i class="fas fa-receipt text-primary me-2"></i>Pagamentos
            </h2>
            <?php if ($aluno_id > 0 && $aluno_nome): ?>
                <p class="text-muted">Filtrando por: <strong><?= htmlspecialchars($aluno_nome) ?></strong></p>
            <?php endif; ?>
        </div>
        <div>
            <a href="novo.php<?= $aluno_id > 0 ? '?aluno_id=' . $aluno_id : '' ?>" class="btn btn-success btn-action">
                <i class="fas fa-plus me-2"></i>Novo Pagamento
            </a>
        </div>
    </div>

    <!-- Filtro por Aluno -->
    <div class="filter-section">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label for="aluno_id" class="form-label fw-bold">
                    <i class="fas fa-user-graduate me-1"></i>Filtrar por Aluno
                </label>
                <select name="aluno_id" id="aluno_id" class="form-select form-select-lg" onchange="this.form.submit()">
                    <option value="0">Todos os Alunos</option>
                    <?php foreach ($lista_alunos as $aluno): ?>
                        <option value="<?= $aluno['id'] ?>" <?= $aluno_id == $aluno['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($aluno['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <?php if ($aluno_id > 0): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-action w-100">
                        <i class="fas fa-times me-2"></i>Limpar Filtro
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Resumo -->
    <?php if ($aluno_id > 0): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="total-info">
                    <div class="small text-white-50">Total de Pagamentos</div>
                    <div class="h3 mb-0"><?= count($pagamentos) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="total-info" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="small text-white-50">Valor Total</div>
                    <div class="h3 mb-0">
                        <?php 
                        $total = 0;
                        foreach ($pagamentos as $p) {
                            $total += $p['valor'];
                        }
                        echo formatarMoeda($total);
                        ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="total-info" style="background: linear-gradient(135deg, #e17055 0%, #d63031 100%);">
                    <div class="small text-white-50">Média por Pagamento</div>
                    <div class="h3 mb-0">
                        <?php 
                        $media = count($pagamentos) > 0 ? $total / count($pagamentos) : 0;
                        echo formatarMoeda($media);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Lista de Pagamentos -->
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">
                <i class="fas fa-list me-2"></i>
                <?= $aluno_id > 0 ? 'Pagamentos de ' . htmlspecialchars($aluno_nome) : 'Todos os Pagamentos' ?>
                <span class="badge bg-light text-dark ms-2"><?= count($pagamentos) ?> registros</span>
            </h4>
        </div>
        <div class="card-body">
            <?php if (count($pagamentos) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-pagamentos table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Aluno</th>
                                <th>Emolumento</th>
                                <th>Valor</th>
                                <th>Data</th>
                                <th>Forma</th>
                                <th>Referência</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagamentos as $p): ?>
                            <tr>
                                <td><?= str_pad($p['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <a href="index.php?aluno_id=<?= $p['aluno_id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($p['aluno_nome']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($p['emolumento_nome'] ?? 'N/A') ?></td>
                                <td class="fw-bold text-primary"><?= formatarMoeda($p['valor']) ?></td>
                                <td><?= date('d/m/Y', strtotime($p['data_pagamento'])) ?></td>
                                <td><?= ucfirst($p['forma_pagamento']) ?></td>
                                <td><?= htmlspecialchars($p['referencia'] ?? '-') ?></td>
                                <td><?= getStatusBadge($p['status']) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="editar.php?id=<?= $p['id'] ?>" class="btn btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($p['status'] == 'confirmado'): ?>
                                        <button onclick="if(confirm('Cancelar este pagamento?')){ window.location.href='cancelar.php?id=<?= $p['id'] ?>'; }" class="btn btn-danger">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="3" class="text-end">TOTAL GERAL:</td>
                                <td class="text-primary">
                                    <?php 
                                    $total_geral = 0;
                                    foreach ($pagamentos as $p) {
                                        $total_geral += $p['valor'];
                                    }
                                    echo formatarMoeda($total_geral);
                                    ?>
                                </td>
                                <td colspan="5"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Nenhum pagamento encontrado</h5>
                    <?php if ($aluno_id > 0): ?>
                        <p class="text-muted">Este aluno ainda não possui pagamentos registrados.</p>
                        <a href="novo.php?aluno_id=<?= $aluno_id ?>" class="btn btn-success mt-2">
                            <i class="fas fa-plus me-2"></i>Registrar Primeiro Pagamento
                        </a>
                    <?php else: ?>
                        <p class="text-muted">Nenhum pagamento cadastrado no sistema.</p>
                        <a href="novo.php" class="btn btn-success mt-2">
                            <i class="fas fa-plus me-2"></i>Registrar Primeiro Pagamento
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>