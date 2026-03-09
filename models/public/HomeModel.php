<?php
// models/public/HomeModel.php

class HomeModel {
    private PDO $db;

    private array $fallbacks = [
        'villa'      => 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=600&h=400&fit=crop',
        'apartment'  => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=600&h=400&fit=crop',
        'house'      => 'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=600&h=400&fit=crop',
        'duplex'     => 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=600&h=400&fit=crop',
        'commercial' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=600&h=400&fit=crop',
        'land'       => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?w=600&h=400&fit=crop',
        'other'      => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=600&h=400&fit=crop',
    ];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // ── Batch-fetch photos for multiple listing IDs (MySQL 5.7 safe) ─────────
    private function fetchPhotos(array $ids): array {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT listing_id, photo_url
             FROM listing_photos
             WHERE listing_id IN ({$placeholders})
             ORDER BY listing_id, is_cover DESC, sort_order ASC"
        );
        $stmt->execute(array_values($ids));
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int)$r['listing_id']][] = $r['photo_url'];
        }
        return $map;
    }

    private function applyFallback(array &$row, array $photoMap): void {
        $id     = (int)$row['id'];
        $photos = $photoMap[$id] ?? [];

        if (empty($photos)) {
            $type   = $row['property_type'] ?? 'other';
            $photos = [$this->fallbacks[$type] ?? $this->fallbacks['other']];
        }

        $row['cover_photo'] = $photos[0];
        $row['photos']      = $photos;
        $row['parking']     = (bool)($row['parking']   ?? false);
        $row['generator']   = (bool)($row['generator'] ?? false);
    }

    // ── Featured approved listings ────────────────────────────────────────────
    public function getFeaturedListings(int $limit = 6): array {
        $stmt = $this->db->prepare("
            SELECT
                l.id, l.title, l.price, l.address, l.city,
                l.property_type, l.transaction_type,
                l.bedrooms, l.bathrooms, l.area,
                l.furnished, l.parking, l.generator,
                l.submitted_at,
                u.name       AS owner_name,
                u.role       AS owner_role,
                u.avatar_url AS owner_avatar,
                u.verified   AS owner_verified
            FROM listings l
            JOIN users u ON u.id = l.user_id
            WHERE l.status IN ('approved', 'pending')
            ORDER BY l.approved_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) return [];

        $photoMap = $this->fetchPhotos(array_column($rows, 'id'));
        foreach ($rows as &$row) {
            $this->applyFallback($row, $photoMap);
        }
        return $rows;
    }

    // ── Platform stats ────────────────────────────────────────────────────────
    public function getPlatformStats(): array {
        $listings = (int)$this->db->query("SELECT COUNT(*) FROM listings WHERE status IN ('approved', 'pending')")->fetchColumn();
        $agents   = (int)$this->db->query("SELECT COUNT(*) FROM users WHERE role = 'agent' AND verification_status = 'verified'")->fetchColumn();
        $sales    = (int)$this->db->query("SELECT COUNT(*) FROM listings WHERE status IN ('approved', 'pending') AND transaction_type = 'sale'")->fetchColumn();
        $cities   = (int)$this->db->query("SELECT COUNT(DISTINCT city) FROM listings WHERE status IN ('approved', 'pending')")->fetchColumn();

        return [
            'listings' => $listings,
            'agents'   => $agents,
            'sales'    => $sales,
            'cities'   => $cities,
        ];
    }

    // ── Top verified agents ───────────────────────────────────────────────────
    public function getTopAgents(int $limit = 3): array {
        $stmt = $this->db->prepare("
            SELECT
                u.id, u.name, u.city,
                u.agency_name, u.agency_type,
                u.years_experience, u.bio, u.avatar_url,
                u.verified, u.verification_status,
                COALESCE(COUNT(DISTINCT l.id), 0)        AS listings_count,
                COALESCE(ROUND(AVG(r.rating), 1), 0)     AS avg_rating,
                COUNT(DISTINCT r.id)                     AS review_count
            FROM users u
            LEFT JOIN listings l ON l.user_id = u.id
                AND l.status IN ('approved', 'pending')
            LEFT JOIN reviews r ON r.agent_id = u.id
            WHERE u.role = 'agent'
              AND u.verification_status = 'verified'
              AND u.status = 'active'
            GROUP BY u.id
            ORDER BY listings_count DESC, avg_rating DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['verified']       = (bool)$row['verified'];
            $row['avg_rating']     = (float)$row['avg_rating'];
            $row['review_count']   = (int)$row['review_count'];
            $row['listings_count'] = (int)$row['listings_count'];
            if (!empty($row['avatar_url']) && str_starts_with($row['avatar_url'], '/uploads/')) {
                $row['avatar_url'] = 'http://localhost:8000' . $row['avatar_url'];
            }
        }
        return $rows;
    }
}