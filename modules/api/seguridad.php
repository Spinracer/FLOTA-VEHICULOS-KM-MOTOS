<?php
// ─────────────────────────────────────────────────────────
// FlotaControl — API Seguridad (2FA Management)
// ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';

require_login();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db = getDB();
$userId = (int)$_SESSION['user_id'];

try {
    // ── GET: retrieve 2FA status ──
    if ($method === 'GET' && $action === '2fa_status') {
        $enabled = totp_is_enabled($db, $userId);
        echo json_encode(['totp_enabled' => $enabled]);
        exit;
    }

    // ── POST: setup 2FA (generate secret, show QR) ──
    if ($method === 'POST' && $action === '2fa_setup') {
        $d = json_decode(file_get_contents('php://input'), true) ?: [];

        // Generate a new secret
        $secret = totp_generate_secret();
        // Store temporarily in session (will be committed when user verifies)
        $_SESSION['2fa_setup_secret'] = $secret;

        $email = $_SESSION['user_email'] ?? 'user';
        $uri = totp_uri($secret, $email);

        echo json_encode([
            'secret' => $secret,
            'uri'    => $uri,
        ]);
        exit;
    }

    // ── POST: verify & enable 2FA ──
    if ($method === 'POST' && $action === '2fa_enable') {
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $code = trim($d['code'] ?? '');
        $secret = $_SESSION['2fa_setup_secret'] ?? '';

        if (!$secret) {
            http_response_code(400);
            echo json_encode(['error' => 'Primero inicia la configuración 2FA.']);
            exit;
        }

        if (!totp_verify($secret, $code)) {
            http_response_code(400);
            echo json_encode(['error' => 'Código incorrecto. Verifica con tu app de autenticación.']);
            exit;
        }

        totp_enable($db, $userId, $secret);
        unset($_SESSION['2fa_setup_secret']);

        audit_log('seguridad', '2fa_enabled', $userId, [], ['email' => $_SESSION['user_email']]);
        echo json_encode(['ok' => true, 'message' => '2FA activado correctamente.']);
        exit;
    }

    // ── POST: disable 2FA ──
    if ($method === 'POST' && $action === '2fa_disable') {
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $password = $d['password'] ?? '';

        // Require password confirmation to disable 2FA
        $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Contraseña incorrecta.']);
            exit;
        }

        totp_disable($db, $userId);
        audit_log('seguridad', '2fa_disabled', $userId, [], ['email' => $_SESSION['user_email']]);
        echo json_encode(['ok' => true, 'message' => '2FA desactivado.']);
        exit;
    }

    // ── POST: admin reset 2FA for another user ──
    if ($method === 'POST' && $action === '2fa_admin_reset') {
        require_admin();
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $targetUserId = (int)($d['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de usuario requerido.']);
            exit;
        }
        totp_disable($db, $targetUserId);
        audit_log('seguridad', '2fa_admin_reset', $targetUserId, [], [
            'reset_by' => $userId,
            'email' => $_SESSION['user_email']
        ]);
        echo json_encode(['ok' => true, 'message' => '2FA reseteado para el usuario.']);
        exit;
    }

    // ── GET: security dashboard stats (admin only) ──
    if ($method === 'GET' && $action === 'stats') {
        require_admin();

        // Users with 2FA
        $twofa = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE totp_enabled = 1 AND activo = 1")->fetchColumn();
        $totalUsers = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn();

        // Recent failed logins (from audit)
        $failedLogins = (int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE entidad = 'auth' AND accion = 'login_failed' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();

        // Rate limit blocks in last hour
        $rateLimits = 0;
        try {
            $rateLimits = (int)$db->query("SELECT COUNT(*) FROM rate_limits WHERE hits >= 5")->fetchColumn();
        } catch (Throwable $e) {}

        // Recent security events
        $events = $db->query("SELECT al.*, u.nombre as user_nombre
            FROM audit_logs al
            LEFT JOIN usuarios u ON al.user_id = u.id
            WHERE al.entidad IN ('auth','seguridad')
            ORDER BY al.created_at DESC
            LIMIT 50")->fetchAll();

        echo json_encode([
            'users_with_2fa' => $twofa,
            'total_users'    => $totalUsers,
            'failed_logins_24h' => $failedLogins,
            'rate_limit_entries' => $rateLimits,
            'events' => $events,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Acción no reconocida.']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => safe_error_msg($e)]);
}
