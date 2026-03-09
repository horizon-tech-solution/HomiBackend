<?php
// models/agent/Settings.php

class Settings {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // ── Get full settings for agent ───────────────────────────────────────────
    public function get($userId) {
        $stmt = $this->db->prepare(
            "SELECT u.email,
                    u.notification_preferences,
                    u.verification_status
             FROM users u
             WHERE u.id = ? AND u.role = 'agent'"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // Parse notification_preferences JSON
        $prefs = $row['notification_preferences']
            ? json_decode($row['notification_preferences'], true)
            : [];

        return [
            'raw_prefs'              => $prefs,
            'email'                  => $row['email'],
            'verification_status'    => $row['verification_status'],
            'emailNotifications'     => $prefs['email']   ?? $this->defaultEmailNotifications(),
            'pushNotifications'      => $prefs['push']    ?? $this->defaultPushNotifications(),
            'smsNotifications'       => $prefs['sms']     ?? $this->defaultSmsNotifications(),
            'autoResponse'           => $prefs['autoResponse']        ?? false,
            'autoResponseMessage'    => $prefs['autoResponseMessage'] ?? '',
            'profileVisibility'      => $prefs['profileVisibility']   ?? 'public',
            'showPhone'              => $prefs['showPhone']            ?? true,
            'showEmail'              => $prefs['showEmail']            ?? true,
            'showWhatsApp'           => $prefs['showWhatsApp']         ?? true,
            'allowMessages'          => $prefs['allowMessages']        ?? true,
            'theme'                  => $prefs['theme']                ?? 'light',
            'language'               => $prefs['language']             ?? 'en',
        ];
    }

    // ── Update email ──────────────────────────────────────────────────────────
    public function updateEmail($userId, $email) {
        // Check email not taken by another user
        $check = $this->db->prepare(
            "SELECT id FROM users WHERE email = ? AND id != ?"
        );
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            return ['success' => false, 'error' => 'Email already in use'];
        }

        $stmt = $this->db->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$email, $userId]);
        return ['success' => true];
    }

    // ── Update notification preferences ───────────────────────────────────────
    public function updateNotificationPreferences($userId, array $prefs) {
        // Merge with existing
        $existing = $this->getRawPreferences($userId);
        $merged   = array_merge($existing, $prefs);

        $stmt = $this->db->prepare(
            "UPDATE users SET notification_preferences = ? WHERE id = ?"
        );
        $stmt->execute([json_encode($merged), $userId]);
        return ['success' => true];
    }

    // ── Update privacy settings ───────────────────────────────────────────────
    public function updatePrivacy($userId, array $data) {
        $allowed = ['profileVisibility', 'showPhone', 'showEmail', 'showWhatsApp', 'allowMessages'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        return $this->updateNotificationPreferences($userId, $filtered);
    }

    // ── Update appearance settings ────────────────────────────────────────────
    public function updateAppearance($userId, array $data) {
        $allowed  = ['theme', 'language'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        return $this->updateNotificationPreferences($userId, $filtered);
    }

    // ── Update auto-response ──────────────────────────────────────────────────
    public function updateAutoResponse($userId, bool $enabled, string $message = '') {
        return $this->updateNotificationPreferences($userId, [
            'autoResponse'        => $enabled,
            'autoResponseMessage' => $message,
        ]);
    }

    // ── Change password ───────────────────────────────────────────────────────
    public function changePassword($userId, $currentPassword, $newPassword) {
        // Validate length
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }

        // Get current hash
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);
        return ['success' => true];
    }

    // ── Merge any subset of preferences ──────────────────────────────────────
    public function mergePreferences(int $userId, array $data): bool {
        $existing = $this->getRawPreferences($userId);
        $merged   = array_merge($existing, $data);
        $stmt = $this->db->prepare(
            "UPDATE users SET notification_preferences = ? WHERE id = ?"
        );
        return $stmt->execute([json_encode($merged), $userId]);
    }

    // ── Delete account ────────────────────────────────────────────────────
    // Or hard delete if your app supports it
    public function deleteAccount($userId, $password) {
        // Verify password before deleting
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Password is incorrect'];
        }

        // Hard delete — cascades to agent_identity, listings etc. via FK
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return ['success' => true];
    }

    // ── Private helpers ───────────────────────────────────────────────────────
    private function getRawPreferences($userId) {
        $stmt = $this->db->prepare(
            "SELECT notification_preferences FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !$row['notification_preferences']) return [];
        return json_decode($row['notification_preferences'], true) ?? [];
    }

    private function defaultEmailNotifications() {
        return [
            'newLeads'       => true,
            'messages'       => true,
            'viewingRequests'=> true,
            'propertyUpdates'=> false,
            'weeklyReport'   => true,
            'marketingEmails'=> false,
        ];
    }

    private function defaultPushNotifications() {
        return [
            'newLeads'       => true,
            'messages'       => true,
            'viewingRequests'=> true,
            'propertyUpdates'=> false,
        ];
    }

    private function defaultSmsNotifications() {
        return [
            'urgentLeads'      => true,
            'viewingReminders' => true,
            'systemAlerts'     => false,
        ];
    }
}