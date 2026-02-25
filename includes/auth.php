<?php
require_once __DIR__ . '/db.php';

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
];

// Mapa de permisos por rol
const ROLE_PERMISSIONS = [
    'coordinador_it' => ['view', 'create', 'edit', 'delete', 'manage_users', 'manage_permissions'],
    'soporte'        => ['view', 'create', 'edit'],
    'monitoreo'      => ['view'],
    // Compatibilidad con roles anteriores
    'admin'          => ['view', 'create', 'edit', 'delete', 'manage_users', 'manage_permissions'],
    'operador'       => ['view', 'create', 'edit'],
    'lectura'        => ['view'],
];

// Etiquetas visuales por rol
const ROLE_BADGES = [
    'coordinador_it' => 'badge-yellow',
    'soporte'        => 'badge-blue',
    'monitoreo'      => 'badge-cyan',
    'admin'          => 'badge-yellow',
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
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    session_init();
    if (!is_logged_in()) {
        header('Location: /index.php');
        exit;
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
        return true;
    }
    return false;
}

function logout(): void {
    session_init();
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
