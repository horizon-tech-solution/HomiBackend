<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth {
    private static $secretKey = 'your-secret-key-change-this'; // use env in production
    private static $algorithm = 'HS256';

    public static function generateToken($adminId, $username) {
        $payload = [
            'iss' => 'propty-api',
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24), // 24 hours
            'sub' => $adminId,
            'username' => $username
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