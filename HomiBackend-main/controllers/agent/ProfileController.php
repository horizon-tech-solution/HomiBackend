<?php
require_once __DIR__ . '/../../models/admin/User.php';

class ProfileController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    public function show() {
        $userModel = new User($this->db);
        $profile = $userModel->getAgentProfile($this->user['id']);
        jsonResponse($profile);
    }

    public function update() {
        $input = getJsonInput();
        $allowed = ['name', 'phone', 'agency_name', 'bio', 'license_number', 'years_experience'];
        $updates = [];
        $params = [];
        foreach ($allowed as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
        if (empty($updates)) {
            jsonResponse(['message' => 'Nothing to update']);
        }
        $params[] = $this->user['id'];
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($params)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Update failed'], 500);
        }
    }
}