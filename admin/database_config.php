<?php
// admin/database_config.php
// Painel de Configuração do Banco de Dados

// ===== CONFIGURAÇÕES INICIAIS =====
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// ===== INICIAR SESSÃO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== VERIFICAR SE É ADMIN =====
if (!isset($_SESSION['usuario_perfil']) || $_SESSION['usuario_perfil'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';
$conexao_atual = '';

// ===== DETECTAR CONEXÃO ATUAL =====
function detectarConexaoAtual() {
    global $pdo;
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $dbname = $pdo->query("SELECT DATABASE()")->fetchColumn();
        return [
            'driver' => $driver,
            'database' => $dbname,
            'host' => DB_HOST ?? 'localhost',
            'user' => DB_USER ?? 'root'
        ];
    } catch (Exception $e) {
        return [
            'driver' => 'desconhecido',
            'database' => 'não conectado',
            'host' => '-',
            'user' => '-'
        ];
    }
}

$conexao_info = detectarConexaoAtual();

// ===== PROCESSAR FORMULÁRIO =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao'])) {
        switch ($_POST['acao']) {
            case 'testar_conexao':
                $host = $_POST['host'] ?? '';
                $port = $_POST['port'] ?? '3306';
                $database = $_POST['database'] ?? '';
                $user = $_POST['user'] ?? '';
                $password = $_POST['password'] ?? '';
                $driver = $_POST['driver'] ?? 'mysql';
                
                try {
                    if ($driver === 'mysql') {
                        $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
                    } elseif ($driver === 'pgsql') {
                        $dsn = "pgsql:host=$host;port=$port;dbname=$database";
                    } else {
                        throw new Exception("Driver não suportado");
                    }
                    
                    $teste = new PDO($dsn, $user, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 5
                    ]);
                    
                    $mensagem = "✅ Conexão testada com sucesso!";
                    $tipo_mensagem = 'success';
                    
                } catch (Exception $e) {
                    $mensagem = "❌ Erro na conexão: " . $e->getMessage();
                    $tipo_mensagem = 'error';
                }
                break;
                
            case 'migrar':
                $driver = $_POST['driver_migracao'] ?? 'mysql';
                $host = $_POST['host_migracao'] ?? '';
                $port = $_POST['port_migracao'] ?? '3306';
                $database = $_POST['database_migracao'] ?? '';
                $user = $_POST['user_migracao'] ?? '';
                $password = $_POST['password_migracao'] ?? '';
                
                $mensagem = executarMigracao($driver, $host, $port, $database, $user, $password);
                $tipo_mensagem = strpos($mensagem, '✅') !== false ? 'success' : 'error';
                break;
                
            case 'exportar_sql':
                $mensagem = exportarDadosSQL();
                $tipo_mensagem = strpos($mensagem, '✅') !== false ? 'success' : 'error';
                break;
                
            case 'importar_sql':
                if (isset($_FILES['arquivo_sql']) && $_FILES['arquivo_sql']['error'] === UPLOAD_ERR_OK) {
                    $mensagem = importarDadosSQL($_FILES['arquivo_sql']['tmp_name']);
                    $tipo_mensagem = strpos($mensagem, '✅') !== false ? 'success' : 'error';
                } else {
                    $mensagem = "❌ Nenhum arquivo enviado ou erro no upload.";
                    $tipo_mensagem = 'error';
                }
                break;
        }
    }
}

// ===== FUNÇÕES DE MIGRAÇÃO =====
function executarMigracao($driver, $host, $port, $database, $user, $password) {
    try {
        // Conectar ao banco de destino
        if ($driver === 'mysql') {
            $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
        } elseif ($driver === 'pgsql') {
            $dsn = "pgsql:host=$host;port=$port;dbname=$database";
        } else {
            return "❌ Driver não suportado";
        }
        
        $destino = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Buscar estrutura do banco atual
        global $pdo;
        $tabelas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        $sqls = [];
        $sqls[] = "-- Migração gerada em " . date('Y-m-d H:i:s');
        $sqls[] = "-- Banco de origem: " . $pdo->query("SELECT DATABASE()")->fetchColumn();
        $sqls[] = "-- Banco de destino: $database";
        $sqls[] = "";
        
        foreach ($tabelas as $tabela) {
            // Estrutura da tabela
            $stmt = $pdo->query("SHOW CREATE TABLE $tabela");
            $create = $stmt->fetch();
            $sqls[] = $create['Create Table'] . ";";
            $sqls[] = "";
            
            // Dados da tabela
            $dados = $pdo->query("SELECT * FROM $tabela")->fetchAll();
            if (count($dados) > 0) {
                $colunas = array_keys($dados[0]);
                $sqls[] = "INSERT INTO $tabela (" . implode(', ', $colunas) . ") VALUES";
                
                $values = [];
                foreach ($dados as $row) {
                    $valores = [];
                    foreach ($row as $valor) {
                        if ($valor === null) {
                            $valores[] = 'NULL';
                        } else {
                            $valores[] = "'" . addslashes($valor) . "'";
                        }
                    }
                    $values[] = "(" . implode(', ', $valores) . ")";
                }
                $sqls[] = implode(",\n", $values) . ";";
                $sqls[] = "";
            }
        }
        
        // Salvar arquivo SQL
        $arquivo_sql = "../backups/migracao_" . date('Ymd_His') . ".sql";
        file_put_contents($arquivo_sql, implode("\n", $sqls));
        
        // Executar no banco de destino
        $destino->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($sqls as $sql) {
            if (trim($sql) && !str_starts_with(trim($sql), '--')) {
                try {
                    $destino->exec($sql);
                } catch (Exception $e) {
                    // Ignorar erros de dados duplicados
                    if (strpos($e->getMessage(), 'Duplicate') === false) {
                        throw $e;
                    }
                }
            }
        }
        $destino->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        return "✅ Migração concluída com sucesso!\n📁 Arquivo gerado: $arquivo_sql";
        
    } catch (Exception $e) {
        return "❌ Erro na migração: " . $e->getMessage();
    }
}

