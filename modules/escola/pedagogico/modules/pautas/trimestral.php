<?php
/**
 * Módulo de Pautas - Pauta Trimestral
 * Estilo SOFTGEST
 */

require_once '../../config/database.php';
require_once '../../classes/Pautas.php';

$pautas = new Pautas();
$dados_pauta = null;
$html_pauta = '';

// Processar requisição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trimestre = intval($_POST['trimestre'] ?? 1);
    $classe = $_POST['classe'] ?? '';
    $turma = $_POST['turma'] ?? '';
    $ano_letivo = $_POST['ano_letivo'] ?? '2024/2025';
    $turno = $_POST['turno'] ?? 'MANHÃ';
    $sala = $_POST['sala'] ?? '09';
    
    if (!empty($classe) && !empty($turma)) {
        $dados_pauta = $pautas->getDadosPautaTrimestral($trimestre, $classe, $turma, $ano_letivo);
        if (!isset($dados_pauta['erro'])) {
            $html_pauta = $pautas->gerarHTMLPautaTrimestral(
                $dados_pauta, $classe, $turma, $trimestre, $turno, $sala, $ano_letivo
            );
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pauta Trimestral - SOFTGEST</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .pauta-preview {
            background: white;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            max-height: 600px;
            overflow: auto;
            font-size: 12px;
        }
        .pauta-preview table {
            width: 100%;
            border-collapse: collapse;
        }
        .pauta-preview th, .pauta-preview td {
            border: 1px solid #333;
            padding: 5px;
            text-align: center;
        }
        .pauta-preview th {
            background-color: #2c3e50;
            color: white;
        }
        .pauta-preview .aprovado { background-color: #d4edda; }
        .pauta-preview .reprovado { background-color: #f8d7da; }
        .pauta-preview .desistente { background-color: #fff3cd; }
    </style>
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
                            <a class="nav-link text-white" href="../notas/index.php">📝 Notas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="index.php">📊 Pautas</a>
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
                    <h1 class="h2">📊 Pauta Trimestral</h1>
                </div>
                
                <!-- Formulário -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Parâmetros da Pauta</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" target="_blank">
                            <div class="row">
                                <div class="col-md-2 mb-3">
                                    <label for="trimestre" class="form-label">Trimestre</label>
                                    <select class="form-select" id="trimestre" name="trimestre">
                                        <option value="1">1º Trimestre</option>
                                        <option value="2">2º Trimestre</option>
                                        <option value="3">3º Trimestre</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="classe" class="form-label">Classe</label>
                                    <select class="form-select" id="classe" name="classe" required>
                                        <option value="">Selecione</option>
                                        <?php for($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?= $i ?>"><?= $i ?>ª</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="turma" class="form-label">Turma</label>
                                    <select class="form-select" id="turma" name="turma" required>
                                        <option value="">Selecione</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="turno" class="form-label">Turno</label>
                                    <select class="form-select" id="turno" name="turno">
                                        <option value="MANHÃ">Manhã</option>
                                        <option value="TARDE">Tarde</option>
                                        <option value="NOITE">Noite</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="sala" class="form-label">Sala</label>
                                    <input type="text" class="form-control" id="sala" name="sala" value="09">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="ano_letivo" class="form-label">Ano Letivo</label>
                                    <input type="text" class="form-control" id="ano_letivo" name="ano_letivo" value="2024/2025">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">📊 Gerar Pauta</button>
                                <button type="button" class="btn btn-success" onclick="window.print()">🖨️ Imprimir</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Visualização da Pauta -->
                <?php if ($html_pauta): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">📋 Pauta Gerada</h5>
                        </div>
                        <div class="card-body">
                            <div class="pauta-preview">
                                <?= $html_pauta ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>