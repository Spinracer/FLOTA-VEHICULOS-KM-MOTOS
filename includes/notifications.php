<?php
/**
 * Sistema de notificaciones — FlotaControl v2.9
 * Crea notificaciones en BD y opcionalmente envía email.
 */

require_once __DIR__ . '/db.php';

/**
 * Crear notificación para un usuario específico.
 */
function notify_user(PDO $db, int $userId, string $tipo, string $titulo, string $mensaje, ?string $entidad = null, ?int $entidadId = null): int {
    $stmt = $db->prepare("INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, entidad, entidad_id) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$userId, $tipo, $titulo, $mensaje, $entidad, $entidadId]);
    return (int)$db->lastInsertId();
}

/**
 * Crear notificación para todos los usuarios con ciertos roles.
 */
function notify_roles(PDO $db, array $roles, string $tipo, string $titulo, string $mensaje, ?string $entidad = null, ?int $entidadId = null): int {
    if (empty($roles)) return 0;
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE rol IN ({$placeholders}) AND activo = 1");
    $stmt->execute($roles);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $count = 0;
    foreach ($ids as $uid) {
        notify_user($db, (int)$uid, $tipo, $titulo, $mensaje, $entidad, $entidadId);
        $count++;
    }
    return $count;
}

/**
 * Crear notificación global (usuario_id = NULL → visible para todos).
 */
function notify_all(PDO $db, string $tipo, string $titulo, string $mensaje, ?string $entidad = null, ?int $entidadId = null): int {
    $stmt = $db->prepare("INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, entidad, entidad_id) VALUES (NULL,?,?,?,?,?)");
    $stmt->execute([$tipo, $titulo, $mensaje, $entidad, $entidadId]);
    return (int)$db->lastInsertId();
}

/**
 * Obtener notificaciones no leídas para un usuario.
 */
function get_unread_notifications(PDO $db, int $userId, int $limit = 20): array {
    $stmt = $db->prepare("SELECT * FROM notificaciones WHERE (usuario_id = ? OR usuario_id IS NULL) AND leida = 0 ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Contar no leídas.
 */
function count_unread(PDO $db, int $userId): int {
    $stmt = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE (usuario_id = ? OR usuario_id IS NULL) AND leida = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Marcar como leída.
 */
function mark_read(PDO $db, int $notifId, int $userId): void {
    $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND (usuario_id = ? OR usuario_id IS NULL)");
    $stmt->execute([$notifId, $userId]);
}

/**
 * Marcar todas como leídas.
 */
function mark_all_read(PDO $db, int $userId): void {
    $db->prepare("UPDATE notificaciones SET leida = 1 WHERE (usuario_id = ? OR usuario_id IS NULL) AND leida = 0")->execute([$userId]);
}

/**
 * Enviar email simple (usa mail() de PHP).
 * Retorna true si se envió, false si no.
 */
function send_notification_email(string $to, string $subject, string $body): bool {
    $headers = "From: FlotaControl <noreply@flotacontrol.local>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $html = "<!DOCTYPE html><html><body style='font-family:sans-serif;background:#0a0c10;color:#f0f2f5;padding:20px'>
        <div style='max-width:500px;margin:0 auto;background:#111318;border:1px solid #222730;border-radius:12px;padding:24px'>
            <h2 style='color:#e8ff47;margin:0 0 12px'>FlotaControl</h2>
            <h3 style='margin:0 0 8px'>{$subject}</h3>
            <p style='color:#8892a4'>{$body}</p>
            <hr style='border-color:#222730;margin:16px 0'>
            <small style='color:#555'>Notificación automática del sistema FlotaControl</small>
        </div></body></html>";
    return @mail($to, "FlotaControl: {$subject}", $html, $headers);
}
