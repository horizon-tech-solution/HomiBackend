<?php
// controllers/agent/NotificationController.php
require_once __DIR__ . '/../../models/agent/Notification.php';

class NotificationController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    // GET /agent/notifications
    public function index($params = []) {
        $model = new Notification($this->db);
        $notifications = $model->getByAgent((int) $this->user['id'], 50);
        jsonResponse($notifications);
    }

    // GET /agent/notifications/unread-count
    // Used by AgentNav to show the bell badge without loading all notifications
    public function unreadCount($params = []) {
        $model = new Notification($this->db);
        jsonResponse(['count' => $model->getUnreadCount((int) $this->user['id'])]);
    }

    // POST /agent/notifications/{id}/read
    public function markRead($params = []) {
        $id = $params['id'] ?? null;
        if (!$id) { http_response_code(400); jsonResponse(['error' => 'ID required']); return; }

        $model = new Notification($this->db);
        if ($model->markRead((int) $id, (int) $this->user['id'])) {
            jsonResponse(['success' => true]);
        } else {
            // Either already read or not found — both are OK from the client's perspective
            jsonResponse(['success' => true, 'note' => 'Already read or not found']);
        }
    }

    // POST /agent/notifications/read-all
    public function markAllRead($params = []) {
        $model   = new Notification($this->db);
        $updated = $model->markAllRead((int) $this->user['id']);
        jsonResponse(['success' => true, 'updated' => $updated]);
    }

    // POST /agent/notifications/read-selected  { ids: [1,2,3] }
    public function markSelectedRead($params = []) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids  = array_filter(array_map('intval', $data['ids'] ?? []), fn($id) => $id > 0);

        if (empty($ids)) { http_response_code(422); jsonResponse(['error' => 'No IDs provided']); return; }

        $model = new Notification($this->db);
        $updated = 0;
        foreach ($ids as $id) {
            if ($model->markRead($id, (int) $this->user['id'])) $updated++;
        }
        jsonResponse(['success' => true, 'updated' => $updated]);
    }

    // DELETE /agent/notifications/{id}
    public function destroy($params = []) {
        $id = $params['id'] ?? null;
        if (!$id) { http_response_code(400); jsonResponse(['error' => 'ID required']); return; }

        $model = new Notification($this->db);
        if ($model->delete((int) $id, (int) $this->user['id'])) {
            jsonResponse(['success' => true]);
        } else {
            http_response_code(404);
            jsonResponse(['error' => 'Notification not found']);
        }
    }

    // DELETE /agent/notifications  (clear all)
    public function destroyAll($params = []) {
        $model   = new Notification($this->db);
        $deleted = $model->deleteAll((int) $this->user['id']);
        jsonResponse(['success' => true, 'deleted' => $deleted]);
    }

    // POST /agent/notifications/delete-selected  { ids: [1,2,3] }
    public function destroySelected($params = []) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids  = array_filter(array_map('intval', $data['ids'] ?? []), fn($id) => $id > 0);

        if (empty($ids)) { http_response_code(422); jsonResponse(['error' => 'No IDs provided']); return; }

        $model   = new Notification($this->db);
        $deleted = $model->deleteSelected($ids, (int) $this->user['id']);
        jsonResponse(['success' => true, 'deleted' => $deleted]);
    }
}