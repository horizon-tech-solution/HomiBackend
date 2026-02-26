<?php
require_once __DIR__ . '/../../models/admin/ActivityLog.php';

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
        $search   = $_GET['search']   ?? '';
        $logs     = $this->logModel->getAll($category, $search);
        jsonResponse(['data' => $logs]);
    }

    public function export() {
        $category = $_GET['category'] ?? 'All';
        $search   = $_GET['search']   ?? '';
        $logs     = $this->logModel->getAll($category, $search);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="activity-log-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Action', 'Actor', 'Target', 'Detail', 'Category', 'Time']);
        foreach ($logs as $log) {
            fputcsv($out, [
                $log['id'],
                $log['action'],
                $log['actor'],
                $log['target'],
                $log['detail'],
                $log['category'],
                $log['time'],
            ]);
        }
        fclose($out);
        exit;
    }
}