<?php
require_once __DIR__ . '/../../models/user/ViewHistory.php';

class HistoryController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    public function browse() {
        $historyModel = new ViewHistory($this->db);
        $limit = $_GET['limit'] ?? 20;
        $history = $historyModel->getByUser($this->user['id'], $limit);
        jsonResponse(['data' => $history]);
    }

    public function search() {
        // This would be a separate model for search history (if we store searches)
        // For now, we might not have that, but we can return empty or from a logs table
        jsonResponse(['data' => []]);
    }

    public function recordView($params) {
        $listingId = $params['listingId'] ?? null;
        if (!$listingId) jsonResponse(['error' => 'Listing ID required'], 400);

        $historyModel = new ViewHistory($this->db);
        $historyModel->record($this->user['id'], $listingId);
        jsonResponse(['success' => true]);
    }
}