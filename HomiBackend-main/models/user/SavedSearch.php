<?php
class SavedSearch {
    private $conn;
    private $table = 'saved_searches';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByUser($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id, $userId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($userId, $name, $criteria) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (user_id, name, criteria) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $name, json_encode($criteria)]);
    }

    public function delete($id, $userId) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    public function countByUser($userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
}