<?php
class Report {
    private $conn;
    private $table = 'reports';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($status = null, $search = null) {
        $sql = "SELECT r.*, u.name as reporter_name, u.email as reporter_email 
                FROM {$this->table} r 
                JOIN users u ON r.reported_by_user_id = u.id 
                WHERE 1=1";
        $params = [];
        if ($status && $status !== 'all') {
            $sql .= " AND r.status = :status";
            $params[':status'] = $status;
        }
        if ($search) {
            $sql .= " AND (r.description LIKE :search OR u.name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        $sql .= " ORDER BY r.submitted_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT r.*, u.name as reporter_name, u.email as reporter_email 
                                      FROM {$this->table} r 
                                      JOIN users u ON r.reported_by_user_id = u.id 
                                      WHERE r.id = ?");
        $stmt->execute([$id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($report) {
            // Decode JSON fields
            $report['evidence'] = json_decode($report['evidence'], true) ?? [];
            $report['messages'] = json_decode($report['messages'], true) ?? [];
        }
        return $report;
    }

    public function resolve($id, $resolution) {
        $sql = "UPDATE {$this->table} SET status = 'resolved', resolved_at = NOW(), resolution = :resolution WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':resolution' => $resolution]);
    }

    public function dismiss($id, $note) {
        $sql = "UPDATE {$this->table} SET status = 'dismissed', resolved_at = NOW(), resolution = :note WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':note' => $note]);
    }
}