<?php
// ============================================================
// config/funcoes_envio.php - Funções de Envio (Email, WhatsApp, SMS)
// ============================================================

// ============================================================
// CONFIGURAÇÕES DE ENVIO
// ============================================================

// Configurações de Email (SMTP)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', 'seuemail@gmail.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'suasenha');
if (!defined('SMTP_FROM')) define('SMTP_FROM', 'seuemail@gmail.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'SoftGest Web');

// Configurações de WhatsApp
if (!defined('WHATSAPP_API_URL')) define('WHATSAPP_API_URL', 'https://api.whatsapp.com/send/');
if (!defined('WHATSAPP_API_TOKEN')) define('WHATSAPP_API_TOKEN', '');

// Configurações de SMS (Twilio)
if (!defined('TWILIO_ACCOUNT_SID')) define('TWILIO_ACCOUNT_SID', '');
if (!defined('TWILIO_AUTH_TOKEN')) define('TWILIO_AUTH_TOKEN', '');
if (!defined('TWILIO_PHONE_NUMBER')) define('TWILIO_PHONE_NUMBER', '');

// ============================================================
// FUNÇÃO: ENVIAR EMAIL
// ============================================================

/**
 * Envia um email usando PHPMailer ou mail() nativo
 * @param string $destinatario Email do destinatário
 * @param string $assunto Assunto do email
 * @param string $mensagem Corpo do email (HTML)
 * @param array $anexos Lista de anexos (caminhos dos arquivos)
 * @param string $destinatario_nome Nome do destinatário (opcional)
 * @return bool
 */
function enviarEmail($destinatario, $assunto, $mensagem, $anexos = [], $destinatario_nome = '') {
    // Verificar se PHPMailer está disponível
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return enviarEmailPHPMailer($destinatario, $assunto, $mensagem, $anexos, $destinatario_nome);
    } else {
        return enviarEmailNativo($destinatario, $assunto, $mensagem, $anexos, $destinatario_nome);
    }
}

/**
 * Envia email usando PHPMailer (recomendado)
 */
function enviarEmailPHPMailer($destinatario, $assunto, $mensagem, $anexos = [], $destinatario_nome = '') {
    try {
        // Carregar PHPMailer se não estiver carregado
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configurações do servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Remetente
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);
        
        // Destinatário
        if (!empty($destinatario_nome)) {
            $mail->addAddress($destinatario, $destinatario_nome);
        } else {
            $mail->addAddress($destinatario);
        }
        
        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $mensagem;
        $mail->AltBody = strip_tags($mensagem);
        
        // Anexos
        foreach ($anexos as $anexo) {
            if (file_exists($anexo)) {
                $mail->addAttachment($anexo);
            }
        }
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erro ao enviar email (PHPMailer): " . $e->getMessage());
        return false;
    }
}

/**
 * Envia email usando mail() nativo (fallback)
 */
function enviarEmailNativo($destinatario, $assunto, $mensagem, $anexos = [], $destinatario_nome = '') {
    try {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
        $headers .= "Reply-To: " . SMTP_FROM . "\r\n";
        
        if (!empty($destinatario_nome)) {
            $destinatario = $destinatario_nome . " <" . $destinatario . ">";
        }
        
        // Processar anexos (apenas se não houver muitos)
        if (!empty($anexos)) {
            $boundary = md5(time());
            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            
            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $mensagem . "\r\n\r\n";
            
            foreach ($anexos as $anexo) {
                if (file_exists($anexo)) {
                    $filename = basename($anexo);
                    $file_content = chunk_split(base64_encode(file_get_contents($anexo)));
                    $file_type = mime_content_type($anexo) ?: 'application/octet-stream';
                    
                    $body .= "--$boundary\r\n";
                    $body .= "Content-Type: $file_type; name=\"$filename\"\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"$filename\"\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                    $body .= $file_content . "\r\n";
                }
            }
            
            $body .= "--$boundary--";
        } else {
            $body = $mensagem;
        }
        
        return mail($destinatario, $assunto, $body, $headers);
        
    } catch (Exception $e) {
        error_log("Erro ao enviar email (nativo): " . $e->getMessage());
        return false;
    }
}

// ============================================================
// FUNÇÃO: ENVIAR WHATSAPP
// ============================================================

/**
 * Envia mensagem via WhatsApp
 * @param string $numero Número do telefone
 * @param string $mensagem Mensagem a ser enviada
 * @param string $tipo Tipo de envio: 'link', 'api' ou 'qr'
 * @return string|bool URL do WhatsApp ou true/false para API
 */
