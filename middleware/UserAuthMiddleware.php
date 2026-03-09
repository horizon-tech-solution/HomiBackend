<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/user_auth.php';

class UserAuthMiddleware {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function authenticate(): ?array {
        $headers     = getallheaders();
        $authHeader  = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return null;
        }

        $payload = UserAuth::validateToken($matches[1]);

        if (!$payload || empty($payload['sub'])) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT id, email, name, phone, role, status, verification_status
             FROM users WHERE id = ? AND status = 'active'"
        );
        $stmt->execute([$payload['sub']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }
}