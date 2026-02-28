<?php

require_once __DIR__ . '/middleware/AuthMiddleware.php';

return [

    // ─────────────────────────────────────────────────────────────
    //  ADMIN ROUTES (auth = true → admin)
    // ─────────────────────────────────────────────────────────────
    ['method' => 'POST', 'path' => 'admin/auth/login',    'handler' => 'AuthController@login',  'auth' => false],
    ['method' => 'POST', 'path' => 'admin/auth/logout',   'handler' => 'AuthController@logout', 'auth' => false],

    ['method' => 'GET',  'path' => 'admin/dashboard/stats',    'handler' => 'DashboardController@stats',    'auth' => true],
    ['method' => 'GET',  'path' => 'admin/dashboard/pending',  'handler' => 'DashboardController@pending',  'auth' => true],
    ['method' => 'GET',  'path' => 'admin/dashboard/activity', 'handler' => 'DashboardController@activity', 'auth' => true],
    ['method' => 'GET',  'path' => 'admin/dashboard/health',   'handler' => 'DashboardController@health',   'auth' => true],

    ['method' => 'GET',   'path' => 'admin/listings',                          'handler' => 'ListingController@index',          'auth' => true],
    ['method' => 'POST',  'path' => 'admin/listings/{id}/approve',              'handler' => 'ListingController@approve',        'auth' => true],
    ['method' => 'POST',  'path' => 'admin/listings/{id}/reject',               'handler' => 'ListingController@reject',         'auth' => true],
    ['method' => 'POST',  'path' => 'admin/listings/{id}/request-changes',      'handler' => 'ListingController@requestChanges', 'auth' => true],
    ['method' => 'PATCH', 'path' => 'admin/listings/{id}/notes',                'handler' => 'ListingController@saveNotes',      'auth' => true],

    ['method' => 'GET',   'path' => 'admin/agents',                             'handler' => 'AgentController@index',            'auth' => true],
    ['method' => 'POST',  'path' => 'admin/agents/{id}/verify',                 'handler' => 'AgentController@verify',           'auth' => true],
    ['method' => 'POST',  'path' => 'admin/agents/{id}/reject',                 'handler' => 'AgentController@reject',           'auth' => true],
    ['method' => 'POST',  'path' => 'admin/agents/{id}/suspend',                'handler' => 'AgentController@suspend',          'auth' => true],
    ['method' => 'POST',  'path' => 'admin/agents/{id}/reinstate',              'handler' => 'AgentController@reinstate',        'auth' => true],
    ['method' => 'PATCH', 'path' => 'admin/agents/{id}/documents/{docId}',      'handler' => 'AgentController@updateDocument',   'auth' => true],
    ['method' => 'PATCH', 'path' => 'admin/agents/{id}/notes',                  'handler' => 'AgentController@saveNotes',        'auth' => true],

    ['method' => 'GET',    'path' => 'admin/users',                             'handler' => 'UserController@index',             'auth' => true],
    ['method' => 'POST',   'path' => 'admin/users/{id}/block',                  'handler' => 'UserController@block',             'auth' => true],
    ['method' => 'POST',   'path' => 'admin/users/{id}/unblock',                'handler' => 'UserController@unblock',           'auth' => true],
    ['method' => 'DELETE', 'path' => 'admin/users/{id}',                        'handler' => 'UserController@delete',            'auth' => true],
    ['method' => 'POST',   'path' => 'admin/users/{id}/message',                'handler' => 'UserController@sendMessage',       'auth' => true],

    ['method' => 'GET',    'path' => 'admin/reports',                           'handler' => 'ReportController@index',           'auth' => true],
    ['method' => 'POST',   'path' => 'admin/reports/{id}/resolve',              'handler' => 'ReportController@resolve',         'auth' => true],
    ['method' => 'POST',   'path' => 'admin/reports/{id}/dismiss',              'handler' => 'ReportController@dismiss',         'auth' => true],
    ['method' => 'POST',   'path' => 'admin/reports/{id}/block-subject',        'handler' => 'ReportController@blockSubject',    'auth' => true],
    ['method' => 'DELETE', 'path' => 'admin/reports/{id}/delete-listing',       'handler' => 'ReportController@deleteListing',   'auth' => true],
    ['method' => 'PATCH',  'path' => 'admin/reports/{id}/notes',                'handler' => 'ReportController@saveNotes',       'auth' => true],

    ['method' => 'GET',  'path' => 'admin/activity-log',                        'handler' => 'ActivityController@index',         'auth' => true],
    ['method' => 'GET',  'path' => 'admin/activity-log/export',                 'handler' => 'ActivityController@export',        'auth' => true],

    ['method' => 'GET',  'path' => 'admin/settings',                            'handler' => 'SettingsController@index',         'auth' => true],
    ['method' => 'PUT',  'path' => 'admin/settings',                            'handler' => 'SettingsController@save',          'auth' => true],
    ['method' => 'POST', 'path' => 'admin/settings/danger/{action}',            'handler' => 'SettingsController@dangerAction',  'auth' => true],

    ['method' => 'GET',  'path' => 'admin/analytics/growth',                    'handler' => 'AnalyticsController@growth',       'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/cities',                    'handler' => 'AnalyticsController@cities',       'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/property-types',            'handler' => 'AnalyticsController@propertyTypes','auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/price-distribution',        'handler' => 'AnalyticsController@priceDistribution', 'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/moderation',                'handler' => 'AnalyticsController@moderation',   'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/funnel',                    'handler' => 'AnalyticsController@funnel',       'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/heatmap',                   'handler' => 'AnalyticsController@heatmap',      'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/top-agents',                'handler' => 'AnalyticsController@topAgents',    'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/export',                    'handler' => 'AnalyticsController@export',       'auth' => true],

    // ─────────────────────────────────────────────────────────────
    //  AGENT ROUTES (auth = user → agent users)
    // ─────────────────────────────────────────────────────────────
    ['method' => 'POST', 'path' => 'agent/auth/login',    'handler' => 'Agent\AuthController@login',  'auth' => false],
    ['method' => 'POST', 'path' => 'agent/auth/register', 'handler' => 'Agent\AuthController@register', 'auth' => false],

    ['method' => 'GET',  'path' => 'agent/dashboard',          'handler' => 'Agent\DashboardController@index', 'auth' => 'user'],

    ['method' => 'GET',    'path' => 'agent/listings',              'handler' => 'Agent\ListingController@index',    'auth' => 'user'],
    ['method' => 'POST',   'path' => 'agent/listings',              'handler' => 'Agent\ListingController@store',    'auth' => 'user'],
    ['method' => 'GET',    'path' => 'agent/listings/{id}',         'handler' => 'Agent\ListingController@show',     'auth' => 'user'],
    ['method' => 'PUT',    'path' => 'agent/listings/{id}',         'handler' => 'Agent\ListingController@update',   'auth' => 'user'],
    ['method' => 'DELETE', 'path' => 'agent/listings/{id}',         'handler' => 'Agent\ListingController@destroy',  'auth' => 'user'],
    ['method' => 'POST',   'path' => 'agent/listings/{id}/photos',  'handler' => 'Agent\ListingController@uploadPhotos', 'auth' => 'user'],
    ['method' => 'POST',   'path' => 'agent/listings/{id}/documents','handler' => 'Agent\ListingController@uploadDocuments', 'auth' => 'user'],

    ['method' => 'GET',    'path' => 'agent/leads',                 'handler' => 'Agent\LeadController@index',      'auth' => 'user'],
    ['method' => 'GET',    'path' => 'agent/leads/{id}/messages',   'handler' => 'Agent\LeadController@messages',   'auth' => 'user'],
    ['method' => 'POST',   'path' => 'agent/leads/{id}/reply',      'handler' => 'Agent\LeadController@reply',      'auth' => 'user'],

    ['method' => 'GET',    'path' => 'agent/notifications',         'handler' => 'Agent\NotificationController@index',    'auth' => 'user'],
    ['method' => 'PATCH',  'path' => 'agent/notifications/{id}/read','handler' => 'Agent\NotificationController@markRead', 'auth' => 'user'],
    ['method' => 'DELETE', 'path' => 'agent/notifications/{id}',    'handler' => 'Agent\NotificationController@destroy',  'auth' => 'user'],

    ['method' => 'GET',    'path' => 'agent/profile',               'handler' => 'Agent\ProfileController@show',    'auth' => 'user'],
    ['method' => 'PUT',    'path' => 'agent/profile',               'handler' => 'Agent\ProfileController@update',  'auth' => 'user'],

    ['method' => 'GET',    'path' => 'agent/settings',              'handler' => 'Agent\SettingsController@index',  'auth' => 'user'],
    ['method' => 'PUT',    'path' => 'agent/settings',              'handler' => 'Agent\SettingsController@update', 'auth' => 'user'],
    ['method' => 'POST',   'path' => 'agent/settings/password',     'handler' => 'Agent\SettingsController@changePassword', 'auth' => 'user'],

    // ─────────────────────────────────────────────────────────────
    //  USER ROUTES (auth = user → regular users)
    // ─────────────────────────────────────────────────────────────
    ['method' => 'POST', 'path' => 'user/auth/register', 'handler' => 'User\AuthController@register', 'auth' => false],
    ['method' => 'POST', 'path' => 'user/auth/login',    'handler' => 'User\AuthController@login',    'auth' => false],
    ['method' => 'POST', 'path' => 'user/auth/register/professional', 'handler' => 'User\AuthController@registerProfessional', 'auth' => false],

    ['method' => 'GET',  'path' => 'user/dashboard',     'handler' => 'User\DashboardController@index', 'auth' => 'user'],

    ['method' => 'GET',    'path' => 'user/favorites',              'handler' => 'User\FavoriteController@index',   'auth' => 'user'],
    ['method' => 'POST',   'path' => 'user/favorites/{listingId}',  'handler' => 'User\FavoriteController@add',     'auth' => 'user'],
    ['method' => 'DELETE', 'path' => 'user/favorites/{listingId}',  'handler' => 'User\FavoriteController@remove',  'auth' => 'user'],

    ['method' => 'GET',    'path' => 'user/saved-searches',          'handler' => 'User\SavedSearchController@index',    'auth' => 'user'],
    ['method' => 'POST',   'path' => 'user/saved-searches',          'handler' => 'User\SavedSearchController@store',    'auth' => 'user'],
    ['method' => 'DELETE', 'path' => 'user/saved-searches/{id}',     'handler' => 'User\SavedSearchController@destroy', 'auth' => 'user'],

    ['method' => 'GET',    'path' => 'user/history/browse',          'handler' => 'User\HistoryController@browse',   'auth' => 'user'],
    ['method' => 'POST',   'path' => 'user/history/view/{listingId}','handler' => 'User\HistoryController@recordView','auth' => 'user'],

    ['method' => 'GET',    'path' => 'user/notifications',                 'handler' => 'User\NotificationController@index',      'auth' => 'user'],
    ['method' => 'PATCH',  'path' => 'user/notifications/{id}/read',       'handler' => 'User\NotificationController@markRead',   'auth' => 'user'],
    ['method' => 'POST',   'path' => 'user/notifications/read-all',        'handler' => 'User\NotificationController@markAllRead','auth' => 'user'],
    ['method' => 'DELETE', 'path' => 'user/notifications/{id}',            'handler' => 'User\NotificationController@destroy',    'auth' => 'user'],
    ['method' => 'DELETE', 'path' => 'user/notifications',                 'handler' => 'User\NotificationController@destroyAll', 'auth' => 'user'],

    ['method' => 'GET',    'path' => 'user/settings/profile',        'handler' => 'User\SettingsController@getProfile',        'auth' => 'user'],
    ['method' => 'PUT',    'path' => 'user/settings/profile',        'handler' => 'User\SettingsController@updateProfile',     'auth' => 'user'],
    ['method' => 'POST',   'path' => 'user/settings/password',       'handler' => 'User\SettingsController@changePassword',    'auth' => 'user'],
    ['method' => 'GET',    'path' => 'user/settings/notifications',  'handler' => 'User\SettingsController@getNotifications',  'auth' => 'user'],
    ['method' => 'PUT',    'path' => 'user/settings/notifications',  'handler' => 'User\SettingsController@updateNotifications','auth' => 'user'],

    ['method' => 'GET',    'path' => 'user/listings',                 'handler' => 'User\ListingController@index',    'auth' => 'user'],
    ['method' => 'POST',   'path' => 'user/listings',                 'handler' => 'User\ListingController@store',    'auth' => 'user'],
    ['method' => 'GET',    'path' => 'user/listings/{id}',            'handler' => 'User\ListingController@show',     'auth' => 'user'],
    ['method' => 'PUT',    'path' => 'user/listings/{id}',            'handler' => 'User\ListingController@update',   'auth' => 'user'],
    ['method' => 'DELETE', 'path' => 'user/listings/{id}',            'handler' => 'User\ListingController@destroy',  'auth' => 'user'],
    ['method' => 'POST',   'path' => 'user/listings/{id}/photos',     'handler' => 'User\ListingController@uploadPhotos', 'auth' => 'user'],
    ['method' => 'POST',   'path' => 'user/listings/{id}/documents',  'handler' => 'User\ListingController@uploadDocuments', 'auth' => 'user'],

    ['method' => 'POST',   'path' => 'user/professional/apply',      'handler' => 'User\ProfessionalController@submit',        'auth' => 'user'],
    ['method' => 'POST',   'path' => 'user/professional/{id}/upload','handler' => 'User\ProfessionalController@uploadDocument','auth' => 'user'],
    ['method' => 'GET',    'path' => 'user/professional/status',     'handler' => 'User\ProfessionalController@index',         'auth' => 'user'],

    // ─────────────────────────────────────────────────────────────
    //  PUBLIC ROUTES (auth = false → no authentication)
    // ─────────────────────────────────────────────────────────────
    ['method' => 'GET',  'path' => 'public/properties',        'handler' => 'Public\PropertyController@index', 'auth' => false],
    ['method' => 'GET',  'path' => 'public/properties/{id}',   'handler' => 'Public\PropertyController@show',   'auth' => false],
    ['method' => 'GET',  'path' => 'public/agents',            'handler' => 'Public\AgentController@index',    'auth' => false],
    ['method' => 'GET',  'path' => 'public/agents/{id}',       'handler' => 'Public\AgentController@show',     'auth' => false],
    ['method' => 'POST', 'path' => 'public/inquiries',         'handler' => 'Public\InquiryController@send',   'auth' => false],
    ['method' => 'GET',  'path' => 'public/cities',            'handler' => 'Public\CityController@index',     'auth' => false],
    ['method' => 'GET',  'path' => 'public/stats',             'handler' => 'Public\StatsController@index',    'auth' => false],
];