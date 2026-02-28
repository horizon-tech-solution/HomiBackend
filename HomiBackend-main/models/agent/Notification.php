<?php
class Notification {
    private $conn;
    private $table = 'notifications';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByUser($userId, $unreadOnly = false) {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        if ($unreadOnly) {
            $sql .= " AND read_at IS NULL";
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnreadCount($userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ? AND read_at IS NULL");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    public function markAsRead($id, $userId) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET read_at = NOW() WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    public function delete($id, $userId) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    public function create($userId, $type, $title, $message, $data = null) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (user_id, type, title, message, data) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $type, $title, $message, $data ? json_encode($data) : null]);
    }
}