<?php
require_once __DIR__ . '/../../models/admin/Agent.php';
require_once __DIR__ . '/../../models/admin/ActivityLog.php';

class AgentController {
    private $db;
    private $admin;
    private $agentModel;
    private $logModel;

    public function __construct($db) {
        $this->db = $db;
        $this->agentModel = new Agent($db);
        $this->logModel = new ActivityLog($db);
    }

    public function setAdmin($admin) {
        $this->admin = $admin;
    }

    public function index() {
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        $agents = $this->agentModel->getAll($status, $search);
        jsonResponse(['data' => $agents]);
    }

    public function show($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $agent = $this->agentModel->getById($id);
        if (!$agent) jsonResponse(['error' => 'Agent not found'], 404);
        jsonResponse($agent);
    }

    public function verify($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);

        if ($this->agentModel->verify($id)) {
            $this->logModel->create('agent_verified', $this->admin['name'], "Agent #$id", '', 'agent');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Verification failed'], 500);
        }
    }

    public function reject($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $input = getJsonInput();
        $reason = $input['reason'] ?? '';

        if ($this->agentModel->reject($id, $reason)) {
            $this->logModel->create('agent_rejected', $this->admin['name'], "Agent #$id", $reason, 'agent');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Rejection failed'], 500);
        }
    }

    public function suspend($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $input = getJsonInput();
        $reason = $input['reason'] ?? '';

        if ($this->agentModel->suspend($id, $reason)) {
            $this->logModel->create('agent_suspended', $this->admin['name'], "Agent #$id", $reason, 'agent');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Suspension failed'], 500);
        }
    }

    public function reinstate($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);

        if ($this->agentModel->reinstate($id)) {
            $this->logModel->create('agent_reinstated', $this->admin['name'], "Agent #$id", '', 'agent');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Reinstatement failed'], 500);
        }
    }
}