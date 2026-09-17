<?php
// ============================================
// coordenador_pedagogico/mensagens.php
// Sistema de Mensagens - Coordenador Pedagógico
// ============================================

session_start();

// ===== VERIFICAR LOGIN =====
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /softgest_web/login.php');
    exit;
}

// ===== VERIFICAR PERFIL =====
$perfil = $_SESSION['usuario_perfil'] ?? '';
if ($perfil != 'coordenador_pedagogico' && $perfil != 'coordenador' && $perfil != 'admin') {
    header('Location: /softgest_web/index.php');
    exit;
}

// ===== CONFIGURAÇÕES =====
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

// ===== DADOS DO USUÁRIO =====
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Coordenador';
$usuario_email = $_SESSION['usuario_email'] ?? '';
$usuario_perfil = $_SESSION['usuario_perfil'] ?? 'coordenador_pedagogico';

// ===== INICIALIZAR VARIÁVEIS =====
$mensagens = [];
$usuarios = [];
$totalNaoLidas = 0;
$totalEnviadas = 0;
$mensagem_erro = '';
$mensagem_sucesso = '';
$empresa = [];
$acao = $_GET['acao'] ?? 'listar';
$mensagem_id = $_GET['id'] ?? null;
$destinatario_id = $_GET['com'] ?? null;
$responder_id = $_GET['responder'] ?? null;
$filtro = $_GET['filtro'] ?? 'todas';

// ===== BUSCAR DADOS DA EMPRESA =====
try {
    $stmt = $pdo->query("SELECT * FROM empresa WHERE id = 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$empresa) {
        $empresa = [
            'id' => 1,
            'razao_social' => 'COMPLEXO ESCOLAR CASTELO REIS',
            'nome_fantasia' => 'COMPLEXO ESCOLAR CASTELO REIS',
            'endereco' => 'Icolo e Bengo; Km44, Desvio do Bom Jesus',
            'telefone' => '972902412',
            'email' => 'complexoescolarcasteloreis@gmail.com'
        ];
    }
} catch (PDOException $e) {
    error_log("Erro ao carregar empresa: " . $e->getMessage());
}

// ===== FUNÇÕES AUXILIARES =====
function formatarData($data) {
    if (!$data) return '-';
    $timestamp = strtotime($data);
    $agora = time();
    $diferenca = $agora - $timestamp;
    
    if ($diferenca < 60) {
        return 'agora mesmo';
    } elseif ($diferenca < 3600) {
        return round($diferenca / 60) . ' min atrás';
    } elseif ($diferenca < 86400) {
        return round($diferenca / 3600) . ' h atrás';
    } elseif ($diferenca < 172800) {
        return 'ontem';
    } else {
        return date('d/m/Y H:i', $timestamp);
    }
}

function getStatusBadge($status) {
    switch ($status) {
        case 'nao_lida': return 'danger';
        case 'lida': return 'success';
        case 'enviada': return 'warning';
        default: return 'secondary';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'nao_lida': return 'Não Lida';
        case 'lida': return 'Lida';
        case 'enviada': return 'Enviada';
        default: return ucfirst($status);
    }
}

// ===== PROCESSAR AÇÕES =====
try {
    // Verificar se a tabela mensagens existe, se não, criar
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mensagens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            remetente_id INT NOT NULL,
            destinatario_id INT NULL,
            assunto VARCHAR(255) NOT NULL,
            mensagem TEXT NOT NULL,
            tipo ENUM('publica','privada') DEFAULT 'publica',
            respondendo_a INT NULL,
            data_envio DATETIME NOT NULL,
            data_leitura DATETIME NULL,
            status ENUM('enviada','lida','nao_lida') DEFAULT 'enviada',
            FOREIGN KEY (remetente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE SET NULL
        )
    ");
    
    // Verificar se a tabela mensagens_anexos existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mensagens_anexos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            mensagem_id INT NOT NULL,
            nome_original VARCHAR(255) NOT NULL,
            nome_arquivo VARCHAR(255) NOT NULL,
            caminho VARCHAR(500) NOT NULL,
            tipo_arquivo VARCHAR(100) NULL,
            tamanho INT NOT NULL,
            data_upload DATETIME NOT NULL,
            FOREIGN KEY (mensagem_id) REFERENCES mensagens(id) ON DELETE CASCADE
        )
    ");
} catch (PDOException $e) {
    // Tabelas já existem ou erro de criação
}

