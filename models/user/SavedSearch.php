<?php
class SavedSearch {
    private $conn;
    private $table = 'saved_searches';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByUser($userId): array {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id, $userId) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($userId, $name, $criteria): bool {
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table} (user_id, name, criteria) VALUES (?, ?, ?)"
        );
        return $stmt->execute([$userId, $name, json_encode($criteria)]);
    }

    // Auto-save: upsert by criteria hash so the same search isn't duplicated.
    // Updates the timestamp so it bubbles to top on re-search.
    public function autoSave($userId, $criteria): void {
        if (empty($criteria)) return;

        // Stable JSON key order for consistent hashing
        ksort($criteria);
        $criteriaJson = json_encode($criteria, JSON_UNESCAPED_UNICODE);
        $hash         = md5($criteriaJson);

        // Human-readable name from criteria
        $parts = [];
        if (!empty($criteria['q']))           $parts[] = $criteria['q'];
        if (!empty($criteria['city']))        $parts[] = $criteria['city'];
        if (!empty($criteria['listingType'])) $parts[] = ucfirst($criteria['listingType']);
        if (!empty($criteria['bedrooms']))    $parts[] = $criteria['bedrooms'] . ' beds';
        $name = $parts ? implode(' · ', $parts) : 'Search';

        // Check if this exact criteria hash already exists
        $check = $this->conn->prepare(
            "SELECT id FROM {$this->table}
             WHERE user_id = ? AND JSON_UNQUOTE(JSON_EXTRACT(criteria, '$.hash')) = ?
             LIMIT 1"
        );
        $check->execute([$userId, $hash]);
        $existing = $check->fetchColumn();

        if ($existing) {
            // Bump the timestamp so it shows as most recent
            $this->conn->prepare(
                "UPDATE {$this->table} SET created_at = NOW() WHERE id = ?"
            )->execute([$existing]);
        } else {
            // Embed the hash inside criteria for future dedup
            $criteria['hash'] = $hash;
            $this->conn->prepare(
                "INSERT INTO {$this->table} (user_id, name, criteria, created_at)
                 VALUES (?, ?, ?, NOW())"
            )->execute([$userId, $name, json_encode($criteria, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function delete($id, $userId): bool {
        $stmt = $this->conn->prepare(
            "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $userId]);
    }

    public function countByUser($userId): int {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}