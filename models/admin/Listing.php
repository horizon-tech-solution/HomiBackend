<?php
class Listing {
    private $conn;
    private $table = 'listings';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($status = null, $search = null) {
        $sql = "SELECT 
                    l.id, l.title, l.description, l.status,
                    l.property_type, l.transaction_type, l.price,
                    l.address, l.city, l.region,
                    l.bedrooms, l.bathrooms, l.area,
                    l.furnished, l.parking, l.generator,
                    l.submitted_at, l.admin_notes, l.fraud_signals,
                    u.name  AS submitter_name,
                    u.email AS submitter_email,
                    u.phone, u.role, u.agency_name,
                    u.verified, u.created_at AS user_joined,
                    u.listings_count, u.reports_count
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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) return [];

        // Fetch all photos in one query
        $ids          = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $photos       = [];
        $documents    = [];

        $photoStmt = $this->conn->prepare(
            "SELECT listing_id, photo_url 
             FROM listing_photos 
             WHERE listing_id IN ($placeholders) 
             ORDER BY sort_order ASC"
        );
        $photoStmt->execute($ids);
        foreach ($photoStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $photos[$p['listing_id']][] = $p['photo_url'];
        }

        // Fetch all documents in one query
        $docStmt = $this->conn->prepare(
            "SELECT listing_id, name, status 
             FROM documents 
             WHERE listing_id IN ($placeholders)"
        );
        $docStmt->execute($ids);
        foreach ($docStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $documents[$d['listing_id']][] = [
                'name'   => $d['name'],
                'status' => $d['status'],
            ];
        }

        return array_map(function($l) use ($photos, $documents) {
            $id = $l['id'];
            return [
                'id'          => $id,
                'title'       => $l['title'],
                'description' => $l['description'] ?? '',
                'status'      => $l['status'],
                'price'       => (float) $l['price'],
                'transaction' => $l['transaction_type'],
                'type'        => $l['property_type'],
                'adminNotes'  => $l['admin_notes'] ?? '',
                'submittedAt' => $l['submitted_at'],
                'fraudSignals' => $l['fraud_signals']
                                    ? json_decode($l['fraud_signals'], true)
                                    : [],
                'location' => [
                    'city'    => $l['city']    ?? '',
                    'address' => $l['address'] ?? '',
                    'region'  => $l['region']  ?? '',
                ],
                'specs' => [
                    'area'      => (int)  ($l['area']      ?? 0),
                    'bedrooms'  =>        $l['bedrooms']   ?? null,
                    'bathrooms' =>        $l['bathrooms']  ?? null,
                    'furnished' =>        $l['furnished']  ?? null,
                    'parking'   => (bool) ($l['parking']   ?? false),
                    'generator' => (bool) ($l['generator'] ?? false),
                ],
                'photos'    => $photos[$id]    ?? [],
                'documents' => $documents[$id] ?? [],
                'submittedBy' => [
                    'name'          => $l['submitter_name']  ?? '',
                    'email'         => $l['submitter_email'] ?? '',
                    'phone'         => $l['phone']           ?? '',
                    'role'          => $l['role']            ?? '',
                    'agencyName'    => $l['agency_name']     ?? null,
                    'verified'      => (bool) ($l['verified'] ?? false),
                    'joined'        => $l['user_joined']     ?? '',
                    'totalListings' => (int) ($l['listings_count'] ?? 0),
                    'reports'       => (int) ($l['reports_count']  ?? 0),
                ],
            ];
        }, $rows);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare(
            "SELECT l.*, 
                    u.name AS submitter_name, u.email AS submitter_email,
                    u.phone, u.role, u.agency_name, u.verified,
                    u.created_at AS user_joined,
                    u.listings_count, u.reports_count
             FROM {$this->table} l
             JOIN users u ON l.user_id = u.id
             WHERE l.id = ?"
        );
        $stmt->execute([$id]);
        $l = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$l) return null;

        $photoStmt = $this->conn->prepare(
            "SELECT photo_url FROM listing_photos WHERE listing_id = ? ORDER BY sort_order ASC"
        );
        $photoStmt->execute([$id]);
        $photos = array_column($photoStmt->fetchAll(PDO::FETCH_ASSOC), 'photo_url');

        $docStmt = $this->conn->prepare(
            "SELECT name, status FROM documents WHERE listing_id = ?"
        );
        $docStmt->execute([$id]);
        $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'id'          => $l['id'],
            'title'       => $l['title'],
            'description' => $l['description'] ?? '',
            'status'      => $l['status'],
            'price'       => (float) $l['price'],
            'transaction' => $l['transaction_type'],
            'type'        => $l['property_type'],
            'adminNotes'  => $l['admin_notes'] ?? '',
            'submittedAt' => $l['submitted_at'],
            'fraudSignals' => $l['fraud_signals']
                                ? json_decode($l['fraud_signals'], true)
                                : [],
            'location' => [
                'city'    => $l['city']    ?? '',
                'address' => $l['address'] ?? '',
                'region'  => $l['region']  ?? '',
            ],
            'specs' => [
                'area'      => (int)  ($l['area']      ?? 0),
                'bedrooms'  =>        $l['bedrooms']   ?? null,
                'bathrooms' =>        $l['bathrooms']  ?? null,
                'furnished' =>        $l['furnished']  ?? null,
                'parking'   => (bool) ($l['parking']   ?? false),
                'generator' => (bool) ($l['generator'] ?? false),
            ],
            'photos'    => $photos,
            'documents' => $documents,
            'submittedBy' => [
                'name'          => $l['submitter_name']  ?? '',
                'email'         => $l['submitter_email'] ?? '',
                'phone'         => $l['phone']           ?? '',
                'role'          => $l['role']            ?? '',
                'agencyName'    => $l['agency_name']     ?? null,
                'verified'      => (bool) ($l['verified'] ?? false),
                'joined'        => $l['user_joined']     ?? '',
                'totalListings' => (int) ($l['listings_count'] ?? 0),
                'reports'       => (int) ($l['reports_count']  ?? 0),
            ],
        ];
    }

    public function approve($id, $adminNotes = '') {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} 
             SET status = 'approved', approved_at = NOW(), admin_notes = :notes 
             WHERE id = :id"
        );
        return $stmt->execute([':id' => $id, ':notes' => $adminNotes]);
    }

    public function reject($id, $reason) {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} 
             SET status = 'rejected', rejected_reason = :reason, admin_notes = :notes 
             WHERE id = :id"
        );
        return $stmt->execute([':id' => $id, ':reason' => $reason, ':notes' => $reason]);
    }

    public function requestChanges($id, $message) {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} 
             SET status = 'pending', requested_changes = :message 
             WHERE id = :id"
        );
        return $stmt->execute([':id' => $id, ':message' => $message]);
    }
}