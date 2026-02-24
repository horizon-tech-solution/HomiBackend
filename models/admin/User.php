<?php
class User {
    private $conn;
    private $table = 'users';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($role = null, $status = null, $search = null) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($role && $role !== 'all') {
            $sql .= " AND role = :role";
            $params[':role'] = $role;
        }
        if ($status) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        if ($search) {
            $sql .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search OR agency_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status, $reason = null) {
        if ($status === 'blocked') {
            $sql = "UPDATE {$this->table} SET status = 'blocked', block_reason = :reason, blocked_at = NOW() WHERE id = :id";
        } else {
            $sql = "UPDATE {$this->table} SET status = 'active', block_reason = NULL, blocked_at = NULL WHERE id = :id";
        }
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':reason' => $reason]);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getActivityLog($userId) {
        // This could be from inquiries, favorites, listings, etc.
        // For simplicity, we'll return recent activity from other tables.
        // Implementation depends on your data.
        return []; // placeholder
    }

    public function getRecentListings($userId) {
        $stmt = $this->conn->prepare("SELECT id, title, status, price FROM listings WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 5");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}