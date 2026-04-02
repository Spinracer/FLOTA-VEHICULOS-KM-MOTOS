<?php
/**
 * API — Importación de Operadores
 *
 * POST ?action=upload   → Sube archivo y retorna encabezados / vista previa
 * POST ?action=sheets   → Cambia hoja en XLSX
 * POST ?action=import   → Ejecuta importación masiva
 * GET  ?action=history  → Historial de importaciones
 * POST ?action=delete_run → Elimina registro de importación
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/importacion_vehiculos.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    if (!can('create')) {
        http_response_code(403);
        echo json_encode(['error' => 'No tienes permisos para importar operadores']);
        exit;
    }

    switch ($action) {
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
            $fileData = importacion_leer_archivo($uploaded['path'], $uploaded['extension'], $sheetIndex);
            $_SESSION['import_file_operadores'] = [
                'path' => $uploaded['path'],
                'extension' => $uploaded['extension'],
                'name' => $uploaded['original_name'],
            ];
            echo json_encode([
                'ok' => true,
                'headers' => $fileData['headers'],
                'preview' => array_slice($fileData['rows'], 0, 5),
                'total_rows' => $fileData['total_rows'],
                'sheets' => $fileData['sheets'],
                'campos' => importacion_campos_destino_operadores(),
            ]);
            break;

        case 'sheets':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
                break;
            }
            $importFile = $_SESSION['import_file_operadores'] ?? null;
            if (!$importFile || !file_exists($importFile['path'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No hay archivo cargado. Sube uno primero.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $sheetIndex = (int)($d['sheet_index'] ?? 0);
            $fileData = importacion_leer_archivo($importFile['path'], $importFile['extension'], $sheetIndex);
            echo json_encode([
                'ok' => true,
                'headers' => $fileData['headers'],
                'preview' => array_slice($fileData['rows'], 0, 5),
                'total_rows' => $fileData['total_rows'],
            ]);
            break;

        case 'import':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
                break;
            }
            $importFile = $_SESSION['import_file_operadores'] ?? null;
            if (!$importFile || !file_exists($importFile['path'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No hay archivo cargado. Sube uno primero.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $mapping = $d['mapping'] ?? [];
            $sheetIndex = (int)($d['sheet_index'] ?? 0);
            $updateExisting = !empty($d['update_existing']);
            $updateKeyField = trim($d['update_key_field'] ?? 'dni');
            if (empty($mapping)) {
                http_response_code(400);
                echo json_encode(['error' => 'No se definió el mapeo de columnas']);
                break;
            }
            $campos = importacion_campos_destino_operadores();
            $mappedFields = array_values(array_filter($mapping, fn($v) => $v !== '' && $v !== '__ignorar__'));
            foreach ($campos as $campo => $info) {
                if ($info['required'] && !in_array($campo, $mappedFields)) {
                    http_response_code(400);
                    echo json_encode(['error' => "Campo obligatorio '{$info['label']}' no está mapeado"]);
                    exit;
                }
            }
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
            $fileData = importacion_leer_archivo($importFile['path'], $importFile['extension'], $sheetIndex);
            $resultado = importacion_ejecutar_operadores($fileData['rows'], $fileData['headers'], $mapping, (int)($_SESSION['user_id'] ?? 0), $importFile['name'], $updateExisting, $updateKeyField);
            importacion_limpiar_archivo($importFile['path']);
            unset($_SESSION['import_file_operadores']);
            echo json_encode([
                'ok' => true,
                'total' => $resultado['total'],
                'creados' => $resultado['creados'],
                'actualizados' => $resultado['actualizados'] ?? 0,
                'errores' => $resultado['errores'],
                'detalle' => $resultado['detalle'],
                'run_id' => $resultado['import_run_id'],
            ]);
            break;

        case 'history':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
                break;
            }
            $stmt = getDB()->prepare("SELECT ir.*, u.nombre AS usuario_nombre
                FROM import_runs ir
                LEFT JOIN usuarios u ON u.id = ir.usuario_id
                WHERE ir.tipo_importacion = 'operadores'
                ORDER BY ir.created_at DESC
                LIMIT 20");
            $stmt->execute();
            echo json_encode(['ok' => true, 'runs' => $stmt->fetchAll()]);
            break;

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

function importacion_campos_destino_operadores(): array {
    return [
        'id'             => ['label' => 'ID interno', 'required' => false, 'type' => 'integer'],
        'nombre'         => ['label' => 'Nombre completo', 'required' => true, 'type' => 'text'],
        'dni'            => ['label' => 'DNI / Identidad', 'required' => false, 'type' => 'text'],
        'departamento_id'=> ['label' => 'Departamento (ID)', 'required' => false, 'type' => 'integer'],
        'licencia'       => ['label' => 'No. Licencia', 'required' => false, 'type' => 'text'],
        'categoria_lic'  => ['label' => 'Categoría de licencia', 'required' => false, 'type' => 'text'],
        'venc_licencia'  => ['label' => 'Venc. licencia', 'required' => false, 'type' => 'date'],
        'telefono'       => ['label' => 'Teléfono', 'required' => false, 'type' => 'text'],
        'email'          => ['label' => 'Email', 'required' => false, 'type' => 'text'],
        'estado'         => ['label' => 'Estado', 'required' => false, 'type' => 'text'],
        'notas'          => ['label' => 'Notas', 'required' => false, 'type' => 'text'],
    ];
}

function importacion_validar_fila_operadores(array $row, array $headers, array $mapping, array $camposDestino): array {
    $data = [];
    $errors = [];
    foreach ($mapping as $headerIdx => $campoDestino) {
        if ($campoDestino === '' || $campoDestino === '__ignorar__') continue;
        $valor = $row[$headerIdx] ?? '';
        $data[$campoDestino] = trim($valor);
    }
    foreach ($camposDestino as $campo => $info) {
        if (!$info['required']) continue;
        $val = trim($data[$campo] ?? '');
        if ($val === '') {
            $errors[] = "Campo '{$info['label']}' es obligatorio";
        }
    }
    if (isset($data['id']) && $data['id'] !== '') {
        $id = filter_var($data['id'], FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            $errors[] = "ID inválido: '{$data['id']}'";
        } else {
            $data['id'] = $id;
        }
    }
    if (isset($data['departamento_id']) && $data['departamento_id'] !== '') {
        $did = filter_var($data['departamento_id'], FILTER_VALIDATE_INT);
        if ($did === false || $did <= 0) {
            $errors[] = "Departamento ID inválido: '{$data['departamento_id']}'";
        } else {
            $data['departamento_id'] = $did;
        }
    }
    if (isset($data['venc_licencia']) && $data['venc_licencia'] !== '') {
        $fecha = importacion_parsear_fecha($data['venc_licencia']);
        if ($fecha === null) {
            $errors[] = "Fecha venc. licencia inválida: '{$data['venc_licencia']}'";
        } else {
            $data['venc_licencia'] = $fecha;
        }
    }
    if (isset($data['estado']) && $data['estado'] !== '') {
        $estadosValidos = ['Activo', 'Inactivo', 'Suspendido'];
        if (!in_array($data['estado'], $estadosValidos)) {
            $errors[] = "Estado inválido: '{$data['estado']}'. Válidos: " . implode(', ', $estadosValidos);
        }
    }
    if (isset($data['email']) && $data['email'] !== '') {
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email inválido: '{$data['email']}'";
        }
    }
    return ['valid' => empty($errors), 'data' => $data, 'errors' => $errors];
}

function importacion_ejecutar_operadores(array $rows, array $headers, array $mapping, int $userId, string $fileName, bool $updateExisting = false, string $updateKeyField = 'dni'): array {
    $db = getDB();
    $campos = importacion_campos_destino_operadores();
    $validKeyFields = ['id', 'dni'];
    if (!in_array($updateKeyField, $validKeyFields)) {
        $updateKeyField = 'dni';
    }
    set_time_limit(300);
    $resultado = ['total' => count($rows), 'creados' => 0, 'actualizados' => 0, 'errores' => 0, 'detalle' => [], 'import_run_id' => null];
    $stmt = $db->prepare("INSERT INTO import_runs (tipo_importacion, nombre_archivo, usuario_id, total_filas, estado) VALUES ('operadores', ?, ?, ?, 'procesando')");
    $stmt->execute([$fileName, $userId, count($rows)]);
    $runId = (int)$db->lastInsertId();
    $resultado['import_run_id'] = $runId;
    $keyFieldsExistentes = [];
    $dniExistentes = [];
    $stmtKey = $db->query("SELECT id, UPPER(TRIM(dni)) AS key_val FROM operadores WHERE deleted_at IS NULL AND dni IS NOT NULL AND dni != ''");
    foreach ($stmtKey->fetchAll() as $row) {
        $dniExistentes[$row['key_val']] = (int)$row['id'];
    }
    if ($updateExisting && $updateKeyField === 'dni') {
        $keyFieldsExistentes = $dniExistentes;
    } elseif ($updateExisting && $updateKeyField === 'id') {
        $stmtId = $db->query("SELECT id, CAST(id AS CHAR) AS key_val FROM operadores WHERE deleted_at IS NULL");
        foreach ($stmtId->fetchAll() as $row) {
            $keyFieldsExistentes[$row['key_val']] = (int)$row['id'];
        }
    }
    $keysEnArchivo = [];
    foreach ($rows as $i => $row) {
        $fila = $i + 2;
        $validacion = importacion_validar_fila_operadores($row, $headers, $mapping, $campos);
        if (!$validacion['valid']) {
            $resultado['errores']++;
            $resultado['detalle'][] = ['fila'=>$fila,'tipo'=>'validacion','errores'=>$validacion['errors'],'nombre'=>$validacion['data']['nombre'] ?? '—'];
            continue;
        }
        $data = $validacion['data'];
        $keyValue = '';
        if ($updateKeyField === 'dni' && !empty($data['dni'])) {
            $keyValue = strtoupper(trim($data['dni']));
        } elseif ($updateKeyField === 'id' && !empty($data['id'])) {
            $keyValue = (string)$data['id'];
        }
        if (isset($keysEnArchivo[$keyValue]) && $keyValue !== '') {
            $resultado['errores']++;
            $resultado['detalle'][] = ['fila'=>$fila,'tipo'=>'duplicado_archivo','errores'=>["Clave '$keyValue' duplicada en el archivo (primera vez en fila {$keysEnArchivo[$keyValue]})"],'nombre'=>$data['nombre'] ?? '—'];
            continue;
        }
        if (!$updateExisting) {
            if (!empty($data['dni']) && isset($dniExistentes[strtoupper(trim($data['dni']))])) {
                $resultado['errores']++;
                $resultado['detalle'][] = ['fila'=>$fila,'tipo'=>'duplicado_bd','errores'=>["DNI '{$data['dni']}' ya existe en el sistema"],'nombre'=>$data['nombre'] ?? '—'];
                continue;
            }
            if (!empty($data['id'])) {
                $stmtId = $db->prepare("SELECT id FROM operadores WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                $stmtId->execute([$data['id']]);
                if ($stmtId->fetch()) {
                    $resultado['errores']++;
                    $resultado['detalle'][] = ['fila'=>$fila,'tipo'=>'duplicado_bd','errores'=>["ID '{$data['id']}' ya existe en el sistema"],'nombre'=>$data['nombre'] ?? '—'];
                    continue;
                }
            }
        }
        if ($updateExisting && $keyValue !== '' && isset($keyFieldsExistentes[$keyValue])) {
            try {
                $existId = $keyFieldsExistentes[$keyValue];
                $setCols = [];
                $setVals = [];
                foreach ($data as $col => $val) {
                    if ($col === 'id') continue;
                    if ($val === '' || $val === null) continue;
                    $setCols[] = "$col = ?";
                    $setVals[] = $val;
                }
                if (!empty($setCols)) {
                    $setVals[] = $existId;
                    $db->prepare("UPDATE operadores SET " . implode(', ', $setCols) . " WHERE id = ?")->execute($setVals);
                    audit_log('operadores', 'update', $existId, [], array_merge($data, ['_via'=>'importacion_update','_import_run'=>$runId,'key_field'=>$updateKeyField]));
                }
                $resultado['actualizados']++;
                $keysEnArchivo[$keyValue] = $fila;
                continue;
            } catch (Throwable $e) {
                $resultado['errores']++;
                $resultado['detalle'][] = ['fila'=>$fila,'tipo'=>'error_bd','errores'=>["Error actualización: " . $e->getMessage()],'nombre'=>$data['nombre'] ?? '—'];
                continue;
            }
        }
        try {
            $db->beginTransaction();
            $insertStmt = $db->prepare("INSERT INTO operadores (nombre, dni, departamento_id, licencia, categoria_lic, venc_licencia, telefono, email, estado, notas) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $insertStmt->execute([
                $data['nombre'],
                $data['dni'] ?? null,
                $data['departamento_id'] ?? null,
                $data['licencia'] ?? null,
                $data['categoria_lic'] ?? null,
                $data['venc_licencia'] ?? null,
                $data['telefono'] ?? null,
                $data['email'] ?? null,
                !empty($data['estado']) ? $data['estado'] : 'Activo',
                $data['notas'] ?? null,
            ]);
            $newId = (int)$db->lastInsertId();
            audit_log('operadores', 'create', $newId, [], array_merge($data, ['_via'=>'importacion','_import_run'=>$runId]));
            $db->commit();
            $resultado['creados']++;
            if (!empty($data['dni'])) {
                $dniExistentes[strtoupper(trim($data['dni']))] = true;
            }
            if ($keyValue !== '') {
                $keysEnArchivo[$keyValue] = $fila;
            }
        } catch (Throwable $e) {
            $db->rollBack();
            $resultado['errores']++;
            $resultado['detalle'][] = ['fila'=>$fila,'tipo'=>'error_bd','errores'=>["Error de BD: " . $e->getMessage()],'nombre'=>$data['nombre'] ?? '—'];
        }
    }
    $estado = $resultado['errores'] > 0 && $resultado['creados'] === 0 && $resultado['actualizados'] === 0 ? 'fallido' : 'completado';
    $detalleJson = !empty($resultado['detalle']) ? json_encode($resultado['detalle'], JSON_UNESCAPED_UNICODE) : null;
    $updateStmt = $db->prepare("UPDATE import_runs SET creados=?, errores=?, estado=?, detalle_errores=?, completed_at=NOW() WHERE id=?");
    $updateStmt->execute([$resultado['creados'] + $resultado['actualizados'], $resultado['errores'], $estado, $detalleJson, $runId]);
    cache_invalidate_prefix('dashboard');
    return $resultado;
}
