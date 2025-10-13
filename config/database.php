<?php

/**
 * Database connection class for Game Configuration API
 */
class Database
{
    private static $instance = null;
    private $pdo;
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->loadConfiguration();
        $this->connect();
    }

    /**
     * Get singleton instance
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load database configuration from environment variables
     */
    private function loadConfiguration()
    {
        // Environment variables are already loaded in config/config.php
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'game_config_api';
        $this->username = $_ENV['DB_USERNAME'] ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
        $this->charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
    }

    /**
     * Establish database connection
     */
    private function connect()
    {
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $this->charset
            ];

            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    /**
     * Get database connection
     * @return PDO
     */
    public function getConnection()
    {
        return $this->pdo;
    }

    /**
     * Prevent cloning of singleton instance
     */
    private function __clone() {}

    /**
     * Prevent unserialization of singleton instance
     */
    public function __wakeup() {}
}