// ===== BUSCAR MENSAGENS =====
try {
    // Total de mensagens não lidas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mensagens WHERE destinatario_id = ? AND status = 'nao_lida'");
    $stmt->execute([$usuario_id]);
    $totalNaoLidas = $stmt->fetchColumn() ?: 0;
    
    // Total de mensagens enviadas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mensagens WHERE remetente_id = ?");
    $stmt->execute([$usuario_id]);
    $totalEnviadas = $stmt->fetchColumn() ?: 0;
    
    // Buscar mensagens com base no filtro
    $sql = "
        SELECT m.*, 
               u1.nome as remetente_nome, 
               u1.perfil as remetente_perfil,
               u2.nome as destinatario_nome,
               u2.perfil as destinatario_perfil,
               (SELECT COUNT(*) FROM mensagens_anexos WHERE mensagem_id = m.id) as total_anexos
        FROM mensagens m
        LEFT JOIN usuarios u1 ON m.remetente_id = u1.id
        LEFT JOIN usuarios u2 ON m.destinatario_id = u2.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($filtro == 'nao_lidas') {
        $sql .= " AND m.destinatario_id = ? AND m.status = 'nao_lida'";
        $params[] = $usuario_id;
    } elseif ($filtro == 'enviadas') {
        $sql .= " AND m.remetente_id = ?";
        $params[] = $usuario_id;
    } elseif ($filtro == 'publicas') {
        $sql .= " AND m.tipo = 'publica'";
    } elseif ($filtro == 'conversa' && $destinatario_id) {
        $sql .= " AND ((m.remetente_id = ? AND m.destinatario_id = ?) OR (m.remetente_id = ? AND m.destinatario_id = ?))";
        $params[] = $usuario_id;
        $params[] = $destinatario_id;
        $params[] = $destinatario_id;
        $params[] = $usuario_id;
    } else {
        $sql .= " AND (m.remetente_id = ? OR m.destinatario_id = ? OR m.tipo = 'publica')";
        $params[] = $usuario_id;
        $params[] = $usuario_id;
    }
    
    $sql .= " ORDER BY m.data_envio DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar usuários para enviar mensagens
    $stmt = $pdo->prepare("
        SELECT id, nome, email, perfil, status 
        FROM usuarios 
        WHERE id != ? AND status = 'ativo'
        ORDER BY nome
    ");
    $stmt->execute([$usuario_id]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $mensagem_erro = "Erro ao carregar mensagens: " . $e->getMessage();
}

// ===== PROCESSAR AÇÕES POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao_post = $_POST['acao'] ?? '';
    
    if ($acao_post === 'enviar') {
        $destinatario_id_post = $_POST['destinatario_id'] ?? 0;
        $assunto = trim($_POST['assunto'] ?? '');
        $mensagem = trim($_POST['mensagem'] ?? '');
        $tipo = $_POST['tipo'] ?? 'privada';
        $respondendo_a = !empty($_POST['respondendo_a']) ? (int)$_POST['respondendo_a'] : null;
        
        if (!$destinatario_id_post && $tipo === 'privada') {
            $mensagem_erro = "Selecione um destinatário.";
        } elseif (empty($assunto)) {
            $mensagem_erro = "O assunto é obrigatório.";
        } elseif (empty($mensagem)) {
            $mensagem_erro = "A mensagem é obrigatória.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO mensagens (remetente_id, destinatario_id, assunto, mensagem, tipo, respondendo_a, data_envio, status)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), 'nao_lida')
                ");
                $stmt->execute([$usuario_id, $destinatario_id_post ?: null, $assunto, $mensagem, $tipo, $respondendo_a]);
                
                $mensagem_id_inserida = $pdo->lastInsertId();
                $mensagem_sucesso = "Mensagem enviada com sucesso!";
                
                // Se for resposta, marcar a mensagem original como lida
                if ($respondendo_a) {
                    $stmt = $pdo->prepare("UPDATE mensagens SET status = 'lida' WHERE id = ?");
                    $stmt->execute([$respondendo_a]);
                }
                
                // Redirecionar para evitar reenvio
                header('Location: mensagens.php?sucesso=1');
                exit;
                
            } catch (PDOException $e) {
                $mensagem_erro = "Erro ao enviar mensagem: " . $e->getMessage();
            }
        }
    }
    
    if ($acao_post === 'excluir') {
        $mensagem_id_excluir = (int)$_POST['mensagem_id'] ?? 0;
        if ($mensagem_id_excluir) {
            try {
                $stmt = $pdo->prepare("DELETE FROM mensagens WHERE id = ? AND (remetente_id = ? OR destinatario_id = ?)");
                $stmt->execute([$mensagem_id_excluir, $usuario_id, $usuario_id]);
                $mensagem_sucesso = "Mensagem excluída com sucesso.";
                header('Location: mensagens.php?sucesso=1');
                exit;
            } catch (PDOException $e) {
                $mensagem_erro = "Erro ao excluir mensagem: " . $e->getMessage();
            }
        }
    }
    
    if ($acao_post === 'marcar_lida') {
        $mensagem_id_marcar = (int)$_POST['mensagem_id'] ?? 0;
        if ($mensagem_id_marcar) {
            try {
                $stmt = $pdo->prepare("UPDATE mensagens SET status = 'lida', data_leitura = NOW() WHERE id = ? AND destinatario_id = ?");
                $stmt->execute([$mensagem_id_marcar, $usuario_id]);
                $mensagem_sucesso = "Mensagem marcada como lida.";
                header('Location: mensagens.php?sucesso=1');
                exit;
            } catch (PDOException $e) {
                $mensagem_erro = "Erro ao marcar mensagem: " . $e->getMessage();
            }
        }
    }
}

