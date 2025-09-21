<?php
// index.php

// Allow CORS
// CORS headers - allow everything
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: *");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/AuditLog.php';
require_once __DIR__ . '/classes/ClientManager.php';
require_once __DIR__ . '/classes/CheckManager.php';
require_once __DIR__ . '/classes/AuditLog.php'; // For any composer packages

$config = require __DIR__ . '/config.php';

// Initialize classes
$dbInst = Database::getInstance($config);
$pdo = $dbInst->pdo();
$auth = new Auth($pdo, $config);
$audit = new AuditLog($pdo);
$clientMgr = new ClientManager($pdo, $audit);
$checkMgr = new CheckManager($pdo, $audit);

// ----------------- HELPERS -----------------
function json($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function getAuthPayload($auth)
{
    $h = getallheaders();
    $token = null;
    if (!empty($h['Authorization']) && preg_match('/Bearer\s(\S+)/', $h['Authorization'], $m)) {
        $token = $m[1];
    }
    if (!$token)
        return false;
    return $auth->verifyJWT($token);
}

// ----------------- ROUTE RESOLVER -----------------
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = $_SERVER['SCRIPT_NAME'];

$path = str_replace($scriptName, '', $requestUri);
$path = rtrim($path, '/');
if ($path === '')
    $path = '/';

// ----------------- LOAD ROUTES -----------------
require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/clients.php';
require __DIR__ . '/routes/checks.php';
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/auditlogs.php';
require __DIR__ . '/routes/notfound.php';