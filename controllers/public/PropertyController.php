<?php
// controllers/public/PropertyController.php

class PropertyController {
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

    private array $cityCoords = [
        'douala'     => ['lat' => 4.0511,  'lng' => 9.7679],
        'yaoundé'    => ['lat' => 3.8480,  'lng' => 11.5021],
        'yaounde'    => ['lat' => 3.8480,  'lng' => 11.5021],
        'bafoussam'  => ['lat' => 5.4737,  'lng' => 10.4175],
        'garoua'     => ['lat' => 9.3017,  'lng' => 13.3970],
        'bamenda'    => ['lat' => 5.9597,  'lng' => 10.1460],
        'maroua'     => ['lat' => 10.5910, 'lng' => 14.3159],
        'kribi'      => ['lat' => 2.9395,  'lng' => 9.9086],
        'limbe'      => ['lat' => 4.0167,  'lng' => 9.2000],
        'buea'       => ['lat' => 4.1527,  'lng' => 9.2408],
        'edéa'       => ['lat' => 3.8003,  'lng' => 10.1276],
        'edea'       => ['lat' => 3.8003,  'lng' => 10.1276],
        'nkongsamba' => ['lat' => 4.9528,  'lng' => 9.9342],
    ];

    private array $neighbourhoodOffsets = [
        'bonanjo'      => [ 0.000,  -0.012],
        'akwa'         => [ 0.010,  -0.008],
        'bepanda'      => [ 0.025,   0.010],
        'bonapriso'    => [-0.008,  -0.015],
        'deido'        => [ 0.018,   0.005],
        'bonamoussadi' => [ 0.030,   0.025],
        'new bell'     => [ 0.005,  -0.005],
        'makepe'       => [ 0.040,   0.020],
        'bonaberi'     => [-0.015,  -0.030],
        'bastos'       => [ 0.018,   0.022],
        'nlongkak'     => [-0.010,   0.015],
        'melen'        => [-0.020,   0.010],
        'essos'        => [ 0.010,  -0.020],
        'ekounou'      => [-0.015,  -0.010],
        'odza'         => [ 0.025,   0.030],
        'logpom'       => [ 0.035,   0.005],
        'mendong'      => [-0.025,  -0.025],
        'mvog-mbi'     => [-0.005,   0.025],
    ];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // ── Parse "lat,lng" string ───────────────────────────────────────────────
    private function parseCoordinates(?string $raw): ?array {
        if (!$raw || trim($raw) === '') return null;
        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            return ['lat' => (float)$parts[0], 'lng' => (float)$parts[1]];
        }
        return null;
    }

    // ── Derive coords from city + address ────────────────────────────────────
    private function deriveCoords(string $city, string $address): array {
        $key  = strtolower(trim($city));
        $base = $this->cityCoords[$key] ?? ['lat' => 4.0511, 'lng' => 9.7679];

        $addrLower = strtolower($address);
        foreach ($this->neighbourhoodOffsets as $hood => $offset) {
            if (str_contains($addrLower, $hood)) {
                return ['lat' => $base['lat'] + $offset[0], 'lng' => $base['lng'] + $offset[1]];
            }
        }

        // Small jitter so markers don't stack
        return [
            'lat' => $base['lat'] + (mt_rand(-80, 80) / 10000),
            'lng' => $base['lng'] + (mt_rand(-80, 80) / 10000),
        ];
    }

    // ── Fetch photos for listing IDs (MySQL 5.7 safe — no JSON_ARRAYAGG) ────
    private function fetchPhotos(array $ids): array {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT listing_id, photo_url, is_cover, sort_order
             FROM listing_photos
             WHERE listing_id IN ({$placeholders})
             ORDER BY listing_id, is_cover DESC, sort_order ASC"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['listing_id']][] = $r['photo_url'];
        }
        return $map;
    }

    // ── Enrich a single row with photos + coords + type-safe booleans ────────
    private function enrichRow(array &$row, array $photoMap = []): void {
        $id = (int)$row['id'];

        // Photos — use pre-fetched map (batch) or fall through to fallback
        $photos = $photoMap[$id] ?? [];
        if (empty($photos) && !empty($row['cover_photo'])) {
            $photos = [$row['cover_photo']];
        }
        if (empty($photos)) {
            $type   = $row['property_type'] ?? 'other';
            $photos = [$this->fallbacks[$type] ?? $this->fallbacks['other']];
        }
        $row['cover_photo'] = $photos[0];
        $row['photos']      = $photos;
        unset($row['_cover_raw']); // cleanup if present

        // Coordinates
        $coords = $this->parseCoordinates($row['coordinates'] ?? null);
        if (!$coords) {
            $coords = $this->deriveCoords($row['city'] ?? '', $row['address'] ?? '');
        }
        $row['lat'] = $coords['lat'];
        $row['lng'] = $coords['lng'];
        unset($row['coordinates']);

        // Booleans
        $row['parking']        = (bool)($row['parking']        ?? false);
        $row['generator']      = (bool)($row['generator']      ?? false);
        $row['owner_verified'] = (bool)($row['owner_verified'] ?? false);
    }

    // ── GET /public/properties ───────────────────────────────────────────────
    public function index(): void {
        try {
            $city     = isset($_GET['city'])             ? trim($_GET['city'])             : null;
            $txType   = isset($_GET['transaction_type']) ? trim($_GET['transaction_type']) : null;
            $propType = isset($_GET['property_type'])    ? trim($_GET['property_type'])    : null;
            $priceMin = isset($_GET['price_min'])  && $_GET['price_min'] !== '' ? (int)$_GET['price_min']  : null;
            $priceMax = isset($_GET['price_max'])  && $_GET['price_max'] !== '' ? (int)$_GET['price_max']  : null;
            $bedrooms = isset($_GET['bedrooms'])   && $_GET['bedrooms']  !== '' ? (int)$_GET['bedrooms']   : null;
            $q        = isset($_GET['q'])          && $_GET['q']         !== '' ? trim($_GET['q'])          : null;
            $limit    = min((int)($_GET['limit']  ?? 20), 100);
            $offset   = max((int)($_GET['offset'] ?? 0), 0);

            // listingType / type alias  (rent | buy→sale | sale)
            foreach (['listingType', 'type'] as $key) {
                if (!$txType && !empty($_GET[$key])) {
                    $raw    = strtolower(trim($_GET[$key]));
                    $txType = ($raw === 'buy') ? 'sale' : $raw;
                    break;
                }
            }

            $where  = ["l.status IN ('approved', 'pending')"];
            $params = [];

            if ($city) {
                // Match either city name or address containing city string
                $where[]         = "(LOWER(l.city) = LOWER(:city) OR LOWER(l.address) LIKE LOWER(:city_like))";
                $params[':city'] = $city;
                $params[':city_like'] = '%' . $city . '%';
            }
            if ($txType && in_array($txType, ['rent', 'sale'], true)) {
                $where[]            = "l.transaction_type = :tx_type";
                $params[':tx_type'] = $txType;
            }
            if ($propType) {
                $where[]              = "l.property_type = :prop_type";
                $params[':prop_type'] = $propType;
            }
            if ($priceMin !== null) {
                $where[]              = "l.price >= :price_min";
                $params[':price_min'] = $priceMin;
            }
            if ($priceMax !== null) {
                $where[]              = "l.price <= :price_max";
                $params[':price_max'] = $priceMax;
            }
            if ($bedrooms !== null) {
                $where[]             = "l.bedrooms >= :bedrooms";
                $params[':bedrooms'] = $bedrooms;
            }
            if ($q) {
                $like = '%' . $q . '%';
                $where[]        = "(l.title LIKE :q1 OR l.address LIKE :q2 OR l.city LIKE :q3 OR l.description LIKE :q4)";
                $params[':q1']  = $like;
                $params[':q2']  = $like;
                $params[':q3']  = $like;
                $params[':q4']  = $like;
            }

            $wc = implode(' AND ', $where);

            // Total count
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM listings l WHERE {$wc}");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Main query — NO subqueries, NO JSON_ARRAYAGG (MySQL 5.7 safe)
            $sql = "
                SELECT
                    l.id, l.title, l.description, l.price, l.address, l.city, l.region,
                    l.property_type, l.transaction_type,
                    l.bedrooms, l.bathrooms, l.area,
                    l.furnished, l.parking, l.generator,
                    l.floor, l.total_floors, l.year_built, l.coordinates,
                    l.approved_at,
                    u.bio        AS owner_bio
                FROM listings l
                JOIN users u ON u.id = l.user_id
                WHERE {$wc}
                ORDER BY l.approved_at DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Batch-fetch photos for all returned listing IDs
            $ids      = array_column($rows, 'id');
            $photoMap = $this->fetchPhotos($ids);

            foreach ($rows as &$row) {
                $this->enrichRow($row, $photoMap);
            }
            unset($row);

            $this->json(['data' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage(), 'trace' => $e->getFile() . ':' . $e->getLine()], 500);
        }
    }

    // ── GET /public/properties/:id ───────────────────────────────────────────
    public function show(array $params): void {
        try {
            $id = (int)($params['id'] ?? 0);
            if ($id <= 0) {
                $this->json(['error' => 'Invalid ID'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                SELECT l.*,
                    u.id         AS owner_id,
                    u.name       AS owner_name,
                    u.phone      AS owner_phone,
                    u.avatar_url AS owner_avatar,
                    u.verified   AS owner_verified,
                    u.role       AS owner_role,
                    u.agency_name,
                    u.bio        AS owner_bio
                FROM listings l
                JOIN users u ON u.id = l.user_id
                WHERE l.id = :id AND l.status IN ('approved', 'pending')
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->json(['error' => 'Listing not found'], 404);
                return;
            }

            // Fetch photos separately (MySQL 5.7 safe)
            $photoMap = $this->fetchPhotos([$id]);
            $this->enrichRow($row, $photoMap);

            $row['fraud_signals']    = null; // never expose to public
            $row['admin_notes']      = null;
            $row['rejected_reason']  = null;

            $this->json($row);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}