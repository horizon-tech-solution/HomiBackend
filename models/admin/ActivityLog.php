<?php
class ActivityLog {
    private $conn;
    private $table = 'activity_logs';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($category = null, $search = null) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        if ($category && $category !== 'All') {
            $categoryMap = [
                'Listings' => 'listing',
                'Agents' => 'agent',
                'Users' => 'user',
                'Reports' => 'report',
                'Settings' => 'settings'
            ];
            if (isset($categoryMap[$category])) {
                $sql .= " AND category = :category";
                $params[':category'] = $categoryMap[$category];
            }
        }
        if ($search) {
            $sql .= " AND (target LIKE :search OR detail LIKE :search OR actor LIKE :search)";
            $params[':search'] = "%$search%";
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($action, $actor, $target, $detail, $category) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (action, actor, target, detail, category) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$action, $actor, $target, $detail, $category]);
    }
}