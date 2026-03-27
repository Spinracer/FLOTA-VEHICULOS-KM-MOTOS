<?php
/**
 * API — Importación de Vehículos V1
 *
 * Endpoints:
 *   POST ?action=upload     → Sube archivo, retorna headers y datos preview
 *   POST ?action=sheets     → Lista hojas de XLSX
 *   POST ?action=import     → Ejecuta importación con mapeo
 *   GET  ?action=history    → Historial de importaciones
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/importacion_vehiculos.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    // Verificar permisos — importar requiere permiso de crear
    if (!can('create')) {
        http_response_code(403);
        echo json_encode(['error' => 'No tienes permisos para importar vehículos']);
        exit;
    }

    switch ($action) {
        // ── Subir archivo y leer encabezados ──
        case 'upload':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
                break;
            }

            if (!isset($_FILES['archivo'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No se recibió ningún archivo']);
                break;
            }

            $uploaded = importacion_subir_archivo($_FILES['archivo']);
            $sheetIndex = (int)($_POST['sheet_index'] ?? 0);

            $fileData = importacion_leer_archivo(
                $uploaded['path'],
                $uploaded['extension'],
                $sheetIndex
            );

            // Guardar ruta en sesión para uso posterior
            $_SESSION['import_file'] = [
                'path'      => $uploaded['path'],
                'extension' => $uploaded['extension'],
                'name'      => $uploaded['original_name'],
            ];

            echo json_encode([
                'ok'         => true,
                'headers'    => $fileData['headers'],
                'preview'    => array_slice($fileData['rows'], 0, 5), // Preview 5 filas
                'total_rows' => $fileData['total_rows'],
                'sheets'     => $fileData['sheets'],
                'campos'     => importacion_campos_destino(),
            ]);
            break;

        // ── Cambiar hoja (XLSX) ──
        case 'sheets':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
                break;
            }

            $importFile = $_SESSION['import_file'] ?? null;
            if (!$importFile || !file_exists($importFile['path'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No hay archivo cargado. Sube uno primero.']);
                break;
            }

            $d = json_decode(file_get_contents('php://input'), true);
            $sheetIndex = (int)($d['sheet_index'] ?? 0);

            $fileData = importacion_leer_archivo(
                $importFile['path'],
                $importFile['extension'],
                $sheetIndex
            );

            echo json_encode([
                'ok'         => true,
                'headers'    => $fileData['headers'],
                'preview'    => array_slice($fileData['rows'], 0, 5),
                'total_rows' => $fileData['total_rows'],
            ]);
            break;

        // ── Ejecutar importación ──
        case 'import':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
                break;
            }

            $importFile = $_SESSION['import_file'] ?? null;
            if (!$importFile || !file_exists($importFile['path'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No hay archivo cargado. Sube uno primero.']);
                break;
            }

            $d = json_decode(file_get_contents('php://input'), true);
            $mapping = $d['mapping'] ?? [];
            $sheetIndex = (int)($d['sheet_index'] ?? 0);
            $updateExisting = !empty($d['update_existing']);

            if (empty($mapping)) {
                http_response_code(400);
                echo json_encode(['error' => 'No se definió el mapeo de columnas']);
                break;
            }

            // Validar que los campos obligatorios estén mapeados
            $campos = importacion_campos_destino();
            $mappedFields = array_values(array_filter($mapping, fn($v) => $v !== '' && $v !== '__ignorar__'));

            foreach ($campos as $campo => $info) {
                if ($info['required'] && !in_array($campo, $mappedFields)) {
                    http_response_code(400);
                    echo json_encode(['error' => "Campo obligatorio '{$info['label']}' no está mapeado"]);
                    exit;
                }
            }

            // Validar duplicados en mapeo
            $mappedNonEmpty = array_filter($mapping, fn($v) => $v !== '' && $v !== '__ignorar__');
            $dupCheck = array_count_values($mappedNonEmpty);
            foreach ($dupCheck as $field => $count) {
                if ($count > 1) {
                    $label = $campos[$field]['label'] ?? $field;
                    http_response_code(400);
                    echo json_encode(['error' => "Campo '$label' está mapeado más de una vez"]);
                    exit;
                }
            }

            // Leer datos completos
            $fileData = importacion_leer_archivo(
                $importFile['path'],
                $importFile['extension'],
                $sheetIndex
            );

            // Ejecutar importación
            $resultado = importacion_ejecutar(
                $fileData['rows'],
                $fileData['headers'],
                $mapping,
                (int)($_SESSION['user_id'] ?? 0),
                $importFile['name'],
                $updateExisting
            );

            // Limpiar archivo temporal
            importacion_limpiar_archivo($importFile['path']);
            unset($_SESSION['import_file']);

            echo json_encode([
                'ok'           => true,
                'total'        => $resultado['total'],
                'creados'      => $resultado['creados'],
                'actualizados' => $resultado['actualizados'] ?? 0,
                'errores'      => $resultado['errores'],
                'detalle'      => $resultado['detalle'],
                'run_id'       => $resultado['import_run_id'],
            ]);
            break;

        // ── Historial de importaciones ──
        case 'history':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
                break;
            }

            $db = getDB();
            $stmt = $db->prepare("SELECT ir.*, u.nombre AS usuario_nombre
                FROM import_runs ir
                LEFT JOIN usuarios u ON u.id = ir.usuario_id
                WHERE ir.tipo_importacion = 'vehiculos'
                ORDER BY ir.created_at DESC
                LIMIT 20");
            $stmt->execute();

            echo json_encode(['ok' => true, 'runs' => $stmt->fetchAll()]);
            break;

        // ── Eliminar registro de historial ──
        case 'delete_run':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $runId = (int)($d['run_id'] ?? 0);
            if ($runId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de importación requerido']);
                break;
            }
            $db = getDB();
            $db->prepare("DELETE FROM import_runs WHERE id = ?")->execute([$runId]);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => safe_error_msg($e)]);
}
