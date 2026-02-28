<?php
require_once __DIR__ . '/../../models/user/Favorite.php';

class FavoriteController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    public function index() {
        $favoriteModel = new Favorite($this->db);
        $favorites = $favoriteModel->getByUser($this->user['id']);
        jsonResponse(['data' => $favorites]);
    }

    public function add($params) {
        $listingId = $params['listingId'] ?? null;
        if (!$listingId) jsonResponse(['error' => 'Listing ID required'], 400);

        $favoriteModel = new Favorite($this->db);
        if ($favoriteModel->add($this->user['id'], $listingId)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to add favorite'], 500);
        }
    }

    public function remove($params) {
        $listingId = $params['listingId'] ?? null;
        if (!$listingId) jsonResponse(['error' => 'Listing ID required'], 400);

        $favoriteModel = new Favorite($this->db);
        if ($favoriteModel->remove($this->user['id'], $listingId)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to remove favorite'], 500);
        }
    }
}