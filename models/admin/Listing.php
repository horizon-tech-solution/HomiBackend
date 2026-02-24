<?php
class Listing {
    private $conn;
    private $table = 'listings';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($status = null, $search = null) {
        $sql = "SELECT l.*, u.name as submitter_name, u.email as submitter_email 
                FROM {$this->table} l 
                JOIN users u ON l.user_id = u.id 
                WHERE 1=1";
        $params = [];
        if ($status) {
            $sql .= " AND l.status = :status";
            $params[':status'] = $status;
        }
        if ($search) {
            $sql .= " AND (l.title LIKE :search OR l.city LIKE :search OR u.name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        $sql .= " ORDER BY l.submitted_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT l.*, u.name as submitter_name, u.email, u.phone, u.role, u.agency_name 
                                      FROM {$this->table} l 
                                      JOIN users u ON l.user_id = u.id 
                                      WHERE l.id = ?");
        $stmt->execute([$id]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($listing) {
            // Get photos
            $photoStmt = $this->conn->prepare("SELECT * FROM listing_photos WHERE listing_id = ? ORDER BY sort_order");
            $photoStmt->execute([$id]);
            $listing['photos'] = $photoStmt->fetchAll(PDO::FETCH_ASSOC);
            // Get documents
            $docStmt = $this->conn->prepare("SELECT * FROM documents WHERE listing_id = ?");
            $docStmt->execute([$id]);
            $listing['documents'] = $docStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $listing;
    }

    public function approve($id, $adminNotes = '') {
        $sql = "UPDATE {$this->table} SET status = 'approved', approved_at = NOW(), admin_notes = :notes WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':notes' => $adminNotes]);
    }

    public function reject($id, $reason) {
        $sql = "UPDATE {$this->table} SET status = 'rejected', rejected_reason = :reason, admin_notes = :notes WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':reason' => $reason, ':notes' => $reason]);
    }

    public function requestChanges($id, $message) {
        $sql = "UPDATE {$this->table} SET status = 'pending', requested_changes = :message WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':message' => $message]);
    }
}