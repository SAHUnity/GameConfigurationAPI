<?php

/**
 * Database connection class for Game Configuration API
 */
class Database
{
    private $host = 'localhost';
    private $db_name = 'game_config_api';
    private $username = 'your_db_username';
    private $password = 'your_db_password';
    private $charset = 'utf8mb4';

    public $pdo;

    /**
     * Get database connection
     * @return PDO
     */
    public function getConnection()
    {
        $this->pdo = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->pdo = new PDO($dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }

        return $this->pdo;
    }
}
