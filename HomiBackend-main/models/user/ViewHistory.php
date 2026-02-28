<?php
class ViewHistory {
    private $conn;
    private $table = 'view_history';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByUser($userId, $limit = 20) {
        $stmt = $this->conn->prepare("SELECT v.*, l.*, u.name as agent_name FROM {$this->table} v JOIN listings l ON v.listing_id = l.id JOIN users u ON l.user_id = u.id WHERE v.user_id = ? ORDER BY v.viewed_at DESC LIMIT ?");
        $stmt->bindParam(1, $userId);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function record($userId, $listingId) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (user_id, listing_id) VALUES (?, ?)");
        return $stmt->execute([$userId, $listingId]);
    }

    public function countByUser($userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    public function clearByUser($userId) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
}