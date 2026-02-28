<?php
require_once __DIR__ . '/../../config/user_auth.php';

class AuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {} // not needed here

    public function login() {
        $input = getJsonInput();
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND role = 'agent'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $token = UserAuth::generateToken($user['id'], $user['email']);
            jsonResponse(['token' => $token, 'user' => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role'  => $user['role']
            ]]);
        } else {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
    }

    public function register() {
        $input = getJsonInput();
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $phone = $input['phone'] ?? '';
        $password = $input['password'] ?? '';

        // Basic validation
        if (empty($name) || empty($email) || empty($phone) || empty($password)) {
            jsonResponse(['error' => 'All fields are required'], 400);
        }

        // Check if user exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Email already registered'], 409);
        }

        // Hash password
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user as agent (verification_status = pending)
        $stmt = $this->db->prepare("INSERT INTO users (name, email, phone, password_hash, role, verification_status, created_at) VALUES (?, ?, ?, ?, 'agent', 'pending', NOW())");
        if ($stmt->execute([$name, $email, $phone, $hash])) {
            $userId = $this->db->lastInsertId();
            $token = UserAuth::generateToken($userId, $email);
            jsonResponse(['token' => $token, 'user' => [
                'id'    => $userId,
                'name'  => $name,
                'email' => $email,
                'phone' => $phone,
                'role'  => 'agent'
            ]], 201);
        } else {
            jsonResponse(['error' => 'Registration failed'], 500);
        }
    }
}