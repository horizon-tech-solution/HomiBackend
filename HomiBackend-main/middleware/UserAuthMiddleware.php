<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/user_auth.php';

class UserAuthMiddleware {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function authenticate() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = UserAuth::validateToken($token);
            if ($payload && isset($payload['sub'])) {
                $stmt = $this->db->prepare("SELECT id, email, name, phone, role, status FROM users WHERE id = ? AND status = 'active'");
                $stmt->execute([$payload['sub']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    return $user;
                }
            }
        }
        return null;
    }
}