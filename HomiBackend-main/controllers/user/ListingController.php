<?php
require_once __DIR__ . '/../../models/admin/Listing.php';
require_once __DIR__ . '/../../models/admin/ListingPhoto.php';

class ListingController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    public function store() {
        $input = getJsonInput();

        // Validate required fields
        $required = ['propertyType', 'listingType', 'address', 'city', 'region', 'landSize', 'price', 'title', 'description'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonResponse(['error' => "$field is required"], 400);
            }
        }

        // Map to database columns
        $sql = "INSERT INTO listings (
            user_id, title, description, property_type, transaction_type, price,
            address, city, region, neighborhood, area, bedrooms, bathrooms,
            year_built, parking, furnished, submitted_at, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending'
        )";

        $stmt = $this->db->prepare($sql);
        $params = [
            $this->user['id'],
            $input['title'],
            $input['description'],
            $input['propertyType'],
            $input['listingType'],
            $input['price'],
            $input['address'],
            $input['city'],
            $input['region'],
            $input['neighborhood'] ?? null,
            $input['landSize'],
            $input['bedrooms'] ?? null,
            $input['bathrooms'] ?? null,
            $input['yearBuilt'] ?? null,
            $input['parkingSpaces'] ?? 0,
            $input['furnished'] ?? null
        ];

        if ($stmt->execute($params)) {
            $listingId = $this->db->lastInsertId();

            // Handle photos (if uploaded separately, they'd be in a separate request)
            // For now, we assume photos are sent as base64 or file uploads via multipart.
            // We'll handle file uploads in a separate endpoint.

            jsonResponse(['id' => $listingId, 'message' => 'Listing submitted for review'], 201);
        } else {
            jsonResponse(['error' => 'Failed to create listing'], 500);
        }
    }

    public function uploadPhotos($params) {
        $listingId = $params['id'] ?? null;
        if (!$listingId) jsonResponse(['error' => 'Listing ID required'], 400);

        // Verify ownership
        $stmt = $this->db->prepare("SELECT id FROM listings WHERE id = ? AND user_id = ?");
        $stmt->execute([$listingId, $this->user['id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Listing not found'], 404);
        }

        if (empty($_FILES['photos'])) {
            jsonResponse(['error' => 'No photos uploaded'], 400);
        }

        $photoModel = new ListingPhoto($this->db);
        $uploadDir = __DIR__ . '/../../../public/uploads/listings/photos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $uploaded = [];
        foreach ($_FILES['photos']['tmp_name'] as $key => $tmpName) {
            $fileName = uniqid() . '_' . basename($_FILES['photos']['name'][$key]);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($tmpName, $targetPath)) {
                $photoUrl = '/uploads/listings/photos/' . $fileName;
                $photoModel->create([
                    'listing_id' => $listingId,
                    'photo_url' => $photoUrl,
                    'is_cover' => $key === 0 ? 1 : 0,
                    'sort_order' => $key
                ]);
                $uploaded[] = $photoUrl;
            }
        }

        jsonResponse(['uploaded' => $uploaded]);
    }

    // For completeness, get listings by user (if needed)
    public function index() {
        $listingModel = new Listing($this->db);
        $listings = $listingModel->getByUserId($this->user['id']);
        jsonResponse(['data' => $listings]);
    }
}