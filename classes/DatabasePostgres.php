<?php
// classes/DatabasePostgres.php
// Classe para gerenciar conexão com PostgreSQL

class DatabasePostgres {
    private static $instance = null;
    private $pdo;
    private $connected = false;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            $host = DB_HOST;
            $port = DB_PORT;
            $dbname = DB_NAME;
            $user = DB_USER;
            $pass = DB_PASS;
            
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
            
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            
            $this->connected = true;
            
        } catch (PDOException $e) {
            $this->connected = false;
            error_log("❌ Database Error: " . $e->getMessage());
            throw new Exception("Erro de conexão com o banco de dados");
        }
    }
    
    public function getConnection() {
        if (!$this->connected) {
            $this->connect();
        }
        return $this->pdo;
    }
    
    public function isConnected() {
        return $this->connected;
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("❌ Query Error: " . $e->getMessage());
            error_log("❌ SQL: " . $sql);
            return false;
        }
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : null;
    }
    
    public function insert($table, $data) {
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
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("❌ Insert Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function update($table, $data, $where, $whereParams = []) {
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
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("❌ Update Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($table, $where, $params = []) {
        try {
            $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("❌ Delete Error: " . $e->getMessage());
            return false;
        }
    }
}