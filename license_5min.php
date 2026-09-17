<?php
// license_5min.php
// Licença de 5 minutos para teste

session_start();
require_once 'config/app_modes.php';

// Verificar se já está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = conectarBanco();
    
    // Criar tabela se não existir
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS licencas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        cliente VARCHAR(100) NOT NULL,
        codigo_licenca VARCHAR(50) UNIQUE NOT NULL,
        tipo VARCHAR(20) DEFAULT 'anual',
        data_ativacao DATETIME NOT NULL,
        data_expiracao DATETIME NOT NULL,
        status VARCHAR(20) DEFAULT 'ativa',
        observacoes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_codigo (codigo_licenca),
        INDEX idx_status (status),
        INDEX idx_expiracao (data_expiracao)
    )");
    
    // Desativar licenças antigas
    if (isset($_GET['force']) && $_GET['force'] == '1') {
        $pdo->exec("UPDATE licencas SET status = 'substituida' WHERE cliente = 'Claudtec' AND status = 'ativa'");
        $pdo->exec("UPDATE licencas SET status = 'substituida' WHERE cliente = 'Claudtec' AND status = 'expirada'");
    }
    
    // Verificar se já existe licença ativa
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM licencas WHERE cliente = 'Claudtec' AND status = 'ativa' AND data_expiracao > NOW()");
    $stmt->execute();
    $ativa = $stmt->fetchColumn();
    
    if ($ativa > 0 && !isset($_GET['force'])) {
        // Redirecionar para o dashboard
        header('Location: index.php');
        exit;
    }
    
    // Gerar código único
    $codigo = 'TEST-' . date('YmdHis') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Inserir licença de 5 minutos
    $sql = "
    INSERT INTO licencas (
        cliente,
        codigo_licenca,
        tipo,
        data_ativacao,
        data_expiracao,
        status,
        observacoes
    ) VALUES (
        'Claudtec',
        :codigo,
        'teste',
        NOW(),
        DATE_ADD(NOW(), INTERVAL 5 MINUTE),
        'ativa',
        :observacoes
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':codigo' => $codigo,
        ':observacoes' => 'Licença de teste - 5 minutos - Gerada em ' . date('d/m/Y H:i:s')
    ]);
    
    // Redirecionar para o dashboard
    header('Location: index.php?license=test');
    exit;
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}
?>