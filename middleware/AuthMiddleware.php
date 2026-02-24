<?php
require_once __DIR__ . '/../config/auth.php';

class AuthMiddleware {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function authenticate() {
        $headers = getallheaders();

        // Normalize to lowercase to handle PHP built-in server case differences
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        $authHeader = $normalizedHeaders['authorization'] ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = Auth::validateToken($token);

            if ($payload && isset($payload['sub'])) {
                $stmt = $this->db->prepare("SELECT id, username, name, email, role FROM admin_users WHERE id = ?");
                $stmt->execute([$payload['sub']]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($admin) {
                    return $admin;
                }
            }
        }

        return null;
    }
}