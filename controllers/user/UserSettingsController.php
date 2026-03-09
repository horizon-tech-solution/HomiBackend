<?php
// controllers/User/SettingsController.php
require_once __DIR__ . '/../../models/user/Notification.php';
require_once __DIR__ . '/../../models/user/Otp.php';
require_once __DIR__ . '/../../config/email.php';

class UserSettingsController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    // ── GET /user/settings/profile ────────────────────────────────────────────
    public function getProfile() {
        $stmt = $this->db->prepare(
            "SELECT id, name, email, phone, city, bio, avatar_url, created_at
             FROM users WHERE id = ?"
        );
        $stmt->execute([$this->user['id']]);
        jsonResponse($stmt->fetch(PDO::FETCH_ASSOC));
    }

    // ── PUT /user/settings/profile ────────────────────────────────────────────
    public function updateProfile() {
        $input   = getJsonInput();
        $allowed = ['name', 'phone', 'city', 'bio'];
        $updates = [];
        $params  = [];

        foreach ($allowed as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[]  = $input[$field];
            }
        }

        if (empty($updates)) {
            jsonResponse(['message' => 'Nothing to update']);
        }

        $params[] = $this->user['id'];
        $stmt = $this->db->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");

        if ($stmt->execute($params)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Update failed'], 500);
        }
    }

    // ── POST /user/settings/otp/send ─────────────────────────────────────────
    // Body: { "reason": "password_change" | "email_change" | "account_delete" | "login_2fa" }
    public function sendOtp() {
        $input  = getJsonInput();
        $reason = $input['reason'] ?? '';

        $allowed = ['password_change', 'email_change', 'account_delete', 'login_2fa'];
        if (!in_array($reason, $allowed)) {
            jsonResponse(['error' => 'Invalid reason'], 400);
        }

        $otpModel = new Otp($this->db);
        $code     = $otpModel->generate($this->user['id'], $reason);

        $sent = EmailService::sendOtp(
            $this->user['email'],
            $this->user['name'],
            $code,
            $reason
        );

        if (!$sent) {
            jsonResponse(['error' => 'Failed to send verification email'], 500);
        }

        jsonResponse(['success' => true, 'message' => 'Verification code sent to your email']);
    }

    // ── POST /user/settings/password ─────────────────────────────────────────
    // Body: { "current_password", "new_password", "otp" }
    public function changePassword() {
        $input   = getJsonInput();
        $current = $input['current_password'] ?? '';
        $new     = $input['new_password']     ?? '';
        $otp     = $input['otp']              ?? '';

        if (strlen($new) < 8) {
            jsonResponse(['error' => 'New password must be at least 8 characters'], 400);
        }

        // Verify OTP first
        $otpModel = new Otp($this->db);
        if (!$otpModel->verify($this->user['id'], $otp, 'password_change')) {
            jsonResponse(['error' => 'Invalid or expired verification code'], 401);
        }

        // Verify current password
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password_hash'])) {
            jsonResponse(['error' => 'Current password is incorrect'], 401);
        }

        $hash   = password_hash($new, PASSWORD_DEFAULT);
        $update = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");

        if ($update->execute([$hash, $this->user['id']])) {
            // Send confirmation email
            EmailService::sendPasswordChanged($this->user['email'], $this->user['name']);

            // Create in-app notification
            $notif = new Notification($this->db);
            $notif->create($this->user['id'], 'system', 'Password changed', 'Your account password was successfully updated.');

            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Password change failed'], 500);
        }
    }

    // ── POST /user/settings/email ─────────────────────────────────────────────
    // Body: { "new_email", "password", "otp" }
    public function changeEmail() {
        $input     = getJsonInput();
        $newEmail  = trim($input['new_email'] ?? '');
        $password  = $input['password']       ?? '';
        $otp       = $input['otp']            ?? '';

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Invalid email address'], 400);
        }

        // Check not already taken
        $check = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$newEmail, $this->user['id']]);
        if ($check->fetch()) {
            jsonResponse(['error' => 'Email already in use'], 409);
        }

        // Verify OTP
        $otpModel = new Otp($this->db);
        if (!$otpModel->verify($this->user['id'], $otp, 'email_change')) {
            jsonResponse(['error' => 'Invalid or expired verification code'], 401);
        }

        // Verify password
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(['error' => 'Password is incorrect'], 401);
        }

        $oldEmail = $this->user['email'];
        $update   = $this->db->prepare("UPDATE users SET email = ? WHERE id = ?");

        if ($update->execute([$newEmail, $this->user['id']])) {
            // Notify old AND new email
            EmailService::sendEmailChanged($oldEmail, $this->user['name'], $newEmail);
            EmailService::sendEmailChanged($newEmail, $this->user['name'], $newEmail);

            $notif = new Notification($this->db);
            $notif->create($this->user['id'], 'system', 'Email address updated', "Your login email has been changed to {$newEmail}.");

            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Email update failed'], 500);
        }
    }

    // ── DELETE /user/settings/account ─────────────────────────────────────────
    // Body: { "password", "otp" }
    public function deleteAccount() {
        $input    = getJsonInput();
        $password = $input['password'] ?? '';
        $otp      = $input['otp']      ?? '';

        // Verify OTP
        $otpModel = new Otp($this->db);
        if (!$otpModel->verify($this->user['id'], $otp, 'account_delete')) {
            jsonResponse(['error' => 'Invalid or expired verification code'], 401);
        }

        // Verify password
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(['error' => 'Password is incorrect'], 401);
        }

        // Send farewell email BEFORE deleting
        EmailService::sendAccountDeleted($this->user['email'], $this->user['name']);

        // Delete — cascades to favorites, notifications, saved_searches, etc.
        $del = $this->db->prepare("DELETE FROM users WHERE id = ?");
        if ($del->execute([$this->user['id']])) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Account deletion failed'], 500);
        }
    }

    // ── GET /user/settings/notifications ──────────────────────────────────────
    public function getNotificationPrefs() {
        $stmt = $this->db->prepare("SELECT notification_preferences FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $row   = $stmt->fetch();
        $prefs = json_decode($row['notification_preferences'] ?? '{}', true) ?: self::defaultPrefs();
        jsonResponse($prefs);
    }

    // ── PUT /user/settings/notifications ──────────────────────────────────────
    public function updateNotificationPrefs() {
        $input     = getJsonInput();
        $prefsJson = json_encode($input);
        $stmt      = $this->db->prepare("UPDATE users SET notification_preferences = ? WHERE id = ?");
        if ($stmt->execute([$prefsJson, $this->user['id']])) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Update failed'], 500);
        }
    }

