<?php
/**
 * CyberCRM — Front Controller
 * public/ = document root, includes/ si templates/ sunt un nivel mai sus
 */
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/router.php';
require_once __DIR__ . '/../includes/helpers.php';

Security::headers();
Auth::init();

$route = Router::resolve();
$page   = $route['page'];
$action = $route['action'];
$id     = $route['id'];

// Pune variabilele din router in $_GET pentru template-uri
$_GET['action'] = $action;
$_GET['id'] = $id;

$publicPages = ['login'];
$isPublic = in_array($page, $publicPages);

if (!$isPublic) Auth::requireAuth();

if ($page === 'api') {
    $endpoint = $action;
    $apiFile = ROOT_PATH . '/includes/api/' . basename($endpoint) . '.php';
    if (file_exists($apiFile)) { require $apiFile; } else { jsonResponse(['error' => 'Not found'], 404); }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isPublic) Security::requireCSRF();

if (isset($_POST['_switch_profile'])) {
    Auth::setProfile((int)$_POST['_switch_profile']);
    Router::redirect($page);
}

$validPages = Router::getValidPages();
if (!in_array($page, $validPages)) $page = 'dashboard';

$pageFile = ROOT_PATH . '/templates/' . str_replace('-', '_', $page) . '.php';
if (!file_exists($pageFile)) { $page = 'dashboard'; $pageFile = ROOT_PATH . '/templates/dashboard.php'; }

if ($page === 'login') { require $pageFile; exit; }

$activeProfile = Auth::getProfile();
$allProfiles   = Auth::getAllProfiles();
$currentPage   = $page;
$pageTitle     = ucfirst(str_replace('-', ' ', $page));

require ROOT_PATH . '/templates/layout.php';
ob_end_flush();