function exportarDadosSQL() {
    try {
        global $pdo;
        $tabelas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        $sqls = [];
        $sqls[] = "-- Backup gerado em " . date('Y-m-d H:i:s');
        $sqls[] = "-- Banco: " . $pdo->query("SELECT DATABASE()")->fetchColumn();
        $sqls[] = "";
        
        foreach ($tabelas as $tabela) {
            $stmt = $pdo->query("SHOW CREATE TABLE $tabela");
            $create = $stmt->fetch();
            $sqls[] = $create['Create Table'] . ";";
            $sqls[] = "";
            
            $dados = $pdo->query("SELECT * FROM $tabela")->fetchAll();
            if (count($dados) > 0) {
                $colunas = array_keys($dados[0]);
                $sqls[] = "INSERT INTO $tabela (" . implode(', ', $colunas) . ") VALUES";
                
                $values = [];
                foreach ($dados as $row) {
                    $valores = [];
                    foreach ($row as $valor) {
                        if ($valor === null) {
                            $valores[] = 'NULL';
                        } else {
                            $valores[] = "'" . addslashes($valor) . "'";
                        }
                    }
                    $values[] = "(" . implode(', ', $valores) . ")";
                }
                $sqls[] = implode(",\n", $values) . ";";
                $sqls[] = "";
            }
        }
        
        $arquivo = "../backups/backup_" . date('Ymd_His') . ".sql";
        file_put_contents($arquivo, implode("\n", $sqls));
        
        return "✅ Backup exportado com sucesso!\n📁 Arquivo: $arquivo";
        
    } catch (Exception $e) {
        return "❌ Erro na exportação: " . $e->getMessage();
    }
}

