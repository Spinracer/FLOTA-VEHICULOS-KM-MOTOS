<?php
/**
 * API Notificaciones — FlotaControl v2.9
 * GET    → lista no leídas (o todas con ?all=1)
 * GET    ?count=1 → solo cantidad de no leídas
 * PUT    ?id=X → marcar como leída
 * PUT    ?all=1 → marcar todas como leídas
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_login();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$userId = (int)$_SESSION['user_id'];

try {
    switch ($method) {
        case 'GET':
            if (!empty($_GET['count'])) {
                echo json_encode(['unread' => count_unread($db, $userId)]);
                break;
            }
            $showAll = !empty($_GET['all']);
            $limit = min(100, max(5, (int)($_GET['limit'] ?? 30)));
            if ($showAll) {
                $stmt = $db->prepare("SELECT * FROM notificaciones WHERE (usuario_id = ? OR usuario_id IS NULL) ORDER BY created_at DESC LIMIT ?");
                $stmt->execute([$userId, $limit]);
            } else {
                $stmt = $db->prepare("SELECT * FROM notificaciones WHERE (usuario_id = ? OR usuario_id IS NULL) AND leida = 0 ORDER BY created_at DESC LIMIT ?");
                $stmt->execute([$userId, $limit]);
            }
            echo json_encode(['rows' => $stmt->fetchAll(), 'unread' => count_unread($db, $userId)]);
            break;
        case 'PUT':
            if (!empty($_GET['all'])) {
                mark_all_read($db, $userId);
                echo json_encode(['ok' => true, 'message' => 'Todas marcadas como leídas']);
            } elseif (!empty($_GET['id'])) {
                mark_read($db, (int)$_GET['id'], $userId);
                echo json_encode(['ok' => true]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Falta id o all']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no soportado']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => safe_error_msg($e)]);
}