function enviarWhatsApp($numero, $mensagem, $tipo = 'link') {
    $numero = formatarNumeroWhatsApp($numero);
    
    switch ($tipo) {
        case 'api':
            return enviarWhatsAppAPI($numero, $mensagem);
        case 'qr':
            return gerarQRCodeWhatsApp($numero, $mensagem);
        default:
            return gerarLinkWhatsApp($numero, $mensagem);
    }
}

/**
 * Formata número para WhatsApp
 */
function formatarNumeroWhatsApp($numero) {
    // Remove todos os caracteres não numéricos
    $numero = preg_replace('/[^0-9]/', '', $numero);
    
    // Adiciona código do país se necessário (Brasil = 55)
    if (strlen($numero) == 10 || strlen($numero) == 11) {
        $numero = '55' . $numero;
    }
    
    return $numero;
}

/**
 * Gera link do WhatsApp
 */
function gerarLinkWhatsApp($numero, $mensagem) {
    $numero = formatarNumeroWhatsApp($numero);
    return 'https://wa.me/' . $numero . '?text=' . urlencode($mensagem);
}

/**
 * Envia mensagem via API do WhatsApp (exemplo com WhatsApp Business API)
 */
function enviarWhatsAppAPI($numero, $mensagem) {
    $numero = formatarNumeroWhatsApp($numero);
    
    // Exemplo com API (personalize conforme seu provedor)
    $api_url = WHATSAPP_API_URL;
    $api_token = WHATSAPP_API_TOKEN;
    
    $data = [
        'phone' => $numero,
        'message' => $mensagem
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_token
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        return true;
    }
    
    error_log("Erro ao enviar WhatsApp: " . $response);
    return false;
}

/**
 * Gera QR Code para WhatsApp (via API)
 */
function gerarQRCodeWhatsApp($numero, $mensagem) {
    $numero = formatarNumeroWhatsApp($numero);
    // URL para gerar QR Code do WhatsApp
    return 'https://api.whatsapp.com/send/?phone=' . $numero . '&text=' . urlencode($mensagem);
}

// ============================================================
// FUNÇÃO: ENVIAR SMS
// ============================================================

/**
 * Envia SMS usando API (Twilio ou similar)
 * @param string $numero Número do telefone
 * @param string $mensagem Mensagem a ser enviada
 * @param string $provedor Provedor de SMS (twilio, vonage, etc.)
 * @return bool|array
 */
function enviarSMS($numero, $mensagem, $provedor = 'twilio') {
    $numero = formatarNumeroSMS($numero);
    
    switch ($provedor) {
        case 'twilio':
            return enviarSMSTwilio($numero, $mensagem);
        case 'vonage':
            return enviarSMSVonage($numero, $mensagem);
        default:
            return enviarSMSTwilio($numero, $mensagem);
    }
}

/**
 * Formata número para SMS
 */
function formatarNumeroSMS($numero) {
    // Remove todos os caracteres não numéricos
    $numero = preg_replace('/[^0-9]/', '', $numero);
    
    // Adiciona código do país se necessário (Brasil = 55)
    if (strlen($numero) == 10 || strlen($numero) == 11) {
        $numero = '55' . $numero;
    }
    
    return '+' . $numero;
}

/**
 * Envia SMS via Twilio
 */
function enviarSMSTwilio($numero, $mensagem) {
    if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
        error_log("Twilio não configurado");
        return false;
    }
    
    $numero = formatarNumeroSMS($numero);
    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";
    
    $data = [
        'To' => $numero,
        'From' => TWILIO_PHONE_NUMBER,
        'Body' => $mensagem
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 || $http_code == 201) {
        return json_decode($response, true);
    }
    
    error_log("Erro ao enviar SMS (Twilio): " . $response);
    return false;
}

/**
 * Envia SMS via Vonage (antiga Nexmo)
 */
function enviarSMSVonage($numero, $mensagem) {
    // Configuração para Vonage
    $api_key = defined('VONAGE_API_KEY') ? VONAGE_API_KEY : '';
    $api_secret = defined('VONAGE_API_SECRET') ? VONAGE_API_SECRET : '';
    $from = defined('VONAGE_FROM') ? VONAGE_FROM : 'SoftGest';
    
    if (empty($api_key) || empty($api_secret)) {
        error_log("Vonage não configurado");
        return false;
    }
    
    $numero = formatarNumeroSMS($numero);
    
    $data = [
        'api_key' => $api_key,
        'api_secret' => $api_secret,
        'to' => $numero,
        'from' => $from,
        'text' => $mensagem
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://rest.nexmo.com/sms/json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['messages'][0]['status']) && $result['messages'][0]['status'] == '0') {
            return true;
        }
    }
    
    error_log("Erro ao enviar SMS (Vonage): " . $response);
    return false;
}

