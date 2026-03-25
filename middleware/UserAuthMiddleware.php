<?php
// middleware/UserAuthMiddleware.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/user_auth.php';

class UserAuthMiddleware {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function authenticate(): ?array {
        // ── 1. Try httpOnly cookie first ──────────────────────────────────
        $token = getTokenFromCookie();

        // ── 2. Fall back to Authorization header (transition period) ──────
        if (!$token) {
            $token = $this->getTokenFromHeader();
        }

        if (!$token) return null;

        $payload = UserAuth::validateToken($token);

        if (!$payload || empty($payload['sub'])) return null;


        $role = $payload['role'] ?? '';
        if (!in_array($role, ['user', 'agent'])) {
            return null; // blocks admin tokens from user routes
        }

        $stmt = $this->db->prepare(
            "SELECT id, email, name, phone, role, status, verification_status
             FROM users WHERE id = ? AND status = 'active'"
        );
        $stmt->execute([$payload['sub']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    // Keep header fallback during transition period
    private function getTokenFromHeader(): ?string {
        $headers           = getallheaders();
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        $authHeader        = $normalizedHeaders['authorization']
            ?? $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }
}