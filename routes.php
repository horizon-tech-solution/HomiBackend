<?php

require_once __DIR__ . '/middleware/AuthMiddleware.php';

return [

    // ── Auth ──────────────────────────────────────────────────────────────────
    ['method' => 'POST', 'path' => 'admin/auth/login',    'handler' => 'AuthController@login',  'auth' => false],
    ['method' => 'POST', 'path' => 'admin/auth/logout',   'handler' => 'AuthController@logout', 'auth' => false],

    // ── Dashboard ─────────────────────────────────────────────────────────────
    ['method' => 'GET',  'path' => 'admin/dashboard/stats',    'handler' => 'DashboardController@stats',    'auth' => true],
    ['method' => 'GET',  'path' => 'admin/dashboard/pending',  'handler' => 'DashboardController@pending',  'auth' => true],
    ['method' => 'GET',  'path' => 'admin/dashboard/activity', 'handler' => 'DashboardController@activity', 'auth' => true],
    ['method' => 'GET',  'path' => 'admin/dashboard/health',   'handler' => 'DashboardController@health',   'auth' => true],

    // ── Listings ──────────────────────────────────────────────────────────────
    ['method' => 'GET',   'path' => 'admin/listings',                          'handler' => 'ListingController@index',          'auth' => true],
    ['method' => 'POST',  'path' => 'admin/listings/{id}/approve',              'handler' => 'ListingController@approve',        'auth' => true],
    ['method' => 'POST',  'path' => 'admin/listings/{id}/reject',               'handler' => 'ListingController@reject',         'auth' => true],
    ['method' => 'POST',  'path' => 'admin/listings/{id}/request-changes',      'handler' => 'ListingController@requestChanges', 'auth' => true],
    ['method' => 'PATCH', 'path' => 'admin/listings/{id}/notes',                'handler' => 'ListingController@saveNotes',      'auth' => true],

    // ── Agents ────────────────────────────────────────────────────────────────
    ['method' => 'GET',   'path' => 'admin/agents',                             'handler' => 'AgentController@index',            'auth' => true],
    ['method' => 'POST',  'path' => 'admin/agents/{id}/verify',                 'handler' => 'AgentController@verify',           'auth' => true],
    ['method' => 'POST',  'path' => 'admin/agents/{id}/reject',                 'handler' => 'AgentController@reject',           'auth' => true],
    ['method' => 'POST',  'path' => 'admin/agents/{id}/suspend',                'handler' => 'AgentController@suspend',          'auth' => true],
    ['method' => 'POST',  'path' => 'admin/agents/{id}/reinstate',              'handler' => 'AgentController@reinstate',        'auth' => true],
    ['method' => 'PATCH', 'path' => 'admin/agents/{id}/documents/{docId}',      'handler' => 'AgentController@updateDocument',   'auth' => true],
    ['method' => 'PATCH', 'path' => 'admin/agents/{id}/notes',                  'handler' => 'AgentController@saveNotes',        'auth' => true],

    // ── Users ─────────────────────────────────────────────────────────────────
    ['method' => 'GET',    'path' => 'admin/users',                             'handler' => 'UserController@index',             'auth' => true],
    ['method' => 'POST',   'path' => 'admin/users/{id}/block',                  'handler' => 'UserController@block',             'auth' => true],
    ['method' => 'POST',   'path' => 'admin/users/{id}/unblock',                'handler' => 'UserController@unblock',           'auth' => true],
    ['method' => 'DELETE', 'path' => 'admin/users/{id}',                        'handler' => 'UserController@delete',            'auth' => true],
    ['method' => 'POST',   'path' => 'admin/users/{id}/message',                'handler' => 'UserController@sendMessage',       'auth' => true],

    // ── Reports ───────────────────────────────────────────────────────────────
    ['method' => 'GET',    'path' => 'admin/reports',                           'handler' => 'ReportController@index',           'auth' => true],
    ['method' => 'POST',   'path' => 'admin/reports/{id}/resolve',              'handler' => 'ReportController@resolve',         'auth' => true],
    ['method' => 'POST',   'path' => 'admin/reports/{id}/dismiss',              'handler' => 'ReportController@dismiss',         'auth' => true],
    ['method' => 'POST',   'path' => 'admin/reports/{id}/block-subject',        'handler' => 'ReportController@blockSubject',    'auth' => true],
    ['method' => 'DELETE', 'path' => 'admin/reports/{id}/delete-listing',       'handler' => 'ReportController@deleteListing',   'auth' => true],
    ['method' => 'PATCH',  'path' => 'admin/reports/{id}/notes',                'handler' => 'ReportController@saveNotes',       'auth' => true],

    // ── Activity Log ──────────────────────────────────────────────────────────
    ['method' => 'GET',  'path' => 'admin/activity-log',                        'handler' => 'ActivityController@index',         'auth' => true],
    ['method' => 'GET',  'path' => 'admin/activity-log/export',                 'handler' => 'ActivityController@export',        'auth' => true],

    // ── Settings ──────────────────────────────────────────────────────────────
    ['method' => 'GET',  'path' => 'admin/settings',                            'handler' => 'SettingsController@index',         'auth' => true],
    ['method' => 'PUT',  'path' => 'admin/settings',                            'handler' => 'SettingsController@save',          'auth' => true],
    ['method' => 'POST', 'path' => 'admin/settings/danger/{action}',            'handler' => 'SettingsController@dangerAction',  'auth' => true],

    // ── Analytics ─────────────────────────────────────────────────────────────
    ['method' => 'GET',  'path' => 'admin/analytics/growth',                    'handler' => 'AnalyticsController@growth',       'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/cities',                    'handler' => 'AnalyticsController@cities',       'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/property-types',            'handler' => 'AnalyticsController@propertyTypes','auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/price-distribution',        'handler' => 'AnalyticsController@priceDistribution', 'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/moderation',                'handler' => 'AnalyticsController@moderation',   'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/funnel',                    'handler' => 'AnalyticsController@funnel',       'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/heatmap',                   'handler' => 'AnalyticsController@heatmap',      'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/top-agents',                'handler' => 'AnalyticsController@topAgents',    'auth' => true],
    ['method' => 'GET',  'path' => 'admin/analytics/export',                    'handler' => 'AnalyticsController@export',       'auth' => true],

];