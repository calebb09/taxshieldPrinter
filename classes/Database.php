<?php
// classes/Database.php
class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct($config)
    {
        try {
            $db = $config->db;
            $dsn = "mysql:host={$db->host};dbname={$db->dbname};charset={$db->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
            $this->pdo = new PDO($dsn, $db->user, $db->pass, $options);
        } catch (PDOException $e) {
            // Custom error message
            throw new Exception("Database connection failed. Please contact the administrator.");
        }
    }

    public static function getInstance($config)
    {
        if (!self::$instance) {
            self::$instance = new Database($config);
        }
        return self::$instance;
    }

    public function pdo()
    {
        return $this->pdo;
    }
}
