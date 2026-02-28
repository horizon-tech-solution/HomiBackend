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

    public function getByUserId($userId, $status = null, $search = null) {
    $sql = "SELECT l.* FROM {$this->table} l WHERE l.user_id = :user_id";
    $params = [':user_id' => $userId];
    if ($status) {
        $sql .= " AND l.status = :status";
        $params[':status'] = $status;
    }
    if ($search) {
        $sql .= " AND (l.title LIKE :search OR l.city LIKE :search)";
        $params[':search'] = "%$search%";
    }
    $sql .= " ORDER BY l.submitted_at DESC";
    $stmt = $this->conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get approved listings with optional filters (public)
 */
public function getPublicListings($filters = []) {
    $sql = "SELECT l.*, u.name as agent_name, u.agency_name, u.phone, u.email, u.verified
            FROM {$this->table} l
            JOIN users u ON l.user_id = u.id
            WHERE l.status = 'approved'";
    $params = [];

    if (!empty($filters['search'])) {
        $sql .= " AND (l.title LIKE :search OR l.city LIKE :search OR l.address LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['city'])) {
        $sql .= " AND l.city LIKE :city";
        $params[':city'] = '%' . $filters['city'] . '%';
    }
    if (!empty($filters['type']) && in_array($filters['type'], ['sale', 'rent'])) {
        $sql .= " AND l.transaction_type = :type";
        $params[':type'] = $filters['type'];
    }
    if (!empty($filters['min_price'])) {
        $sql .= " AND l.price >= :min_price";
        $params[':min_price'] = $filters['min_price'];
    }
    if (!empty($filters['max_price'])) {
        $sql .= " AND l.price <= :max_price";
        $params[':max_price'] = $filters['max_price'];
    }
    if (!empty($filters['bedrooms'])) {
        $sql .= " AND l.bedrooms >= :bedrooms";
        $params[':bedrooms'] = $filters['bedrooms'];
    }
    if (!empty($filters['bathrooms'])) {
        $sql .= " AND l.bathrooms >= :bathrooms";
        $params[':bathrooms'] = $filters['bathrooms'];
    }
    if (!empty($filters['property_type'])) {
        $sql .= " AND l.property_type = :property_type";
        $params[':property_type'] = $filters['property_type'];
    }

    $sql .= " ORDER BY l.submitted_at DESC";

    // Optional limit (e.g., for homepage)
    if (!empty($filters['limit'])) {
        $sql .= " LIMIT :limit";
        $params[':limit'] = (int)$filters['limit'];
    }

    $stmt = $this->conn->prepare($sql);
    foreach ($params as $key => &$val) {
        if ($key === ':limit') {
            $stmt->bindParam($key, $val, PDO::PARAM_INT);
        } else {
            $stmt->bindParam($key, $val);
        }
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}