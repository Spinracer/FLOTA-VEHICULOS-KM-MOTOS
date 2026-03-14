<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/totp.php';

// ─────────────────────────────────────────────────────────
// ROLES DEL SISTEMA
// ─────────────────────────────────────────────────────────
// coordinador_it  → Administrador total: usuarios, permisos y todo el sistema
// soporte         → Soporte/Coordinador: puede ver, crear y editar registros
// monitoreo       → Solo visualización: lectura de datos sin modificar
// ─────────────────────────────────────────────────────────

const ROLES = [
    'coordinador_it' => 'Coordinador IT',
    'soporte'        => 'Soporte',
    'monitoreo'      => 'Monitoreo',
    'visitante'      => 'Visitante',
];

// Mapa de permisos por rol
const ROLE_PERMISSIONS = [
    'coordinador_it' => ['view', 'create', 'edit', 'delete', 'manage_users', 'manage_permissions'],
    'soporte'        => ['view', 'create', 'edit'],
    'monitoreo'      => ['view'],
    'visitante'      => ['view'],
    // Compatibilidad con roles anteriores
    'admin'          => ['view', 'create', 'edit', 'delete', 'manage_users', 'manage_permissions'],
    'taller'         => ['view', 'create', 'edit'],
    'operador'       => ['view', 'create', 'edit'],
    'lectura'        => ['view'],
];

// Etiquetas visuales por rol
const ROLE_BADGES = [
    'coordinador_it' => 'badge-yellow',
    'soporte'        => 'badge-blue',
    'monitoreo'      => 'badge-cyan',
    'visitante'      => 'badge-gray',
    'admin'          => 'badge-yellow',
    'taller'         => 'badge-orange',
    'operador'       => 'badge-blue',
    'lectura'        => 'badge-gray',
];

// ─────────────────────────────────────────────────────────

function session_init(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $name     = getenv('SESSION_NAME') ?: 'FLOTACONTROL';
        $lifetime = (int)(getenv('SESSION_LIFETIME') ?: 7200);
        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => false,   // true en HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function is_logged_in(): bool {
    session_init();
    if (!isset($_SESSION['user_id'])) return false;
    // If 2FA is pending, user is not fully authenticated
    if (!empty($_SESSION['2fa_pending'])) return false;
    return true;
}

function require_login(): void {
    session_init();
    if (!is_logged_in()) {
        // API requests get JSON response
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No autenticado.']);
            exit;
        }
        header('Location: /index.php');
        exit;
    }

    // Enforce CSRF on write operations for API requests
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        csrf_enforce();
        // Rate limiting: write vs read
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? 'api_write' : 'api_read';
        $identifier = (string)($_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        rate_limit_enforce($action, $identifier);
    }
}

/**
 * Restringe acceso a los roles indicados.
 * Roles válidos: coordinador_it, soporte, monitoreo
 */
function require_role(string ...$roles): void {
    require_login();
    if (!in_array($_SESSION['user_rol'] ?? '', $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/403.php';
        exit;
    }
}

/**
 * Restringe acceso solo a Coordinador IT (administrador total).
 */
function require_admin(): void {
    require_role('coordinador_it', 'admin');
}

function current_user(): array {
    session_init();
    return [
        'id'     => $_SESSION['user_id']     ?? null,
        'nombre' => $_SESSION['user_nombre'] ?? '',
        'email'  => $_SESSION['user_email']  ?? '',
        'rol'    => $_SESSION['user_rol']    ?? '',
    ];
}

/**
 * Verifica si el usuario actual tiene el permiso dado.
 */
function can(string $action): bool {
    $rol = $_SESSION['user_rol'] ?? '';
    return in_array($action, ROLE_PERMISSIONS[$rol] ?? [], true);
}

/**
 * Verifica permiso granular por módulo.
 * Primero consulta user_module_permissions (por usuario);
 * si no existe, cae a role_module_permissions; después al can() global.
 */
function can_module(string $modulo, string $permiso): bool {
    $rol = $_SESSION['user_rol'] ?? '';
    $uid = (int)($_SESSION['user_id'] ?? 0);
    // coordinador_it y admin siempre tienen acceso total
    if (in_array($rol, ['coordinador_it', 'admin'])) return true;
    try {
        $db = getDB();
        // 1) Permisos personalizados por usuario
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM user_module_permissions WHERE user_id = ? AND modulo = ? AND permiso = ?"
        );
        $stmt->execute([$uid, $modulo, $permiso]);
        if ((int)$stmt->fetchColumn() > 0) return true;
        // Si el usuario tiene ALGÚN registro personalizado, esos son sus permisos exclusivos
        $anyStmt = $db->prepare("SELECT COUNT(*) FROM user_module_permissions WHERE user_id = ?");
        $anyStmt->execute([$uid]);
        if ((int)$anyStmt->fetchColumn() > 0) return false;
        // 2) Fallback a permisos por rol
        $stmt2 = $db->prepare(
            "SELECT COUNT(*) FROM role_module_permissions WHERE rol = ? AND modulo = ? AND permiso = ?"
        );
        $stmt2->execute([$rol, $modulo, $permiso]);
        return (int)$stmt2->fetchColumn() > 0;
    } catch (Throwable $e) {
        // Tabla no existe → fallback a permisos globales
        return can($permiso);
    }
}

/**
 * Requiere permiso granular en módulo, o devuelve 403.
 */
function require_module_permission(string $modulo, string $permiso): void {
    require_login();
    if (!can_module($modulo, $permiso)) {
        http_response_code(403);
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                header('Content-Type: application/json');
                echo json_encode(['error' => "Sin permisos: {$permiso} en {$modulo}"]);
                exit;
            }
        }
        include __DIR__ . '/403.php';
        exit;
    }
}

/**
 * Retorna la etiqueta CSS del badge para el rol.
 */
function role_badge(string $rol): string {
    return ROLE_BADGES[$rol] ?? 'badge-gray';
}

/**
 * Retorna la etiqueta legible del rol.
 */
function role_label(string $rol): string {
    return ROLES[$rol] ?? ucfirst($rol);
}

/**
 * Autentica un usuario por email y contraseña.
 */
function login(string $email, string $password): bool {
    session_init();
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_nombre'] = $user['nombre'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['user_rol']    = $user['rol'];
        $db->prepare(
            "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?"
        )->execute([$user['id']]);
        audit_log('auth', 'login', (int)$user['id'], [], [
            'email' => $user['email'],
            'rol'   => $user['rol']
        ]);
        return true;
    }
    return false;
}

function logout(): void {
    session_init();
    audit_log('auth', 'logout', (int)($_SESSION['user_id'] ?? 0), [], [
        'email' => $_SESSION['user_email'] ?? null,
        'rol'   => $_SESSION['user_rol'] ?? null
    ]);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: /index.php');
    exit;
}
