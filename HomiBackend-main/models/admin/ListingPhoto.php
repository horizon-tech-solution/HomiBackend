<?php
class ListingPhoto {
    private $conn;
    private $table = 'listing_photos';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByListing($listingId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE listing_id = ? ORDER BY sort_order");
        $stmt->execute([$listingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table} (listing_id, photo_url, is_cover, sort_order) 
                VALUES (:listing_id, :photo_url, :is_cover, :sort_order)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':listing_id' => $data['listing_id'],
            ':photo_url' => $data['photo_url'],
            ':is_cover' => $data['is_cover'] ?? 0,
            ':sort_order' => $data['sort_order'] ?? 0
        ]);
    }

    public function setCover($id, $listingId) {
        // First, unset current cover for this listing
        $this->conn->prepare("UPDATE {$this->table} SET is_cover = 0 WHERE listing_id = ?")->execute([$listingId]);
        // Then set this photo as cover
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET is_cover = 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
}