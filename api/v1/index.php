<?php
/**
 * API v1 Router — FlotaControl
 * 
 * Enruta peticiones `/api/v1/{recurso}` a los módulos API existentes.
 * Mantiene compatibilidad total con `/api/{recurso}.php`.
 * 
 * Uso:
 *   GET  /api/v1/vehiculos          → /modules/api/vehiculos.php
 *   POST /api/v1/combustible        → /modules/api/combustible.php
 *   GET  /api/v1/openapi.json       → Spec OpenAPI 3.0
 *   GET  /api/v1/docs               → Swagger UI interactivo
 * 
 * Nota: PHP built-in server no soporta .htaccess, así que se usa
 *       query param _resource como fallback: /api/v1/index.php?_resource=vehiculos
 */

// Determinar recurso solicitado
$resource = '';

// 1) Intentar desde PATH_INFO o REQUEST_URI
$uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($uri, PHP_URL_PATH);

if (preg_match('#/api/v1/([a-z_]+)#', $path, $m) && $m[1] !== 'index') {
    $resource = $m[1];
}

// 2) Fallback a query param
if (!$resource && isset($_GET['_resource'])) {
    $resource = preg_replace('/[^a-z_]/', '', $_GET['_resource']);
}

// Header de versión API
header('X-API-Version: 1.0');
header('X-Powered-By: FlotaControl/2.8');

// Mapa de recursos válidos
$routes = [
    'vehiculos'      => '/../../modules/api/vehiculos.php',
    'asignaciones'   => '/../../modules/api/asignaciones.php',
    'combustible'    => '/../../modules/api/combustible.php',
    'mantenimientos' => '/../../modules/api/mantenimientos.php',
    'operadores'     => '/../../modules/api/operadores.php',
    'incidentes'     => '/../../modules/api/incidentes.php',
    'componentes'    => '/../../modules/api/componentes.php',
    'reportes'       => '/../../modules/api/reportes.php',
    'catalogos'      => '/../../modules/api/catalogos.php',
    'usuarios'       => '/../../modules/api/usuarios.php',
    'proveedores'    => '/../../modules/api/proveedores.php',
    'recordatorios'  => '/../../modules/api/recordatorios.php',
    'preventivos'    => '/../../modules/api/preventivos.php',
    'permisos'       => '/../../modules/api/permisos.php',
    'auditoria'      => '/../../modules/api/auditoria.php',
    'attachments'    => '/../../modules/api/attachments.php',
];

// ── Rutas especiales ──

// OpenAPI Spec
if ($resource === 'openapi' || $resource === 'openapi_json' || str_ends_with($path, 'openapi.json')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    readfile(__DIR__ . '/openapi.json');
    exit;
}

// Swagger UI
if ($resource === 'docs' || str_ends_with($path, '/docs') || str_ends_with($path, '/docs/')) {
    require __DIR__ . '/docs.php';
    exit;
}

// Health check
if ($resource === 'health') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'version' => '1.0',
        'app' => 'FlotaControl',
        'timestamp' => date('c'),
        'endpoints' => count($routes),
    ]);
    exit;
}

// ── Enrutamiento principal ──
if ($resource === '' || $resource === 'index') {
    header('Content-Type: application/json');
    echo json_encode([
        'api' => 'FlotaControl API',
        'version' => '1.0',
        'docs' => '/api/v1/docs',
        'openapi' => '/api/v1/openapi.json',
        'health' => '/api/v1/health',
        'endpoints' => array_map(fn($r) => "/api/v1/{$r}", array_keys($routes)),
    ]);
    exit;
}

if (!isset($routes[$resource])) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => "Recurso '{$resource}' no encontrado",
        'available' => array_keys($routes),
        'docs' => '/api/v1/docs',
    ]);
    exit;
}

// Cargar módulo
require __DIR__ . $routes[$resource];
