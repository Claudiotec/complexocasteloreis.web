<?php
/**
 * Configuração do Banco de Dados - Módulo Pedagógico
 * Estilo SOFTGEST
 */

class DatabaseConfig {
    private static $instance = null;
    private $conn;
    
    private $host = 'localhost';
    private $dbname = 'softgest';
    private $username = 'root';
    private $password = '';
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Erro na conexão: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function execute($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Erro SQL: " . $e->getMessage());
            return false;
        }
    }
    
    public function fetchAll($query, $params = []) {
        $stmt = $this->execute($query, $params);
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }
    
    public function fetchOne($query, $params = []) {
        $stmt = $this->execute($query, $params);
        if ($stmt) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        
        $query = "INSERT INTO {$table} (" . implode(',', $fields) . ") VALUES ({$placeholders})";
        $stmt = $this->execute($query, $values);
        
        if ($stmt) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $fields = array_keys($data);
        $values = array_values($data);
        
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        $query = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $params = array_merge($values, $whereParams);
        $stmt = $this->execute($query, $params);
        
        return $stmt ? $stmt->rowCount() : false;
    }
    
    public function delete($table, $where, $params = []) {
        $query = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->execute($query, $params);
        return $stmt ? $stmt->rowCount() : false;
    }
}