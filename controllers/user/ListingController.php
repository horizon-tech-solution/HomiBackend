<?php
// controllers/User/ListingController.php
require_once __DIR__ . '/../../models/agent/Listing.php';

class ListingController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    // GET /user/listings
    public function index($params = []) {
        $stmt = $this->db->prepare("
            SELECT l.*, 
                   (SELECT photo_url FROM listing_photos WHERE listing_id = l.id AND is_cover = 1 LIMIT 1) AS cover_photo
            FROM listings l
            WHERE l.user_id = ?
            ORDER BY l.submitted_at DESC
        ");
        $stmt->execute([$this->user['id']]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // GET /user/listings/{id}
    public function show($params = []) {
        $stmt = $this->db->prepare("
            SELECT * FROM listings WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$params['id'], $this->user['id']]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$listing) {
            http_response_code(404);
            jsonResponse(['error' => 'Listing not found']);
            return;
        }

        $listing['photos'] = $this->getPhotos((int)$listing['id']);
        jsonResponse($listing);
    }

    // POST /user/listings
    public function store($params = []) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data) || !is_array($data)) {
            http_response_code(422);
            jsonResponse(['error' => 'Invalid JSON body']);
            return;
        }

        $title       = trim($data['title']       ?? '');
        $description = trim($data['description'] ?? '');
        $propType    = trim($data['propertyType'] ?? '');
        $listType    = trim($data['listingType']  ?? '');
        $price       = isset($data['price']) ? (int)$data['price'] : 0;

        if (!$title || !$description || !$propType || !$listType || $price <= 0) {
            http_response_code(422);
            jsonResponse(['error' => 'Missing required fields']);
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO listings
                (user_id, title, description, property_type, transaction_type,
                 price, address, city, region, coordinates,
                 bedrooms, bathrooms, area, year_built,
                 furnished, parking, generator, status, submitted_at)
            VALUES
                (?, ?, ?, ?, ?,
                 ?, ?, ?, ?, ?,
                 ?, ?, ?, ?,
                 ?, ?, ?, 'pending', NOW())
        ");

        $stmt->execute([
            $this->user['id'],
            $title,
            $description,
            $propType,
            $listType,
            min($price, 2147483647),
            trim($data['address']  ?? ''),
            trim($data['city']     ?? ''),
            trim($data['region']   ?? ''),
            trim($data['coordinates'] ?? '') ?: null,
            isset($data['bedrooms'])  && $data['bedrooms']  !== null ? (int)$data['bedrooms']  : null,
            isset($data['bathrooms']) && $data['bathrooms'] !== null ? (int)$data['bathrooms'] : null,
            isset($data['landSize'])  && $data['landSize']  !== null ? (int)$data['landSize']  : null,
            isset($data['yearBuilt']) && $data['yearBuilt'] !== null ? (int)$data['yearBuilt'] : null,
            trim($data['furnished'] ?? '') ?: null,
            isset($data['parkingSpaces']) ? (int)$data['parkingSpaces'] : 0,
            isset($data['generator'])     ? (int)$data['generator']     : 0,
        ]);

        $listingId = (int)$this->db->lastInsertId();

        http_response_code(201);
        jsonResponse(['id' => $listingId, 'status' => 'pending']);
    }

    // PUT /user/listings/{id}
    public function update($params = []) {
        $check = $this->db->prepare("SELECT id FROM listings WHERE id = ? AND user_id = ?");
        $check->execute([$params['id'], $this->user['id']]);
        if (!$check->fetch()) {
            http_response_code(404);
            jsonResponse(['error' => 'Listing not found']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data) || !is_array($data)) {
            http_response_code(422);
            jsonResponse(['error' => 'Invalid JSON body']);
            return;
        }

        // Reuse the agent Listing model's update — same table, same fields
        $model = new Listing($this->db);
        $model->update($params['id'], $this->user['id'], $data);
        jsonResponse(['message' => 'Listing updated successfully']);
    }

    // DELETE /user/listings/{id}
    public function destroy($params = []) {
        $stmt = $this->db->prepare("
            DELETE FROM listings WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$params['id'], $this->user['id']]);

        if ($stmt->rowCount() === 0) {
            http_response_code(403);
            jsonResponse(['error' => 'Cannot delete — not found, not yours, or already approved.']);
            return;
        }

        jsonResponse(['message' => 'Listing deleted successfully']);
    }

    // POST /user/listings/{id}/photos
    public function uploadPhotos($params = []) {
        $check = $this->db->prepare("SELECT id FROM listings WHERE id = ? AND user_id = ?");
        $check->execute([$params['id'], $this->user['id']]);
        if (!$check->fetch()) {
            http_response_code(404);
            jsonResponse(['error' => 'Listing not found']);
            return;
        }

        if (empty($_FILES['photos'])) {
            http_response_code(422);
            jsonResponse(['error' => 'No photos provided']);
            return;
        }

        $uploadDir = __DIR__ . '/../../public/uploads/listings/' . $params['id'] . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $files = $_FILES['photos'];
        if (is_array($files['name'])) {
            $fileList = array_map(null, $files['name'], $files['tmp_name'], $files['type'], $files['error']);
        } else {
            $fileList = [[$files['name'], $files['tmp_name'], $files['type'], $files['error']]];
        }

        $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
        $maxBytes = 7 * 1024 * 1024;
        $uploaded = [];

        foreach ($fileList as [$name, $tmp, $type, $err]) {
            if ($err !== UPLOAD_ERR_OK) continue;
            if (filesize($tmp) > $maxBytes) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) continue;

            $filename = uniqid('photo_', true) . '.' . $ext;
            $dest     = $uploadDir . $filename;
            $urlPath  = '/uploads/listings/' . $params['id'] . '/' . $filename;

            if (move_uploaded_file($tmp, $dest)) {
                $uploaded[] = $urlPath;
            }
        }

        if (empty($uploaded)) {
            http_response_code(422);
            jsonResponse(['error' => 'No valid photos could be saved']);
            return;
        }

        $hasCover = (bool)$this->db->query(
            "SELECT COUNT(*) FROM listing_photos WHERE listing_id = {$params['id']} AND is_cover = 1"
        )->fetchColumn();

        foreach ($uploaded as $i => $urlPath) {
            $isCover = (!$hasCover && $i === 0) ? 1 : 0;
            $stmt = $this->db->prepare(
                "INSERT INTO listing_photos (listing_id, photo_url, is_cover, sort_order) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$params['id'], $urlPath, $isCover, $i]);
            if ($isCover) $hasCover = true;
        }

        jsonResponse([
            'photos'  => $uploaded,
            'message' => count($uploaded) . ' photo(s) uploaded successfully',
        ]);
    }

    // POST /user/listings/{id}/documents
    public function uploadDocuments($params = []) {
        jsonResponse(['message' => 'Documents not supported for user listings']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getPhotos(int $listingId): array {
        $stmt = $this->db->prepare(
            "SELECT id, photo_url, is_cover, sort_order
             FROM listing_photos
             WHERE listing_id = ?
             ORDER BY is_cover DESC, sort_order ASC, id ASC"
        );
        $stmt->execute([$listingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}