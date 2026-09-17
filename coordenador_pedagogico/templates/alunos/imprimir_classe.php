<?php
// imprimir_classe.php - Relatório de alunos por classe/turma

// Detecta automaticamente o caminho base do sistema
$base_path = realpath(__DIR__ . '/../../../') . '/';
$config_file = $base_path . 'config/config.php';

// Se não achar em config/config.php, tenta outros locais comuns
if (!file_exists($config_file)) {
    $possiveis_caminhos = [
        $base_path . 'config.php',
        $base_path . 'include/config.php',
        $base_path . 'includes/config.php',
        __DIR__ . '/../../../config/config.php',
        __DIR__ . '/../../config.php',
        __DIR__ . '/../config.php',
    ];
    
    foreach ($possiveis_caminhos as $caminho) {
        if (file_exists($caminho)) {
            $config_file = $caminho;
            break;
        }
    }
}

// Tenta incluir o config.php
if (file_exists($config_file)) {
    require_once($config_file);
} else {
    // Se não achar, cria configuração manual (APENAS PARA TESTE!)
    die('Arquivo de configuração não encontrado em: ' . $base_path . 'config/config.php');
}

// Verifica se as constantes foram definidas
if (!defined('DB_HOST')) {
    // Configuração manual (ajuste para seu banco)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'softgest_db');
}

// Pega os parâmetros da URL
$classe = isset($_GET['classe']) ? $_GET['classe'] : '';
$turma = isset($_GET['turma']) ? $_GET['turma'] : '';

// Verifica se os parâmetros foram enviados
if (empty($classe) || empty($turma)) {
    die('Erro: Parâmetros classe e turma são obrigatórios.');
}

// Conexão com o banco de dados
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Erro de conexão: ' . $conn->connect_error);
}

// Ajusta encoding
$conn->set_charset("utf8");

// Consulta os alunos da turma (ajuste os nomes das tabelas conforme seu banco)
$sql = "SELECT 
            a.id, 
            a.nome, 
            a.matricula, 
            a.data_nascimento,
            a.nome_pai,
            a.nome_mae
        FROM 
            alunos a
        INNER JOIN 
            turmas t ON a.turma_id = t.id
        WHERE 
            t.nome_classe = ? 
            AND t.codigo_turma = ?
        ORDER BY 
            a.nome ASC";

$stmt = $conn->prepare($sql);

// Se a consulta preparada falhar, tenta uma versão simplificada
if (!$stmt) {
    // Versão sem prepared statement (apenas para teste)
    $sql_simples = "SELECT * FROM alunos WHERE classe = '$classe' AND turma = '$turma'";
    $result = $conn->query($sql_simples);
    
    if (!$result) {
        die('Erro na consulta: ' . $conn->error);
    }
    
    $alunos = [];
    while ($row = $result->fetch_assoc()) {
        $alunos[] = $row;
    }
    $total_alunos = count($alunos);
} else {
    $stmt->bind_param('ss', $classe, $turma);
    $stmt->execute();
    $result = $stmt->get_result();
    $alunos = [];
    while ($row = $result->fetch_assoc()) {
        $alunos[] = $row;
    }
    $total_alunos = count($alunos);
    $stmt->close();
}

$conn->close();

// Verifica se encontrou alunos
if ($total_alunos == 0) {
    echo '<h3>Nenhum aluno encontrado para a turma ' . htmlspecialchars($classe) . ' - ' . htmlspecialchars($turma) . '</h3>';
    echo '<p><strong>Verifique:</strong> Os nomes das tabelas/colunas no seu banco de dados podem ser diferentes.</p>';
    echo '<p>Para corrigir, ajuste a consulta SQL no arquivo.</p>';
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório de Alunos - <?php echo htmlspecialchars($classe . ' - ' . $turma); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header-info {
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            text-align: left;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        table tr:hover {
            background-color: #f5f5f5;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        .btn-print {
            display: block;
            margin: 20px auto;
            padding: 10px 30px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        .btn-print:hover {
            background: #0056b3;
        }
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 10px;
            margin: 20px 0;
            font-size: 12px;
        }
        @media print {
            .btn-print, .no-print, .debug-info {
                display: none !important;
            }
            table th {
                background-color: #333 !important;
            }
        }
    </style>
</head>
<body>

    <h1>Relação de Alunos</h1>
    <div class="header-info">
        <strong>Classe:</strong> <?php echo htmlspecialchars($classe); ?> &nbsp;|&nbsp;
        <strong>Turma:</strong> <?php echo htmlspecialchars($turma); ?> &nbsp;|&nbsp;
        <strong>Data:</strong> <?php echo date('d/m/Y H:i'); ?>
    </div>

    <?php if (!empty($alunos)): ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Matrícula</th>
                <th>Nome do Aluno</th>
                <th>Data Nasc.</th>
                <th>Nome do Pai</th>
                <th>Nome da Mãe</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $contador = 1;
            foreach ($alunos as $row): 
            ?>
            <tr>
                <td><?php echo $contador++; ?></td>
                <td><?php echo htmlspecialchars($row['matricula'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['nome'] ?? ''); ?></td>
                <td><?php echo isset($row['data_nascimento']) && $row['data_nascimento'] ? date('d/m/Y', strtotime($row['data_nascimento'])) : ''; ?></td>
                <td><?php echo htmlspecialchars($row['nome_pai'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['nome_mae'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="footer">
        Total de alunos: <strong><?php echo $total_alunos; ?></strong>
    </div>

    <button class="btn-print no-print" onclick="window.print()">🖨️ Imprimir</button>

    <!-- Informações de debug (remova depois) -->
    <div class="debug-info no-print">
        <strong>Debug:</strong><br>
        Caminho do arquivo: <?php echo __FILE__; ?><br>
        Caminho base: <?php echo $base_path ?? 'não definido'; ?><br>
        Config usado: <?php echo $config_file ?? 'não encontrado'; ?><br>
        DB_HOST: <?php echo defined('DB_HOST') ? DB_HOST : 'não definido'; ?><br>
        DB_NAME: <?php echo defined('DB_NAME') ? DB_NAME : 'não definido'; ?>
    </div>

</body>
</html>