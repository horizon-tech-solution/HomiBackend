<?php

class UserAuth {
    private static $secretKey;
    private static $algorithm = 'HS256';

    private static function getSecretKey(): string {
        if (!self::$secretKey) {
            self::$secretKey = $_ENV['USER_JWT_SECRET'] ?? $_ENV['JWT_SECRET'] ?? 'your-user-secret-key-change-this';
        }
        return self::$secretKey;
    }

    public static function generateToken(int $userId, string $email, string $role = 'user'): string {
        $payload = [
            'iss'   => 'homi-api',
            'iat'   => time(),
            'exp'   => time() + (60 * 60 * 24 * 7), // 7 days
            'sub'   => $userId,
            'email' => $email,
            'role'  => $role,
        ];
        return \Firebase\JWT\JWT::encode($payload, self::getSecretKey(), self::$algorithm);
    }

    public static function validateToken(string $token): ?array {
        try {
            $decoded = \Firebase\JWT\JWT::decode(
                $token,
                new \Firebase\JWT\Key(self::getSecretKey(), self::$algorithm)
            );
            $payload = (array) $decoded;

            // Block admin tokens from being used on user/agent routes
            if (($payload['role'] ?? null) === 'admin') {
                return null;
            }

            return $payload;
        } catch (Exception $e) {
            return null;
        }
    }
}