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
            'listings' => 'listing',
            'agents'   => 'agent',
            'users'    => 'user',
            'reports'  => 'report',
            'settings' => 'settings',
        ];
        $key = strtolower($category);
        if (isset($categoryMap[$key])) {
            $sql .= " AND category = :category";
            $params[':category'] = $categoryMap[$key];
        }
    }

    if ($search) {
        $sql .= " AND (target LIKE :search OR detail LIKE :search OR actor LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $sql .= " ORDER BY created_at DESC LIMIT 200";
    $stmt = $this->conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(fn($r) => [
        'id'     => $r['id'],
        'action' => $r['action'],
        'actor'  => $r['actor'],
        'target' => $r['target'],
        'detail' => $r['detail'] ?? '',
        'category' => $r['category'],
        'time'   => $r['created_at'],   // React expects 'time'
    ], $rows);
}
    public function create($action, $actor, $target, $detail, $category) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (action, actor, target, detail, category) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$action, $actor, $target, $detail, $category]);
    }
}