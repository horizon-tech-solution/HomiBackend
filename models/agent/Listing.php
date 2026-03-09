<?php
// models/agent/Listing.php

class Listing {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // ── All listings for an agent ────────────────────────────────────────────

    public function getByAgent(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT
                l.id, l.title, l.price, l.transaction_type, l.property_type,
                l.address, l.city, l.region, l.coordinates,
                l.bedrooms, l.bathrooms, l.area, l.floor, l.total_floors,
                l.furnished, l.parking, l.generator,
                l.status, l.submitted_at, l.approved_at, l.rejected_reason,
                (SELECT lp.photo_url FROM listing_photos lp
                 WHERE lp.listing_id = l.id AND lp.is_cover = 1
                 LIMIT 1) AS cover_photo
             FROM listings l
             WHERE l.user_id = ?
             ORDER BY l.submitted_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Single listing belonging to agent ────────────────────────────────────

    public function getByIdAndAgent($id, int $userId) {
        $stmt = $this->db->prepare(
            "SELECT
                l.*,
                (SELECT lp.photo_url FROM listing_photos lp
                 WHERE lp.listing_id = l.id AND lp.is_cover = 1
                 LIMIT 1) AS cover_photo
             FROM listings l
             WHERE l.id = ? AND l.user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function create(int $userId, array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO listings
                (user_id, title, description, property_type, transaction_type,
                 price, address, city, region, coordinates,
                 bedrooms, bathrooms, area, year_built,
                 furnished, parking, generator,
                 status, submitted_at)
             VALUES
                (?, ?, ?, ?, ?,
                 ?, ?, ?, ?, ?,
                 ?, ?, ?, ?,
                 ?, ?, ?,
                 'pending', NOW())"
        );
        $stmt->execute([
            $userId,
            $data['title'],
            $data['description']      ?? null,
            $data['property_type']    ?? null,
            $data['transaction_type'] ?? null,
            $data['price']            ?? null,
            $data['address']          ?? null,
            $data['city']             ?? null,
            $data['region']           ?? null,
            $data['coordinates']      ?? null,
            isset($data['bedrooms'])   && $data['bedrooms']  !== '' ? (int)$data['bedrooms']  : null,
            isset($data['bathrooms'])  && $data['bathrooms'] !== '' ? (int)$data['bathrooms'] : null,
            isset($data['area'])       && $data['area']      !== '' ? (int)$data['area']      : 0,
            isset($data['year_built']) && $data['year_built']!== '' ? (int)$data['year_built']: null,
            $data['furnished']  ?? null,
            isset($data['parking'])   ? (int)$data['parking']   : 0,
            isset($data['generator']) ? (int)$data['generator'] : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function update($id, int $userId, array $data): bool {
        $allowed = [
            'title', 'description', 'property_type', 'transaction_type',
            'price', 'address', 'city', 'region', 'coordinates',
            'bedrooms', 'bathrooms', 'area', 'year_built',
            'furnished', 'parking', 'generator',
        ];

        $fields = [];
        $values = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = ($data[$field] === '' ? null : $data[$field]);
            }
        }

        if (empty($fields)) return false;

        // Re-submit for review whenever agent edits
        $fields[] = "`status` = 'pending'";

        $values[] = $id;
        $values[] = $userId;

        $stmt = $this->db->prepare(
            "UPDATE listings SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute($values);
    }

    // ── Delete (only pending / rejected) ─────────────────────────────────────

    public function delete($id, int $userId): bool {
        $stmt = $this->db->prepare(
            "DELETE FROM listings
             WHERE id = ? AND user_id = ? AND status IN ('pending', 'rejected')"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    // ── Aggregates ────────────────────────────────────────────────────────────

    public function getTotalViews(int $userId): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(vh.id)
             FROM view_history vh
             JOIN listings l ON vh.listing_id = l.id
             WHERE l.user_id = ?"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function getTotalLeads(int $userId): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(i.id) FROM inquiries i
             WHERE i.to_user_id = ?"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}