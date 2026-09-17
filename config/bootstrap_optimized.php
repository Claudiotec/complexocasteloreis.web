<?php
// ============================================
// config/bootstrap_optimized.php
// ============================================

// ===== 1. CARREGAR CONFIGURAÇÃO EXISTENTE =====
// Usar caminho absoluto para garantir
$databasePath = __DIR__ . '/database.php';

if (file_exists($databasePath)) {
    require_once $databasePath;
} else {
    die("❌ Arquivo database.php não encontrado em: " . $databasePath);
}

// ===== 2. VERIFICAR SE A FUNÇÃO CONECTAR BANCO EXISTE =====
if (!function_exists('conectarBanco')) {
    // Criar função de fallback
    function conectarBanco() {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=softgest_db;charset=utf8mb4",
                "root",
                "Claudtec",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            return $pdo;
        } catch (PDOException $e) {
            die("❌ Erro de conexão: " . $e->getMessage());
        }
    }
}

// ===== 3. GARANTIR QUE $pdo EXISTE =====
if (!isset($pdo) || !$pdo) {
    try {
        $pdo = conectarBanco();
    } catch (Exception $e) {
        die("❌ Erro ao conectar: " . $e->getMessage());
    }
}

// ===== 4. COMPRESSÃO DE SAÍDA =====
if (!ini_get('zlib.output_compression')) {
    ini_set('zlib.output_compression', 'On');
    ini_set('zlib.output_compression_level', '6');
}

// ===== 5. CACHE DE SESSÃO =====
ini_set('session.cache_expire', 180);
ini_set('session.cache_limiter', 'private');
ini_set('session.gc_maxlifetime', 86400);

// ===== 6. LIMITE DE MEMÓRIA =====
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);

// ===== 7. CARREGAR CACHE MANAGER =====
$cacheManagerPath = __DIR__ . '/cache_manager.php';
if (file_exists($cacheManagerPath)) {
    require_once $cacheManagerPath;
} else {
    // Criar classe CacheManager simples se não existir
    if (!class_exists('CacheManager')) {
        class CacheManager {
            private $cacheDir;
            private $defaultTTL;
            
            public function __construct($defaultTTL = 600) {
                $this->cacheDir = __DIR__ . '/../cache/';
                $this->defaultTTL = $defaultTTL;
                if (!is_dir($this->cacheDir)) {
                    mkdir($this->cacheDir, 0777, true);
                }
            }
            
            public function remember($key, $callback, $ttl = null) {
                $data = $this->get($key);
                if ($data === null) {
                    $data = $callback();
                    $this->set($key, $data, $ttl);
                }
                return $data;
            }
            
            public function get($key) {
                $file = $this->cacheDir . md5($key) . '.cache';
                if (!file_exists($file)) return null;
                $content = @unserialize(file_get_contents($file));
                if ($content === false) return null;
                if (time() - $content['time'] > ($content['ttl'] ?? $this->defaultTTL)) {
                    unlink($file);
                    return null;
                }
                return $content['data'];
            }
            
            public function set($key, $data, $ttl = null) {
                $ttl = $ttl ?? $this->defaultTTL;
                $file = $this->cacheDir . md5($key) . '.cache';
                file_put_contents($file, serialize(['time' => time(), 'ttl' => $ttl, 'data' => $data]));
            }
            
            public function clear($key) {
                $file = $this->cacheDir . md5($key) . '.cache';
                if (file_exists($file)) unlink($file);
            }
            
            public function clearAll() {
                $files = glob($this->cacheDir . '*.cache');
                foreach ($files as $file) {
                    unlink($file);
                }
            }
        }
    }
}

// ===== 8. INICIALIZAR CACHE GLOBAL =====
$GLOBALS['cache'] = new CacheManager(600);

// ===== 9. FUNÇÃO PARA CARREGAR DADOS COM CACHE =====
if (!function_exists('getCachedData')) {
    function getCachedData($key, $callback, $ttl = 600) {
        $cache = $GLOBALS['cache'];
        $data = $cache->get($key);
        if ($data === null) {
            $data = $callback();
            $cache->set($key, $data, $ttl);
        }
        return $data;
    }
}

// ===== 10. FUNÇÃO PARA LIMPAR CACHE =====
if (!function_exists('clearCache')) {
    function clearCache($key = null) {
        $cache = $GLOBALS['cache'];
        if ($key) {
            $cache->clear($key);
        } else {
            $cache->clearAll();
        }
    }
}

// ===== 11. VERIFICAR SE É REDE LENTA =====
if (!function_exists('isSlowNetwork')) {
    function isSlowNetwork() {
        if (isset($_COOKIE['slow_network'])) {
            return $_COOKIE['slow_network'] === 'true';
        }
        return false;
    }
}

// ===== 12. FUNÇÃO PARA TEMPO DE CARREGAMENTO =====
if (!function_exists('getLoadTime')) {
    function getLoadTime() {
        if (!isset($GLOBALS['start_time'])) {
            return 0;
        }
        return round((microtime(true) - $GLOBALS['start_time']) * 1000, 2);
    }
}

// ===== 13. REGISTRAR START TIME =====
$GLOBALS['start_time'] = microtime(true);

// ===== 14. DEFINIR CONSTANTE DE OTIMIZAÇÃO =====
if (!defined('OPTIMIZATION_ENABLED')) {
    define('OPTIMIZATION_ENABLED', true);
}

// ===== 15. LOG DE PERFORMANCE =====
if (!function_exists('logPerformance')) {
    function logPerformance($message) {
        $logFile = __DIR__ . '/../logs/performance.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }
}