<?php
require_once __DIR__ . '/../../models/agent/Message.php';

class LeadController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    public function index() {
        // Get all inquiries for agent's listings
        $stmt = $this->db->prepare("
            SELECT i.*, u.name as user_name, u.email, u.phone, l.title as listing_title, l.id as listing_id,
                   (SELECT COUNT(*) FROM messages WHERE inquiry_id = i.id) as message_count
            FROM inquiries i
            JOIN users u ON i.from_user_id = u.id
            JOIN listings l ON i.listing_id = l.id
            WHERE l.user_id = ?
            ORDER BY i.created_at DESC
        ");
        $stmt->execute([$this->user['id']]);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['data' => $leads]);
    }

    public function messages($params) {
        $inquiryId = $params['id'] ?? null;
        if (!$inquiryId) jsonResponse(['error' => 'ID required'], 400);

        // Verify the inquiry belongs to agent's listing
        $stmt = $this->db->prepare("
            SELECT i.* FROM inquiries i
            JOIN listings l ON i.listing_id = l.id
            WHERE i.id = ? AND l.user_id = ?
        ");
        $stmt->execute([$inquiryId, $this->user['id']]);
        $inquiry = $stmt->fetch();
        if (!$inquiry) {
            jsonResponse(['error' => 'Inquiry not found'], 404);
        }

        $messageModel = new Message($this->db);
        $messages = $messageModel->getByInquiry($inquiryId);
        jsonResponse(['inquiry' => $inquiry, 'messages' => $messages]);
    }

    public function reply($params) {
        $inquiryId = $params['id'] ?? null;
        if (!$inquiryId) jsonResponse(['error' => 'ID required'], 400);
        $input = getJsonInput();
        $message = $input['message'] ?? '';
        if (empty($message)) jsonResponse(['error' => 'Message cannot be empty'], 400);

        // Verify inquiry belongs to agent
        $stmt = $this->db->prepare("
            SELECT i.* FROM inquiries i
            JOIN listings l ON i.listing_id = l.id
            WHERE i.id = ? AND l.user_id = ?
        ");
        $stmt->execute([$inquiryId, $this->user['id']]);
        $inquiry = $stmt->fetch();
        if (!$inquiry) {
            jsonResponse(['error' => 'Inquiry not found'], 404);
        }

        $messageModel = new Message($this->db);
        if ($messageModel->create($inquiryId, $this->user['id'], 'agent', $message)) {
            // Optionally create notification for the lead user
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to send message'], 500);
        }
    }
}