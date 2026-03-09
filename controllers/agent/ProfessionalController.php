<?php
// controllers/User/ProfessionalController.php

class ProfessionalController {
    private $db;
    private $user;

    public function __construct($db) { $this->db = $db; }
    public function setUser($user)   { $this->user = $user; }

    // POST /user/professional/apply
    public function submit() {
        $input = getJsonInput();

        $required = ['professional_type', 'business_name', 'tax_id', 'office_address', 'phone'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonResponse(['error' => "Field '$field' is required", 'received' => array_keys($input)], 400);
            }
        }

        $allowedTypes = ['landlord', 'agent', 'landSeller'];
        if (!in_array($input['professional_type'], $allowedTypes)) {
            jsonResponse(['error' => 'Invalid professional type'], 400);
        }

        // Block duplicate pending applications
        $stmt = $this->db->prepare(
            "SELECT id, status FROM professional_applications
             WHERE user_id = ? ORDER BY created_at DESC LIMIT 1"
        );
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

    // POST /user/professional/{id}/upload
    public function uploadDocument($params) {
        $applicationId = $params['id'] ?? null;
        if (!$applicationId) {
            jsonResponse(['error' => 'Application ID required'], 400);
        }

        // Ensure the application belongs to this user
        $stmt = $this->db->prepare(
            "SELECT id FROM professional_applications WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$applicationId, $this->user['id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Application not found'], 404);
        }

        if (empty($_FILES['document'])) {
            jsonResponse(['error' => 'No file uploaded'], 400);
        }

        $file         = $_FILES['document'];
        $type         = $_POST['type'] ?? 'other';
        $allowedTypes = ['id', 'land_title', 'license', 'business_reg', 'other'];

        if (!in_array($type, $allowedTypes)) {
            jsonResponse(['error' => 'Invalid document type'], 400);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowedMimes)) {
            jsonResponse(['error' => 'Only JPG, PNG, and PDF allowed'], 400);
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            jsonResponse(['error' => 'File must be under 5MB'], 400);
        }

        $uploadDir = __DIR__ . '/../../public/uploads/applications/' . $applicationId . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $type . '_' . time() . '.' . strtolower($ext);
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            jsonResponse(['error' => 'Failed to save file'], 500);
        }

        $filePath = '/uploads/applications/' . $applicationId . '/' . $filename;

        $stmt = $this->db->prepare("
            INSERT INTO application_documents (application_id, document_type, file_path, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), created_at = NOW()
        ");
        $stmt->execute([$applicationId, $type, $filePath]);

        jsonResponse(['path' => $filePath]);
    }

    // GET /user/professional/status
    public function index() {
        $stmt = $this->db->prepare("
            SELECT id, professional_type, business_name, status,
                   created_at, reviewed_at, rejection_reason
            FROM professional_applications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$this->user['id']]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        jsonResponse($app ?: ['status' => 'none']);
    }
}