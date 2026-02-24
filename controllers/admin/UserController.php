<?php
require_once __DIR__ . '../../models/User.php';
require_once __DIR__ . '../../models/ActivityLog.php';

class UserController {
    private $db;
    private $admin;
    private $userModel;
    private $logModel;

    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($db);
        $this->logModel = new ActivityLog($db);
    }

    public function setAdmin($admin) {
        $this->admin = $admin;
    }

    public function index() {
        $role = $_GET['role'] ?? 'all';
        $search = $_GET['search'] ?? null;
        $users = $this->userModel->getAll($role, null, $search);
        jsonResponse(['data' => $users]);
    }

    public function show($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $user = $this->userModel->getById($id);
        if (!$user) jsonResponse(['error' => 'User not found'], 404);
        $user['activity_log'] = $this->userModel->getActivityLog($id);
        $user['recent_listings'] = $this->userModel->getRecentListings($id);
        jsonResponse($user);
    }

    public function block($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $input = getJsonInput();
        $reason = $input['reason'] ?? '';

        if ($this->userModel->updateStatus($id, 'blocked', $reason)) {
            $this->logModel->create('user_blocked', $this->admin['name'], "User #$id", $reason, 'user');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Block failed'], 500);
        }
    }

    public function unblock($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);

        if ($this->userModel->updateStatus($id, 'active')) {
            $this->logModel->create('user_unblocked', $this->admin['name'], "User #$id", '', 'user');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Unblock failed'], 500);
        }
    }

    public function delete($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);

        if ($this->userModel->delete($id)) {
            $this->logModel->create('user_deleted', $this->admin['name'], "User #$id", '', 'user');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Delete failed'], 500);
        }
    }
}