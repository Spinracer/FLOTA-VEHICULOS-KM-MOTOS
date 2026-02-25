<?php
// ─────────────────────────────────────────────────────────
// FlotaControl — Cargador de variables de entorno (.env)
// ─────────────────────────────────────────────────────────

function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim(trim($value), '"\'');
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// Cargar .env desde la raíz del proyecto
loadEnv(dirname(__DIR__) . '/.env');

// ─────────────────────────────────────────────────────────
// Constantes de conexión (desde .env o fallback)
// ─────────────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'flotacontrol');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

define('APP_NAME',  getenv('APP_NAME')  ?: 'FlotaControl');
define('APP_DEBUG', getenv('APP_DEBUG') === 'true');

/**
 * Retorna una instancia PDO singleton conectada a MySQL.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn  = "mysql:host=" . DB_HOST . ";port=" . DB_PORT
              . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
        } catch (PDOException $e) {
            http_response_code(500);
            $msg = APP_DEBUG ? $e->getMessage() : 'Error de conexión a la base de datos.';
            die(json_encode(['error' => $msg]));
        }
    }
    return $pdo;
}
