<?php
class Document {
    private $conn;
    private $table = 'documents';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByUser($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByListing($listingId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE listing_id = ?");
        $stmt->execute([$listingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table} (user_id, listing_id, name, type, file_path, status) 
                VALUES (:user_id, :listing_id, :name, :type, :file_path, :status)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':user_id' => $data['user_id'] ?? null,
            ':listing_id' => $data['listing_id'] ?? null,
            ':name' => $data['name'],
            ':type' => $data['type'],
            ':file_path' => $data['file_path'],
            ':status' => $data['status'] ?? 'pending'
        ]);
    }

    public function updateStatus($id, $status) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET status = :status WHERE id = :id");
        return $stmt->execute([':id' => $id, ':status' => $status]);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
}