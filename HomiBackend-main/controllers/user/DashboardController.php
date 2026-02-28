<?php
require_once __DIR__ . '/../../models/user/Favorite.php';
require_once __DIR__ . '/../../models/user/SavedSearch.php';
require_once __DIR__ . '/../../models/user/ViewHistory.php';
require_once __DIR__ . '/../../models/user/Notification.php';
require_once __DIR__ . '/../../models/user/Inquiry.php';

class DashboardController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    public function index() {
        $favoriteModel = new Favorite($this->db);
        $savedSearchModel = new SavedSearch($this->db);
        $historyModel = new ViewHistory($this->db);
        $notificationModel = new Notification($this->db);
        $inquiryModel = new Inquiry($this->db);

        $favorites = $favoriteModel->getByUser($this->user['id']);
        $favoriteCount = count($favorites);
        $savedSearchCount = $savedSearchModel->countByUser($this->user['id']);
        $recentViewsCount = $historyModel->countByUser($this->user['id']);
        $unreadMessages = $notificationModel->getUnreadCount($this->user['id']); // or count from inquiries

        // For recent activity, we can combine from favorites, inquiries, etc.
        $recentActivity = [];
        // Example: get last 5 interactions

        // Get recent favorites (first 3)
        $recentFavorites = array_slice($favorites, 0, 3);

        // Get recent saved searches
        $recentSearches = $savedSearchModel->getByUser($this->user['id']);
        $recentSearches = array_slice($recentSearches, 0, 3);

        // Get recent activity (simulate from notifications)
        $notifications = $notificationModel->getByUser($this->user['id'], false, 4);

        jsonResponse([
            'stats' => [
                'favorites' => $favoriteCount,
                'savedSearches' => $savedSearchCount,
                'recentViews' => $recentViewsCount,
                'unreadMessages' => $unreadMessages
            ],
            'recentActivity' => $notifications,
            'favorites' => $recentFavorites,
            'savedSearches' => $recentSearches
        ]);
    }
}