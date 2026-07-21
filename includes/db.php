<?php
/**
 * Database Connection and Helper Functions
 * 
 * This file handles database connections and provides helper functions
 * for common database operations.
 */

// Load database configuration
require_once __DIR__ . '/../config/database.php';

class Database {
    private static $instance = null;
    private $connection;
    
    /**
     * Get database connection instance (Singleton pattern)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false
                ]
            );
        } catch (PDOException $e) {
            // Log the error and show a user-friendly message
            error_log("Database Connection Error: " . $e->getMessage());
            die("Unable to connect to the database. Please try again later.");
        }
    }
    
    /**
     * Get the PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Execute a query with parameters
     */
    public function query($sql, $params = []) {
        try {
            error_log("Preparing SQL: $sql");
            error_log("With params: " . print_r($params, true));
            
            $stmt = $this->connection->prepare($sql);
            if ($stmt === false) {
                $error = $this->connection->errorInfo();
                throw new PDOException("Prepare failed: " . ($error[2] ?? 'Unknown error'));
            }
            
            // If no parameters, just execute
            if (empty($params)) {
                $stmt->execute();
                return $stmt;
            }
            
            // Check if params is an associative array (for named parameters)
            $isAssoc = (array_keys($params) !== range(0, count($params) - 1));
            
            if ($isAssoc) {
                // For named parameters
                foreach ($params as $key => $value) {
                    $paramName = (strpos($key, ':') === 0) ? $key : ":$key";
                    $type = is_int($value) ? PDO::PARAM_INT : 
                           (is_bool($value) ? PDO::PARAM_BOOL : 
                           (is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR));
                    $stmt->bindValue($paramName, $value, $type);
                }
            } else {
                // For positional parameters
                foreach ($params as $i => $value) {
                    $param = $i + 1; // Parameters are 1-indexed
                    $type = is_int($value) ? PDO::PARAM_INT : 
                           (is_bool($value) ? PDO::PARAM_BOOL : 
                           (is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR));
                    $stmt->bindValue($param, $value, $type);
                }
            }
            
            $result = $stmt->execute();
            if ($result === false) {
                $error = $stmt->errorInfo();
                throw new PDOException("Execute failed: " . ($error[2] ?? 'Unknown error'));
            }
            
            return $stmt;
        } catch (PDOException $e) {
            $errorInfo = $this->connection->errorInfo();
            $errorMsg = "Query Error: " . $e->getMessage() . "\n" .
                       "SQL: $sql\n" .
                       "Params: " . print_r($params, true) . "\n" .
                       "PDO Error Info: " . print_r($errorInfo, true);
            error_log($errorMsg);
            // Throw the original error message for debugging
            throw $e;
        }
    }
    
    /**
     * Fetch a single row
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get the last insert ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Check if a table exists in the database
     * 
     * @param string $tableName The name of the table to check
     * @return bool True if the table exists, false otherwise
     */
    public function tableExists($tableName) {
        try {
            $result = $this->fetch(
                "SHOW TABLES LIKE ?", 
                [$tableName]
            );
            return !empty($result);
        } catch (PDOException $e) {
            error_log("Error checking if table exists: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback a transaction
     */
    public function rollBack() {
        return $this->connection->rollBack();
    }
    
    /**
     * Insert a row into a table
     */
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        $this->query($sql, $data);
        return $this->lastInsertId();
    }
    
    /**
     * Update rows in a table
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        $params = [];
        
        // Process SET clause parameters
        foreach ($data as $column => $value) {
            $param = 'set_' . $column;
            $set[] = "$column = :$param";
            $params[$param] = $value;
        }
        $setClause = implode(', ', $set);
        
        // Process WHERE clause parameters
        $whereParamsProcessed = [];
        foreach ($whereParams as $key => $value) {
            $param = 'where_' . (is_numeric($key) ? 'param' . $key : $key);
            $where = str_replace('?', ':' . $param, $where, $count);
            $where = str_replace(':' . $key, ':' . $param, $where);
            $params[$param] = $value;
        }
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
        
        error_log("UPDATE SQL: $sql");
        error_log("Params: " . print_r($params, true));
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete rows from a table
     * 
     * @param string $table The table name
     * @param string|array $where The WHERE clause (without the WHERE keyword) or an associative array of conditions
     * @param array $params Parameters to bind to the query (only used if $where is a string)
     * @return int Number of affected rows
     * @throws PDOException If the query fails
     * @throws InvalidArgumentException If the table name is invalid or conditions are empty
     */
    public function delete($table, $where, $params = []) {
        // Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Invalid table name');
        }
        
        // Handle array conditions
        if (is_array($where) && !empty($where)) {
            $conditions = [];
            $bindParams = [];
            
            foreach ($where as $column => $value) {
                $conditions[] = "`$column` = ?";
                $bindParams[] = $value;
            }
            
            $whereClause = implode(' AND ', $conditions);
            $params = $bindParams;
        } else if (is_string($where) && !empty(trim($where))) {
            $whereClause = $where;
        } else {
            throw new InvalidArgumentException('WHERE conditions cannot be empty for DELETE operations');
        }
        
        // Use prepared statements for the WHERE clause
        $sql = "DELETE FROM `$table` WHERE $whereClause";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Check if a record exists
     */
    public function exists($table, $where, $params = []) {
        $sql = "SELECT 1 FROM $table WHERE $where LIMIT 1";
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn() !== false;
    }
    
    /**
     * Count records in a table
     */
    public function count($table, $where = '1', $params = []) {
        $sql = "SELECT COUNT(*) FROM $table WHERE $where";
        $stmt = $this->query($sql, $params);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Helper function to get database instance
function db() {
    return Database::getInstance();
}

// Example usage:
// $db = db();
// $user = $db->fetch("SELECT * FROM users WHERE id = ?", [1]);
// $users = $db->fetchAll("SELECT * FROM users WHERE active = ?", [1]);
// $id = $db->insert('users', ['username' => 'test', 'email' => 'test@example.com']);
// $affected = $db->update('users', ['username' => 'newname'], 'id = ?', [1]);
// $deleted = $db->delete('users', 'id = ?', [1]);
// $exists = $db->exists('users', 'email = ?', ['test@example.com']);
// $count = $db->count('users', 'active = ?', [1]);
