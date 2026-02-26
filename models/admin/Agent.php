<?php
class Agent {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($status = null, $search = null) {
        $sql = "SELECT 
                    id, name, email, phone, role, status,
                    agency_name, agency_type, license_number,
                    years_experience, bio, verification_status,
                    verified_at, rejected_reason, suspended_reason,
                    listings_count, reports_count, created_at
                FROM users 
                WHERE role = 'agent'";
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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) return [];

        // Fetch all documents in one query
        $ids          = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $documents    = [];

        $docStmt = $this->conn->prepare(
            "SELECT id, user_id, name, type, file_path, status 
             FROM documents 
             WHERE user_id IN ($placeholders) AND listing_id IS NULL"
        );
        $docStmt->execute($ids);
        foreach ($docStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $documents[$d['user_id']][] = [
                'id'     => $d['id'],
                'name'   => $d['name'],
                'type'   => $d['type'],
                'url'    => $d['file_path'],
                'status' => $d['status'],
            ];
        }

        return array_map(function($a) use ($documents) {
            $id = $a['id'];
            return [
                'id'               => $id,
                'name'             => $a['name'],
                'email'            => $a['email'],
                'phone'            => $a['phone']            ?? '',
                'status'           => $a['verification_status'] ?? 'pending',
                'agencyName'       => $a['agency_name']      ?? '',
                'agencyType'       => $a['agency_type']      ?? 'individual',
                'licenseNumber'    => $a['license_number']   ?? '',
                'yearsExperience'  => (int) ($a['years_experience'] ?? 0),
                'bio'              => $a['bio']              ?? '',
                'city'             => '',   // not in users table — add if needed
                'region'           => '',
                'adminNotes'       => '',   // not in users table
                'rejectionReason'  => $a['rejected_reason']  ?? '',
                'suspendedReason'  => $a['suspended_reason'] ?? '',
                'submittedAt'      => $a['created_at'],
                'verifiedAt'       => $a['verified_at']      ?? null,
                'reports'          => (int) ($a['reports_count']  ?? 0),
                'documents'        => $documents[$id] ?? [],
                'stats' => [
                    'listings' => (int) ($a['listings_count'] ?? 0),
                    'reviews'  => 0,
                    'rating'   => null,
                ],
            ];
        }, $rows);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare(
            "SELECT id, name, email, phone, role, status,
                    agency_name, agency_type, license_number,
                    years_experience, bio, verification_status,
                    verified_at, rejected_reason, suspended_reason,
                    listings_count, reports_count, created_at
             FROM users WHERE id = ? AND role = 'agent'"
        );
        $stmt->execute([$id]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) return null;

        $docStmt = $this->conn->prepare(
            "SELECT id, name, type, file_path, status 
             FROM documents WHERE user_id = ? AND listing_id IS NULL"
        );
        $docStmt->execute([$id]);
        $documents = array_map(fn($d) => [
            'id'     => $d['id'],
            'name'   => $d['name'],
            'type'   => $d['type'],
            'url'    => $d['file_path'],
            'status' => $d['status'],
        ], $docStmt->fetchAll(PDO::FETCH_ASSOC));

        return [
            'id'               => $a['id'],
            'name'             => $a['name'],
            'email'            => $a['email'],
            'phone'            => $a['phone']            ?? '',
            'status'           => $a['verification_status'] ?? 'pending',
            'agencyName'       => $a['agency_name']      ?? '',
            'agencyType'       => $a['agency_type']      ?? 'individual',
            'licenseNumber'    => $a['license_number']   ?? '',
            'yearsExperience'  => (int) ($a['years_experience'] ?? 0),
            'bio'              => $a['bio']              ?? '',
            'city'             => '',
            'region'           => '',
            'adminNotes'       => '',
            'rejectionReason'  => $a['rejected_reason']  ?? '',
            'suspendedReason'  => $a['suspended_reason'] ?? '',
            'submittedAt'      => $a['created_at'],
            'verifiedAt'       => $a['verified_at']      ?? null,
            'reports'          => (int) ($a['reports_count']  ?? 0),
            'documents'        => $documents,
            'stats' => [
                'listings' => (int) ($a['listings_count'] ?? 0),
                'reviews'  => 0,
                'rating'   => null,
            ],
        ];
    }

    public function verify($id, $adminNotes = '') {
        $stmt = $this->conn->prepare(
            "UPDATE users SET verification_status = 'verified', verified = 1, verified_at = NOW() 
             WHERE id = :id AND role = 'agent'"
        );
        return $stmt->execute([':id' => $id]);
    }

    public function reject($id, $reason) {
        $stmt = $this->conn->prepare(
            "UPDATE users SET verification_status = 'rejected', rejected_reason = :reason 
             WHERE id = :id AND role = 'agent'"
        );
        return $stmt->execute([':id' => $id, ':reason' => $reason]);
    }

    public function suspend($id, $reason) {
        $stmt = $this->conn->prepare(
            "UPDATE users SET verification_status = 'suspended', suspended_reason = :reason 
             WHERE id = :id AND role = 'agent'"
        );
        return $stmt->execute([':id' => $id, ':reason' => $reason]);
    }

    public function reinstate($id) {
        $stmt = $this->conn->prepare(
            "UPDATE users SET verification_status = 'verified', suspended_reason = NULL 
             WHERE id = :id AND role = 'agent'"
        );
        return $stmt->execute([':id' => $id]);
    }
}