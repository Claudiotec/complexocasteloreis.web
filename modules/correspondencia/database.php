<?php
// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'softgest_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configurações do sistema
define('SITE_NAME', 'SoftGest Web');
define('SITE_URL', 'http://localhost/softgest_web/');
define('TIMEZONE', 'America/Sao_Paulo');

// Definir timezone
date_default_timezone_set(TIMEZONE);

// Conexão com o banco de dados
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    die("❌ Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Iniciar sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Função para verificar login
function isLoggedIn() {
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

// Função para redirecionar
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit;
}

// Função para exibir mensagens
function showMessage($type, $message) {
    $types = [
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️'
    ];
    $icon = isset($types[$type]) ? $types[$type] : 'ℹ️';
    return "<div class='alert alert-{$type}'>{$icon} {$message}</div>";
}

// ===== FUNÇÕES DA EMPRESA =====
function getEmpresa() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
        $empresa = $stmt->fetch();
        if (!$empresa) {
            $stmt = $pdo->prepare("INSERT INTO empresa (razao_social, nome_fantasia, cnpj) VALUES (?, ?, ?)");
            $stmt->execute(['SoftGest Sistemas Ltda', 'SoftGest Web', '00.000.000/0001-00']);
            $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
            $empresa = $stmt->fetch();
        }
        return $empresa;
    } catch(PDOException $e) {
        return null;
    }
}

function updateEmpresa($dados) {
    global $pdo;
    try {
        $sql = "UPDATE empresa SET 
            razao_social = ?, 
            nome_fantasia = ?, 
            cnpj = ?, 
            inscricao_estadual = ?, 
            inscricao_municipal = ?, 
            endereco = ?, 
            numero = ?, 
            complemento = ?, 
            bairro = ?, 
            cidade = ?, 
            estado = ?, 
            cep = ?, 
            telefone = ?, 
            celular = ?, 
            email = ?, 
            site = ? 
            WHERE id = 1";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $dados['razao_social'],
            $dados['nome_fantasia'],
            $dados['cnpj'],
            $dados['inscricao_estadual'],
            $dados['inscricao_municipal'],
            $dados['endereco'],
            $dados['numero'],
            $dados['complemento'],
            $dados['bairro'],
            $dados['cidade'],
            $dados['estado'],
            $dados['cep'],
            $dados['telefone'],
            $dados['celular'],
            $dados['email'],
            $dados['site']
        ]);
    } catch(PDOException $e) {
        return false;
    }
}

// ===== FUNÇÕES DE ENVIO =====

/**
 * Envia um email
 */
function enviarEmail($destinatario, $assunto, $mensagem, $anexos = []) {
    // Configuração do servidor SMTP (exemplo com Gmail)
    $smtp_user = 'seuemail@gmail.com';  // ALTERE PARA SEU EMAIL
    $smtp_pass = 'suasenha';             // ALTERE PARA SUA SENHA
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <" . $smtp_user . ">\r\n";
    $headers .= "Reply-To: " . $smtp_user . "\r\n";
    
    return mail($destinatario, $assunto, $mensagem, $headers);
}

/**
 * Gera link do WhatsApp
 */
function gerarLinkWhatsApp($numero, $mensagem) {
    $numero = preg_replace('/[^0-9]/', '', $numero);
    if (strlen($numero) == 10 || strlen($numero) == 11) {
        $numero = '55' . $numero;
    }
    return 'https://wa.me/' . $numero . '?text=' . urlencode($mensagem);
}

/**
 * Envia SMS (usando API Twilio)
 */
function enviarSMS($numero, $mensagem) {
    // Configuração da API de SMS (exemplo com Twilio)
    $account_sid = 'seu_account_sid';     // ALTERE
    $auth_token = 'seu_auth_token';       // ALTERE
    $twilio_number = '+5511999999999';    // ALTERE
    
    $numero = preg_replace('/[^0-9]/', '', $numero);
    if (strlen($numero) == 10 || strlen($numero) == 11) {
        $numero = '55' . $numero;
    }
    
    $url = "https://api.twilio.com/2010-04-01/Accounts/$account_sid/Messages.json";
    
    $data = [
        'To' => '+' . $numero,
        'From' => $twilio_number,
        'Body' => $mensagem
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, "$account_sid:$auth_token");
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Processa variáveis no texto
 */
function processarVariaveis($texto, $dados) {
    foreach ($dados as $chave => $valor) {
        $texto = str_replace('{{' . $chave . '}}', $valor, $texto);
    }
    return $texto;
}
?>