function importarDadosSQL($arquivo) {
    try {
        global $pdo;
        $sql = file_get_contents($arquivo);
        
        if (empty($sql)) {
            return "❌ Arquivo vazio ou inválido.";
        }
        
        // Dividir em comandos SQL
        $comandos = array_filter(array_map('trim', explode(';', $sql)));
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        $total = 0;
        foreach ($comandos as $comando) {
            if (!empty($comando) && !str_starts_with($comando, '--')) {
                try {
                    $pdo->exec($comando);
                    $total++;
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate') === false) {
                        throw $e;
                    }
                }
            }
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        return "✅ Importação concluída! $total comandos executados.";
        
    } catch (Exception $e) {
        return "❌ Erro na importação: " . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Banco de Dados - SoftGest</title>
    <style>
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
            margin-bottom: 25px;
        }
        
        .page-header h1 {
            font-size: 28px;
            color: #1a2332;
        }
        
        .page-header h1 span {
            color: #c9a84c;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
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
        
        .card h2 .icon {
            margin-right: 10px;
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
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus {
            border-color: #c9a84c;
            outline: none;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
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
        
        .btn-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-info:hover {
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn-warning:hover {
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
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
            white-space: pre-line;
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
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .info-item .label {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .info-item .value {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
            margin-top: 5px;
        }
        
        .info-item .value .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-mysql {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-pgsql {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-sqlite {
            background: #fef3c7;
            color: #92400e;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn-group .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- ===== HEADER ===== -->
    <div class="page-header">
        <h1>🗄️ <span>Gerenciador de Banco de Dados</span></h1>
        <div>
            <a href="../index.php" class="btn btn-info">← Voltar ao Dashboard</a>
        </div>
    </div>
    
    <!-- ===== MENSAGENS ===== -->
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_mensagem ?>">
            <?= nl2br(htmlspecialchars($mensagem)) ?>
        </div>
    <?php endif; ?>
    
    <!-- ===== CONEXÃO ATUAL ===== -->
    <div class="card">
        <h2><span class="icon">🔌</span> Conexão Atual</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="label">Driver</div>
                <div class="value">
                    <span class="badge badge-<?= $conexao_info['driver'] ?>">
                        <?= strtoupper($conexao_info['driver']) ?>
                    </span>
                </div>
            </div>
            <div class="info-item">
                <div class="label">Banco de Dados</div>
                <div class="value"><?= htmlspecialchars($conexao_info['database']) ?></div>
            </div>
            <div class="info-item">
                <div class="label">Host</div>
                <div class="value"><?= htmlspecialchars($conexao_info['host']) ?></div>
            </div>
            <div class="info-item">
                <div class="label">Usuário</div>
                <div class="value"><?= htmlspecialchars($conexao_info['user']) ?></div>
            </div>
        </div>
    </div>
    
    <!-- ===== TESTAR CONEXÃO ===== -->
    <div class="card">
        <h2><span class="icon">🔍</span> Testar Nova Conexão</h2>
        <form method="POST">
            <input type="hidden" name="acao" value="testar_conexao">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Driver</label>
                    <select name="driver" required>
                        <option value="mysql">MySQL</option>
                        <option value="pgsql">PostgreSQL</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Host</label>
                    <input type="text" name="host" placeholder="localhost" value="localhost" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Porta</label>
                    <input type="text" name="port" placeholder="3306" value="3306" required>
                </div>
                <div class="form-group">
                    <label>Banco de Dados</label>
                    <input type="text" name="database" placeholder="nome_do_banco" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Usuário</label>
                    <input type="text" name="user" placeholder="root" required>
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="password" placeholder="********">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">🔌 Testar Conexão</button>
        </form>
    </div>
    
    <!-- ===== MIGRAR DADOS ===== -->
    <div class="card">
        <h2><span class="icon">🔄</span> Migrar Dados</h2>
        <p style="color: #64748b; margin-bottom: 15px;">
            Migre todos os dados do banco atual para um novo banco de dados.
        </p>
        
        <form method="POST">
            <input type="hidden" name="acao" value="migrar">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Driver de Destino</label>
                    <select name="driver_migracao" required>
                        <option value="mysql">MySQL</option>
                        <option value="pgsql">PostgreSQL</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Host</label>
                    <input type="text" name="host_migracao" placeholder="localhost" value="localhost" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Porta</label>
                    <input type="text" name="port_migracao" placeholder="3306" value="3306" required>
                </div>
                <div class="form-group">
                    <label>Banco de Dados</label>
                    <input type="text" name="database_migracao" placeholder="nome_do_banco" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Usuário</label>
                    <input type="text" name="user_migracao" placeholder="root" required>
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="password_migracao" placeholder="********">
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-success">🔄 Migrar Dados</button>
            </div>
        </form>
    </div>
    
    <!-- ===== BACKUP E RESTAURAÇÃO ===== -->
    <div class="card">
        <h2><span class="icon">💾</span> Backup e Restauração</h2>
        
        <div class="form-row">
            <div class="form-group">
                <form method="POST">
                    <input type="hidden" name="acao" value="exportar_sql">
                    <button type="submit" class="btn btn-warning">📤 Exportar Backup (SQL)</button>
                </form>
                <p style="font-size: 12px; color: #94a3b8; margin-top: 5px;">
                    Gera um arquivo SQL com toda a estrutura e dados.
                </p>
            </div>
            
            <div class="form-group">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="importar_sql">
                    <div class="file-input-wrapper">
                        <button type="button" class="btn btn-info">📥 Importar Backup</button>
                        <input type="file" name="arquivo_sql" accept=".sql" required onchange="this.form.submit()">
                    </div>
                    <p style="font-size: 12px; color: #94a3b8; margin-top: 5px;">
                        Selecione um arquivo SQL para importar.
                    </p>
                </form>
            </div>
        </div>
    </div>
    
    <!-- ===== COMANDOS RÁPIDOS ===== -->
    <div class="card">
        <h2><span class="icon">⚡</span> Comandos Rápidos</h2>
        <div class="btn-group">
            <button class="btn btn-danger" onclick="if(confirm('Tem certeza que deseja limpar o cache?')){window.location.href='?limpar_cache=1'}">
                🗑️ Limpar Cache
            </button>
            <button class="btn btn-warning" onclick="if(confirm('Tem certeza que deseja otimizar as tabelas?')){window.location.href='?otimizar=1'}">
                ⚡ Otimizar Tabelas
            </button>
            <button class="btn btn-info" onclick="window.location.href='?verificar_integridade=1'">
                🔍 Verificar Integridade
            </button>
        </div>
    </div>
</div>

<script>
// Auto-submit do formulário de importação
document.querySelector('input[type="file"]').addEventListener('change', function() {
    if (this.files.length > 0) {
        this.form.submit();
    }
});

// Confirmar ações destrutivas
document.querySelectorAll('.btn-danger, .btn-warning').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm('Tem certeza que deseja executar esta ação?')) {
            e.preventDefault();
        }
    });
});
</script>

</body>
</html>

<?php include '../includes/footer.php'; ?>