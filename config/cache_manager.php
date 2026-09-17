<?php
// ============================================
// config/cache_manager.php - Gerenciador de Cache Avançado
// ============================================

class CacheManager {
    private $cacheDir;
    private $defaultTTL;
    private $stats = [];
    
    public function __construct($defaultTTL = 600) {
        $this->cacheDir = __DIR__ . '/../cache/';
        $this->defaultTTL = $defaultTTL;
        
        // Criar diretório de cache
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        
        // Limpar cache antigo automaticamente (a cada 100 requisições)
        if (rand(1, 100) === 1) {
            $this->cleanOldCache();
        }
    }
    
    /**
     * Salvar dados em cache
     */
    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?? $this->defaultTTL;
        $filename = $this->getFilename($key);
        
        $content = serialize([
            'time' => time(),
            'ttl' => $ttl,
            'data' => $data
        ]);
        
        return file_put_contents($filename, $content, LOCK_EX);
    }
    
    /**
     * Buscar dados do cache
     */
    public function get($key) {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            $this->stats['miss'] = ($this->stats['miss'] ?? 0) + 1;
            return null;
        }
        
        $content = @unserialize(file_get_contents($filename));
        
        if ($content === false) {
            unlink($filename);
            $this->stats['miss'] = ($this->stats['miss'] ?? 0) + 1;
            return null;
        }
        
        // Verificar se expirou
        if (time() - $content['time'] > $content['ttl']) {
            unlink($filename);
            $this->stats['miss'] = ($this->stats['miss'] ?? 0) + 1;
            return null;
        }
        
        $this->stats['hit'] = ($this->stats['hit'] ?? 0) + 1;
        return $content['data'];
    }
    
    /**
     * Buscar ou gerar cache
     */
    public function remember($key, $callback, $ttl = null) {
        $data = $this->get($key);
        
        if ($data === null) {
            $data = $callback();
            $this->set($key, $data, $ttl);
        }
        
        return $data;
    }
    
    /**
     * Limpar cache específico
     */
    public function clear($key = null) {
        if ($key) {
            $filename = $this->getFilename($key);
            if (file_exists($filename)) {
                unlink($filename);
                return true;
            }
            return false;
        }
        
        // Limpar todo o cache
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    /**
     * Limpar cache mais antigo que o TTL
     */
    public function cleanOldCache() {
        $files = glob($this->cacheDir . '*.cache');
        $now = time();
        $deleted = 0;
        
        foreach ($files as $file) {
            $content = @unserialize(file_get_contents($file));
            if ($content !== false) {
                if ($now - $content['time'] > $content['ttl']) {
                    unlink($file);
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Estatísticas do cache
     */
    public function getStats() {
        $files = glob($this->cacheDir . '*.cache');
        
        return [
            'hits' => $this->stats['hit'] ?? 0,
            'misses' => $this->stats['miss'] ?? 0,
            'files' => count($files),
            'hit_ratio' => $this->getHitRatio()
        ];
    }
    
    private function getHitRatio() {
        $hits = $this->stats['hit'] ?? 0;
        $misses = $this->stats['miss'] ?? 0;
        $total = $hits + $misses;
        
        if ($total === 0) {
            return 0;
        }
        
        return round(($hits / $total) * 100, 2);
    }
    
    private function getFilename($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }
}
?>