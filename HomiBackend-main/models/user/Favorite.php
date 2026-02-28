<?php
class Favorite {
    private $conn;
    private $table = 'favorites';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByUser($userId) {
        $stmt = $this->conn->prepare("SELECT f.*, l.*, u.name as agent_name FROM {$this->table} f JOIN listings l ON f.listing_id = l.id JOIN users u ON l.user_id = u.id WHERE f.user_id = ? ORDER BY f.created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add($userId, $listingId) {
        $check = $this->conn->prepare("SELECT * FROM {$this->table} WHERE user_id = ? AND listing_id = ?");
        $check->execute([$userId, $listingId]);
        if ($check->rowCount() > 0) {
            return true;
        }
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (user_id, listing_id) VALUES (?, ?)");
        return $stmt->execute([$userId, $listingId]);
    }

    public function remove($userId, $listingId) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE user_id = ? AND listing_id = ?");
        return $stmt->execute([$userId, $listingId]);
    }

    public function countByUser($userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
}