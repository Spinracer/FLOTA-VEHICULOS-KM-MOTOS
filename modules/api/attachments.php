<?php
/**
 * API genérica de adjuntos.
 * GET    ?entidad=X&entidad_id=Y   → lista adjuntos
 * GET    ?action=download&id=Z     → descarga archivo
 * POST   multipart/form-data       → sube archivo (entidad, entidad_id, archivo)
 * DELETE ?id=Z                     → soft-delete adjunto
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/attachments.php';
require_login();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

try {
    $action = trim($_GET['action'] ?? '');

    // ─── Download ───
    if ($method === 'GET' && $action === 'download') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido.']); exit; }
        $att = attachment_get($id);
        if (!$att) { http_response_code(404); echo json_encode(['error' => 'Adjunto no encontrado.']); exit; }
        $path = attachment_path($id);
        if (!$path) { http_response_code(404); echo json_encode(['error' => 'Archivo no encontrado en disco.']); exit; }

        header('Content-Type: ' . $att['mime_type']);
        header('Content-Disposition: inline; filename="' . $att['original_name'] . '"');
        header('Content-Length: ' . filesize($path));
        header_remove('X-Powered-By');
        readfile($path);
        exit;
    }

    switch ($method) {
        case 'GET':
            $entidad = trim($_GET['entidad'] ?? '');
            $entidadId = (int)($_GET['entidad_id'] ?? 0);
            if (!$entidad || $entidadId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'entidad y entidad_id son obligatorios.']);
                break;
            }
            $list = attachment_list($entidad, $entidadId);
            echo json_encode(['attachments' => $list]);
            break;

        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para subir archivos.']);
                break;
            }
            $entidad = trim($_POST['entidad'] ?? '');
            $entidadId = (int)($_POST['entidad_id'] ?? 0);
            if (!$entidad || $entidadId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'entidad y entidad_id son obligatorios.']);
                break;
            }
            if (!isset($_FILES['archivo'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No se recibió archivo (campo: archivo).']);
                break;
            }

            // Multi-file support
            $files = $_FILES['archivo'];
            $results = [];
            if (is_array($files['name'])) {
                for ($i = 0; $i < count($files['name']); $i++) {
                    $single = [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ];
                    $results[] = attachment_upload($entidad, $entidadId, $single);
                }
            } else {
                $results[] = attachment_upload($entidad, $entidadId, $files);
            }
            echo json_encode(['ok' => true, 'uploaded' => $results]);
            break;

        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para eliminar adjuntos.']);
                break;
            }
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ID requerido.']);
                break;
            }
            $ok = attachment_delete($id);
            if (!$ok) { http_response_code(404); echo json_encode(['error' => 'Adjunto no encontrado.']); break; }
            echo json_encode(['ok' => true, 'deleted' => $id]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
    }
} catch (RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => safe_error_msg($e)]);
}
