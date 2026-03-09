<?php
// middleware/AgentAuthMiddleware.php
require_once __DIR__ . '/../config/user_auth.php';

class AgentAuthMiddleware {

    public function authenticate(): ?array {
        $headers    = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No token provided']);
            exit;
        }

        $token   = substr($authHeader, 7);
        $payload = UserAuth::validateToken($token); // uses validateToken, not verifyToken

        if (!$payload) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }

        $userId = $payload['sub'] ?? null;
        if (!$userId) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid token payload']);
            exit;
        }

        $db   = (new Database())->getConnection();
        $stmt = $db->prepare(
            "SELECT id, name, email, phone, role, status, verification_status, avatar_url
             FROM users WHERE id = ? AND role = 'agent'"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Agent account not found']);
            exit;
        }

        if ($user['status'] === 'blocked') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Account suspended']);
            exit;
        }

        return $user;
    }
}