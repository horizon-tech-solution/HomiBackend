<?php
class ViewHistory {
    private $conn;
    private $table = 'view_history';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByUser($userId, $limit = 20) {
        $stmt = $this->conn->prepare(
            "SELECT
                v.id,
                v.viewed_at,
                l.id          AS listing_id,
                l.title,
                l.price,
                l.city,
                l.region,
                l.transaction_type,
                l.bedrooms,
                l.bathrooms,
                l.area,
                l.property_type,
                (SELECT lp.photo_url FROM listing_photos lp
                 WHERE lp.listing_id = l.id AND lp.is_cover = 1
                 LIMIT 1)     AS photo_url
             FROM {$this->table} v
             JOIN listings l ON v.listing_id = l.id
             WHERE v.user_id = ?
             ORDER BY v.viewed_at DESC
             LIMIT ?"
        );
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalise photo URL
        foreach ($rows as &$r) {
            if (!empty($r['photo_url']) && str_starts_with($r['photo_url'], '/uploads/')) {
                $r['photo_url'] = 'https://homibackend-production.up.railway.app/' . $r['photo_url'];
            }
        }
        unset($r);

        return $rows;
    }

    // Upsert — update viewed_at if already exists so it bubbles to top
    public function record($userId, $listingId): bool {
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table} (user_id, listing_id, viewed_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE viewed_at = NOW()"
        );
        return $stmt->execute([$userId, $listingId]);
    }

    public function countByUser($userId): int {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function clearByUser($userId): bool {
        $stmt = $this->conn->prepare(
            "DELETE FROM {$this->table} WHERE user_id = ?"
        );
        return $stmt->execute([$userId]);
    }
}