<?php
require_once __DIR__ . '/../../models/user/ProfessionalApplication.php';
require_once __DIR__ . '/../../models/user/ApplicationDocument.php';

class ProfessionalController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    // Submit application (for existing users – BecomeAgent flow)
    public function submit() {
        $input = getJsonInput();

        $required = ['professional_type', 'business_name', 'tax_id', 'office_address', 'phone_number'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonResponse(['error' => "$field is required"], 400);
            }
        }

        $appModel = new ProfessionalApplication($this->db);
        $data = [
            'user_id' => $this->user['id'],
            'professional_type' => $input['professional_type'],
            'business_name' => $input['business_name'],
            'tax_id' => $input['tax_id'],
            'license_number' => $input['license_number'] ?? null,
            'years_experience' => $input['years_experience'] ?? null,
            'office_address' => $input['office_address'],
            'phone_number' => $input['phone_number'],
            'region' => $input['region'] ?? null,
            'city' => $input['city'] ?? null,
            'notary_name' => $input['notary_name'] ?? null,
            'notary_contact' => $input['notary_contact'] ?? null
        ];

        if ($appModel->create($data)) {
            $applicationId = $this->db->lastInsertId();
            jsonResponse(['id' => $applicationId, 'message' => 'Application submitted'], 201);
        } else {
            jsonResponse(['error' => 'Failed to submit application'], 500);
        }
    }

    // Upload document for an application
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

    require_once BASE_PATH . '/config/cloudinary.php';
    $filePath = uploadToCloudinary($file['tmp_name'], 'applications/' . $applicationId);

    $stmt = $this->db->prepare("
        INSERT INTO application_documents (application_id, document_type, file_path, created_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), created_at = NOW()
    ");
    $stmt->execute([$applicationId, $type, $filePath]);

    jsonResponse(['path' => $filePath]);
}

    // Get user's applications and their documents
    public function index() {
        $appModel = new ProfessionalApplication($this->db);
        $applications = $appModel->getByUser($this->user['id']);

        $docModel = new ApplicationDocument($this->db);
        foreach ($applications as &$app) {
            $app['documents'] = $docModel->getByApplication($app['id']);
        }

        jsonResponse(['data' => $applications]);
    }
}