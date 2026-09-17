<?php
/**
 * Funções Globais - Softgest
 * 
 * @package Softgest
 * @subpackage Includes
 * @version 1.0.0
 */

/**
 * Verifica se o usuário tem permissão
 * 
 * @param string $modulo
 * @param string $acao
 * @return bool
 */

/**
 * Formata valor para moeda brasileira
 * 
 * @param float $valor
 * @return string
 */
function formatarMoeda($valor)
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Formata data para padrão brasileiro
 * 
 * @param string $data
 * @param string $formato
 * @return string
 */
function formatarData($data, $formato = 'd/m/Y')
{
    if (empty($data) || $data == '0000-00-00') {
        return '-';
    }
    
    $timestamp = strtotime($data);
    return date($formato, $timestamp);
}

/**
 * Obtém nome do mês
 * 
 * @param int $mes
 * @return string
 */
function getNomeMes($mes)
{
    $meses = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];
    
    return isset($meses[(int)$mes]) ? $meses[(int)$mes] : 'Mês inválido';
}

/**
 * Gera um UUID v4
 * 
 * @return string
 */
function gerarUUID()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

/**
 * Limpa string para evitar XSS
 * 
 * @param string $texto
 * @return string
 */
function limparTexto($texto)
{
    return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
}

/**
 * Calcula a idade a partir da data de nascimento
 * 
 * @param string $data_nascimento
 * @return int
 */
function calcularIdade($data_nascimento)
{
    $nascimento = new DateTime($data_nascimento);
    $hoje = new DateTime('now');
    $idade = $hoje->diff($nascimento);
    
    return $idade->y;
}

/**
 * Gera um slug a partir de uma string
 * 
 * @param string $texto
 * @return string
 */
function gerarSlug($texto)
{
    $texto = preg_replace('/[^a-zA-Z0-9]/', '-', $texto);
    $texto = preg_replace('/-+/', '-', $texto);
    $texto = trim($texto, '-');
    $texto = strtolower($texto);
    
    return $texto;
}

/**
 * Obtém o IP do usuário
 * 
 * @return string
 */
function getIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Gera um token CSRF
 * 
 * @return string
 */
function gerarCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida token CSRF
 * 
 * @param string $token
 * @return bool
 */
function validarCSRFToken($token)
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Registra log de atividade
 * 
 * @param string $acao
 * @param string $tabela
 * @param int $registro_id
 * @param string $descricao
 * @return bool
 */
function registrarLog($acao, $tabela, $registro_id, $descricao = '')
{
    global $conn;
    
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    $ip = getIP();
    $data_hora = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO logs (usuario_id, acao, tabela, registro_id, descricao, ip, data_hora) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ississi', $usuario_id, $acao, $tabela, $registro_id, $descricao, $ip, $data_hora);
    
    return $stmt->execute();
}

/**
 * Envia email
 * 
 * @param string $destinatario
 * @param string $assunto
 * @param string $mensagem
 * @param array $headers
 * @return bool
 */
function enviarEmail($destinatario, $assunto, $mensagem, $headers = [])
{
    // Configurações do mailer
    $headers_default = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . (defined('EMAIL_REMETENTE') ? EMAIL_REMETENTE : 'noreply@softgest.com.br')
    ];
    
    $headers = array_merge($headers_default, $headers);
    
    return mail($destinatario, $assunto, $mensagem, implode("\r\n", $headers));
}
?>