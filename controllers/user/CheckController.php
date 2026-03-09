<?php
// controllers/User/CheckController.php
// Tells the frontend whether an email/phone is already registered.
// This lets Auth.jsx decide: show login (password step) or register (create step).

class CheckController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function check() {
        $input = getJsonInput();

        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');

        if (empty($email) && empty($phone)) {
            jsonResponse(['error' => 'email or phone is required'], 400);
        }

        if ($email) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
        }

        jsonResponse(['exists' => (bool) $stmt->fetch()]);
    }
}