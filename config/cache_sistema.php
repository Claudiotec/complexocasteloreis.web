<?php
// ============================================
// config/cache_sistema.php - Cache Local
// ============================================

class SistemaCache {
    private $cache_dir;
    private $tempo_cache = 3600; // 1 hora
    
    public function __construct() {
        $this->cache_dir = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/cache/';
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0777, true);
        }
    }
    
    // Salvar dados em cache
    public function salvar($chave, $dados, $tempo = null) {
        $tempo = $tempo ?? $this->tempo_cache;
        $arquivo = $this->cache_dir . md5($chave) . '.cache';
        $conteudo = [
            'dados' => $dados,
            'expiracao' => time() + $tempo,
            'criado_em' => date('Y-m-d H:i:s')
        ];
        return file_put_contents($arquivo, json_encode($conteudo));
    }
    
    // Buscar dados do cache
    public function buscar($chave) {
        $arquivo = $this->cache_dir . md5($chave) . '.cache';
        if (!file_exists($arquivo)) {
            return null;
        }
        
        $conteudo = json_decode(file_get_contents($arquivo), true);
        if (!$conteudo || $conteudo['expiracao'] < time()) {
            @unlink($arquivo);
            return null;
        }
        
        return $conteudo['dados'];
    }
    
    // Limpar cache
    public function limpar() {
        $arquivos = glob($this->cache_dir . '*.cache');
        foreach ($arquivos as $arquivo) {
            @unlink($arquivo);
        }
        return true;
    }
}
?>