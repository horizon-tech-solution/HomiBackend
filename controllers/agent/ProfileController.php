<?php
// controllers/agent/ProfileController.php
require_once __DIR__ . '/../../models/agent/Profile.php';

class ProfileController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    // ── GET /agent/profile ────────────────────────────────────────────────────

    public function index($params = []) {
        $model   = new Profile($this->db);
        $profile = $model->get((int) $this->user['id']);

        if (!$profile) {
            http_response_code(404);
            jsonResponse(['error' => 'Profile not found']);
            return;
        }

        jsonResponse($profile);
    }

    // ── PUT /agent/profile ────────────────────────────────────────────────────

    public function update($params = []) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Basic validation
        if (!empty($data['name']) && strlen(trim($data['name'])) < 2) {
            http_response_code(422);
            jsonResponse(['error' => 'Name must be at least 2 characters']);
            return;
        }

        // Sanitise years_experience
        if (isset($data['years_experience'])) {
            $data['years_experience'] = max(0, min(60, (int) $data['years_experience']));
        }

        // profile_meta must be valid JSON string if provided
        if (isset($data['profile_meta']) && !is_string($data['profile_meta'])) {
            $data['profile_meta'] = json_encode($data['profile_meta']);
        }

        $model   = new Profile($this->db);
        $success = $model->update((int) $this->user['id'], $data);

        if (!$success) {
            http_response_code(500);
            jsonResponse(['error' => 'Update failed']);
            return;
        }

        jsonResponse(['success' => true, 'message' => 'Profile updated']);
    }

    // ── POST /agent/profile/avatar ────────────────────────────────────────────

    public function uploadAvatar($params = []) {
        if (empty($_FILES['avatar'])) {
            http_response_code(400);
            jsonResponse(['error' => 'No file uploaded']);
            return;
        }

        $file  = $_FILES['avatar'];
        $error = $file['error'];

        if ($error !== UPLOAD_ERR_OK) {
            http_response_code(400);
            jsonResponse(['error' => 'Upload error code: ' . $error]);
            return;
        }

        // Type check — use extension + client MIME (mime_content_type unavailable on Windows without fileinfo)
        $clientMime   = $file['type'] ?? '';
        $origName     = strtolower($file['name'] ?? '');
        $ext          = pathinfo($origName, PATHINFO_EXTENSION);
        $allowedExts  = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($ext, $allowedExts) || !in_array($clientMime, $allowedMimes)) {
            http_response_code(422);
            jsonResponse(['error' => 'Only JPG, PNG, and WEBP images are allowed']);
            return;
        }

        // Normalise ext
        if ($ext === 'jpeg') $ext = 'jpg';

        // Size check (5 MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            http_response_code(422);
            jsonResponse(['error' => 'Image must be under 5 MB']);
            return;
        }

        $dir = __DIR__ . '/../../public/uploads/avatars/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = 'agent_' . $this->user['id'] . '_' . time() . '.' . $ext;
        $dest     = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            http_response_code(500);
            jsonResponse(['error' => 'Failed to save image']);
            return;
        }

        $publicPath = '/uploads/avatars/' . $filename;
        $model = new Profile($this->db);
        $model->updateAvatar((int) $this->user['id'], $publicPath);

        jsonResponse(['success' => true, 'avatar_url' => $publicPath]);
    }

    // ── POST /agent/profile/change-password ───────────────────────────────────

    public function changePassword($params = []) {
        $data            = json_decode(file_get_contents('php://input'), true) ?? [];
        $currentPassword = trim($data['current_password'] ?? '');
        $newPassword     = trim($data['new_password'] ?? '');

        if (!$currentPassword || !$newPassword) {
            http_response_code(422);
            jsonResponse(['error' => 'Both current and new passwords are required']);
            return;
        }

        if (strlen($newPassword) < 8) {
            http_response_code(422);
            jsonResponse(['error' => 'New password must be at least 8 characters']);
            return;
        }

        $model  = new Profile($this->db);
        $result = $model->changePassword((int) $this->user['id'], $currentPassword, $newPassword);

        if (!$result['success']) {
            http_response_code(401);
            jsonResponse(['error' => $result['error']]);
            return;
        }

        jsonResponse(['success' => true, 'message' => 'Password changed successfully']);
    }
}