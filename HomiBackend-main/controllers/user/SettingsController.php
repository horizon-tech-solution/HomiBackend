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

    public function getProfile() {
        $stmt = $this->db->prepare("SELECT id, name, email, phone, location, bio, created_at FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        jsonResponse($profile);
    }

    public function updateProfile() {
        $input = getJsonInput();
        $allowed = ['name', 'phone', 'location', 'bio'];
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

    public function changePassword() {
        $input = getJsonInput();
        $current = $input['current_password'] ?? '';
        $new = $input['new_password'] ?? '';

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

    public function getNotifications() {
        $stmt = $this->db->prepare("SELECT notification_preferences FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $row = $stmt->fetch();
        $prefs = json_decode($row['notification_preferences'] ?? '{}', true);
        jsonResponse($prefs);
    }

    public function updateNotifications() {
        $input = getJsonInput();
        $prefsJson = json_encode($input);
        $stmt = $this->db->prepare("UPDATE users SET notification_preferences = ? WHERE id = ?");
        if ($stmt->execute([$prefsJson, $this->user['id']])) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Update failed'], 500);
        }
    }
}