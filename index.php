<?php
echo json_encode(scandir(__DIR__ . '/controllers/user'));
exit;

define('BASE_PATH', __DIR__);

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: https://homi-three.vercel.app');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Fix: PHP built-in server strips Authorization on multipart requests ────────
// Patch $_SERVER early so every middleware can rely on HTTP_AUTHORIZATION.
if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $allHeaders = getallheaders();
    $authValue  = $allHeaders['Authorization'] ?? $allHeaders['authorization'] ?? '';
    if ($authValue !== '') {
        $_SERVER['HTTP_AUTHORIZATION'] = $authValue;
    }
}

// ── Static file serving (PHP built-in server doesn't serve files automatically) ──
// Serves /uploads/* and /public/* directly from the filesystem.
$staticUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/uploads/(.+)$#', $staticUri, $m) ||
    preg_match('#^/public/uploads/(.+)$#', $staticUri, $m)) {
    // Try /public/uploads/ first, then /uploads/ at project root
    $candidates = [
        __DIR__ . '/public/uploads/' . $m[1],
        __DIR__ . '/uploads/'        . $m[1],
    ];
    $filePath = null;
    foreach ($candidates as $c) {
        if (file_exists($c) && is_file($c)) { $filePath = $c; break; }
    }
    if ($filePath) {
        $ext  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            'pdf'         => 'application/pdf',
            default       => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found']);
    exit;
}

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (str_contains($value, ' #')) {
            $value = trim(explode(' #', $value, 2)[0]);
        }
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        if (!isset($_ENV[$key])) {
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

$requestMethod = $_SERVER['REQUEST_METHOD'];

require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/user_auth.php';
require_once __DIR__ . '/config/utils.php';
require_once __DIR__ . '/config/cloudinary.php'; 

require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/middleware/UserAuthMiddleware.php';
require_once __DIR__ . '/middleware/AgentAuthMiddleware.php';

$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptDir === '/' || $scriptDir === '\\') {
    $scriptDir = '';
}

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$apiPrefix = $scriptDir . '/api';
if (str_starts_with($requestUri, $apiPrefix)) {
    $uri = substr($requestUri, strlen($apiPrefix));
} elseif (str_starts_with($requestUri, $scriptDir)) {
    $uri = substr($requestUri, strlen($scriptDir));
} else {
    $uri = $requestUri;
}

$uri      = trim($uri, '/');
$uriParts = $uri === '' ? [] : explode('/', $uri);

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
    echo json_encode(['error' => 'Not found']);
    exit;
}

$handler = $matchedRoute['handler'];

if (!preg_match('/^[A-Za-z\\\\]+@[A-Za-z]+$/', $handler)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid handler']);
    exit;
}

[$controllerName, $method] = explode('@', $handler);

if (str_contains($controllerName, '\\')) {
    $parts           = explode('\\', $controllerName);
    $controllerClass = array_pop($parts);
    $namespacePath   = implode('/', array_map('strtolower', $parts));
    $controllerFile  = __DIR__ . '/controllers/' . $namespacePath . '/' . $controllerClass . '.php';
} else {
    $controllerFile  = __DIR__ . '/controllers/admin/' . $controllerName . '.php';
    $controllerClass = $controllerName;
}

$realBase = realpath(__DIR__ . '/controllers');
$realFile = realpath($controllerFile);
if (!$realFile || !str_starts_with($realFile, $realBase)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden', 'tried' => $controllerFile]);
    exit;
}

require_once $controllerFile;

if (!class_exists($controllerClass)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Class not found: ' . $controllerClass]);
    exit;
}

$db         = (new Database())->getConnection();
$controller = new $controllerClass($db);

$authUser = null;
$authType = null;

if (!empty($matchedRoute['auth'])) {
    $authRole = $matchedRoute['auth'];

    if ($authRole === 'admin') {
        $authMiddleware = new AuthMiddleware();
        $authUser       = $authMiddleware->authenticate();
        $authType       = 'admin';
    } elseif ($authRole === 'user') {
        $authMiddleware = new UserAuthMiddleware();
        $authUser       = $authMiddleware->authenticate();
        $authType       = 'user';
    } else {
        $authMiddleware = new AuthMiddleware();
        $authUser       = $authMiddleware->authenticate();
        $authType       = 'admin';
    }

    if (!$authUser) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if ($authType === 'admin' && method_exists($controller, 'setAdmin')) {
        $controller->setAdmin($authUser);
    } elseif ($authType === 'user' && method_exists($controller, 'setUser')) {
        $controller->setUser($authUser);
    }
}

try {
    header('Content-Type: application/json');
    $response = $controller->$method($params);
    if ($response !== null) {
        echo json_encode($response);
    }
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
}