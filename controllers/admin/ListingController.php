<?php
require_once __DIR__ . '../../models/Listing.php';
require_once __DIR__ . '../../models/ActivityLog.php';

class ListingController {
    private $db;
    private $admin;
    private $listingModel;
    private $logModel;

    public function __construct($db) {
        $this->db = $db;
        $this->listingModel = new Listing($db);
        $this->logModel = new ActivityLog($db);
    }

    public function setAdmin($admin) {
        $this->admin = $admin;
    }

    public function index() {
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        $listings = $this->listingModel->getAll($status, $search);
        jsonResponse(['data' => $listings]);
    }

    public function show($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $listing = $this->listingModel->getById($id);
        if (!$listing) jsonResponse(['error' => 'Listing not found'], 404);
        jsonResponse($listing);
    }

    public function approve($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $input = getJsonInput();
        $adminNotes = $input['admin_notes'] ?? '';

        if ($this->listingModel->approve($id, $adminNotes)) {
            $this->logModel->create('listing_approved', $this->admin['name'], "Listing #$id", $adminNotes, 'listing');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Approval failed'], 500);
        }
    }

    public function reject($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $input = getJsonInput();
        $reason = $input['reason'] ?? '';

        if ($this->listingModel->reject($id, $reason)) {
            $this->logModel->create('listing_rejected', $this->admin['name'], "Listing #$id", $reason, 'listing');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Rejection failed'], 500);
        }
    }

    public function requestChanges($params) {
        $id = $params['id'] ?? null;
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $input = getJsonInput();
        $message = $input['message'] ?? '';

        if ($this->listingModel->requestChanges($id, $message)) {
            $this->logModel->create('changes_requested', $this->admin['name'], "Listing #$id", $message, 'listing');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Request failed'], 500);
        }
    }
}