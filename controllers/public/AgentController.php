<?php
// controllers/public/AgentController.php

class AgentController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // GET /public/agents
    public function index(): void {
        ob_start();
        try {
            $city   = $_GET['city']  ?? null;
            $limit  = min((int)($_GET['limit']  ?? 10), 50);
            $offset = (int)($_GET['offset'] ?? 0);

            // Only show active + verified agents to the public
            $where  = [
                "u.role = 'agent'",
                "u.status = 'active'",
                "u.verification_status = 'verified'",
            ];
            $params = [];

            if ($city) {
                $where[]         = "LOWER(u.city) = LOWER(:city)";
                $params[':city'] = $city;
            }

            $wc = implode(' AND ', $where);

            $cStmt = $this->db->prepare("SELECT COUNT(*) FROM users u WHERE {$wc}");
            $cStmt->execute($params);
            $total = (int)$cStmt->fetchColumn();

            $sql = "
                SELECT
                    u.id, u.name, u.city,
                    u.agency_name, u.agency_type,
                    u.years_experience, u.bio, u.avatar_url,
                    u.verified, u.verification_status,
                    u.profile_meta,
                    COALESCE(COUNT(DISTINCT l.id), 0)        AS listings_count,
                    COALESCE(ROUND(AVG(r.rating), 1), 0)     AS avg_rating,
                    COUNT(DISTINCT r.id)                     AS review_count
                FROM users u
                LEFT JOIN listings l ON l.user_id = u.id
                    AND l.status IN ('approved', 'pending')
                LEFT JOIN reviews r ON r.agent_id = u.id
                WHERE {$wc}
                GROUP BY u.id
                ORDER BY listings_count DESC, avg_rating DESC
                LIMIT :limit OFFSET :offset
            ";
            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
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
            unset($row);

            ob_end_clean();
            $this->json(['data' => $rows, 'total' => $total]);

        } catch (Throwable $e) {
            ob_end_clean();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // GET /public/agents/:id
    public function show(array $params): void {
        ob_start();
        try {
            $id = (int)($params['id'] ?? 0);
            if ($id <= 0) {
                ob_end_clean();
                $this->json(['error' => 'Invalid ID'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                SELECT
                    u.id, u.name, u.city, u.phone, u.email,
                    u.agency_name, u.agency_type, u.license_number,
                    u.years_experience, u.bio, u.avatar_url,
                    u.verified, u.verification_status,
                    u.listings_count, u.profile_meta,
                    COALESCE(ROUND(AVG(r.rating), 1), 0) AS avg_rating,
                    COUNT(r.id) AS review_count
                FROM users u
                LEFT JOIN reviews r ON r.agent_id = u.id
                WHERE u.id = :id
                  AND u.role = 'agent'
                  AND u.status = 'active'
                  AND u.verification_status = 'verified'
                GROUP BY u.id
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                ob_end_clean();
                $this->json(['error' => 'Agent not found'], 404);
                return;
            }

            $row['verified']       = (bool)$row['verified'];
            $row['avg_rating']     = (float)$row['avg_rating'];
            $row['review_count']   = (int)$row['review_count'];
            $row['listings_count'] = (int)$row['listings_count'];
            if (!empty($row['avatar_url']) && str_starts_with($row['avatar_url'], '/uploads/')) {
                $row['avatar_url'] = 'http://localhost:8000' . $row['avatar_url'];
            }

            // Agent's listings (approved + pending) — MySQL 5.7 safe
            $lStmt = $this->db->prepare("
                SELECT l.id, l.title, l.price, l.address, l.city,
                       l.transaction_type, l.property_type,
                       l.bedrooms, l.bathrooms, l.area, l.status
                FROM listings l
                WHERE l.user_id = :uid
                  AND l.status IN ('approved', 'pending')
                ORDER BY l.submitted_at DESC
                LIMIT 12
            ");
            $lStmt->execute([':uid' => $id]);
            $listings = $lStmt->fetchAll(PDO::FETCH_ASSOC);

            // Batch-fetch cover photos (MySQL 5.7 safe — no subqueries)
            if (!empty($listings)) {
                $ids = array_column($listings, 'id');
                $ph  = implode(',', array_fill(0, count($ids), '?'));
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
                foreach ($listings as &$l) {
                    $l['cover_photo'] = $photoMap[(int)$l['id']] ?? null;
                }
                unset($l);
            }

            $row['listings'] = $listings;

            ob_end_clean();
            $this->json($row);

        } catch (Throwable $e) {
            ob_end_clean();
            $this->json(['error' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    private function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}