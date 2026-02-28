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

    public function getAgentProfile($id) {
    $stmt = $this->conn->prepare("SELECT id, name, email, phone, agency_name, agency_type, license_number, years_experience, bio, verification_status, created_at, listings_count, inquiries_count, profile_image FROM users WHERE id = ? AND role = 'agent'");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get verified agents with optional filters (public)
 */
public function getPublicAgents($filters = []) {
    $sql = "SELECT id, name, email, phone, agency_name, license_number, years_experience, bio, region, city, listings_count, created_at
            FROM users
            WHERE role = 'agent' AND verification_status = 'verified'";
    $params = [];

    if (!empty($filters['search'])) {
        $sql .= " AND (name LIKE :search OR agency_name LIKE :search OR city LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['location'])) {
        $sql .= " AND (city LIKE :location OR region LIKE :location)";
        $params[':location'] = '%' . $filters['location'] . '%';
    }

    $sql .= " ORDER BY listings_count DESC";

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

/**
 * Get a single agent profile with their recent listings
 */
public function getPublicAgentById($id) {
    $stmt = $this->conn->prepare("SELECT id, name, email, phone, agency_name, license_number, years_experience, bio, region, city, listings_count, created_at FROM users WHERE id = ? AND role = 'agent' AND verification_status = 'verified'");
    $stmt->execute([$id]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($agent) {
        // Get agent's recent approved listings
        $listingStmt = $this->conn->prepare("SELECT id, title, price, transaction_type, city, bedrooms, bathrooms, area, property_type, (SELECT photo_url FROM listing_photos WHERE listing_id = listings.id ORDER BY is_cover DESC, sort_order LIMIT 1) as image FROM listings WHERE user_id = ? AND status = 'approved' ORDER BY submitted_at DESC LIMIT 5");
        $listingStmt->execute([$id]);
        $agent['listings'] = $listingStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $agent;
}
}