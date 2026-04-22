<?php
/**
 * Router principal de la API
 * Carga las dependencias y enruta las peticiones
 */

// Configuración base
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Cargar configuración
require_once dirname(__DIR__) . '/config/env.php';

// Autoload de clases
spl_autoload_register(function (string $class) {
    $paths = [
        dirname(__DIR__) . '/lib/' . $class . '.php',
        dirname(__DIR__) . '/analyzers/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Inicializar base de datos si no existe
try {
    $db = Database::getInstance();
    $db->initSchema();
} catch (Throwable $e) {
    Logger::error('Error inicializando DB: ' . $e->getMessage());
}

// Headers CORS
Response::cors();

// Determinar el endpoint solicitado
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$basePath = '/api/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Remover el prefijo base
if (str_contains($path, $basePath)) {
    $endpoint = substr($path, strpos($path, $basePath) + strlen($basePath));
} else {
    $endpoint = basename($path, '.php');
}

$endpoint = rtrim($endpoint, '/');

// Enrutamiento
switch ($endpoint) {
    case 'audit':
        require __DIR__ . '/audit.php';
        break;

    case 'audit-status':
        require __DIR__ . '/audit-status.php';
        break;

    case 'scan-progress':
        require __DIR__ . '/scan-progress.php';
        break;

    case 'config':
        require __DIR__ . '/config.php';
        break;

    case 'health':
        require __DIR__ . '/health.php';
        break;

    case 'diag':
        require __DIR__ . '/diag.php';
        break;

    case 'setup':
        require __DIR__ . '/setup.php';
        break;

    case 'admin/login':
        require __DIR__ . '/admin/login.php';
        break;

    case 'admin/logout':
        require __DIR__ . '/admin/logout.php';
        break;

    case 'admin/session':
        require __DIR__ . '/admin/session.php';
        break;

    case 'admin/dashboard':
        require __DIR__ . '/admin/dashboard.php';
        break;

    case 'admin/leads':
        require __DIR__ . '/admin/leads.php';
        break;

    case 'admin/lead-detail':
        require __DIR__ . '/admin/lead-detail.php';
        break;

    case 'admin/settings':
        require __DIR__ . '/admin/settings.php';
        break;

    case 'admin/vulnerabilities':
        require __DIR__ . '/admin/vulnerabilities.php';
        break;

    case 'admin/checklist':
        require __DIR__ . '/admin/checklist.php';
        break;

    case 'admin/waterfall':
        require __DIR__ . '/admin/waterfall.php';
        break;

    case 'admin/snapshot':
        require __DIR__ . '/admin/snapshot.php';
        break;

    case 'admin/queue-status':
        require __DIR__ . '/admin/queue-status.php';
        break;

    case 'admin/pin-audit':
        require __DIR__ . '/admin/pin-audit.php';
        break;

    case 'admin/retention-preview':
        require __DIR__ . '/admin/retention-preview.php';
        break;

    case 'admin/users':
        require __DIR__ . '/admin/users.php';
        break;

    case 'admin/plans':
        require __DIR__ . '/admin/plans.php';
        break;

    case 'admin/projects':
        require __DIR__ . '/admin/projects.php';
        break;

    case 'user/login':
        require __DIR__ . '/user/login.php';
        break;

    case 'user/logout':
        require __DIR__ . '/user/logout.php';
        break;

    case 'user/session':
        require __DIR__ . '/user/session.php';
        break;

    case 'user/audits':
        require __DIR__ . '/user/audits.php';
        break;

    case 'user/projects':
        require __DIR__ . '/user/projects.php';
        break;

    default:
        Response::error(Translator::t('api.common.endpoint_not_found'), 404);
}
