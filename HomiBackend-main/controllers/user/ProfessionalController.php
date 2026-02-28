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

        $appModel = new ProfessionalApplication($this->db);
        $app = $appModel->getById($applicationId);
        if (!$app || $app['user_id'] != $this->user['id']) {
            jsonResponse(['error' => 'Application not found'], 404);
        }

        $documentType = $_POST['document_type'] ?? '';
        if (!in_array($documentType, ['id','land_title','license','business_reg'])) {
            jsonResponse(['error' => 'Invalid document type'], 400);
        }

        if (empty($_FILES['file'])) {
            jsonResponse(['error' => 'No file uploaded'], 400);
        }

        $file = $_FILES['file'];
        $uploadDir = __DIR__ . '/../../../public/uploads/professional_docs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = uniqid() . '_' . basename($file['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $filePath = '/uploads/professional_docs/' . $fileName;
            $docModel = new ApplicationDocument($this->db);
            if ($docModel->create($applicationId, $documentType, $filePath)) {
                jsonResponse(['path' => $filePath]);
            } else {
                jsonResponse(['error' => 'Failed to save document record'], 500);
            }
        } else {
            jsonResponse(['error' => 'Failed to upload file'], 500);
        }
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