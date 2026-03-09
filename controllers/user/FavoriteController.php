<?php
// controllers/user/FavoriteController.php

class FavoriteController {
    private PDO $db;
    private array $user = [];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // Called by router after auth middleware
    public function setUser(array $user): void {
        $this->user = $user;
    }

    // GET /user/favorites
    public function index(array $params = []): void {
        ob_start();
        try {
            $userId = (int)($this->user['id'] ?? 0);

            $stmt = $this->db->prepare("
                SELECT
                    l.id, l.title, l.price, l.address, l.city, l.region,
                    l.transaction_type, l.property_type,
                    l.bedrooms, l.bathrooms, l.area, l.status,
                    l.submitted_at,
                    f.created_at AS favorited_at,
                    u.id         AS owner_id,
                    u.name       AS owner_name,
                    u.avatar_url AS owner_avatar,
                    u.verified   AS owner_verified,
                    u.role       AS owner_role,
                    u.agency_name,
                    u.phone      AS owner_phone
                FROM favorites f
                JOIN listings l ON l.id = f.listing_id
                JOIN users u    ON u.id = l.user_id
                WHERE f.user_id = :uid
                  AND l.status  = 'approved'
                ORDER BY f.created_at DESC
            ");
            $stmt->execute([':uid' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Batch-fetch cover photos
            if (!empty($rows)) {
                $ids   = array_column($rows, 'id');
                $ph    = implode(',', array_fill(0, count($ids), '?'));
                $pStmt = $this->db->prepare(
                    "SELECT listing_id, photo_url
                     FROM listing_photos
                     WHERE listing_id IN ({$ph})
                     ORDER BY listing_id, is_cover DESC, sort_order ASC"
                );
                $pStmt->execute(array_values($ids));
                $photoMap = [];
                foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                    $lid = (int)$p['listing_id'];
                    if (!isset($photoMap[$lid])) $photoMap[$lid] = $p['photo_url'];
                }
                foreach ($rows as &$row) {
                    $cover = $photoMap[(int)$row['id']] ?? null;
                    $row['cover_photo']    = $cover
                        ? (str_starts_with($cover, '/uploads/')
                            ? 'http://localhost:8000' . $cover
                            : $cover)
                        : null;
                    $row['owner_verified'] = (bool)$row['owner_verified'];
                    $row['price']          = (int)$row['price'];
                    $row['bedrooms']       = $row['bedrooms']  !== null ? (int)$row['bedrooms']  : null;
                    $row['bathrooms']      = $row['bathrooms'] !== null ? (int)$row['bathrooms'] : null;
                    $row['area']           = (int)$row['area'];
                    $row['is_favorited']   = true;
                    if (!empty($row['owner_avatar']) && str_starts_with($row['owner_avatar'], '/uploads/')) {
                        $row['owner_avatar'] = 'http://localhost:8000' . $row['owner_avatar'];
                    }
                }
                unset($row);
            }

            ob_end_clean();
            $this->json(['data' => $rows, 'total' => count($rows)]);

        } catch (Throwable $e) {
            ob_end_clean();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // POST /user/favorites/:listingId
    public function add(array $params = []): void {
        ob_start();
        try {
            $userId    = (int)($this->user['id'] ?? 0);
            $listingId = (int)($params['listingId'] ?? 0);

            if ($listingId <= 0) {
                ob_end_clean();
                $this->json(['error' => 'Invalid listing ID'], 400);
                return;
            }

            $chk = $this->db->prepare("SELECT id FROM listings WHERE id = :id AND status = 'approved'");
            $chk->execute([':id' => $listingId]);
            if (!$chk->fetch()) {
                ob_end_clean();
                $this->json(['error' => 'Listing not found or not approved'], 404);
                return;
            }

            $ins = $this->db->prepare(
                "INSERT IGNORE INTO favorites (user_id, listing_id) VALUES (:uid, :lid)"
            );
            $ins->execute([':uid' => $userId, ':lid' => $listingId]);

            if ($ins->rowCount() > 0) {
                $this->db->prepare(
                    "UPDATE users SET favorites_count = favorites_count + 1 WHERE id = :uid"
                )->execute([':uid' => $userId]);
            }

            ob_end_clean();
            $this->json(['favorited' => true, 'message' => 'Added to favorites'], 201);

        } catch (Throwable $e) {
            ob_end_clean();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // DELETE /user/favorites/:listingId
    public function remove(array $params = []): void {
        ob_start();
        try {
            $userId    = (int)($this->user['id'] ?? 0);
            $listingId = (int)($params['listingId'] ?? 0);

            if ($listingId <= 0) {
                ob_end_clean();
                $this->json(['error' => 'Invalid listing ID'], 400);
                return;
            }

            $del = $this->db->prepare(
                "DELETE FROM favorites WHERE user_id = :uid AND listing_id = :lid"
            );
            $del->execute([':uid' => $userId, ':lid' => $listingId]);

            if ($del->rowCount() > 0) {
                $this->db->prepare(
                    "UPDATE users SET favorites_count = GREATEST(favorites_count - 1, 0) WHERE id = :uid"
                )->execute([':uid' => $userId]);
            }

            ob_end_clean();
            $this->json(['favorited' => false, 'message' => 'Removed from favorites']);

        } catch (Throwable $e) {
            ob_end_clean();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}