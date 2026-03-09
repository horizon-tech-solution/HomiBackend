<?php
require_once __DIR__ . '/../../models/user/SavedSearch.php';

class SavedSearchController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    public function index() {
        $searchModel = new SavedSearch($this->db);
        $searches = $searchModel->getByUser($this->user['id']);
        jsonResponse(['data' => $searches]);
    }

    public function store() {
        $input = getJsonInput();
        $name = $input['name'] ?? '';
        $criteria = $input['criteria'] ?? [];

        if (empty($name) || empty($criteria)) {
            jsonResponse(['error' => 'Name and criteria are required'], 400);
        }

        $searchModel = new SavedSearch($this->db);
        if ($searchModel->create($this->user['id'], $name, $criteria)) {
            jsonResponse(['success' => true], 201);
        } else {
            jsonResponse(['error' => 'Failed to save search'], 500);
        }
    }

    public function destroy($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);

        $searchModel = new SavedSearch($this->db);
        if ($searchModel->delete($id, $this->user['id'])) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Search not found'], 404);
        }
    }
}