<?php
require_once __DIR__ . '../../models/ActivityLog.php';

class ActivityController {
    private $db;
    private $admin;
    private $logModel;

    public function __construct($db) {
        $this->db = $db;
        $this->logModel = new ActivityLog($db);
    }

    public function setAdmin($admin) {
        $this->admin = $admin;
    }

    public function index() {
        $category = $_GET['category'] ?? 'All';
        $search = $_GET['search'] ?? '';
        $logs = $this->logModel->getAll($category, $search);
        jsonResponse(['data' => $logs]);
    }
}