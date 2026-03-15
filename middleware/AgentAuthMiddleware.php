<?php
// middleware/AgentAuthMiddleware.php
require_once __DIR__ . '/../config/user_auth.php';

class AgentAuthMiddleware {

    public function authenticate(): ?array {
        // ── 1. Try httpOnly cookie first ──────────────────────────────────
        $token = getTokenFromCookie();

        // ── 2. Fall back to Authorization header (transition period) ──────
        if (!$token) {
            $headers    = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
            }
        }

        if (!$token) {
            $this->abort(401, 'No token provided');
        }

        $payload = UserAuth::validateToken($token);

        if (!$payload) {
            $this->abort(401, 'Invalid or expired token');
        }

        // ── Reject non-agent tokens at token level ────────────────────────
        // Prevents a user token from accessing agent routes even if the DB
        // record somehow has role = 'agent'
        if (($payload['role'] ?? '') !== 'agent') {
            $this->abort(403, 'Agent access required');
        }

        $userId = $payload['sub'] ?? null;
        if (!$userId) {
            $this->abort(401, 'Invalid token payload');
        }

        $db   = (new Database())->getConnection();
        $stmt = $db->prepare(
            "SELECT id, name, email, phone, role, status, verification_status, avatar_url
             FROM users WHERE id = ? AND role = 'agent'"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->abort(403, 'Agent account not found');
        }

        if ($user['status'] === 'blocked') {
            $this->abort(403, 'Account suspended');
        }

        return $user;
    }

    private function abort(int $code, string $message): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}