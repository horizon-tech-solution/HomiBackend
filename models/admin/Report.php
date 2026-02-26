<?php
class Report {
    private $conn;
    private $table = 'reports';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($status = null, $search = null) {
        $sql = "SELECT r.*,
                       u.name          AS reporter_name,
                       u.email         AS reporter_email,
                       su.name         AS subject_name,
                       su.email        AS subject_email,
                       su.phone        AS subject_phone,
                       su.role         AS subject_role,
                       su.reports_count AS subject_reports
                FROM {$this->table} r
                LEFT JOIN users u  ON r.reported_by_user_id = u.id
                LEFT JOIN users su ON r.subject_type = 'user' AND r.subject_id = su.id
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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'shape'], $rows);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare(
            "SELECT r.*,
                    u.name          AS reporter_name,
                    u.email         AS reporter_email,
                    su.name         AS subject_name,
                    su.email        AS subject_email,
                    su.phone        AS subject_phone,
                    su.role         AS subject_role,
                    su.reports_count AS subject_reports
             FROM {$this->table} r
             LEFT JOIN users u  ON r.reported_by_user_id = u.id
             LEFT JOIN users su ON r.subject_type = 'user' AND r.subject_id = su.id
             WHERE r.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->shape($row) : null;
    }

    private function shape($r) {
        return [
            'id'            => $r['id'],
            'type'          => $r['type'],
            'priority'      => $r['priority']     ?? 'medium',
            'status'        => $r['status'],
            'subjectType'   => $r['subject_type'],
            'subjectId'     => $r['subject_id'],
            'subjectName'   => $r['subject_name']  ?? ('Subject #' . $r['subject_id']),
            'reportCount'   => 1,
            'description'   => $r['description']  ?? '',
            'adminNotes'    => $r['admin_notes']  ?? '',
            'resolution'    => $r['resolution']   ?? '',
            'submittedAt'   => $r['submitted_at'],
            'resolvedAt'    => $r['resolved_at']  ?? null,
            'linkedListing' => null,
            'evidence'      => $r['evidence']
                                    ? json_decode($r['evidence'], true) ?? []
                                    : [],
            'messages'      => $r['messages']
                                    ? json_decode($r['messages'], true) ?? []
                                    : [],
            'reportedBy' => [
                'name'  => $r['reporter_name']  ?? '',
                'email' => $r['reporter_email'] ?? '',
            ],
            'subject' => [
                'name'    => $r['subject_name']    ?? ('Subject #' . $r['subject_id']),
                'email'   => $r['subject_email']   ?? '',
                'phone'   => $r['subject_phone']   ?? null,
                'role'    => $r['subject_role']    ?? '',
                'reports' => (int) ($r['subject_reports'] ?? 0),
            ],
        ];
    }

    public function resolve($id, $resolution) {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
             SET status = 'resolved', resolved_at = NOW(), resolution = :resolution
             WHERE id = :id"
        );
        return $stmt->execute([':id' => $id, ':resolution' => $resolution]);
    }

    public function dismiss($id, $note) {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
             SET status = 'dismissed', resolved_at = NOW(), resolution = :note
             WHERE id = :id"
        );
        return $stmt->execute([':id' => $id, ':note' => $note]);
    }

    public function saveNotes($id, $notes) {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET admin_notes = :notes WHERE id = :id"
        );
        return $stmt->execute([':id' => $id, ':notes' => $notes]);
    }

    public function blockSubject($id) {
        $stmt = $this->conn->prepare(
            "UPDATE users u
             JOIN {$this->table} r ON r.subject_id = u.id
             SET u.status = 'blocked', u.blocked_at = NOW()
             WHERE r.id = :id AND r.subject_type = 'user'"
        );
        return $stmt->execute([':id' => $id]);
    }

    public function deleteListing($id) {
        $stmt = $this->conn->prepare(
            "DELETE l FROM listings l
             JOIN {$this->table} r ON r.subject_id = l.id
             WHERE r.id = :id AND r.subject_type = 'listing'"
        );
        return $stmt->execute([':id' => $id]);
    }
}