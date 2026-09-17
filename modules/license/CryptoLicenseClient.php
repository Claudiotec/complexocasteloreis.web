<?php
// modules/license/CryptoLicenseClient.php

class CryptoLicenseClient {
    private $api_url;
    private $master_key = "SOFTGEST_MASTER_KEY_2026";
    
    public function __construct($api_url = 'http://localhost:5000/api/licenca') {
        $this->api_url = $api_url;
    }
    
    /**
     * Gera chave do cliente baseada no email e empresa
     */
    public function gerarChaveCliente($email, $empresa = '') {
        $base = $email . '|' . $empresa . '|' . $this->master_key;
        return substr(hash('sha256', $base), 0, 32);
    }
    
    /**
     * Criptografa dados usando AES-256
     */
    public function criptografar($dados, $chave) {
        $method = 'aes-256-cbc';
        $key = hash('sha256', $chave, true);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        
        $dados_json = json_encode($dados);
        $cripto = openssl_encrypt($dados_json, $method, $key, 0, $iv);
        
        return base64_encode($iv . $cripto);
    }
    
    /**
     * Descriptografa dados usando AES-256
     */
    public function descriptografar($dados_cripto_b64, $chave) {
        $method = 'aes-256-cbc';
        $key = hash('sha256', $chave, true);
        
        $dados = base64_decode($dados_cripto_b64);
        $iv_size = openssl_cipher_iv_length($method);
        $iv = substr($dados, 0, $iv_size);
        $cripto = substr($dados, $iv_size);
        
        $decrypt = openssl_decrypt($cripto, $method, $key, 0, $iv);
        
        if ($decrypt === false) {
            return null;
        }
        
        return json_decode($decrypt, true);
    }
    
    /**
     * Gera chave de ativação
     */
    public function gerarChaveAtivacao($codigo, $chave_cliente) {
        $base = $codigo . '|' . $chave_cliente . '|' . $this->master_key;
        $chave = substr(hash('sha256', $base), 0, 16);
        return substr($chave, 0, 4) . '-' . substr($chave, 4, 4) . '-' . substr($chave, 8, 4) . '-' . substr($chave, 12, 4);
    }
    
    /**
     * Gerar licença criptografada
     */
    public function gerar($dados) {
        // Gerar chave do cliente
        $chave_cliente = $this->gerarChaveCliente($dados['cliente_email'], $dados['cliente_empresa'] ?? '');
        
        // Adicionar chave cliente aos dados
        $dados['chave_cliente'] = $chave_cliente;
        
        // Chamar API Python para gerar licença
        $ch = curl_init($this->api_url . '/gerar-crypto');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code != 200) {
            throw new Exception('Erro na API: ' . $http_code);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Validar licença com criptografia
     */
    public function validar($codigo, $chave_ativacao, $chave_cliente) {
        $ch = curl_init($this->api_url . '/validar-crypto');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'codigo' => $codigo,
            'chave_ativacao' => $chave_ativacao,
            'chave_cliente' => $chave_cliente
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code != 200) {
            throw new Exception('Erro na API: ' . $http_code);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Verifica se a chave corresponde à licença
     */
    public function verificarChave($codigo, $chave_ativacao, $email_cliente, $empresa_cliente = '') {
        $chave_cliente = $this->gerarChaveCliente($email_cliente, $empresa_cliente);
        $chave_esperada = $this->gerarChaveAtivacao($codigo, $chave_cliente);
        
        return $chave_ativacao === $chave_esperada;
    }
}