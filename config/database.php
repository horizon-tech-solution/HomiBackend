<?php

class Database {
    private string $host;
    private string $dbname;
    private string $user;
    private string $pass;
    private string $port;
    private ?PDO $pdo = null;

    public function __construct() {
        $this->host   = $_ENV['DB_HOST'] ?? 'bp73o4lklwrsh9u3qr4l-mysql.services.clever-cloud.com';
        $this->port   = $_ENV['DB_PORT'] ?? '3306';
        $this->dbname = $_ENV['DB_NAME'] ?? 'bp73o4lklwrsh9u3qr4l';
        $this->user   = $_ENV['DB_USER'] ?? 'uqz4bxbhmnceb6np';
        $this->pass   = $_ENV['DB_PASS'] ?? 'SsRWvU3uxaYD6DrZwBBD';
    }

    public function getConnection(): PDO {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";

            $this->pdo = new PDO($dsn, $this->user, $this->pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }

        return $this->pdo;
    }
}