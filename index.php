<?php

// ── CORS ──────────────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/utils.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// ── Router ────────────────────────────────────────────────────────────────────
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip /api prefix
$basePath = '/api';
if (str_starts_with($requestUri, $basePath)) {
    $uri = substr($requestUri, strlen($basePath));
} else {
    $uri = $requestUri;
}
$uri      = trim($uri, '/');
$uriParts = $uri === '' ? [] : explode('/', $uri);

// Load routes
$routes = require __DIR__ . '/routes.php';

$matchedRoute = null;
$params       = [];

foreach ($routes as $route) {
    if ($route['method'] !== $requestMethod) continue;

    $routePath  = trim($route['path'], '/');
    $routeParts = explode('/', $routePath);

    if (count($routeParts) !== count($uriParts)) continue;

    $match      = true;
    $tempParams = [];

    for ($i = 0; $i < count($routeParts); $i++) {
        if (str_starts_with($routeParts[$i], '{') && str_ends_with($routeParts[$i], '}')) {
            $paramName              = trim($routeParts[$i], '{}');
            $tempParams[$paramName] = $uriParts[$i];
        } elseif ($routeParts[$i] !== $uriParts[$i]) {
            $match = false;
            break;
        }
    }

    if ($match) {
        $matchedRoute = $route;
        $params       = $tempParams;
        break;
    }
}

if (!$matchedRoute) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'error'      => 'Route not found',
        'method'     => $requestMethod,
        'uri'        => $uri,
        'uri_parts'  => $uriParts,
        'raw'        => $requestUri,
    ]);
    exit;
}

// ── Auth middleware ───────────────────────────────────────────────────────────
$admin = null;
if ($matchedRoute['auth'] ?? false) {
    $authMiddleware = new AuthMiddleware();
    $admin          = $authMiddleware->authenticate();
    if (!$admin) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// ── Load & call controller ────────────────────────────────────────────────────
$handler = $matchedRoute['handler'];
[$controllerName, $method] = explode('@', $handler);

$controllerFile = __DIR__ . '/controllers/' . $controllerName . '.php';
if (!file_exists($controllerFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Controller file not found: ' . $controllerName]);
    exit;
}

require_once $controllerFile;

if (!class_exists($controllerName)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Controller class not found: ' . $controllerName]);
    exit;
}

$db         = (new Database())->getConnection();
$controller = new $controllerName($db);

if ($admin !== null && method_exists($controller, 'setAdmin')) {
    $controller->setAdmin($admin);
}

if (!method_exists($controller, $method)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not found: ' . $method]);
    exit;
}

// ── Execute ───────────────────────────────────────────────────────────────────
try {
    header('Content-Type: application/json');
    $response = $controller->$method($params);
    if ($response !== null) {
        echo json_encode($response);
    }
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}