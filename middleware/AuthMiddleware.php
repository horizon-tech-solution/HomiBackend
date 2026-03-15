<?php
// middleware/AuthMiddleware.php  (Admin)
require_once __DIR__ . '/../config/auth.php';

class AuthMiddleware {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function authenticate() {
        // ── 1. Try httpOnly cookie first ──────────────────────────────────
        // Admin uses a separate cookie name to avoid any clash with user cookie
        $token = $_COOKIE['homi_admin_token'] ?? null;

        // ── 2. Fall back to Authorization header ──────────────────────────
        if (!$token) {
            $headers           = getallheaders();
            $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
            $authHeader        = $normalizedHeaders['authorization'] ?? '';

            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }

        if (!$token) return null;

        $payload = Auth::validateToken($token);

        // Auth::validateToken already enforces role = 'admin' internally
        if (!$payload || !isset($payload['sub'])) return null;

        // Double-check role in payload (defence in depth)
        if (($payload['role'] ?? '') !== 'admin') return null;

        $stmt = $this->db->prepare(
            "SELECT id, username, name, email, role FROM admin_users WHERE id = ?"
        );
        $stmt->execute([$payload['sub']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        return $admin ?: null;
    }
}