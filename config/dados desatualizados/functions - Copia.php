<?php
// ===== FUNÇÕES DE ENVIO =====

/**
 * Envia um email usando PHPMailer
 */
function enviarEmail($destinatario, $assunto, $mensagem, $anexos = []) {
    // Configuração do servidor SMTP (exemplo com Gmail)
    $smtp_host = 'smtp.gmail.com';  // Altere para seu servidor
    $smtp_port = 587;
    $smtp_user = 'seuemail@gmail.com';  // Seu email
    $smtp_pass = 'suasenha';  // Sua senha
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <" . $smtp_user . ">\r\n";
    $headers .= "Reply-To: " . $smtp_user . "\r\n";
    
    return mail($destinatario, $assunto, $mensagem, $headers);
}

/**
 * Envia mensagem via WhatsApp (usando API)
 */
function enviarWhatsApp($numero, $mensagem) {
    // Remove formatação do número
    $numero = preg_replace('/[^0-9]/', '', $numero);
    
    // URL da API do WhatsApp (exemplo com WhatsApp Business API)
    $api_url = 'https://api.whatsapp.com/send/?phone=' . $numero . '&text=' . urlencode($mensagem);
    
    // Salva o link para redirecionar
    return $api_url;
}

/**
 * Envia SMS (usando API de SMS)
 */
function enviarSMS($numero, $mensagem) {
    // Configuração da API de SMS (exemplo com Twilio)
    $account_sid = 'seu_account_sid';
    $auth_token = 'seu_auth_token';
    $twilio_number = '+5511999999999';
    
    // Remove formatação do número
    $numero = preg_replace('/[^0-9]/', '', $numero);
    if (strlen($numero) == 10 || strlen($numero) == 11) {
        $numero = '55' . $numero;
    }
    
    // URL para enviar SMS via Twilio
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
 * Processa variáveis no texto
 */
function processarVariaveis($texto, $dados) {
    foreach ($dados as $chave => $valor) {
        $texto = str_replace('{{' . $chave . '}}', $valor, $texto);
    }
    return $texto;
}
?>