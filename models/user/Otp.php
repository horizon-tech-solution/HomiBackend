<?php
// models/user/Otp.php
// Requires this table — add to your schema:


class Otp {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Generate a 6-digit OTP, delete any previous ones for this user+reason
    public function generate(int $userId, string $reason): string {
        // Delete old OTPs for this user + reason
        $del = $this->conn->prepare("DELETE FROM otps WHERE user_id = ? AND reason = ?");
        $del->execute([$userId, $reason]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $stmt = $this->conn->prepare("INSERT INTO otps (user_id, code, reason, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $code, $reason, $expires]);

        return $code;
    }

    // Verify the code — returns true and marks it used, or returns false
    public function verify(int $userId, string $code, string $reason): bool {
        $stmt = $this->conn->prepare(
            "SELECT id FROM otps
             WHERE user_id = ? AND code = ? AND reason = ?
             AND expires_at > NOW() AND used_at IS NULL"
        );
        $stmt->execute([$userId, $code, $reason]);
        $row = $stmt->fetch();

        if (!$row) return false;

        // Mark as used
        $update = $this->conn->prepare("UPDATE otps SET used_at = NOW() WHERE id = ?");
        $update->execute([$row['id']]);

        return true;
    }
}