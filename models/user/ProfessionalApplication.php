<?php
class ProfessionalApplication {
    private $conn;
    private $table = 'professional_applications';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table} 
                (user_id, professional_type, business_name, tax_id, license_number, years_experience, 
                 office_address, phone_number, region, city, notary_name, notary_contact)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $data['user_id'],
            $data['professional_type'],
            $data['business_name'],
            $data['tax_id'],
            $data['license_number'] ?? null,
            $data['years_experience'] ?? null,
            $data['office_address'],
            $data['phone_number'],
            $data['region'] ?? null,
            $data['city'] ?? null,
            $data['notary_name'] ?? null,
            $data['notary_contact'] ?? null
        ]);
    }

    public function getByUser($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY submitted_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status, $adminNotes = '') {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET status = ?, admin_notes = ?, reviewed_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $adminNotes, $id]);
    }
}