<?php
require_once __DIR__ . '/../../models/admin/Report.php';
require_once __DIR__ . '/../../models/admin/ActivityLog.php';

class ReportController {
    private $db;
    private $admin;
    private $reportModel;
    private $logModel;

    public function __construct($db) {
        $this->db = $db;
        $this->reportModel = new Report($db);
        $this->logModel = new ActivityLog($db);
    }

    public function setAdmin($admin) {
        $this->admin = $admin;
    }

    public function index() {
        $status = $_GET['status'] ?? 'all';
        $search = $_GET['search'] ?? null;
        $reports = $this->reportModel->getAll($status, $search);
        jsonResponse(['data' => $reports]);
    }

    public function show($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $report = $this->reportModel->getById($id);
        if (!$report) jsonResponse(['error' => 'Report not found'], 404);
        jsonResponse($report);
    }

    public function resolve($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $input = getJsonInput();
        $resolution = $input['resolution'] ?? '';

        if ($this->reportModel->resolve($id, $resolution)) {
            $this->logModel->create('report_resolved', $this->admin['name'], "Report #$id", $resolution, 'report');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Resolution failed'], 500);
        }
    }

    public function dismiss($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $input = getJsonInput();
        $note = $input['note'] ?? '';

        if ($this->reportModel->dismiss($id, $note)) {
            $this->logModel->create('report_dismissed', $this->admin['name'], "Report #$id", $note, 'report');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Dismissal failed'], 500);
        }
    }
}