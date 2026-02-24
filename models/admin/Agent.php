<?php
class Agent {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($status = null, $search = null) {
        $sql = "SELECT * FROM users WHERE role = 'agent'";
        $params = [];
        if ($status) {
            $sql .= " AND verification_status = :status";
            $params[':status'] = $status;
        }
        if ($search) {
            $sql .= " AND (name LIKE :search OR email LIKE :search OR agency_name LIKE :search OR license_number LIKE :search)";
            $params[':search'] = "%$search%";
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'agent'");
        $stmt->execute([$id]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($agent) {
            // Get documents
            $docStmt = $this->conn->prepare("SELECT * FROM documents WHERE user_id = ?");
            $docStmt->execute([$id]);
            $agent['documents'] = $docStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $agent;
    }

    public function verify($id, $adminNotes = '') {
        $sql = "UPDATE users SET verification_status = 'verified', verified_at = NOW(), admin_notes = :notes WHERE id = :id AND role = 'agent'";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':notes' => $adminNotes]);
    }

    public function reject($id, $reason) {
        $sql = "UPDATE users SET verification_status = 'rejected', rejected_reason = :reason WHERE id = :id AND role = 'agent'";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':reason' => $reason]);
    }

    public function suspend($id, $reason) {
        $sql = "UPDATE users SET verification_status = 'suspended', suspended_reason = :reason WHERE id = :id AND role = 'agent'";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':reason' => $reason]);
    }

    public function reinstate($id) {
        $sql = "UPDATE users SET verification_status = 'verified', suspended_reason = NULL WHERE id = :id AND role = 'agent'";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}