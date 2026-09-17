<?php
// modules/license/LicenseClient.php

class LicenseClient {
    private $api_url;
    private $timeout = 30;
    
    public function __construct($api_url = 'http://localhost:5000/api/licenca') {
        $this->api_url = $api_url;
    }
    
    /**
     * Faz requisição para a API
     */
    private function request($endpoint, $method = 'POST', $data = null) {
        $url = $this->api_url . $endpoint;
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Erro na API: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('API retornou erro ' . $http_code);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Validar licença
     */
    public function validar($codigo, $hardware_id = null, $dominio = null) {
        return $this->request('/validar', 'POST', [
            'codigo' => $codigo,
            'hardware_id' => $hardware_id,
            'dominio' => $dominio
        ]);
    }
    
    /**
     * Ativar licença
     */
    public function ativar($codigo, $chave_ativacao, $hardware_id = null) {
        return $this->request('/ativar', 'POST', [
            'codigo' => $codigo,
            'chave_ativacao' => $chave_ativacao,
            'hardware_id' => $hardware_id
        ]);
    }
    
    /**
     * Gerar nova licença (apenas admin)
     */
    public function gerar($dados) {
        return $this->request('/gerar', 'POST', $dados);
    }
    
    /**
     * Listar licenças (apenas admin)
     */
    public function listar() {
        return $this->request('/listar', 'GET');
    }
    
    /**
     * Estatísticas (apenas admin)
     */
    public function estatisticas() {
        return $this->request('/estatisticas', 'GET');
    }
}