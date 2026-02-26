<?php

require_once __DIR__ . '/db.php';

function audit_log(string $entidad, string $accion, ?int $entidad_id = null, array $antes = [], array $despues = [], array $meta = []): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO audit_logs (
            user_id, user_email, user_rol, entidad, entidad_id, accion,
            antes_json, despues_json, meta_json, ip, created_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");

        $uid   = $_SESSION['user_id'] ?? null;
        $email = $_SESSION['user_email'] ?? null;
        $rol   = $_SESSION['user_rol'] ?? null;
        $ip    = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

        $stmt->execute([
            $uid,
            $email,
            $rol,
            $entidad,
            $entidad_id,
            $accion,
            $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
            $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
            $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            $ip,
        ]);
    } catch (Throwable $e) {
        error_log('audit_log error: ' . $e->getMessage());
    }
}
