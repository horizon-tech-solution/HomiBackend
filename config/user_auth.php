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
            'exp'   => time() + (60 * 60 * 24 * 7),
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

            if (($payload['role'] ?? null) === 'admin') {
                return null;
            }

            return $payload;
        } catch (Exception $e) {
            return null;
        }
    }
}  // ← class ends HERE

// ── Cookie helpers — outside the class ───────────────────────────────────────

function setAuthCookie(string $token, int $days = 30): void {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('homi_token', $token, [
        'expires'  => time() + ($days * 24 * 60 * 60),
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearAuthCookie(): void {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('homi_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function getTokenFromCookie(): ?string {
    return $_COOKIE['homi_token'] ?? null;
}