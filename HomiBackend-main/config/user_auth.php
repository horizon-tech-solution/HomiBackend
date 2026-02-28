<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class UserAuth {
    private static $secretKey = 'your-user-secret-key-change-this'; // use env in production
    private static $algorithm = 'HS256';

    public static function generateToken($userId, $email) {
        $payload = [
            'iss' => 'homi-api',
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24 * 7), // 7 days
            'sub' => $userId,
            'email' => $email
        ];
        return JWT::encode($payload, self::$secretKey, self::$algorithm);
    }

    public static function validateToken($token) {
        try {
            $decoded = JWT::decode($token, new Key(self::$secretKey, self::$algorithm));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }
}