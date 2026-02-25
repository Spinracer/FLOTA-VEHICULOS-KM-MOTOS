<?php
require_once __DIR__ . '/db.php';

function session_init() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('FLOTACONTROL');
        session_start();
    }
}

function is_logged_in(): bool {
    session_init();
    return isset($_SESSION['user_id']);
}

function require_login() {
    session_init();
    if (!is_logged_in()) {
        header('Location: /index.php');
        exit;
    }
}

function require_role(string ...$roles) {
    require_login();
    if (!in_array($_SESSION['user_rol'], $roles)) {
        http_response_code(403);
        die('Acceso denegado: no tienes permisos para esta acción.');
    }
}

function current_user(): array {
    session_init();
    return [
        'id'     => $_SESSION['user_id']   ?? null,
        'nombre' => $_SESSION['user_nombre'] ?? '',
        'email'  => $_SESSION['user_email'] ?? '',
        'rol'    => $_SESSION['user_rol']   ?? '',
    ];
}

function can(string $action): bool {
    $rol = $_SESSION['user_rol'] ?? '';
    $perms = [
        'admin'    => ['view','create','edit','delete','manage_users'],
        'operador' => ['view','create','edit'],
        'lectura'  => ['view'],
    ];
    return in_array($action, $perms[$rol] ?? []);
}

function login(string $email, string $password): bool {
    session_init();
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_nombre'] = $user['nombre'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['user_rol']    = $user['rol'];
        // Actualizar último acceso
        $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$user['id']]);
        return true;
    }
    return false;
}

function logout() {
    session_init();
    session_destroy();
    header('Location: /index.php');
    exit;
}
