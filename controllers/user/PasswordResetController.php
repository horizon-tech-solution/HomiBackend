<?php
// controllers/user/PasswordResetController.php
// DEV VERSION — no email/token needed, just email + new password
// When email service is ready: swap resetPassword() to use tokens

require_once __DIR__ . '/../../config/user_auth.php';

class PasswordResetController {
    private $db;

    public function __construct($db) { $this->db = $db; }

    // ── POST /user/auth/reset-password ────────────────────────────────────────
    // Body: { email, password, password_confirmation }
    public function resetPassword(): void {
        $input    = getJsonInput();
        $email    = trim(strtolower($input['email']                ?? ''));
        $password = trim($input['password']                        ?? '');
        $confirm  = trim($input['password_confirmation']           ?? '');

        if (!$email || !$password || !$confirm) {
            jsonResponse(['error' => 'Email, password and confirmation are required'], 400);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Invalid email'], 400);
            return;
        }

        if ($password !== $confirm) {
            jsonResponse(['error' => 'Passwords do not match'], 400);
            return;
        }

        if (strlen($password) < 8) {
            jsonResponse(['error' => 'Password must be at least 8 characters'], 400);
            return;
        }

        // Verify user exists
        $stmt = $this->db->prepare(
            "SELECT id FROM users WHERE email = ? AND status = 'active' LIMIT 1"
        );
        $stmt->execute([$email]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'No account found with this email'], 404);
            return;
        }

        // Update password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->db->prepare(
            "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?"
        )->execute([$hash, $email]);

        jsonResponse(['message' => 'Password reset successfully. You can now log in.']);
    }
}