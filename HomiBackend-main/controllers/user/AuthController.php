<?php
require_once __DIR__ . '/../../config/user_auth.php';

class AuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {} // not needed

    public function register() {
        $input = getJsonInput();
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $phone = $input['phone'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($name) || empty($email) || empty($phone) || empty($password)) {
            jsonResponse(['error' => 'All fields are required'], 400);
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Email already registered'], 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (name, email, phone, password_hash, role, created_at) VALUES (?, ?, ?, ?, 'user', NOW())");
        if ($stmt->execute([$name, $email, $phone, $hash])) {
            $userId = $this->db->lastInsertId();
            $token = UserAuth::generateToken($userId, $email);
            jsonResponse([
                'token' => $token,
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => 'user'
                ]
            ], 201);
        } else {
            jsonResponse(['error' => 'Registration failed'], 500);
        }
    }

    public function login() {
        $input = getJsonInput();
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $token = UserAuth::generateToken($user['id'], $user['email']);
            jsonResponse([
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
    }

    public function registerProfessional() {
    $input = getJsonInput();

    $required = ['email', 'password', 'professionalType', 'firstName', 'lastName', 'phoneNumber', 'nationalId', 'region', 'city', 'businessName', 'taxId', 'officeAddress'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            jsonResponse(['error' => "$field is required"], 400);
        }
    }

    // Check if user exists
    $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Email already registered'], 409);
    }

    // Create user with role 'agent' and verification_status = 'pending'
    $hash = password_hash($input['password'], PASSWORD_DEFAULT);
    $name = $input['firstName'] . ' ' . $input['lastName'];
    $userStmt = $this->db->prepare("INSERT INTO users (name, email, phone, password_hash, role, verification_status, created_at) VALUES (?, ?, ?, ?, 'agent', 'pending', NOW())");
    if (!$userStmt->execute([$name, $input['email'], $input['phoneNumber'], $hash])) {
        jsonResponse(['error' => 'User creation failed'], 500);
    }
    $userId = $this->db->lastInsertId();

    // Create professional application
    $appModel = new ProfessionalApplication($this->db);
    $appData = [
        'user_id' => $userId,
        'professional_type' => $input['professionalType'],
        'business_name' => $input['businessName'],
        'tax_id' => $input['taxId'],
        'license_number' => $input['licenseNumber'] ?? null,
        'years_experience' => $input['yearsExperience'] ?? null,
        'office_address' => $input['officeAddress'],
        'phone_number' => $input['phoneNumber'],
        'region' => $input['region'],
        'city' => $input['city'],
        'notary_name' => $input['notaryName'] ?? null,
        'notary_contact' => $input['notaryContact'] ?? null
    ];
    if (!$appModel->create($appData)) {
        // Rollback user? For simplicity, just return error
        jsonResponse(['error' => 'Application creation failed'], 500);
    }
    $applicationId = $this->db->lastInsertId();

    // Generate token
    $token = UserAuth::generateToken($userId, $input['email']);
    jsonResponse([
        'token' => $token,
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $input['email'],
            'phone' => $input['phoneNumber'],
            'role' => 'agent'
        ],
        'application_id' => $applicationId
    ], 201);
}
}