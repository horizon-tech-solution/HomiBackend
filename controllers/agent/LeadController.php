<?php
// controllers/agent/LeadController.php
require_once __DIR__ . '/../../models/agent/Lead.php';

class LeadController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    // GET /agent/leads
    public function index($params = []) {
        $model = new Lead($this->db);
        $leads = $model->getByAgent((int) $this->user['id']);
        jsonResponse($leads);
    }

    // GET /agent/leads/{id}
    public function show($params = []) {
        $id = $params['id'] ?? null;
        if (!$id) { http_response_code(400); jsonResponse(['error' => 'ID required']); return; }

        $model = new Lead($this->db);
        $lead  = $model->getWithMessages((int) $id, (int) $this->user['id']);

        if (!$lead) {
            http_response_code(404);
            jsonResponse(['error' => 'Inquiry not found']);
            return;
        }

        jsonResponse($lead);
    }

    // POST /agent/leads/{id}/reply
    public function reply($params = []) {
        $id = $params['id'] ?? null;
        if (!$id) { http_response_code(400); jsonResponse(['error' => 'ID required']); return; }

        $data    = json_decode(file_get_contents('php://input'), true) ?? [];
        $message = trim($data['message'] ?? '');

        if ($message === '') {
            http_response_code(422);
            jsonResponse(['error' => 'Message cannot be empty']);
            return;
        }

        // Allow reply if agent owns the listing (received) OR is the original sender (sent)
        $check = $this->db->prepare(
            "SELECT i.id, i.from_user_id, i.to_user_id FROM inquiries i
             JOIN listings l ON i.listing_id = l.id
             WHERE i.id = ?
               AND (l.user_id = ? OR i.from_user_id = ?)"
        );
        $check->execute([$id, $this->user['id'], $this->user['id']]);
        $inquiry = $check->fetch(PDO::FETCH_ASSOC);

        if (!$inquiry) {
            http_response_code(404);
            jsonResponse(['error' => 'Inquiry not found']);
            return;
        }

        // ── Use the actual role of the sender, not a hardcoded string ────────
        // This fixes messages showing on the wrong side in the conversation view.
        $senderType = $this->user['role'] ?? 'user'; // 'agent' | 'user'

        $model     = new Lead($this->db);
        $messageId = $model->reply((int) $id, (int) $this->user['id'], $message, $senderType);

        // Notify the other party
        $notifyUserId = ((int)$inquiry['from_user_id'] === (int)$this->user['id'])
            ? (int)$inquiry['to_user_id']
            : (int)$inquiry['from_user_id'];

        $this->notifyUser((int) $id, $notifyUserId, $message);

        http_response_code(201);
        jsonResponse([
            'id'          => $messageId,
            'text'        => $message,
            'sender_type' => $senderType,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function notifyUser(int $inquiryId, int $toUserId, string $preview): void {
        try {
            $stmt = $this->db->prepare(
                "SELECT l.title FROM inquiries i
                 JOIN listings l ON i.listing_id = l.id
                 WHERE i.id = ? LIMIT 1"
            );
            $stmt->execute([$inquiryId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->db->prepare(
                "INSERT INTO notifications (user_id, type, title, message, data, created_at)
                 VALUES (?, 'message', 'New reply from agent', ?, ?, NOW())"
            )->execute([
                $toUserId,
                'You have a new reply regarding: ' . ($row['title'] ?? 'your inquiry'),
                json_encode(['inquiry_id' => $inquiryId]),
            ]);
        } catch (\Exception $e) {
            // Notification failure must never break the reply
        }
    }
}