<?php
require_once __DIR__ . '/../../config/user_auth.php';

class AuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {} // not needed for auth endpoints

    // ── POST /user/auth/register ──────────────────────────────────────────────
    public function register() {
        $input = getJsonInput();

        $name     = trim($input['name'] ?? '');
        $email    = trim($input['email'] ?? '');
        $phone    = trim($input['phone'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            jsonResponse(['error' => 'Name, email and password are required'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Invalid email address'], 400);
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Email already registered'], 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, phone, password_hash, role, status, created_at)
             VALUES (?, ?, ?, ?, 'user', 'active', NOW())"
        );

        if ($stmt->execute([$name, $email, $phone, $hash])) {
            $userId = $this->db->lastInsertId();
            $token  = UserAuth::generateToken($userId, $email, 'user');
            jsonResponse([
                'token' => $token,
                'user'  => [
                    'id'    => (int) $userId,
                    'name'  => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role'  => 'user',
                ]
            ], 201);
        } else {
            jsonResponse(['error' => 'Registration failed'], 500);
        }
    }

    // ── POST /user/auth/login ─────────────────────────────────────────────────
    public function login() {
        $input = getJsonInput();

        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            jsonResponse(['error' => 'Email and password are required'], 400);
        }

        $stmt = $this->db->prepare(
            "SELECT id, name, email, phone, role, status, password_hash
             FROM users WHERE email = ?"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }

        if ($user['status'] === 'blocked') {
            jsonResponse(['error' => 'Your account has been suspended. Please contact support.'], 403);
        }

        $token = UserAuth::generateToken($user['id'], $user['email'], $user['role']);

        jsonResponse([
            'token' => $token,
            'user'  => [
                'id'    => (int) $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role'  => $user['role'],
            ]
        ]);
    }

    // ── POST /user/auth/check ─────────────────────────────────────────────────
    // Checks if an email or phone already has an account.
    // Called on the identifier step of Auth.jsx to decide login vs signup.
    public function check() {
        $input = getJsonInput();
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');

        if (!$email && !$phone) {
            jsonResponse(['exists' => false]);
        }

        if ($email) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
        }

        jsonResponse(['exists' => (bool) $stmt->fetch()]);
    }

    // ── POST /user/auth/register/professional ─────────────────────────────────
    public function registerProfessional() {
        $input = getJsonInput();

        $required = [
            'email', 'password', 'professionalType', 'firstName', 'lastName',
            'phoneNumber', 'region', 'city', 'businessName', 'taxId', 'officeAddress'
        ];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonResponse(['error' => "$field is required"], 400);
            }
        }

        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Invalid email address'], 400);
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Email already registered'], 409);
        }

        $hash = password_hash($input['password'], PASSWORD_DEFAULT);
        $name = trim($input['firstName'] . ' ' . $input['lastName']);

        $userStmt = $this->db->prepare(
            "INSERT INTO users (name, email, phone, password_hash, role, status, verification_status, created_at)
             VALUES (?, ?, ?, ?, 'agent', 'active', 'pending', NOW())"
        );
        if (!$userStmt->execute([$name, $input['email'], $input['phoneNumber'], $hash])) {
            jsonResponse(['error' => 'User creation failed'], 500);
        }
        $userId = $this->db->lastInsertId();

        $appStmt = $this->db->prepare(
            "INSERT INTO professional_applications
                (user_id, professional_type, business_name, tax_id, license_number,
                 years_experience, office_address, phone_number, region, city,
                 notary_name, notary_contact, status, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
        );
        $appOk = $appStmt->execute([
            $userId,
            $input['professionalType'],
            $input['businessName'],
            $input['taxId'],
            $input['licenseNumber']   ?? null,
            $input['yearsExperience'] ?? null,
            $input['officeAddress'],
            $input['phoneNumber'],
            $input['region'],
            $input['city'],
            $input['notaryName']    ?? null,
            $input['notaryContact'] ?? null,
        ]);

        if (!$appOk) {
            jsonResponse(['error' => 'Application creation failed'], 500);
        }
        $applicationId = $this->db->lastInsertId();

        $token = UserAuth::generateToken($userId, $input['email'], 'agent');

        jsonResponse([
            'token'          => $token,
            'user'           => [
                'id'    => (int) $userId,
                'name'  => $name,
                'email' => $input['email'],
                'phone' => $input['phoneNumber'],
                'role'  => 'agent',
            ],
            'application_id' => (int) $applicationId,
        ], 201);
    }
}