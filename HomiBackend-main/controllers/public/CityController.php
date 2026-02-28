<?php
class CityController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * GET /public/cities – get list of distinct cities with listings
     */
    public function index() {
        $stmt = $this->db->query("SELECT DISTINCT city FROM listings WHERE status = 'approved' ORDER BY city");
        $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
        jsonResponse($cities);
    }
}