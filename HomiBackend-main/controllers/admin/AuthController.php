<?php
require_once __DIR__ . '/../../models/admin/User.php';

class AuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setAdmin($admin) {}

    public function login($params = []) {
        $input = getJsonInput();
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        // TEMP DEBUG
        error_log("LOGIN ATTEMPT: username=$username password=$password");

        $stmt = $this->db->prepare("SELECT * FROM admin_users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        // TEMP DEBUG
        error_log("ADMIN FOUND: " . ($admin ? 'yes' : 'no'));
        if ($admin) {
            error_log("HASH IN DB: " . $admin['password_hash']);
            error_log("VERIFY RESULT: " . (password_verify($password, $admin['password_hash']) ? 'true' : 'false'));
        }

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $token = Auth::generateToken($admin['id'], $admin['username']);
            jsonResponse(['token' => $token, 'admin' => [
                'id'       => $admin['id'],
                'username' => $admin['username'],
                'name'     => $admin['name'],
                'email'    => $admin['email'],
                'role'     => $admin['role'],
            ]]);
        } else {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
    }

    public function logout($params = []) {
        jsonResponse(['success' => true]);
    }
}