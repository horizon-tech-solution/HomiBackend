<?php
require_once __DIR__ . '/../../models/user/ViewHistory.php';
require_once __DIR__ . '/../../models/user/SavedSearch.php';

class HistoryController {
    private $db;
    private $user;

    public function __construct($db) { $this->db = $db; }
    public function setUser($user)   { $this->user = $user; }

    // GET /user/history/browse
    public function browse(): void {
        $model   = new ViewHistory($this->db);
        $limit   = min((int)($_GET['limit'] ?? 20), 50);
        $history = $model->getByUser($this->user['id'], $limit);
        jsonResponse(['data' => $history, 'total' => count($history)]);
    }

    // POST /user/history/view/:listingId
    public function recordView(array $params): void {
        $listingId = (int)($params['listingId'] ?? 0);
        if (!$listingId) { jsonResponse(['error' => 'Listing ID required'], 400); return; }

        $model = new ViewHistory($this->db);
        $model->record($this->user['id'], $listingId);
        jsonResponse(['success' => true]);
    }

    // POST /user/history/search  — auto-called when user applies filters
    // Body: { q?, city?, listingType?, bedrooms?, price_min?, price_max? }
    public function recordSearch(): void {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $criteria = array_filter($body, fn($v) => $v !== '' && $v !== null);

        if (empty($criteria)) {
            jsonResponse(['success' => true]); // nothing to save
            return;
        }

        $model = new SavedSearch($this->db);
        $model->autoSave($this->user['id'], $criteria);
        jsonResponse(['success' => true]);
    }
}