<?php
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

    public function index() {
        $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';
        $notificationModel = new Notification($this->db);
        $notifications = $notificationModel->getByUser($this->user['id'], $unreadOnly);
        jsonResponse(['data' => $notifications]);
    }

    public function markRead($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $notificationModel = new Notification($this->db);
        if ($notificationModel->markAsRead($id, $this->user['id'])) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Notification not found'], 404);
        }
    }

    public function destroy($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $notificationModel = new Notification($this->db);
        if ($notificationModel->delete($id, $this->user['id'])) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Notification not found'], 404);
        }
    }
}