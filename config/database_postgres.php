<?php
// config/database_postgres.php
// Configuração para PostgreSQL (Neon.tech)

// ============================================
// CONFIGURAÇÕES DO BANCO POSTGRESQL
// ============================================

// String de conexão fornecida
$db_url = "postgresql://neondb_owner:npg_gRkKHXNJAI40@ep-holy-resonance-atm1ick2-pooler.c-9.us-east-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require";

// Extrair informações da string de conexão
function parsePostgresUrl($url) {
    $parts = parse_url($url);
    
    return [
        'host' => $parts['host'] ?? '',
        'port' => $parts['port'] ?? 5432,
        'user' => $parts['user'] ?? '',
        'password' => $parts['pass'] ?? '',
        'dbname' => ltrim($parts['path'] ?? '', '/'),
        'sslmode' => 'require'
    ];
}

$db_config = parsePostgresUrl($db_url);

// Configurações do sistema
define('DB_HOST', $db_config['host']);
define('DB_PORT', $db_config['port']);
define('DB_NAME', $db_config['dbname']);
define('DB_USER', $db_config['user']);
define('DB_PASS', $db_config['password']);
define('DB_SSL_MODE', $db_config['sslmode']);

// ============================================
// CONEXÃO COM POSTGRESQL VIA PDO
// ============================================

try {
    // DSN para PostgreSQL
    $dsn = sprintf(
        "pgsql:host=%s;port=%s;dbname=%s;sslmode=%s",
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_SSL_MODE
    );
    
    // Opções PDO
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 30,
    ];
    
    // Conectar
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Definir timezone
    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
    
    // Testar conexão
    $stmt = $pdo->query("SELECT version() as version");
    $version = $stmt->fetch();
    
    // Definir constante para saber que está usando PostgreSQL
    define('DB_DRIVER', 'pgsql');
    define('DB_CONNECTED', true);
    
    // Log de conexão (opcional)
    error_log("✅ Conectado ao PostgreSQL: " . $version['version']);
    
} catch (PDOException $e) {
    // Erro na conexão
    define('DB_CONNECTED', false);
    
    // Log do erro
    error_log("❌ Erro PostgreSQL: " . $e->getMessage());
    
    // Se for erro de SSL, tentar sem SSL
    if (strpos($e->getMessage(), 'SSL') !== false) {
        try {
            $dsn = sprintf(
                "pgsql:host=%s;port=%s;dbname=%s",
                DB_HOST,
                DB_PORT,
                DB_NAME
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            define('DB_CONNECTED', true);
            error_log("✅ Conectado ao PostgreSQL (sem SSL)");
        } catch (PDOException $e2) {
            error_log("❌ Erro PostgreSQL (sem SSL): " . $e2->getMessage());
            die("Erro de conexão com o banco de dados. Verifique os logs.");
        }
    } else {
        die("Erro de conexão com o banco de dados. Verifique os logs.");
    }
}

// ============================================
// FUNÇÕES AUXILIARES PARA POSTGRESQL
// ============================================

/**
 * Executa uma consulta SQL
 */
function dbQuery($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("❌ Query Error: " . $e->getMessage());
        error_log("❌ SQL: " . $sql);
        return false;
    }
}

/**
 * Busca todos os registros
 */
function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    if ($stmt) {
        return $stmt->fetchAll();
    }
    return [];
}

/**
 * Busca um registro
 */
function dbFetchOne($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    if ($stmt) {
        return $stmt->fetch();
    }
    return null;
}

/**
 * Insere um registro
 */
function dbInsert($table, $data) {
    global $pdo;
    try {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) {
            return ":" . $field;
        }, $fields);
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) RETURNING id",
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("❌ Insert Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Atualiza um registro
 */
function dbUpdate($table, $data, $where, $whereParams = []) {
    global $pdo;
    try {
        $set = [];
        foreach ($data as $field => $value) {
            $set[] = "$field = :$field";
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $set),
            $where
        );
        
        $params = array_merge($data, $whereParams);
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("❌ Update Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Deleta um registro
 */
function dbDelete($table, $where, $params = []) {
    global $pdo;
    try {
        $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("❌ Delete Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se uma tabela existe
 */
function dbTableExists($table) {
    global $pdo;
    try {
        $sql = "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Obtém a estrutura de uma tabela
 */
function dbTableStructure($table) {
    global $pdo;
    try {
        $sql = "SELECT column_name, data_type, is_nullable, column_default 
                FROM information_schema.columns 
                WHERE table_name = ? 
                ORDER BY ordinal_position";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

echo "✅ Banco de dados PostgreSQL configurado com sucesso!\n";
?>