// ===== MARCAR COMO LIDA POR GET =====
if ($acao == 'ler' && $mensagem_id) {
    try {
        $stmt = $pdo->prepare("UPDATE mensagens SET status = 'lida', data_leitura = NOW() WHERE id = ? AND destinatario_id = ?");
        $stmt->execute([$mensagem_id, $usuario_id]);
        header('Location: mensagens.php?acao=ver&id=' . $mensagem_id);
        exit;
    } catch (PDOException $e) {
        // Erro ao marcar como lida
    }
}

// ===== VER MENSAGEM ESPECÍFICA =====
$mensagem_visualizar = null;
if ($acao == 'ver' && $mensagem_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   u1.nome as remetente_nome, 
                   u1.perfil as remetente_perfil,
                   u1.email as remetente_email,
                   u2.nome as destinatario_nome,
                   u2.perfil as destinatario_perfil,
                   (SELECT COUNT(*) FROM mensagens_anexos WHERE mensagem_id = m.id) as total_anexos
            FROM mensagens m
            LEFT JOIN usuarios u1 ON m.remetente_id = u1.id
            LEFT JOIN usuarios u2 ON m.destinatario_id = u2.id
            WHERE m.id = ?
        ");
        $stmt->execute([$mensagem_id]);
        $mensagem_visualizar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mensagem_visualizar && $mensagem_visualizar['destinatario_id'] == $usuario_id) {
            // Marcar como lida se for destinatário
            $stmt = $pdo->prepare("UPDATE mensagens SET status = 'lida', data_leitura = NOW() WHERE id = ?");
            $stmt->execute([$mensagem_id]);
            $mensagem_visualizar['status'] = 'lida';
        }
    } catch (PDOException $e) {
        // Erro ao buscar mensagem
    }
}

// ===== VERIFICAR SE HÁ MENSAGEM DE SUCESSO =====
if (isset($_GET['sucesso'])) {
    $mensagem_sucesso = "Operação realizada com sucesso!";
}

