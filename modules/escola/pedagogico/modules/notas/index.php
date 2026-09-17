<?php
/**
 * Módulo de Notas - Página Principal
 * Estilo SOFTGEST
 */

require_once '../../config/database.php';
require_once '../../classes/Notas.php';

$notas = new Notas();
$mensagem = '';
$tipo_mensagem = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'lancar') {
        // Validar dados
        $dados = [
            'id_aluno' => $_POST['id_aluno'] ?? '',
            'nome_aluno' => $_POST['nome_aluno'] ?? '',
            'disciplina' => $_POST['disciplina'] ?? '',
            'turma' => $_POST['turma'] ?? '',
            'classe' => $_POST['classe'] ?? '',
            'ano_letivo' => $_POST['ano_letivo'] ?? '2024/2025',
            'mac_t1' => floatval($_POST['mac_t1'] ?? 0),
            'npt_t1' => floatval($_POST['npt_t1'] ?? 0),
            'mac_t2' => floatval($_POST['mac_t2'] ?? 0),
            'npt_t2' => floatval($_POST['npt_t2'] ?? 0),
            'mac_t3' => floatval($_POST['mac_t3'] ?? 0),
            'npt_t3' => floatval($_POST['npt_t3'] ?? 0),
            'sexo' => $_POST['sexo'] ?? '',
            'idade' => intval($_POST['idade'] ?? 0),
            'sala' => $_POST['sala'] ?? '',
            'turno' => $_POST['turno'] ?? ''
        ];
        
        if (empty($dados['id_aluno']) || empty($dados['disciplina'])) {
            $mensagem = 'ID do aluno e disciplina são obrigatórios!';
            $tipo_mensagem = 'danger';
        } else {
            $resultado = $notas->lancarNota($dados);
            if ($resultado) {
                $mensagem = 'Nota lançada com sucesso!';
                $tipo_mensagem = 'success';
            } else {
                $mensagem = 'Erro ao lançar nota!';
                $tipo_mensagem = 'danger';
            }
        }
    }
    
    if ($acao === 'buscar') {
        $id_aluno = $_POST['id_aluno_busca'] ?? '';
        if (!empty($id_aluno)) {
            $notas_aluno = $notas->getNotasAluno($id_aluno);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lançamento de Notas - SOFTGEST</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Menu Lateral -->
            <nav class="col-md-2 d-md-block bg-dark sidebar" style="min-height: 100vh;">
                <div class="position-sticky pt-3">
                    <h5 class="text-white text-center py-3">📚 SOFTGEST</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../index.php">🏠 Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="index.php">📝 Notas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../pautas/index.php">📊 Pautas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../boletins/index.php">📄 Boletins</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../frequencia/index.php">📅 Frequência</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../certificados/index.php">🎓 Certificados</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../ocorrencias/index.php">⚠️ Ocorrências</a>
                        </li>
                    </ul>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../../../index.php">🔙 Voltar ao Menu</a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Conteúdo Principal -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">📝 Lançamento de Notas</h1>
                </div>
                
                <?php if ($mensagem): ?>
                    <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show" role="alert">
                        <?= $mensagem ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Formulário de Lançamento -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Lançar Nota</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="acao" value="lancar">
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="id_aluno" class="form-label">ID do Aluno *</label>
                                    <input type="text" class="form-control" id="id_aluno" name="id_aluno" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="nome_aluno" class="form-label">Nome do Aluno *</label>
                                    <input type="text" class="form-control" id="nome_aluno" name="nome_aluno" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="disciplina" class="form-label">Disciplina *</label>
                                    <input type="text" class="form-control" id="disciplina" name="disciplina" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="classe" class="form-label">Classe *</label>
                                    <select class="form-select" id="classe" name="classe" required>
                                        <option value="">Selecione</option>
                                        <?php for($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?= $i ?>"><?= $i ?>ª</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="turma" class="form-label">Turma *</label>
                                    <select class="form-select" id="turma" name="turma" required>
                                        <option value="">Selecione</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="ano_letivo" class="form-label">Ano Letivo</label>
                                    <input type="text" class="form-control" id="ano_letivo" name="ano_letivo" value="2024/2025">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="sexo" class="form-label">Sexo</label>
                                    <select class="form-select" id="sexo" name="sexo">
                                        <option value="">Selecione</option>
                                        <option value="M">Masculino</option>
                                        <option value="F">Feminino</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-2 mb-3">
                                    <label for="idade" class="form-label">Idade</label>
                                    <input type="number" class="form-control" id="idade" name="idade">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="sala" class="form-label">Sala</label>
                                    <input type="text" class="form-control" id="sala" name="sala">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="turno" class="form-label">Turno</label>
                                    <select class="form-select" id="turno" name="turno">
                                        <option value="">Selecione</option>
                                        <option value="MANHÃ">Manhã</option>
                                        <option value="TARDE">Tarde</option>
                                        <option value="NOITE">Noite</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <h6 class="mb-3">Notas por Trimestre</h6>
                                </div>
                                
                                <!-- 1º Trimestre -->
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-primary text-white">
                                            1º Trimestre
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label for="mac_t1" class="form-label">MAC</label>
                                                    <input type="number" step="0.1" class="form-control" id="mac_t1" name="mac_t1">
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label for="npt_t1" class="form-label">NPT</label>
                                                    <input type="number" step="0.1" class="form-control" id="npt_t1" name="npt_t1">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 2º Trimestre -->
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-success text-white">
                                            2º Trimestre
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label for="mac_t2" class="form-label">MAC</label>
                                                    <input type="number" step="0.1" class="form-control" id="mac_t2" name="mac_t2">
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label for="npt_t2" class="form-label">NPT</label>
                                                    <input type="number" step="0.1" class="form-control" id="npt_t2" name="npt_t2">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 3º Trimestre -->
                                <div class="col-md-6 mt-3">
                                    <div class="card">
                                        <div class="card-header bg-warning text-dark">
                                            3º Trimestre
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label for="mac_t3" class="form-label">MAC</label>
                                                    <input type="number" step="0.1" class="form-control" id="mac_t3" name="mac_t3">
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label for="npt_t3" class="form-label">NPT</label>
                                                    <input type="number" step="0.1" class="form-control" id="npt_t3" name="npt_t3">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">💾 Lançar Nota</button>
                                <button type="reset" class="btn btn-secondary">🔄 Limpar</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Buscar Notas -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">🔍 Consultar Notas</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="acao" value="buscar">
                            <div class="col-md-4">
                                <label for="id_aluno_busca" class="form-label">ID do Aluno</label>
                                <input type="text" class="form-control" id="id_aluno_busca" name="id_aluno_busca">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-info">🔍 Buscar</button>
                            </div>
                        </form>
                        
                        <?php if (isset($notas_aluno) && !empty($notas_aluno)): ?>
                            <div class="table-responsive mt-3">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Disciplina</th>
                                            <th>MAC T1</th>
                                            <th>NPT T1</th>
                                            <th>MT1</th>
                                            <th>MAC T2</th>
                                            <th>NPT T2</th>
                                            <th>MT2</th>
                                            <th>MAC T3</th>
                                            <th>NPT T3</th>
                                            <th>MT3</th>
                                            <th>MFD</th>
                                            <th>Classificação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($notas_aluno as $nota): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($nota['disciplina']) ?></td>
                                                <td><?= $nota['mac_t1'] ?: 0 ?></td>
                                                <td><?= $nota['npt_t1'] ?: 0 ?></td>
                                                <td><?= number_format($nota['mt1'], 1) ?></td>
                                                <td><?= $nota['mac_t2'] ?: 0 ?></td>
                                                <td><?= $nota['npt_t2'] ?: 0 ?></td>
                                                <td><?= number_format($nota['mt2'], 1) ?></td>
                                                <td><?= $nota['mac_t3'] ?: 0 ?></td>
                                                <td><?= $nota['npt_t3'] ?: 0 ?></td>
                                                <td><?= number_format($nota['mt3'], 1) ?></td>
                                                <td><strong><?= number_format($nota['mfd'], 1) ?></strong></td>
                                                <td><?= $nota['classificacao'] ?: '—' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>