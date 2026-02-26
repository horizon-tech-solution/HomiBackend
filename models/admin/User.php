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

        if ($role === 'blocked') {
            $sql .= " AND status = 'blocked'";
        } elseif ($role === 'user' || $role === 'agent') {
            $sql .= " AND role = :role AND status != 'blocked'";
            $params[':role'] = $role;
        }

        if ($search) {
            $sql .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search OR agency_name LIKE :search)";
            $params[':search'] = "%$search%";
        }

        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'shape'], $rows);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->shape($row) : null;
    }

    private function shape($u) {
        return [
            'id'          => $u['id'],
            'name'        => $u['name'],
            'email'       => $u['email'],
            'phone'       => $u['phone']        ?? '',
            'role'        => $u['role'],
            'status'      => $u['status'],
            'verified'    => (bool) ($u['verified'] ?? false),
            'agencyName'  => $u['agency_name']  ?? null,
            'joined'      => $u['created_at'],
            'lastActive'  => $u['last_active']  ?? '—',
            'blockReason' => $u['block_reason'] ?? null,
            'blockedAt'   => $u['blocked_at']   ?? null,
            'listings'    => (int) ($u['listings_count']  ?? 0),
            'favorites'   => (int) ($u['favorites_count'] ?? 0),
            'inquiries'   => (int) ($u['inquiries_count'] ?? 0),
            'reports'     => (int) ($u['reports_count']   ?? 0),
            'recentListings' => [],
            'activityLog'    => [],
        ];
    }

    public function updateStatus($id, $status, $reason = null) {
        if ($status === 'blocked') {
            $sql = "UPDATE {$this->table} SET status = 'blocked', block_reason = :reason, blocked_at = NOW() WHERE id = :id";
            return $this->conn->prepare($sql)->execute([':id' => $id, ':reason' => $reason]);
        } else {
            $sql = "UPDATE {$this->table} SET status = 'active', block_reason = NULL, blocked_at = NULL WHERE id = :id";
            return $this->conn->prepare($sql)->execute([':id' => $id]);
        }
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getRecentListings($userId) {
        $stmt = $this->conn->prepare(
            "SELECT id, title, status, price FROM listings 
             WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 5"
        );
        $stmt->execute([$userId]);
        return array_map(fn($l) => [
            'id'     => $l['id'],
            'title'  => $l['title'],
            'status' => $l['status'],
            'price'  => number_format($l['price']) . ' XAF',
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getActivityLog($userId) {
        return [];
    }
}