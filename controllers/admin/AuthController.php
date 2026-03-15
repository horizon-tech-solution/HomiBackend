<?php
// controllers/admin/AuthController.php
require_once __DIR__ . '/../../models/admin/User.php';

class AuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setAdmin($admin) {}

    public function login($params = []) {
        $input    = getJsonInput();
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (!$username || !$password) {
            jsonResponse(['error' => 'Username and password required'], 400);
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM admin_users WHERE username = ? OR email = ?"
        );
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }

        $token = Auth::generateToken($admin['id'], $admin['username']);

        // ── Set httpOnly cookie for admin — separate cookie name ──────────
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('homi_admin_token', $token, [
            'expires'  => time() + (60 * 60 * 24), // 24 hours — matches token expiry
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // ── Return only admin info, NOT the token ─────────────────────────
        jsonResponse([
            'admin' => [
                'id'       => $admin['id'],
                'username' => $admin['username'],
                'name'     => $admin['name'],
                'email'    => $admin['email'],
                'role'     => $admin['role'],
            ]
        ]);
    }

    public function logout($params = []) {
        // Clear the admin cookie
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('homi_admin_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        jsonResponse(['success' => true]);
    }
}