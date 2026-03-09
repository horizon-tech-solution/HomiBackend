<?php
// models/agent/Profile.php

class Profile {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // ── Get full profile ──────────────────────────────────────────────────────

    public function get(int $userId) {
        $stmt = $this->db->prepare(
            "SELECT
                u.id, u.name, u.email, u.phone, u.avatar_url,
                u.agency_name, u.agency_type, u.license_number,
                u.years_experience, u.bio, u.city,
                u.verification_status, u.verified_at, u.last_active,
                u.profile_meta,
                ai.national_id_number, ai.agent_type,
                -- Stats subqueries
                (SELECT COUNT(*) FROM listings WHERE user_id = u.id)                         AS total_listings,
                (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND status = 'approved')  AS active_listings,
                (SELECT COUNT(*) FROM inquiries i
                 JOIN listings l ON i.listing_id = l.id
                 WHERE l.user_id = u.id)                                                     AS total_leads,
                (SELECT COUNT(*) FROM view_history vh
                 JOIN listings l ON vh.listing_id = l.id
                 WHERE l.user_id = u.id)                                                     AS total_views
             FROM users u
             LEFT JOIN agent_identity ai ON ai.user_id = u.id
             WHERE u.id = ? AND u.role = 'agent'"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // Attach stats as nested object
        $row['stats'] = [
            'total_listings'  => (int) $row['total_listings'],
            'active_listings' => (int) $row['active_listings'],
            'total_leads'     => (int) $row['total_leads'],
            'total_views'     => (int) $row['total_views'],
        ];
        unset($row['total_listings'], $row['active_listings'], $row['total_leads'], $row['total_views']);

        return $row;
    }

    // ── Update profile fields ─────────────────────────────────────────────────

    public function update(int $userId, array $data): bool {
        $allowed = [
            'name', 'phone', 'bio', 'agency_name',
            'license_number', 'years_experience', 'city', 'profile_meta',
        ];

        $fields = [];
        $values = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $values[] = $userId;
        $stmt = $this->db->prepare(
            "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute($values);
    }

    // ── Avatar ────────────────────────────────────────────────────────────────

    public function updateAvatar(int $userId, string $path): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET avatar_url = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$path, $userId]);
    }

    // ── Change password ───────────────────────────────────────────────────────

    public function changePassword(int $userId, string $currentPassword, string $newPassword): array {
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hash, $userId]);
        return ['success' => true];
    }

    // ── Notification preferences (stored in users.notification_preferences) ──

    public function updateNotificationPreferences(int $userId, array $prefs): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET notification_preferences = ? WHERE id = ?"
        );
        return $stmt->execute([json_encode($prefs), $userId]);
    }

    public function getNotificationPreferences(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT notification_preferences FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !$row['notification_preferences']) return [];
        return json_decode($row['notification_preferences'], true) ?? [];
    }
}