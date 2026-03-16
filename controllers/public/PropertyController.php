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

    private function parseCoordinates(?string $raw): ?array {
        if (!$raw || trim($raw) === '') return null;
        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            return ['lat' => (float)$parts[0], 'lng' => (float)$parts[1]];
        }
        return null;
    }

    private function deriveCoords(string $city, string $address): array {
        $key  = strtolower(trim($city));
        $base = $this->cityCoords[$key] ?? ['lat' => 4.0511, 'lng' => 9.7679];
        $addrLower = strtolower($address);
        foreach ($this->neighbourhoodOffsets as $hood => $offset) {
            if (str_contains($addrLower, $hood)) {
                return ['lat' => $base['lat'] + $offset[0], 'lng' => $base['lng'] + $offset[1]];
            }
        }
        return [
            'lat' => $base['lat'] + (mt_rand(-80, 80) / 10000),
            'lng' => $base['lng'] + (mt_rand(-80, 80) / 10000),
        ];
    }

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
            $lid = (int)$r['listing_id'];
            $url = $r['photo_url'];
            // Normalise relative URLs
            if (!empty($url) && str_starts_with($url, '/uploads/')) {
                $url = 'https://homibackend-production.up.railway.app/' . $url;
            }
            $map[$lid][] = $url;
        }
        return $map;
    }

    private function enrichRow(array &$row, array $photoMap = []): void {
        $id = (int)$row['id'];
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

        $coords = $this->parseCoordinates($row['coordinates'] ?? null);
        if (!$coords) {
            $coords = $this->deriveCoords($row['city'] ?? '', $row['address'] ?? '');
        }
        $row['lat'] = $coords['lat'];
        $row['lng'] = $coords['lng'];
        unset($row['coordinates']);

        $row['parking']        = (bool)($row['parking']        ?? false);
        $row['generator']      = (bool)($row['generator']      ?? false);
        $row['owner_verified'] = (bool)($row['owner_verified'] ?? false);
    }

    // ── GET /public/properties ───────────────────────────────────────────────
    public function index(): void {
        try {
            // ── Collect all possible param aliases ──────────────────────────
            // Location: city, search, q, neighborhood — all mean the same thing
            $rawSearch = null;
            foreach (['search', 'q', 'city', 'neighborhood', 'neighbourhood'] as $key) {
                if (!empty($_GET[$key])) { $rawSearch = trim($_GET[$key]); break; }
            }

            // Transaction type: listingType, type, transaction_type
            $txType = null;
            foreach (['listingType', 'type', 'transaction_type'] as $key) {
                if (!empty($_GET[$key])) {
                    $raw    = strtolower(trim($_GET[$key]));
                    $txType = ($raw === 'buy' || $raw === 'for sale' || $raw === 'sale') ? 'sale'
                            : (($raw === 'for rent' || $raw === 'rent') ? 'rent' : null);
                    if ($txType) break;
                }
            }

            $propType = !empty($_GET['property_type']) ? trim($_GET['property_type']) : null;
            $priceMin = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? (int)$_GET['price_min'] : null;
            $priceMax = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (int)$_GET['price_max'] : null;
            $bedrooms = isset($_GET['bedrooms'])  && $_GET['bedrooms']  !== '' ? (int)$_GET['bedrooms']  : null;
            $bathrooms = isset($_GET['bathrooms']) && $_GET['bathrooms'] !== '' ? (int)$_GET['bathrooms'] : null;
            $limit    = min((int)($_GET['limit']  ?? 50), 100);
            $offset   = max((int)($_GET['offset'] ?? 0),  0);

            $where  = ["l.status = 'approved'"];
            $params = [];

            // ── Smart location search ────────────────────────────────────────
            // Matches: city, region, address, neighbourhood — case-insensitive
            if ($rawSearch) {
                $like = '%' . $rawSearch . '%';
                $where[] = "(
                    LOWER(l.city)    LIKE LOWER(:loc1)
                    OR LOWER(l.region)  LIKE LOWER(:loc2)
                    OR LOWER(l.address) LIKE LOWER(:loc3)
                    OR LOWER(l.title)   LIKE LOWER(:loc4)
                )";
                $params[':loc1'] = $like;
                $params[':loc2'] = $like;
                $params[':loc3'] = $like;
                $params[':loc4'] = $like;
            }

            if ($txType) {
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
            if ($bathrooms !== null) {
                $where[]              = "l.bathrooms >= :bathrooms";
                $params[':bathrooms'] = $bathrooms;
            }
            // ── Bbox filter (geo-boundary from Nominatim) ────────────────
            $latMin = isset($_GET['lat_min']) && $_GET['lat_min'] !== '' ? (float)$_GET['lat_min'] : null;
            $latMax = isset($_GET['lat_max']) && $_GET['lat_max'] !== '' ? (float)$_GET['lat_max'] : null;
            $lngMin = isset($_GET['lng_min']) && $_GET['lng_min'] !== '' ? (float)$_GET['lng_min'] : null;
            $lngMax = isset($_GET['lng_max']) && $_GET['lng_max'] !== '' ? (float)$_GET['lng_max'] : null;

            if ($latMin !== null && $latMax !== null && $lngMin !== null && $lngMax !== null) {
                // Parse coordinates column "lat,lng" inline — no schema change needed
                $where[] = "(
                    CAST(SUBSTRING_INDEX(l.coordinates, ',', 1) AS DECIMAL(10,6)) BETWEEN :lat_min AND :lat_max
                    AND CAST(SUBSTRING_INDEX(l.coordinates, ',', -1) AS DECIMAL(10,6)) BETWEEN :lng_min AND :lng_max
                )";
                $params[':lat_min'] = $latMin;
                $params[':lat_max'] = $latMax;
                $params[':lng_min'] = $lngMin;
                $params[':lng_max'] = $lngMax;
            }

            $wc = implode(' AND ', $where);

            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM listings l WHERE {$wc}");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $sql = "
                SELECT
                    l.id, l.title, l.description, l.price, l.address, l.city, l.region,
                    l.property_type, l.transaction_type,
                    l.bedrooms, l.bathrooms, l.area,
                    l.furnished, l.parking, l.generator,
                    l.floor, l.total_floors, l.year_built, l.coordinates,
                    l.approved_at,
                    u.id         AS owner_id,
                    u.name       AS owner_name,
                    u.avatar_url AS owner_avatar,
                    u.bio        AS owner_bio,
                    u.role       AS owner_role
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

            $ids      = array_column($rows, 'id');
            $photoMap = $this->fetchPhotos($ids);

            foreach ($rows as &$row) {
                $this->enrichRow($row, $photoMap);
                // Normalise owner avatar
                if (!empty($row['owner_avatar']) && str_starts_with($row['owner_avatar'], '/uploads/')) {
                    $row['owner_avatar'] = 'https://homibackend-production.up.railway.app/' . $row['owner_avatar'];
                }
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
            if ($id <= 0) { $this->json(['error' => 'Invalid ID'], 400); return; }

            $stmt = $this->db->prepare("
                SELECT l.*,
                    u.id         AS owner_id,
                    u.name       AS owner_name,
                    u.phone      AS owner_phone,
                    u.avatar_url AS owner_avatar,
                    u.verified   AS owner_verified,
                    u.role       AS owner_role,
                    u.agency_name,
                    u.bio        AS owner_bio,
                    JSON_UNQUOTE(JSON_EXTRACT(u.profile_meta, '$.whatsapp')) AS owner_whatsapp
                FROM listings l
                JOIN users u ON u.id = l.user_id
                WHERE l.id = :id AND l.status IN ('approved', 'pending')
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) { $this->json(['error' => 'Listing not found'], 404); return; }

            $photoMap = $this->fetchPhotos([$id]);
            $this->enrichRow($row, $photoMap);

            if (!empty($row['owner_avatar']) && str_starts_with($row['owner_avatar'], '/uploads/')) {
                $row['owner_avatar'] = 'https://homibackend-production.up.railway.app/' . $row['owner_avatar'];
            }

            if (empty($row['owner_whatsapp']) || $row['owner_whatsapp'] === 'null') {
                $row['owner_whatsapp'] = $row['owner_phone'] ?? null;
            }

            $row['fraud_signals']   = null;
            $row['admin_notes']     = null;
            $row['rejected_reason'] = null;

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