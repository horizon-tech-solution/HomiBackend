<?php
require_once __DIR__ . '/../../models/admin/Listing.php';

class PropertyController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * GET /public/properties – list properties with filters
     */
    public function index() {
        $filters = [
            'search' => $_GET['search'] ?? null,
            'city' => $_GET['city'] ?? null,
            'type' => $_GET['type'] ?? null,
            'min_price' => isset($_GET['min_price']) ? (int)$_GET['min_price'] : null,
            'max_price' => isset($_GET['max_price']) ? (int)$_GET['max_price'] : null,
            'bedrooms' => isset($_GET['bedrooms']) ? (int)$_GET['bedrooms'] : null,
            'bathrooms' => isset($_GET['bathrooms']) ? (int)$_GET['bathrooms'] : null,
            'property_type' => $_GET['property_type'] ?? null,
            'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : null,
        ];

        $listingModel = new Listing($this->db);
        $listings = $listingModel->getPublicListings($filters);

        // For each listing, get the main photo
        foreach ($listings as &$listing) {
            $photoStmt = $this->db->prepare("SELECT photo_url FROM listing_photos WHERE listing_id = ? ORDER BY is_cover DESC, sort_order LIMIT 1");
            $photoStmt->execute([$listing['id']]);
            $photo = $photoStmt->fetch(PDO::FETCH_ASSOC);
            $listing['image'] = $photo ? $photo['photo_url'] : null;
        }

        jsonResponse(['data' => $listings]);
    }

    /**
     * GET /public/properties/{id} – get single property details
     */
    public function show($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);

        $listingModel = new Listing($this->db);
        $listing = $listingModel->getById($id);

        if (!$listing || $listing['status'] !== 'approved') {
            jsonResponse(['error' => 'Property not found'], 404);
        }

        // Get all photos
        $photoStmt = $this->db->prepare("SELECT photo_url FROM listing_photos WHERE listing_id = ? ORDER BY is_cover DESC, sort_order");
        $photoStmt->execute([$id]);
        $listing['images'] = $photoStmt->fetchAll(PDO::FETCH_COLUMN);

        // Get agent details
        $agentStmt = $this->db->prepare("SELECT id, name, email, phone, agency_name, bio FROM users WHERE id = ?");
        $agentStmt->execute([$listing['user_id']]);
        $listing['agent'] = $agentStmt->fetch(PDO::FETCH_ASSOC);

        jsonResponse($listing);
    }
}