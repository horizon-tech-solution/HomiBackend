<?php
class SettingsController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    public function index() {
        // Get user settings from `users.settings` JSON column (we'll assume it exists)
        $stmt = $this->db->prepare("SELECT settings, notification_preferences FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        jsonResponse([
            'settings' => json_decode($row['settings'] ?? '{}', true),
            'notification_preferences' => json_decode($row['notification_preferences'] ?? '{}', true)
        ]);
    }

    public function update() {
        $input = getJsonInput();
        // Update settings JSON column
        $stmt = $this->db->prepare("UPDATE users SET settings = ? WHERE id = ?");
        $settingsJson = json_encode($input['settings'] ?? []);
        if ($stmt->execute([$settingsJson, $this->user['id']])) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Update failed'], 500);
        }
    }

    public function changePassword() {
        $input = getJsonInput();
        $current = $input['current_password'] ?? '';
        $new = $input['new_password'] ?? '';

        // Verify current password
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($current, $user['password_hash'])) {
            jsonResponse(['error' => 'Current password is incorrect'], 401);
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $update = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        if ($update->execute([$hash, $this->user['id']])) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Password change failed'], 500);
        }
    }
}