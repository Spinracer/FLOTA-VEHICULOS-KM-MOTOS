<?php
/**
 * Sistema de adjuntos reutilizable.
 * Almacena archivos en /uploads/{entidad}/{entidad_id}/
 * Tipos permitidos: imágenes, PDF, documentos comunes.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';

define('UPLOAD_BASE', __DIR__ . '/../uploads');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB
define('UPLOAD_ALLOWED_TYPES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
]);
define('UPLOAD_ALLOWED_EXT', ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx']);

/**
 * Sube un archivo asociado a una entidad.
 * @param string $entidad Ej: 'vehiculos', 'mantenimientos', 'combustible'
 * @param int $entidadId ID del registro
 * @param array $file $_FILES['archivo'] element
 * @return array ['id'=>int, 'filename'=>string, ...] o lanza Exception
 */
function attachment_upload(string $entidad, int $entidadId, array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'Archivo excede tamaño máximo del servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'Archivo excede tamaño máximo del formulario.',
            UPLOAD_ERR_PARTIAL    => 'Archivo subido parcialmente.',
            UPLOAD_ERR_NO_FILE    => 'No se recibió ningún archivo.',
        ];
        throw new RuntimeException($errors[$file['error']] ?? 'Error al subir archivo.');
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        throw new RuntimeException('El archivo excede el tamaño máximo de ' . round(UPLOAD_MAX_SIZE/1024/1024) . ' MB.');
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, UPLOAD_ALLOWED_TYPES)) {
        throw new RuntimeException("Tipo de archivo no permitido: {$mime}");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXT)) {
        throw new RuntimeException("Extensión no permitida: .{$ext}");
    }

    $dir = UPLOAD_BASE . '/' . $entidad . '/' . $entidadId;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $safeName = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $destPath = $dir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Error al mover archivo al almacenamiento.');
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO attachments (entidad, entidad_id, filename, original_name, mime_type, size_bytes, uploaded_by) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        $entidad,
        $entidadId,
        $entidad . '/' . $entidadId . '/' . $safeName,
        $file['name'],
        $mime,
        $file['size'],
        $_SESSION['user_id'] ?? null,
    ]);
    $id = (int)$db->lastInsertId();

    audit_log('attachments', 'create', $id, [], [
        'entidad' => $entidad,
        'entidad_id' => $entidadId,
        'original_name' => $file['name'],
        'size' => $file['size'],
    ]);

    return [
        'id' => $id,
        'filename' => $safeName,
        'original_name' => $file['name'],
        'mime_type' => $mime,
        'size_bytes' => $file['size'],
    ];
}

/**
 * Lista adjuntos de una entidad.
 */
function attachment_list(string $entidad, int $entidadId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, filename, original_name, mime_type, size_bytes, uploaded_by, created_at FROM attachments WHERE entidad=? AND entidad_id=? AND deleted_at IS NULL ORDER BY created_at DESC");
    $stmt->execute([$entidad, $entidadId]);
    return $stmt->fetchAll();
}

/**
 * Obtiene ruta del archivo para descarga.
 */
function attachment_path(int $id): ?string {
    $db = getDB();
    $stmt = $db->prepare("SELECT filename FROM attachments WHERE id=? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $path = UPLOAD_BASE . '/' . $row['filename'];
    return file_exists($path) ? $path : null;
}

/**
 * Obtiene metadatos de un adjunto.
 */
function attachment_get(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM attachments WHERE id=? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Soft-delete de adjunto.
 */
function attachment_delete(int $id): bool {
    $db = getDB();
    $att = attachment_get($id);
    if (!$att) return false;

    $db->prepare("UPDATE attachments SET deleted_at=NOW() WHERE id=?")->execute([$id]);

    audit_log('attachments', 'delete', $id, $att, []);
    return true;
}
