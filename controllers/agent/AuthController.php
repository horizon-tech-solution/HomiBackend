<?php
// controllers/Agent/AuthController.php
require_once __DIR__ . '/../../config/user_auth.php';

class AuthController {
    private $db;
    private $user;

    public function __construct($db) { $this->db = $db; }
    public function setUser($user)   { $this->user = $user; }

    // ─────────────────────────────────────────────────────────────────
    // POST /agent/auth/login
    // ─────────────────────────────────────────────────────────────────
    public function login() {
        $input    = getJsonInput();
        $email    = trim($input['email']    ?? '');
        $password = trim($input['password'] ?? '');

        if (!$email || !$password) {
            jsonResponse(['error' => 'Email and password required'], 400);
        }

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND role = 'agent'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }

        if ($user['status'] === 'blocked') {
            jsonResponse(['error' => 'Account suspended'], 403);
        }

        $token = UserAuth::generateToken($user['id'], $user['email'], 'agent');
        jsonResponse([
            'token' => $token,
            'user'  => [
                'id'                  => $user['id'],
                'name'                => $user['name'],
                'email'               => $user['email'],
                'phone'               => $user['phone'],
                'role'                => $user['role'],
                'avatar_url'          => $user['avatar_url'],
                'verification_status' => $user['verification_status'],
            ]
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /agent/auth/register
    // Simplified: name, email, phone, password, national_id
    //             + optional: agency_name, agent_type
    // ─────────────────────────────────────────────────────────────────
    public function register() {
        $input    = getJsonInput();

        // Trim every string field so whitespace-only values are caught
        $name       = trim($input['name']       ?? '');
        $email      = trim($input['email']      ?? '');
        $phone      = trim($input['phone']      ?? '');
        $password   = trim($input['password']   ?? '');
        $nationalId = trim($input['national_id'] ?? '');
        $agencyName = trim($input['agency_name'] ?? '');
        $agentType  = trim($input['agent_type']  ?? 'agent');  // landlord | agent | landSeller

        // ── validation ──────────────────────────────────────────────
        $missing = [];
        if (!$name)       $missing[] = 'name';
        if (!$email)      $missing[] = 'email';
        if (!$phone)      $missing[] = 'phone';
        if (!$password)   $missing[] = 'password';
        if (!$nationalId) $missing[] = 'national_id';

        if ($missing) {
            jsonResponse(['error' => 'Missing required fields: ' . implode(', ', $missing)], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Invalid email address'], 400);
        }

        if (strlen($password) < 8) {
            jsonResponse(['error' => 'Password must be at least 8 characters'], 400);
        }

        $allowedTypes = ['agent', 'landlord', 'landSeller'];
        if (!in_array($agentType, $allowedTypes)) {
            $agentType = 'agent';
        }

        // ── duplicate check ─────────────────────────────────────────
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'This email is already registered'], 409);
        }

        // ── insert ──────────────────────────────────────────────────
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            INSERT INTO users
              (name, email, phone, password_hash, role, agency_name,
               verification_status, status, created_at, updated_at)
            VALUES
              (?, ?, ?, ?, 'agent', ?,
               'pending', 'active', NOW(), NOW())
        ");

        if (!$stmt->execute([$name, $email, $phone, $hash, $agencyName ?: null])) {
            jsonResponse(['error' => 'Registration failed. Please try again.'], 500);
        }

        $userId = (int) $this->db->lastInsertId();

        // Store national_id in a dedicated table (or as a user meta column if you add one).
        // For now we insert into agent_identity table. If it doesn't exist yet, see migration below.
        try {
            $stmt = $this->db->prepare("
                INSERT INTO agent_identity (user_id, national_id_number, agent_type, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $nationalId, $agentType]);
        } catch (Exception $e) {
            // Non-fatal: table may not exist yet. Log and continue.
            error_log('agent_identity insert failed: ' . $e->getMessage());
        }

        $token = UserAuth::generateToken($userId, $email, 'agent');

        jsonResponse([
            'token' => $token,
            'user'  => [
                'id'                  => $userId,
                'name'                => $name,
                'email'               => $email,
                'phone'               => $phone,
                'role'                => 'agent',
                'verification_status' => 'pending',
            ]
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /agent/auth/upload-id     (authenticated)
    // Stores the national ID document for a newly registered agent.
    // Called right after register() from the frontend.
    // ─────────────────────────────────────────────────────────────────
    public function uploadId() {
        if (empty($_FILES['document'])) {
            jsonResponse(['error' => 'No file uploaded'], 400);
        }

        $file    = $_FILES['document'];
        $type    = $_POST['type'] ?? 'national_id';   // national_id | selfie
        $allowed = ['national_id', 'selfie'];

        if (!in_array($type, $allowed)) {
            $type = 'national_id';
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowedMimes)) {
            jsonResponse(['error' => 'Only JPG, PNG, and PDF files are allowed'], 400);
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            jsonResponse(['error' => 'File must be under 5MB'], 400);
        }

        $userId    = $this->user['id'];
        $uploadDir = __DIR__ . '/../../public/uploads/identity/' . $userId . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $type . '_' . time() . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            jsonResponse(['error' => 'Failed to save file. Please try again.'], 500);
        }

        $filePath     = '/uploads/identity/' . $userId . '/' . $filename;
        $nationalIdNo = trim($_POST['national_id_number'] ?? '');

        // Upsert into agent_identity
        try {
            $stmt = $this->db->prepare("
                INSERT INTO agent_identity (user_id, national_id_number, {$type}_path, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    {$type}_path        = VALUES({$type}_path),
                    national_id_number  = IF(? != '', VALUES(national_id_number), national_id_number)
            ");
            $stmt->execute([$userId, $nationalIdNo, $filePath, $nationalIdNo]);
        } catch (Exception $e) {
            error_log('agent_identity upsert failed: ' . $e->getMessage());
        }

        jsonResponse(['success' => true, 'path' => $filePath]);
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /user/professional/apply   (full verification — separate flow)
    // ─────────────────────────────────────────────────────────────────
    public function apply() {
        $input = getJsonInput();

        $required = ['professional_type', 'business_name', 'tax_id', 'office_address', 'phone'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonResponse(['error' => "Field '$field' is required"], 400);
            }
        }

        $allowedTypes = ['landlord', 'agent', 'landSeller'];
        if (!in_array($input['professional_type'], $allowedTypes)) {
            jsonResponse(['error' => 'Invalid professional type'], 400);
        }

        $stmt = $this->db->prepare("
            SELECT id, status FROM professional_applications
            WHERE user_id = ? ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$this->user['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing && $existing['status'] === 'pending') {
            jsonResponse(['error' => 'You already have a pending application'], 409);
        }

        $stmt = $this->db->prepare("
            INSERT INTO professional_applications
              (user_id, professional_type, business_name, tax_id, license_number,
               years_experience, office_address, phone_number, region, city,
               notary_name, notary_contact, status, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $this->user['id'],
            $input['professional_type'],
            $input['business_name'],
            $input['tax_id'],
            $input['license_number']   ?? null,
            $input['years_experience'] ?? null,
            $input['office_address'],
            $input['phone'],
            $input['region']           ?? null,
            $input['city']             ?? null,
            $input['notary_name']      ?? null,
            $input['notary_contact']   ?? null,
        ]);

        $applicationId = $this->db->lastInsertId();
        jsonResponse(['id' => $applicationId, 'status' => 'pending'], 201);
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /user/professional/{id}/upload
    // ─────────────────────────────────────────────────────────────────
    public function uploadDocument($params) {
        $applicationId = $params['id'] ?? null;
        if (!$applicationId) jsonResponse(['error' => 'Application ID required'], 400);

        $stmt = $this->db->prepare(
            "SELECT id FROM professional_applications WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$applicationId, $this->user['id']]);
        if (!$stmt->fetch()) jsonResponse(['error' => 'Application not found'], 404);

        if (empty($_FILES['document'])) jsonResponse(['error' => 'No file uploaded'], 400);

        $file    = $_FILES['document'];
        $type    = $_POST['type'] ?? 'other';
        $allowed = ['id', 'land_title', 'license', 'business_reg', 'other'];

        if (!in_array($type, $allowed)) jsonResponse(['error' => 'Invalid document type'], 400);

        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowedMimes)) jsonResponse(['error' => 'Only JPG, PNG, and PDF allowed'], 400);
        if ($file['size'] > 5 * 1024 * 1024) jsonResponse(['error' => 'File must be under 5MB'], 400);

        $uploadDir = __DIR__ . '/../../public/uploads/applications/' . $applicationId . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $type . '_' . time() . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) jsonResponse(['error' => 'Failed to save file'], 500);

        $filePath = '/uploads/applications/' . $applicationId . '/' . $filename;

        $stmt = $this->db->prepare("
            INSERT INTO application_documents (application_id, document_type, file_path, uploaded_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), uploaded_at = NOW()
        ");
        $stmt->execute([$applicationId, $type, $filePath]);

        jsonResponse(['path' => $filePath]);
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /user/professional/status
    // ─────────────────────────────────────────────────────────────────
    public function applicationStatus() {
        $stmt = $this->db->prepare("
            SELECT id, professional_type, business_name, status, submitted_at, reviewed_at, admin_notes
            FROM professional_applications
            WHERE user_id = ?
            ORDER BY submitted_at DESC
            LIMIT 1
        ");
        $stmt->execute([$this->user['id']]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        jsonResponse($app ?: ['status' => 'none']);
    }
}