private static function defaultPrefs(): array {
    return [
        'email' => [
            'price_drop'      => true,
            'new_listing'     => true,
            'message'         => true,
            'saved_searches'  => true,
            'recommendations' => false,
        ],
        'push' => [
            'price_drop' => true,
            'new_listing'=> false,
            'message'    => true,
        ],
    ];
}

// ── POST /user/settings/avatar ────────────────────────────────────────────
public function uploadAvatar() {
        if (empty($_FILES['avatar'])) {
            jsonResponse(['error' => 'No file uploaded'], 400);
        }

        $file     = $_FILES['avatar'];
        $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize  = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed)) {
            jsonResponse(['error' => 'Only JPG, PNG, and WebP images are allowed'], 400);
        }
        if ($file['size'] > $maxSize) {
            jsonResponse(['error' => 'Image must be under 5MB'], 400);
        }

        $uploadDir = __DIR__ . '/../../public/uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Delete old avatar file if it exists
        $stmt = $this->db->prepare("SELECT avatar_url FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $old = $stmt->fetchColumn();
        if ($old) {
            $oldFile = __DIR__ . '/../../public' . parse_url($old, PHP_URL_PATH);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $ext      = match($file['type']) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
        $filename = 'user_' . $this->user['id'] . '_' . time() . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            jsonResponse(['error' => 'Failed to save image'], 500);
        }

        $appUrl    = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        $avatarUrl = $appUrl . '/uploads/avatars/' . $filename;

        $update = $this->db->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
        $update->execute([$avatarUrl, $this->user['id']]);

        jsonResponse(['avatar_url' => $avatarUrl]);
    }
}