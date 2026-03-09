<?php
// controllers/agent/SettingsController.php
require_once __DIR__ . '/../../models/agent/Settings.php';

class SettingsController {
    private $db;
    private $user;

    public function __construct($db) { $this->db = $db; }
    public function setUser($user)   { $this->user = $user; }

    // ── GET /agent/settings ───────────────────────────────────────────────────

    public function index($params = []) {
        $model = new Settings($this->db);
        $data  = $model->get((int) $this->user['id']);

        if (!$data) {
            http_response_code(404);
            jsonResponse(['error' => 'Settings not found']);
            return;
        }

        // Return only the fields the frontend actually uses
        $prefs = $data['raw_prefs'] ?? [];

        jsonResponse([
            'notifs' => [
                'new_inquiry'    => (bool) ($prefs['new_inquiry']    ?? true),
                'inquiry_reply'  => (bool) ($prefs['inquiry_reply']  ?? true),
                'listing_status' => (bool) ($prefs['listing_status'] ?? true),
            ],
            'privacy' => [
                'show_phone'     => (bool) ($prefs['show_phone']     ?? true),
                'show_email'     => (bool) ($prefs['show_email']     ?? true),
                'show_whatsapp'  => (bool) ($prefs['show_whatsapp']  ?? true),
                'allow_messages' => (bool) ($prefs['allow_messages'] ?? true),
            ],
        ]);
    }

    // ── PUT /agent/settings/notifications ────────────────────────────────────

    public function updateNotifications($params = []) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $allowed = ['new_inquiry', 'inquiry_reply', 'listing_status'];
        $clean   = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $clean[$key] = (bool) $data[$key];
            }
        }

        if (empty($clean)) {
            http_response_code(422);
            jsonResponse(['error' => 'No valid fields provided']);
            return;
        }

        $model = new Settings($this->db);
        $model->mergePreferences((int) $this->user['id'], $clean);
        jsonResponse(['success' => true]);
    }

    // ── PUT /agent/settings/privacy ───────────────────────────────────────────

    public function updatePrivacy($params = []) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $allowed = ['show_phone', 'show_email', 'show_whatsapp', 'allow_messages'];
        $clean   = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $clean[$key] = (bool) $data[$key];
            }
        }

        if (empty($clean)) {
            http_response_code(422);
            jsonResponse(['error' => 'No valid fields provided']);
            return;
        }

        $model = new Settings($this->db);
        $model->mergePreferences((int) $this->user['id'], $clean);
        jsonResponse(['success' => true]);
    }

    // ── POST /agent/settings/change-password ──────────────────────────────────

    public function changePassword($params = []) {
        $data    = json_decode(file_get_contents('php://input'), true) ?? [];
        $current = trim($data['current_password'] ?? '');
        $new     = trim($data['new_password']     ?? '');

        if (!$current || !$new) {
            http_response_code(422);
            jsonResponse(['error' => 'Both current and new passwords are required']);
            return;
        }
        if (strlen($new) < 8) {
            http_response_code(422);
            jsonResponse(['error' => 'New password must be at least 8 characters']);
            return;
        }

        // Delegate to Profile model (it has changePassword already)
        require_once __DIR__ . '/../../models/agent/Profile.php';
        $profile = new Profile($this->db);
        $result  = $profile->changePassword((int) $this->user['id'], $current, $new);

        if (!$result['success']) {
            http_response_code(401);
            jsonResponse(['error' => $result['error']]);
            return;
        }

        jsonResponse(['success' => true, 'message' => 'Password changed successfully']);
    }

    // ── POST /agent/settings/delete-account ───────────────────────────────────

    public function deleteAccount($params = []) {
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $password = trim($data['password'] ?? '');

        if (!$password) {
            http_response_code(422);
            jsonResponse(['error' => 'Password is required to delete your account']);
            return;
        }

        $model  = new Settings($this->db);
        $result = $model->deleteAccount((int) $this->user['id'], $password);

        if (!$result['success']) {
            http_response_code(401);
            jsonResponse(['error' => $result['error']]);
            return;
        }

        jsonResponse(['success' => true]);
    }
}