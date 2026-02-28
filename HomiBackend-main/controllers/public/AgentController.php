<?php
require_once __DIR__ . '/../../models/admin/User.php';

class AgentController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * GET /public/agents – list verified agents
     */
    public function index() {
        $filters = [
            'search' => $_GET['search'] ?? null,
            'location' => $_GET['location'] ?? null,
            'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : null,
        ];

        $userModel = new User($this->db);
        $agents = $userModel->getPublicAgents($filters);

        // Add profile image placeholder
        foreach ($agents as &$agent) {
            $agent['image'] = 'https://ui-avatars.com/api/?name=' . urlencode($agent['name']) . '&size=200&background=random';
        }

        jsonResponse(['data' => $agents]);
    }

    /**
     * GET /public/agents/{id} – get agent profile with recent listings
     */
    public function show($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);

        $userModel = new User($this->db);
        $agent = $userModel->getPublicAgentById($id);

        if (!$agent) {
            jsonResponse(['error' => 'Agent not found'], 404);
        }

        // Add profile image
        $agent['image'] = 'https://ui-avatars.com/api/?name=' . urlencode($agent['name']) . '&size=200&background=random';

        jsonResponse($agent);
    }
}