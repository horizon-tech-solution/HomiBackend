<?php
require_once __DIR__ . '/../../models/public/Inquiry.php';
require_once __DIR__ . '/../../models/admin/Listing.php';
require_once __DIR__ . '/../../models/admin/User.php';

class InquiryController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * POST /public/inquiries – send an inquiry (property or agent)
     */
    public function send() {
        $input = getJsonInput();

        $listingId = $input['listing_id'] ?? null;
        $agentId = $input['agent_id'] ?? null;
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $message = trim($input['message'] ?? '');

        if (empty($name) || empty($email) || empty($phone) || empty($message)) {
            jsonResponse(['error' => 'All fields are required'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Invalid email address'], 400);
        }

        // Check if user exists with this email
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $fromUserId = $user['id'];
        } else {
            // Create a new user (guest)
            $passwordHash = password_hash(uniqid(), PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'user')");
            if ($stmt->execute([$name, $email, $phone, $passwordHash])) {
                $fromUserId = $this->db->lastInsertId();
            } else {
                jsonResponse(['error' => 'Failed to create user'], 500);
            }
        }

        $inquiryModel = new Inquiry($this->db);

        if ($listingId) {
            // Verify listing exists and is approved
            $listingModel = new Listing($this->db);
            $listing = $listingModel->getById($listingId);
            if (!$listing || $listing['status'] !== 'approved') {
                jsonResponse(['error' => 'Listing not found'], 404);
            }
            $toUserId = $listing['user_id'];
            $success = $inquiryModel->create($listingId, $fromUserId, $toUserId, $message);
        } elseif ($agentId) {
            // Verify agent exists and is verified
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND role = 'agent' AND verification_status = 'verified'");
            $stmt->execute([$agentId]);
            if (!$stmt->fetch()) {
                jsonResponse(['error' => 'Agent not found'], 404);
            }
            $success = $inquiryModel->createAgentContact($agentId, $fromUserId, $message);
        } else {
            jsonResponse(['error' => 'Either listing_id or agent_id is required'], 400);
        }

        if ($success) {
            jsonResponse(['success' => true, 'message' => 'Inquiry sent successfully']);
        } else {
            jsonResponse(['error' => 'Failed to send inquiry'], 500);
        }
    }
}