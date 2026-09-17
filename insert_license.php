<?php
// insert_license.php
// Script para inserir chave de licença no sistema - ESTRUTURA CORRIGIDA

// ============================================
// CARREGAR CONFIGURAÇÕES
// ============================================

if (!file_exists('config/database.php')) {
    die('❌ Arquivo config/database.php não encontrado!');
}

require_once 'config/database.php';

// Verificar se a variável $conn existe
if (!isset($conn) || $conn === null) {
    $host = 'localhost';
    $user = 'root';
    $password = '';
    $database = 'softgest_db';
    
    $conn = mysqli_connect($host, $user, $password, $database);
    
    if (!$conn) {
        die('❌ Erro ao conectar ao banco de dados: ' . mysqli_connect_error());
    }
    
    mysqli_set_charset($conn, "utf8mb4");
}

if (!$conn) {
    die('❌ Conexão com o banco de dados falhou!');
}

$mensagem = '';
$tipo_mensagem = '';

// ============================================
// PROCESSAR FORMULÁRIO
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chave = trim($_POST['chave'] ?? '');
    $empresa = trim($_POST['empresa'] ?? 'SoftGest Sistemas');
    $cnpj = trim($_POST['cnpj'] ?? '00.000.000/0001-00');
    $email = trim($_POST['email'] ?? 'admin@softgest.com');
    $telefone = trim($_POST['telefone'] ?? '');
    $validade = $_POST['validade'] ?? date('Y-m-d', strtotime('+1 year'));
    
    // Converter data para formato YYYY-MM-DD
    if (strpos($validade, '/') !== false) {
        $partes = explode('/', $validade);
        $validade = $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    
    if (empty($chave)) {
        $mensagem = "⚠️ Por favor, informe uma chave de licença.";
        $tipo_mensagem = 'warning';
    } else {
        try {
            // 1. Verificar se a tabela existe com a estrutura correta
            $check = mysqli_query($conn, "SHOW TABLES LIKE 'licencas'");
            if (!$check || mysqli_num_rows($check) == 0) {
                // Criar tabela com a estrutura correta
                $sql = "CREATE TABLE licencas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    cliente VARCHAR(255) NOT NULL,
                    codigo_licenca VARCHAR(255) NOT NULL UNIQUE,
                    tipo VARCHAR(50) DEFAULT 'anual',
                    data_ativacao DATE,
                    data_expiracao DATE NOT NULL,
                    status VARCHAR(20) DEFAULT 'ativa',
                    observacoes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                
                if (!mysqli_query($conn, $sql)) {
                    throw new Exception("Erro ao criar tabela: " . mysqli_error($conn));
                }
            }
            
            // 2. Verificar se a licença já existe (usando codigo_licenca)
            $sql_check = "SELECT COUNT(*) as total FROM licencas WHERE codigo_licenca = ?";
            $stmt = mysqli_prepare($conn, $sql_check);
            
            if (!$stmt) {
                throw new Exception("Erro ao preparar verificação: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "s", $chave);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $existe = $row['total'] ?? 0;
            mysqli_stmt_close($stmt);
            
            if ($existe > 0) {
                $mensagem = "⚠️ Licença '$chave' já existe no sistema!";
                $tipo_mensagem = 'warning';
            } else {
                // 3. Inserir licença com a estrutura correta
                $data_ativacao = date('Y-m-d');
                $sql_insert = "INSERT INTO licencas (cliente, codigo_licenca, tipo, data_ativacao, data_expiracao, status, observacoes) 
                               VALUES (?, ?, ?, ?, ?, 'ativa', ?)";
                $stmt = mysqli_prepare($conn, $sql_insert);
                
                if (!$stmt) {
                    throw new Exception("Erro ao preparar inserção: " . mysqli_error($conn));
                }
                
                $tipo = 'anual';
                $observacoes = "Licença inserida manualmente em " . date('d/m/Y H:i:s');
                
                mysqli_stmt_bind_param($stmt, "ssssss", $empresa, $chave, $tipo, $data_ativacao, $validade, $observacoes);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Erro ao inserir licença: " . mysqli_stmt_error($stmt));
                }
                
                mysqli_stmt_close($stmt);
                
                $mensagem = "✅ Licença inserida com sucesso!\n";
                $mensagem .= "📋 Código: $chave\n";
                $mensagem .= "🏢 Cliente: $empresa\n";
                $mensagem .= "📅 Validade: " . date('d/m/Y', strtotime($validade));
                $tipo_mensagem = 'success';
            }
            
        } catch (Exception $e) {
            $mensagem = "❌ Erro: " . $e->getMessage();
            $tipo_mensagem = 'error';
        }
    }
}

// ============================================
// LISTAR LICENÇAS EXISTENTES
// ============================================

$licencas = [];
try {
    // Verificar se a tabela existe
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'licencas'");
    if ($check && mysqli_num_rows($check) > 0) {
        // Verificar a estrutura da tabela
        $columns = [];
        $result = mysqli_query($conn, "SHOW COLUMNS FROM licencas");
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = $row['Field'];
        }
        
        // Verificar qual coluna de código existe
        $coluna_codigo = in_array('codigo_licenca', $columns) ? 'codigo_licenca' : 'licenca';
        $coluna_cliente = in_array('cliente', $columns) ? 'cliente' : 'empresa';
        $coluna_validade = in_array('data_expiracao', $columns) ? 'data_expiracao' : 'data_validade';
        
        // Buscar licenças
        $sql = "SELECT id, $coluna_codigo as codigo, $coluna_cliente as cliente, $coluna_validade as validade, status 
                FROM licencas 
                ORDER BY id DESC 
                LIMIT 10";
        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $licencas[] = $row;
            }
            mysqli_free_result($result);
        }
    }
} catch (Exception $e) {
    // Tabela pode não existir ainda
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inserir Licença - SoftGest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #1a2332, #2c3e50); color: white; padding: 20px 30px; border-radius: 12px; margin-bottom: 25px; }
        .header h1 { font-size: 28px; }
        .header h1 span { color: #f5d76e; }
        .header p { color: #94a3b8; margin-top: 5px; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; }
        .card h2 { font-size: 20px; color: #1a2332; margin-bottom: 20px; border-bottom: 2px solid #f5d76e; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; font-size: 14px; color: #1a2332; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
        .form-group input:focus { border-color: #c9a84c; outline: none; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn { width: 100%; padding: 12px; border: none; border-radius: 8px; font-weight: 600; font-size: 16px; cursor: pointer; background: linear-gradient(135deg, #c9a84c, #f5d76e); color: #1a2332; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197, 165, 50, 0.3); }
        .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 15px; font-weight: 500; white-space: pre-line; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .info { background: #f8fafc; padding: 15px; border-radius: 8px; margin: 15px 0; font-size: 13px; color: #64748b; border: 1px solid #e2e8f0; }
        .info strong { color: #1a2332; }
        .chave-exemplo { background: #f1f5f9; padding: 8px 12px; border-radius: 6px; font-family: monospace; font-size: 14px; color: #1a2332; margin-top: 5px; display: inline-block; border: 1px solid #e2e8f0; }
        .table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .table th { background: #f8fafc; text-align: left; padding: 10px; border-bottom: 2px solid #e2e8f0; font-weight: 600; color: #1a2332; }
        .table td { padding: 10px; border-bottom: 1px solid #f1f5f9; }
        .table tr:hover { background: #f8fafc; }
        .badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .actions { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        .btn-sm { padding: 8px 16px; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-sm:hover { transform: translateY(-1px); }
        .btn-sm-primary { background: #c9a84c; color: #1a2332; }
        .btn-sm-success { background: #2ecc71; color: white; }
        .btn-sm-danger { background: #e74c3c; color: white; }
        .btn-sm-secondary { background: #e2e8f0; color: #1a2332; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
        .text-center { text-align: center; }
        .mt-20 { margin-top: 20px; }
        .mb-10 { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔑 <span>Gerenciar Licenças</span></h1>
            <p>SoftGest - Sistema de Gestão Escolar</p>
        </div>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?>"><?= nl2br(htmlspecialchars($mensagem)) ?></div>
        <?php endif; ?>
        
        <?php if (empty($licencas)): ?>
            <div class="alert alert-info">
                ℹ️ Nenhuma licença cadastrada ainda. Preencha o formulário abaixo para adicionar uma licença.
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>📝 Inserir Nova Licença</h2>
            
            <div class="info">
                <strong>📌 Como gerar uma chave:</strong><br>
                1. Use o gerador de licenças em Python<br>
                2. Ou use uma chave no formato: <strong>CLD-YYYYMMDD-XXXX</strong> ou <strong>TEST-YYYYMMDDHHMMSS-XXXX</strong>
                <div class="chave-exemplo">CLD-20260706-7A8D1</div>
                <div class="chave-exemplo">TEST-20260706143022-9B3E</div>
            </div>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>🏢 Cliente *</label>
                        <input type="text" name="empresa" value="Claudtec" required>
                    </div>
                    
                    <div class="form-group">
                        <label>📋 CNPJ *</label>
                        <input type="text" name="cnpj" value="00.000.000/0001-00" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>📧 Email *</label>
                        <input type="email" name="email" value="admin@softgest.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label>📞 Telefone</label>
                        <input type="text" name="telefone" value="(11) 99999-9999">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>🔑 Código da Licença *</label>
                        <input type="text" name="chave" placeholder="Ex: CLD-20260706-7A8D1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>📅 Data de Validade *</label>
                        <input type="date" name="validade" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                    </div>
                </div>
                
                <button type="submit" class="btn">✅ Inserir Licença</button>
            </form>
        </div>
        
        <?php if (!empty($licencas)): ?>
        <div class="card">
            <h2>📋 Licenças Cadastradas</h2>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Validade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($licencas as $lic): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($lic['codigo'] ?? 'N/A') ?></strong></td>
                        <td><?= htmlspecialchars($lic['cliente'] ?? 'N/A') ?></td>
                        <td><?= isset($lic['validade']) ? date('d/m/Y', strtotime($lic['validade'])) : 'N/A' ?></td>
                        <td>
                            <span class="badge <?= ($lic['status'] ?? '') === 'ativa' ? 'badge-success' : (($lic['status'] ?? '') === 'expirada' ? 'badge-danger' : 'badge-warning') ?>">
                                <?= strtoupper($lic['status'] ?? 'DESCONHECIDO') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="text-center">
                <a href="login.php" class="btn-sm btn-sm-primary">← Voltar para o Login</a>
                <a href="admin/sync_database.php" class="btn-sm btn-sm-success">🔄 Sincronizar Banco</a>
                <a href="admin/gerenciar_licencas.php" class="btn-sm btn-sm-primary">🔑 Gerenciar Licenças</a>
            </div>
        </div>
    </div>
</body>
</html>