// ===== INCLUIR HEADER =====
include_once $base_path . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensagens - <?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest') ?></title>
    <link href="/softgest_web/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/softgest_web/assets/css/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #c9a84c;
            --primary-dark: #b8973a;
            --secondary-color: #1a2332;
            --bg-light: #f8f9fa;
            --text-muted: #718096;
            --border-color: #e2e8f0;
            --shadow: 0 2px 10px rgba(0,0,0,0.08);
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 260px;
            background: var(--secondary-color);
            color: white;
            min-height: 100vh;
            padding: 0;
            flex-shrink: 0;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 2px 0 20px rgba(0,0,0,0.2);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

        .sidebar-brand {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }
        .sidebar-brand h4 {
            font-weight: 700;
            margin: 0;
            color: #f5d76e;
            font-size: 16px;
        }
        .sidebar-brand small {
            color: rgba(255,255,255,0.5);
            font-size: 11px;
        }

        .sidebar-user {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar-user .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .sidebar-user .user-info .name {
            font-weight: 600;
            font-size: 14px;
            color: white;
        }
        .sidebar-user .user-info .email {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
        }
        .sidebar-user .user-info .perfil {
            font-size: 10px;
            background: rgba(245, 215, 110, 0.2);
            color: #f5d76e;
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
        }

        .sidebar-nav {
            padding: 10px 0;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            gap: 12px;
            font-size: 13px;
            cursor: pointer;
        }
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.05);
            color: white;
            border-left-color: var(--primary-color);
        }
        .sidebar-nav a.active {
            background: rgba(245, 215, 110, 0.1);
            color: #f5d76e;
            border-left-color: var(--primary-color);
        }
        .sidebar-nav a .nav-icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        .sidebar-nav a .badge {
            margin-left: auto;
            font-size: 10px;
            padding: 2px 8px;
            background: #e74c3c;
            animation: pulse-badge 2s infinite;
        }
        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.05);
            margin: 10px 20px;
        }

        .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
            margin-top: auto;
        }
        .sidebar-footer .user-name {
            font-weight: 600;
            color: white;
            font-size: 13px;
        }
        .sidebar-footer .user-role {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
        }
        .sidebar-footer .btn-logout {
            display: block;
            text-align: center;
            padding: 8px;
            margin-top: 10px;
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.3s;
        }
        .sidebar-footer .btn-logout:hover {
            background: rgba(231, 76, 60, 0.4);
            color: #fff;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            cursor: pointer;
        }
        .sidebar-overlay.active { display: block !important; }

        .btn-toggle-sidebar {
            display: none;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--secondary-color);
            margin-right: 15px;
        }

        /* ===== TOP BAR ===== */
        .top-bar {
            background: white;
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 2px solid var(--primary-color);
        }
        .top-bar .page-title h3 {
            margin: 0;
            font-weight: 700;
            color: var(--secondary-color);
            font-size: 20px;
        }
        .top-bar .page-title small {
            color: var(--text-muted);
            font-size: 13px;
        }
        .top-bar .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .top-bar .user-info .welcome-text {
            font-size: 14px;
            color: var(--text-color);
        }
        .top-bar .user-info .badge-perfil {
            background: var(--primary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 11px;
        }
        .top-bar .user-info .time {
            color: var(--text-muted);
            font-size: 13px;
        }

        /* ===== CONTENT ===== */
        .content-area {
            padding: 25px 30px;
        }

        .mensagens-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .mensagens-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }

        .mensagens-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #1a2332;
            margin: 0;
        }

        .mensagens-header .badge-count {
            background: #e74c3c;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
        }

        .mensagens-header .badge-count.hidden {
            display: none;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: #c9a84c;
            color: white;
        }

        .btn-primary:hover {
            background: #b8973a;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .mensagem-card {
            background: white;
            border-radius: 14px;
            padding: 20px 25px;
            margin-bottom: 15px;
            border: 1px solid #eef2f7;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            color: inherit;
            cursor: pointer;
        }

        .mensagem-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transform: translateY(-2px);
        }

        .mensagem-card.nao-lida {
            border-left: 4px solid #c9a84c;
            background: #fefcf5;
        }

        .mensagem-card .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }

        .mensagem-card .remetente {
            font-weight: 600;
            color: #1a2332;
            font-size: 16px;
        }

        .mensagem-card .remetente .perfil-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
            background: #e8f0fe;
            color: #1a56db;
        }

        .mensagem-card .data {
            color: #94a3b8;
            font-size: 13px;
        }

        .mensagem-card .assunto {
            font-weight: 600;
            color: #1a2332;
            font-size: 17px;
            margin: 8px 0;
        }

        .mensagem-card .mensagem-preview {
            color: #4a5568;
            font-size: 15px;
            line-height: 1.5;
        }

        .mensagem-card .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
        }

        .mensagem-card .anexos-info {
            color: #94a3b8;
            font-size: 13px;
        }

        .form-mensagem {
            background: white;
            border-radius: 14px;
            padding: 25px;
            border: 1px solid #eef2f7;
            margin-bottom: 30px;
        }

        .form-mensagem .form-group {
            margin-bottom: 18px;
        }

        .form-mensagem label {
            display: block;
            font-weight: 600;
            color: #1a2332;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .form-mensagem .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            background: white;
        }

        .form-mensagem .form-control:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
        }

        .form-mensagem textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .tabs .tab {
            padding: 10px 20px;
            background: #f1f5f9;
            border-radius: 10px;
            color: #4a5568;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            position: relative;
        }

        .tabs .tab:hover {
            background: #e2e8f0;
        }

        .tabs .tab.active {
            background: #c9a84c;
            color: white;
        }

        .tabs .tab .badge {
            background: #e74c3c;
            color: white;
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 5px;
        }

        .tabs .tab .badge.hidden {
            display: none;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 14px;
            border: 1px solid #eef2f7;
        }

        .empty-state .icon {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #4a5568;
            margin: 0 0 5px;
        }

        .empty-state p {
            color: #94a3b8;
        }

        .alert {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .btn-toggle-sidebar {
                display: block;
            }
            .top-bar {
                padding: 10px 15px;
                flex-wrap: wrap;
                gap: 8px;
            }
            .top-bar .user-info .welcome-text {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .content-area {
                padding: 15px;
            }
            .mensagens-header {
                flex-direction: column;
                align-items: stretch;
            }
            .mensagens-header .btn {
                justify-content: center;
            }
            .mensagem-card .header {
                flex-direction: column;
                align-items: flex-start;
            }
            .tabs {
                flex-direction: column;
            }
            .tabs .tab {
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4>🏫 <?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest') ?></h4>
            <small>Enterprise • Dashboard</small>
        </div>
        
        <div class="sidebar-user">
            <div class="avatar">👤</div>
            <div class="user-info">
                <div class="name"><?= htmlspecialchars($usuario_nome) ?></div>
                <div class="email"><?= htmlspecialchars($usuario_email) ?></div>
                <div class="perfil"><?= ucfirst(str_replace('_', ' ', $usuario_perfil)) ?></div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php">
                <span class="nav-icon">📊</span> Dashboard
            </a>
            <a href="mensagens.php" class="active">
                <span class="nav-icon">💬</span> Mensagens
                <span class="badge" id="msgBadge" <?= $totalNaoLidas > 0 ? '' : 'style="display:none;"' ?>><?= $totalNaoLidas ?></span>
            </a>
            <a href="dashboard.php#section-distribuicao">
                <span class="nav-icon">👨‍🏫</span> Distribuição de Professores
            </a>
            <a href="dashboard.php#section-bancoNotas">
                <span class="nav-icon">📝</span> Banco de Notas
            </a>
            <a href="dashboard.php#section-turmas">
                <span class="nav-icon">🏫</span> Gestão de Turmas
            </a>
            <a href="dashboard.php#section-disciplinas">
                <span class="nav-icon">📚</span> Disciplinas
            </a>
            <a href="dashboard.php#section-alunos">
                <span class="nav-icon">🎓</span> Alunos
            </a>
            <a href="dashboard.php#section-professores">
                <span class="nav-icon">👨‍🏫</span> Professores
            </a>
            <a href="dashboard.php#section-pautas-trimestrais">
                <span class="nav-icon">📅</span> Pautas Trimestrais
            </a>
            <a href="dashboard.php#section-pautas-finais">
                <span class="nav-icon">📄</span> Pautas Finais
            </a>
            <a href="dashboard.php#section-boletins">
                <span class="nav-icon">📃</span> Boletins
            </a>
            <a href="dashboard.php#section-estatisticas">
                <span class="nav-icon">📈</span> Estatísticas
            </a>
            
            <div class="sidebar-divider"></div>
            
            <a href="dashboard.php#section-prazos">
                <span class="nav-icon">⏰</span> Prazos
                <span class="badge">!</span>
            </a>
            <a href="dashboard.php#section-pagamentos">
                <span class="nav-icon">💰</span> Pagamentos
            </a>
            <a href="dashboard.php#section-configuracoes">
                <span class="nav-icon">⚙️</span> Configurações
            </a>
            <a href="dashboard.php#section-logs">
                <span class="nav-icon">📋</span> Logs de Atividades
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-name">👤 <?= htmlspecialchars($usuario_nome) ?></div>
            <div class="user-role">Função: Coordenador Pedagógico</div>
            <a href="/softgest_web/logout.php" class="btn-logout">🚪 Sair do Sistema</a>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <button class="btn-toggle-sidebar" onclick="toggleSidebar()">☰</button>
                <h3>💬 Mensagens</h3>
                <small>Sistema de comunicação interna</small>
            </div>
            <div class="user-info">
                <span class="welcome-text">Bem-vindo, <?= htmlspecialchars($usuario_nome) ?></span>
                <span class="badge-perfil">📋 Coordenador Pedagógico</span>
                <span class="time">
                    <i class="bi bi-clock"></i>
                    <span id="currentTime">00:00:00</span>
                </span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <div class="mensagens-container">
                
                <!-- Cabeçalho -->
                <div class="mensagens-header">
                    <div>
                        <h1>💬 Mensagens</h1>
                        <?php if ($totalNaoLidas > 0): ?>
                            <span class="badge-count" id="badgeCount"><?= $totalNaoLidas ?> não lidas</span>
                        <?php else: ?>
                            <span class="badge-count hidden" id="badgeCount">0 não lidas</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="mensagens.php" class="btn btn-secondary">
                            <span>📥</span> Caixa de Entrada
                        </a>
                        <a href="mensagens.php?acao=nova" class="btn btn-primary">
                            <span>✏️</span> Nova Mensagem
                        </a>
                        <a href="mensagens.php?filtro=publicas" class="btn btn-secondary">
                            <span>🌐</span> Públicas
                        </a>
                    </div>
                </div>

                <!-- Mensagens de Feedback -->
                <?php if ($mensagem_sucesso): ?>
                    <div class="alert alert-success">✅ <?= $mensagem_sucesso ?></div>
                <?php endif; ?>

                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger">❌ <?= $mensagem_erro ?></div>
                <?php endif; ?>

                <!-- Formulário de Nova Mensagem -->
                <?php if ($acao == 'nova'): ?>
                <div class="form-mensagem">
                    <h2 style="margin-top: 0; margin-bottom: 20px; color: #1a2332;">✏️ Nova Mensagem</h2>
                    <form action="mensagens.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="enviar">
                        
                        <div class="form-group">
                            <label for="tipo">Tipo de Mensagem</label>
                            <select name="tipo" id="tipo" class="form-control" onchange="toggleDestinatario(this.value)">
                                <option value="publica">🌐 Pública (todos veem)</option>
                                <option value="privada">🔒 Privada (apenas destinatário)</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="destinatario-group">
                            <label for="destinatario_id">Destinatário</label>
                            <select name="destinatario_id" id="destinatario_id" class="form-control">
                                <option value="">Selecione um usuário</option>
                                <?php foreach($usuarios as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>" <?= ($destinatario_id == $usuario['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($usuario['nome']) ?> (<?= $usuario['perfil'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="assunto">Assunto</label>
                            <input type="text" name="assunto" id="assunto" class="form-control" placeholder="Digite o assunto da mensagem" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="mensagem">Mensagem</label>
                            <textarea name="mensagem" id="mensagem" class="form-control" placeholder="Digite sua mensagem..." required></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">📤 Enviar Mensagem</button>
                            <a href="mensagens.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
                
                <script>
                function toggleDestinatario(tipo) {
                    const group = document.getElementById('destinatario-group');
                    if (tipo === 'publica') {
                        group.style.display = 'none';
                    } else {
                        group.style.display = 'block';
                    }
                }
                </script>
                <?php endif; ?>

                <!-- Visualizar Mensagem Específica -->
                <?php if ($mensagem_visualizar): ?>
                <div style="background: white; border-radius: 14px; padding: 25px; border: 1px solid #eef2f7; margin-bottom: 30px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
                        <div>
                            <h3 style="margin: 0;">📩 <?= htmlspecialchars($mensagem_visualizar['assunto']) ?></h3>
                            <div style="color: #94a3b8; font-size: 14px; margin-top: 5px;">
                                De: <strong><?= htmlspecialchars($mensagem_visualizar['remetente_nome']) ?></strong>
                                <?php if ($mensagem_visualizar['destinatario_nome']): ?>
                                    | Para: <strong><?= htmlspecialchars($mensagem_visualizar['destinatario_nome']) ?></strong>
                                <?php endif; ?>
                                | <?= date('d/m/Y H:i', strtotime($mensagem_visualizar['data_envio'])) ?>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <a href="mensagens.php" class="btn btn-secondary">← Voltar</a>
                            <?php if ($mensagem_visualizar['remetente_id'] != $usuario_id): ?>
                                <a href="mensagens.php?acao=nova&responder=<?= $mensagem_visualizar['id'] ?>" class="btn btn-primary">Responder</a>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="mensagem_id" value="<?= $mensagem_visualizar['id'] ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta mensagem?')">🗑️ Excluir</button>
                            </form>
                        </div>
                    </div>
                    
                    <div style="font-size: 16px; line-height: 1.6; padding: 15px 0; border-top: 1px solid #eef2f7; border-bottom: 1px solid #eef2f7;">
                        <?= nl2br(htmlspecialchars($mensagem_visualizar['mensagem'])) ?>
                    </div>
                    
                    <div style="margin-top: 15px; display: flex; gap: 15px; flex-wrap: wrap;">
                        <span class="badge bg-<?= getStatusBadge($mensagem_visualizar['status']) ?>">
                            <?= getStatusText($mensagem_visualizar['status']) ?>
                        </span>
                        <?php if ($mensagem_visualizar['total_anexos'] > 0): ?>
                            <span class="badge bg-info">📎 <?= $mensagem_visualizar['total_anexos'] ?> anexo(s)</span>
                        <?php endif; ?>
                        <?php if ($mensagem_visualizar['tipo'] == 'publica'): ?>
                            <span class="badge bg-primary">🌐 Pública</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tabs -->
                <?php if (!$acao == 'nova' && !$mensagem_visualizar): ?>
                <div class="tabs">
                    <a href="mensagens.php" class="tab <?= $filtro == 'todas' ? 'active' : '' ?>">📥 Todas</a>
                    <a href="mensagens.php?filtro=nao_lidas" class="tab <?= $filtro == 'nao_lidas' ? 'active' : '' ?>">
                        🔴 Não Lidas 
                        <span class="badge <?= $totalNaoLidas > 0 ? '' : 'hidden' ?>" id="tabBadge"><?= $totalNaoLidas ?></span>
                    </a>
                    <a href="mensagens.php?filtro=enviadas" class="tab <?= $filtro == 'enviadas' ? 'active' : '' ?>">📤 Enviadas (<?= $totalEnviadas ?>)</a>
                    <a href="mensagens.php?filtro=publicas" class="tab <?= $filtro == 'publicas' ? 'active' : '' ?>">🌐 Públicas</a>
                </div>
                
                <!-- Lista de Mensagens -->
                <?php if (empty($mensagens)): ?>
                    <div class="empty-state">
                        <span class="icon">📭</span>
                        <h3>Nenhuma mensagem encontrada</h3>
                        <p>Comece uma conversa enviando uma nova mensagem.</p>
                        <a href="mensagens.php?acao=nova" class="btn btn-primary" style="margin-top: 15px;">✏️ Nova Mensagem</a>
                    </div>
                <?php else: ?>
                    <?php foreach($mensagens as $msg): ?>
                        <?php 
                        $is_nao_lida = ($msg['tipo'] == 'privada' && $msg['destinatario_id'] == $usuario_id && $msg['status'] == 'nao_lida');
                        $is_publica = ($msg['tipo'] == 'publica');
                        ?>
                        <a href="mensagens.php?acao=ver&id=<?= $msg['id'] ?>" style="text-decoration: none; display: block;">
                            <div class="mensagem-card <?= $is_nao_lida ? 'nao-lida' : '' ?>">
                                <div class="header">
                                    <div>
                                        <span class="remetente">
                                            <?php if ($is_publica): ?>🌐<?php endif; ?>
                                            <?= htmlspecialchars($msg['remetente_nome'] ?? 'Sistema') ?>
                                            <?php if ($msg['remetente_perfil']): ?>
                                                <span class="perfil-badge"><?= $msg['remetente_perfil'] ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($is_nao_lida): ?>
                                            <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 8px;">NOVA</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="data"><?= formatarData($msg['data_envio']) ?></span>
                                </div>
                                <div class="assunto"><?= htmlspecialchars($msg['assunto']) ?></div>
                                <div class="mensagem-preview"><?= nl2br(htmlspecialchars(substr($msg['mensagem'], 0, 150))) ?></div>
                                <div class="footer">
                                    <div>
                                        <?php if ($msg['total_anexos'] > 0): ?>
                                            <span class="anexos-info">📎 <?= $msg['total_anexos'] ?> anexo(s)</span>
                                        <?php endif; ?>
                                        <?php if ($msg['destinatario_nome'] && $msg['tipo'] == 'privada'): ?>
                                            <span class="anexos-info" style="margin-left: 10px;">
                                                👤 Para: <?= htmlspecialchars($msg['destinatario_nome']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 13px; color: #94a3b8;">
                                        <?php if ($is_nao_lida): ?>
                                            <span style="color: #c9a84c;">Clique para ler ➜</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </main>
</div>

<script>
// ============================================
// SIDEBAR
// ============================================
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
}

// ============================================
// RELÓGIO
// ============================================
function updateDateTime() {
    const now = new Date();
    const time = now.toLocaleTimeString('pt-BR');
    document.getElementById('currentTime').textContent = time;
}
setInterval(updateDateTime, 1000);
updateDateTime();

// ============================================
// ATUALIZAR CONTAGEM DE MENSAGENS
// ============================================
function atualizarContador() {
    fetch('ajax_contador_mensagens.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.total > 0) {
                const badge = document.getElementById('msgBadge');
                const badgeCount = document.getElementById('badgeCount');
                const tabBadge = document.getElementById('tabBadge');
                
                if (badge) {
                    badge.textContent = data.total;
                    badge.style.display = 'inline';
                }
                if (badgeCount) {
                    badgeCount.textContent = data.total + ' não lidas';
                    badgeCount.classList.remove('hidden');
                }
                if (tabBadge) {
                    tabBadge.textContent = data.total;
                    tabBadge.classList.remove('hidden');
                }
                
                // Atualizar título da página
                document.title = '(' + data.total + ') Mensagens - <?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest') ?>';
            }
        })
        .catch(error => console.error('Erro ao atualizar contador:', error));
}

// Atualizar a cada 30 segundos
setInterval(atualizarContador, 30000);

// Atualizar quando a página ganha foco
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        atualizarContador();
    }
});

// ============================================
// INICIALIZAÇÃO
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('💬 Sistema de Mensagens inicializado');
    console.log('📩 Mensagens não lidas:', <?= $totalNaoLidas ?>);
    
    <?php if ($totalNaoLidas > 0): ?>
        // Mostrar notificação de mensagens não lidas
        setTimeout(function() {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #c9a84c;
                color: white;
                padding: 15px 25px;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(201, 168, 76, 0.4);
                z-index: 9999;
                font-weight: 600;
                animation: slideIn 0.5s ease;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            notification.innerHTML = `
                <span style="font-size: 24px;">📩</span>
                <div>
                    <div style="font-weight: 700;">Mensagens não lidas</div>
                    <div style="font-size: 13px; opacity: 0.9;">Você tem <?= $totalNaoLidas ?> mensagem(ns) não lida(s)</div>
                </div>
            `;
            notification.onclick = function() {
                window.location.href = 'mensagens.php?filtro=nao_lidas';
            };
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(() => notification.remove(), 500);
            }, 8000);
        }, 1500);
    <?php endif; ?>
});
</script>

<?php include_once $base_path . '/includes/footer.php'; ?>
</body>
</html>