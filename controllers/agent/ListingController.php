<?php
// controllers/agent/ListingController.php
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

    // ── GET /agent/listings ──────────────────────────────────────────────────

    public function index($params = []) {
        $model    = new Listing($this->db);
        $listings = $model->getByAgent($this->user['id']);

        foreach ($listings as &$listing) {
            $listing['views_count']     = $this->countRows('view_history', 'listing_id', $listing['id']);
            $listing['leads_count']     = $this->countRows('inquiries',    'listing_id', $listing['id']);
            $listing['favorites_count'] = $this->countRows('favorites',    'listing_id', $listing['id']);
        }

        jsonResponse($listings);
    }

    // ── GET /agent/listings/{id} ─────────────────────────────────────────────

    public function show($params = []) {
        $model   = new Listing($this->db);
        $listing = $model->getByIdAndAgent($params['id'], $this->user['id']);

        if (!$listing) {
            http_response_code(404);
            jsonResponse(['error' => 'Listing not found']);
            return;
        }

        $listing['photos']          = $this->getPhotos((int)$listing['id']);
        $listing['views_count']     = $this->countRows('view_history', 'listing_id', $listing['id']);
        $listing['leads_count']     = $this->countRows('inquiries',    'listing_id', $listing['id']);
        $listing['favorites_count'] = $this->countRows('favorites',    'listing_id', $listing['id']);

        jsonResponse($listing);
    }

    // ── POST /agent/listings (JSON) ──────────────────────────────────────────

    public function store($params = []) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data) || !is_array($data)) {
            http_response_code(422);
            jsonResponse(['error' => 'Invalid JSON body']);
            return;
        }

        if (empty(trim($data['title'] ?? ''))) {
            http_response_code(422);
            jsonResponse(['error' => 'Title is required']);
            return;
        }

        $model     = new Listing($this->db);
        $listingId = $model->create($this->user['id'], $data);

        http_response_code(201);
        jsonResponse(['id' => $listingId, 'message' => 'Listing created successfully']);
    }

    // ── PUT /agent/listings/{id} (JSON) ─────────────────────────────────────

    public function update($params = []) {
        $model   = new Listing($this->db);
        $listing = $model->getByIdAndAgent($params['id'], $this->user['id']);

        if (!$listing) {
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

        $model->update($params['id'], $this->user['id'], $data);
        jsonResponse(['message' => 'Listing updated successfully']);
    }

    // ── DELETE /agent/listings/{id} ──────────────────────────────────────────

    public function destroy($params = []) {
        $model   = new Listing($this->db);
        $deleted = $model->delete($params['id'], $this->user['id']);

        if (!$deleted) {
            http_response_code(403);
            jsonResponse(['error' => 'Cannot delete — listing not found, not yours, or already approved.']);
            return;
        }

        jsonResponse(['message' => 'Listing deleted successfully']);
    }

    // ── POST /agent/listings/{id}/photos (multipart) ─────────────────────────

public function uploadPhotos($params = []) {
    $model   = new Listing($this->db);
    $listing = $model->getByIdAndAgent($params['id'], $this->user['id']);
    if (!$listing) {
        http_response_code(404);
        jsonResponse(['error' => 'Listing not found']);
        return;
    }
    if (empty($_FILES['photos'])) {
        http_response_code(422);
        jsonResponse(['error' => 'No photos provided']);
        return;
    }

    require_once BASE_PATH . '/config/cloudinary.php';

    $files = $_FILES['photos'];
    $fileList = is_array($files['name'])
        ? array_map(null, $files['name'], $files['tmp_name'], $files['type'], $files['error'])
        : [[$files['name'], $files['tmp_name'], $files['type'], $files['error']]];

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxBytes     = 10 * 1024 * 1024;
    $uploaded     = [];

    foreach ($fileList as [$name, $tmp, $type, $err]) {
        if ($err !== UPLOAD_ERR_OK) continue;
        if (filesize($tmp) > $maxBytes) continue;
        if (!in_array($type, $allowedMimes, true)) continue;

        $url = uploadToCloudinary($tmp, 'listings/' . $params['id']);
        $uploaded[] = $url;
    }

    if (empty($uploaded)) {
        http_response_code(422);
        jsonResponse(['error' => 'No valid photos could be saved']);
        return;
    }

    $hasCover = (bool) $this->db->query(
        "SELECT COUNT(*) FROM listing_photos WHERE listing_id = {$params['id']} AND is_cover = 1"
    )->fetchColumn();

    foreach ($uploaded as $i => $url) {
        $isCover = (!$hasCover && $i === 0) ? 1 : 0;
        $stmt = $this->db->prepare(
            "INSERT INTO listing_photos (listing_id, photo_url, is_cover, sort_order) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$params['id'], $url, $isCover, $i]);
        if ($isCover) $hasCover = true;
    }

    jsonResponse([
        'photos'  => $uploaded,
        'message' => count($uploaded) . ' photo(s) uploaded successfully',
    ]);
}

    // ── POST /agent/listings/{id}/documents (multipart) ──────────────────────

public function uploadDocuments($params = []) {
    $model   = new Listing($this->db);
    $listing = $model->getByIdAndAgent($params['id'], $this->user['id']);

    if (!$listing) {
        http_response_code(404);
        jsonResponse(['error' => 'Listing not found']);
        return;
    }

    if (empty($_FILES['documents'])) {
        http_response_code(422);
        jsonResponse(['error' => 'No documents provided']);
        return;
    }

    require_once BASE_PATH . '/config/cloudinary.php';

    $files = $_FILES['documents'];
    $fileList = is_array($files['name'])
        ? array_map(null, $files['name'], $files['tmp_name'], $files['type'], $files['error'])
        : [[$files['name'], $files['tmp_name'], $files['type'], $files['error']]];

    $allowedExts = ['pdf', 'doc', 'docx'];
    $maxBytes    = 20 * 1024 * 1024;
    $uploaded    = [];

    foreach ($fileList as [$name, $tmp, $type, $err]) {
        if ($err !== UPLOAD_ERR_OK) continue;
        if (filesize($tmp) > $maxBytes) continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) continue;

        $url = uploadToCloudinary($tmp, 'listings/' . $params['id'] . '/docs');
        $uploaded[] = [
            'name' => $name,
            'url'  => $url,
        ];
    }

    jsonResponse([
        'documents' => $uploaded,
        'message'   => count($uploaded) . ' document(s) uploaded successfully',
    ]);
}

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function countRows(string $table, string $col, $val): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?");
        $stmt->execute([$val]);
        return (int) $stmt->fetchColumn();
    }

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