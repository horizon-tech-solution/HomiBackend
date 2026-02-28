<?php
require_once __DIR__ . '/../../models/admin/Listing.php';
require_once __DIR__ . '/../../models/admin/ListingPhoto.php';
require_once __DIR__ . '/../../models/admin/Document.php';

class ListingController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    public function index() {
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        $listingModel = new Listing($this->db);
        $listings = $listingModel->getByUserId($this->user['id'], $status, $search);
        jsonResponse(['data' => $listings]);
    }

    public function show($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $listingModel = new Listing($this->db);
        $listing = $listingModel->getById($id);
        if (!$listing || $listing['user_id'] != $this->user['id']) {
            jsonResponse(['error' => 'Listing not found'], 404);
        }
        jsonResponse($listing);
    }

    public function store() {
        $input = getJsonInput();
        // Validate required fields
        $required = ['title', 'description', 'property_type', 'transaction_type', 'price', 'address', 'city', 'region', 'area'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonResponse(['error' => "$field is required"], 400);
            }
        }

        $listingModel = new Listing($this->db);
        // We'll need to map input to database columns and insert.
        // For brevity, assume direct insert with prepared statement.
        $sql = "INSERT INTO listings (user_id, title, description, property_type, transaction_type, price, address, city, region, area, bedrooms, bathrooms, year_built, parking, furnished, pet_friendly, submitted_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $params = [
            $this->user['id'],
            $input['title'],
            $input['description'],
            $input['property_type'],
            $input['transaction_type'],
            $input['price'],
            $input['address'],
            $input['city'],
            $input['region'],
            $input['area'],
            $input['bedrooms'] ?? null,
            $input['bathrooms'] ?? null,
            $input['year_built'] ?? null,
            $input['parking'] ?? 0,
            $input['furnished'] ?? null,
            $input['pet_friendly'] ?? 0
        ];
        if ($stmt->execute($params)) {
            $listingId = $this->db->lastInsertId();
            jsonResponse(['id' => $listingId, 'message' => 'Listing created'], 201);
        } else {
            jsonResponse(['error' => 'Failed to create listing'], 500);
        }
    }

    public function uploadPhotos($params) {
        $listingId = $params['id'] ?? null;
        if (!$listingId) jsonResponse(['error' => 'Listing ID required'], 400);
        // Verify ownership
        $listingModel = new Listing($this->db);
        $listing = $listingModel->getById($listingId);
        if (!$listing || $listing['user_id'] != $this->user['id']) {
            jsonResponse(['error' => 'Listing not found'], 404);
        }

        if (empty($_FILES['photos'])) {
            jsonResponse(['error' => 'No photos uploaded'], 400);
        }

        $photoModel = new ListingPhoto($this->db);
        $uploadDir = __DIR__ . '/../../../uploads/listings/photos/';
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

    // Similar for uploadDocuments, update, destroy...
}