// ============================================================
// FUNÇÃO: PROCESSAR VARIÁVEIS NO TEXTO
// ============================================================

/**
 * Processa variáveis no texto (substitui {{variavel}} pelos valores)
 * @param string $texto Texto com variáveis
 * @param array $dados Array com os valores
 * @return string
 */
function processarVariaveis($texto, $dados) {
    foreach ($dados as $chave => $valor) {
        $texto = str_replace('{{' . $chave . '}}', $valor, $texto);
    }
    return $texto;
}

// ============================================================
// FUNÇÕES AUXILIARES PARA TEMPLATES
// ============================================================

/**
 * Carrega um template de email
 */
function carregarTemplateEmail($template, $dados = []) {
    $caminho = __DIR__ . '/../templates/emails/' . $template . '.php';
    
    if (!file_exists($caminho)) {
        return "<p>Template não encontrado: $template</p>";
    }
    
    ob_start();
    extract($dados);
    include $caminho;
    return ob_get_clean();
}

/**
 * Envia email usando template
 */
function enviarEmailTemplate($destinatario, $template, $dados = [], $assunto = '', $anexos = []) {
    $mensagem = carregarTemplateEmail($template, $dados);
    
    if (empty($assunto)) {
        $assunto = 'Mensagem do ' . SITE_NAME;
    }
    
    return enviarEmail($destinatario, $assunto, $mensagem, $anexos);
}

// ============================================================
// FUNÇÃO: REGISTRAR LOG DE ENVIO
// ============================================================

/**
 * Registra log de envio de mensagem
 */
function logEnvio($tipo, $destinatario, $assunto, $status, $erro = '') {
    $log = [
        'data' => date('Y-m-d H:i:s'),
        'tipo' => $tipo, // email, whatsapp, sms
        'destinatario' => $destinatario,
        'assunto' => $assunto,
        'status' => $status ? 'sucesso' : 'falha',
        'erro' => $erro,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'usuario_id' => $_SESSION['usuario_id'] ?? ''
    ];
    
    $log_file = __DIR__ . '/../logs/envios_' . date('Y-m') . '.log';
    @file_put_contents($log_file, json_encode($log) . "\n", FILE_APPEND);
}

// ============================================================
// FUNÇÃO: ENVIAR NOTIFICAÇÃO (MULTI-CANAL)
// ============================================================

/**
 * Envia notificação via múltiplos canais
 * @param string $destinatario Email ou telefone
 * @param string $mensagem Mensagem a ser enviada
 * @param array $canais Canais a serem usados ['email', 'whatsapp', 'sms']
 * @param string $assunto Assunto (para email)
 * @return array
 */
function enviarNotificacao($destinatario, $mensagem, $canais = ['email'], $assunto = '') {
    $resultados = [];
    
    foreach ($canais as $canal) {
        switch ($canal) {
            case 'email':
                $resultados['email'] = enviarEmail($destinatario, $assunto ?: 'Notificação', $mensagem);
                break;
                
            case 'whatsapp':
                $resultados['whatsapp'] = enviarWhatsApp($destinatario, $mensagem);
                break;
                
            case 'sms':
                $resultados['sms'] = enviarSMS($destinatario, $mensagem);
                break;
        }
    }
    
    return $resultados;
}

// ============================================================
// FUNÇÃO: ENVIAR EMAIL COM ANEXO
// ============================================================

/**
 * Envia email com anexo
 */
function enviarEmailComAnexo($destinatario, $assunto, $mensagem, $arquivo_anexo, $nome_anexo = '') {
    if (empty($nome_anexo)) {
        $nome_anexo = basename($arquivo_anexo);
    }
    
    return enviarEmail($destinatario, $assunto, $mensagem, [$arquivo_anexo]);
}

// ============================================================
// FUNÇÃO: ENVIAR EMAIL PARA MÚLTIPLOS DESTINATÁRIOS
// ============================================================

/**
 * Envia email para múltiplos destinatários
 */
function enviarEmailMultiplos($destinatarios, $assunto, $mensagem, $anexos = []) {
    $resultados = [];
    
    foreach ($destinatarios as $destinatario) {
        if (is_array($destinatario)) {
            $email = $destinatario['email'] ?? '';
            $nome = $destinatario['nome'] ?? '';
        } else {
            $email = $destinatario;
            $nome = '';
        }
        
        if (!empty($email)) {
            $resultados[$email] = enviarEmail($email, $assunto, $mensagem, $anexos, $nome);
        }
    }
    
    return $resultados;
}