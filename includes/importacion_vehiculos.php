<?php
/**
 * Servicio de Importación de Vehículos V1
 *
 * Maneja la lectura de archivos y la importación masiva de vehículos
 * reutilizando las mismas reglas del módulo manual.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/odometro.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/xlsx_reader.php';

/**
 * Extensiones de archivo permitidas para importación
 */
function importacion_extensiones_permitidas(): array {
    return ['csv', 'xlsx'];
}

/**
 * Tamaño máximo de archivo (10 MB)
 */
function importacion_max_size(): int {
    return 10 * 1024 * 1024;
}

/**
 * Campos del sistema disponibles para mapeo
 * Retorna [campo => [label, required, type]]
 */
function importacion_campos_destino(): array {
    return [
        'placa'              => ['label' => 'Placa',              'required' => true,  'type' => 'text'],
        'marca'              => ['label' => 'Marca',              'required' => true,  'type' => 'text'],
        'modelo'             => ['label' => 'Modelo',             'required' => true,  'type' => 'text'],
        'anio'               => ['label' => 'Año',                'required' => false, 'type' => 'integer'],
        'tipo'               => ['label' => 'Tipo',               'required' => false, 'type' => 'text'],
        'combustible'        => ['label' => 'Combustible',        'required' => false, 'type' => 'text'],
        'km_actual'          => ['label' => 'Kilómetros',         'required' => false, 'type' => 'decimal'],
        'color'              => ['label' => 'Color',              'required' => false, 'type' => 'text'],
        'vin'                => ['label' => 'VIN',                'required' => false, 'type' => 'text'],
        'estado'             => ['label' => 'Estado',             'required' => false, 'type' => 'text'],
        'venc_seguro'        => ['label' => 'Venc. Seguro',       'required' => false, 'type' => 'date'],
        'notas'              => ['label' => 'Notas',              'required' => false, 'type' => 'text'],
        'sucursal_id'        => ['label' => 'Sucursal (ID)',      'required' => false, 'type' => 'integer'],
        'costo_adquisicion'  => ['label' => 'Costo Adquisición',  'required' => false, 'type' => 'decimal'],
        'aseguradora'        => ['label' => 'Aseguradora',        'required' => false, 'type' => 'text'],
        'poliza_numero'      => ['label' => 'Póliza Número',      'required' => false, 'type' => 'text'],
    ];
}

/**
 * Sube y valida el archivo de importación
 * @return array ['path' => string, 'extension' => string, 'original_name' => string]
 */
function importacion_subir_archivo(array $file): array {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'Archivo demasiado grande (excede límite de PHP)',
            UPLOAD_ERR_FORM_SIZE  => 'Archivo demasiado grande (excede límite del formulario)',
            UPLOAD_ERR_PARTIAL    => 'Archivo subido parcialmente',
            UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Error de servidor: sin directorio temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error de servidor: no se pudo escribir',
        ];
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        throw new RuntimeException($errors[$code] ?? 'Error al subir archivo');
    }

    if ($file['size'] > importacion_max_size()) {
        throw new RuntimeException('Archivo demasiado grande. Máximo: 10 MB');
    }

    $originalName = basename($file['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($ext, importacion_extensiones_permitidas())) {
        throw new RuntimeException('Formato no soportado. Permitidos: ' . implode(', ', importacion_extensiones_permitidas()));
    }

    // Mover a directorio temporal de importaciones
    $dir = __DIR__ . '/../uploads/importaciones';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $safeName = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $destPath = $dir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Error al guardar el archivo');
    }

    return [
        'path'          => $destPath,
        'extension'     => $ext,
        'original_name' => $originalName,
    ];
}

/**
 * Lee encabezados y datos de un archivo
 * @return array ['headers' => string[], 'rows' => array[], 'sheets' => string[]|null, 'total_rows' => int]
 */
function importacion_leer_archivo(string $path, string $extension, int $sheetIndex = 0): array {
    if ($extension === 'csv') {
        return importacion_leer_csv($path);
    }

    if ($extension === 'xlsx') {
        return importacion_leer_xlsx($path, $sheetIndex);
    }

    throw new RuntimeException("Formato no soportado: $extension");
}

/**
 * Lee un archivo CSV
 */
function importacion_leer_csv(string $path): array {
    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException('No se pudo abrir el archivo CSV');
    }

    // Detectar BOM UTF-8 y eliminarlo
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    // Auto-detectar separador (coma, punto y coma, tabulador)
    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        throw new RuntimeException('Archivo CSV vacío');
    }
    rewind($handle);
    // Saltar BOM si existe
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $separator = ',';
    if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
        $separator = ';';
    } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
        $separator = "\t";
    }

    // Leer encabezados
    $headers = fgetcsv($handle, 0, $separator);
    if ($headers === false || empty($headers)) {
        fclose($handle);
        throw new RuntimeException('No se pudieron leer los encabezados del CSV');
    }

    // Limpiar encabezados
    $headers = array_map(function($h) {
        return trim(preg_replace('/[\x00-\x1F\x7F]/', '', $h));
    }, $headers);

    // Leer datos (máximo 2000 filas para V1)
    $rows = [];
    $maxRows = 2000;
    while (($row = fgetcsv($handle, 0, $separator)) !== false && count($rows) < $maxRows) {
        // Ignorar filas completamente vacías
        $nonEmpty = array_filter($row, fn($v) => trim($v) !== '');
        if (empty($nonEmpty)) continue;

        // Normalizar encoding a UTF-8
        $row = array_map(function($val) {
            $val = trim($val);
            if (!mb_detect_encoding($val, 'UTF-8', true)) {
                $val = mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
            }
            return $val;
        }, $row);

        // Asegurar que la fila tenga mismo número de columnas que headers
        while (count($row) < count($headers)) {
            $row[] = '';
        }
        $row = array_slice($row, 0, count($headers));

        $rows[] = $row;
    }

    fclose($handle);

    return [
        'headers'    => $headers,
        'rows'       => $rows,
        'sheets'     => null,
        'total_rows' => count($rows),
    ];
}

/**
 * Lee un archivo XLSX
 */
function importacion_leer_xlsx(string $path, int $sheetIndex = 0): array {
    $reader = new SimpleXlsxReader($path);
    $sheets = $reader->getSheetNames();

    $allRows = $reader->getRows($sheetIndex);
    if (empty($allRows)) {
        throw new RuntimeException('La hoja seleccionada está vacía');
    }

    // Primera fila = encabezados
    $headers = array_map(function($h) {
        return trim(preg_replace('/[\x00-\x1F\x7F]/', '', (string)$h));
    }, $allRows[0]);

    // Resto = datos (máximo 2000 filas)
    $rows = [];
    $maxRows = 2000;
    for ($i = 1; $i < count($allRows) && count($rows) < $maxRows; $i++) {
        $row = $allRows[$i];
        // Ignorar filas vacías
        $nonEmpty = array_filter($row, fn($v) => trim((string)$v) !== '');
        if (empty($nonEmpty)) continue;

        // Normalizar valores
        $row = array_map(fn($v) => trim((string)$v), $row);

        // Ajustar columnas
        while (count($row) < count($headers)) {
            $row[] = '';
        }
        $row = array_slice($row, 0, count($headers));

        $rows[] = $row;
    }

    return [
        'headers'    => $headers,
        'rows'       => $rows,
        'sheets'     => $sheets,
        'total_rows' => count($rows),
    ];
}

/**
 * Valida una fila según el mapeo de columnas
 * @return array ['valid' => bool, 'data' => array, 'errors' => string[]]
 */
function importacion_validar_fila(array $row, array $headers, array $mapping, array $camposDestino): array {
    $data = [];
    $errors = [];

    // Construir datos según mapeo
    foreach ($mapping as $headerIdx => $campoDestino) {
        if ($campoDestino === '' || $campoDestino === '__ignorar__') continue;
        $valor = $row[$headerIdx] ?? '';
        $data[$campoDestino] = $valor;
    }

    // Validar campos obligatorios
    foreach ($camposDestino as $campo => $info) {
        if (!$info['required']) continue;
        $val = trim($data[$campo] ?? '');
        if ($val === '') {
            $errors[] = "Campo '{$info['label']}' es obligatorio";
        }
    }

    // Validar placa: normalizar
    if (isset($data['placa']) && $data['placa'] !== '') {
        $data['placa'] = strtoupper(preg_replace('/\s+/', '', $data['placa']));
    }

    // Validar tipos de datos
    if (isset($data['anio']) && $data['anio'] !== '') {
        $anio = filter_var($data['anio'], FILTER_VALIDATE_INT);
        if ($anio === false || $anio < 1900 || $anio > (int)date('Y') + 2) {
            $errors[] = "Año inválido: '{$data['anio']}'";
        } else {
            $data['anio'] = $anio;
        }
    }

    if (isset($data['km_actual']) && $data['km_actual'] !== '') {
        // Limpiar formato numérico (comas, espacios)
        $km = str_replace([',', ' '], ['.', ''], $data['km_actual']);
        if (!is_numeric($km) || (float)$km < 0) {
            $errors[] = "Kilómetros inválido: '{$data['km_actual']}'";
        } else {
            $data['km_actual'] = (float)$km;
        }
    }

    if (isset($data['costo_adquisicion']) && $data['costo_adquisicion'] !== '') {
        $costo = str_replace([',', ' ', '$'], ['.', '', ''], $data['costo_adquisicion']);
        if (!is_numeric($costo)) {
            $errors[] = "Costo adquisición inválido: '{$data['costo_adquisicion']}'";
        } else {
            $data['costo_adquisicion'] = (float)$costo;
        }
    }

    if (isset($data['venc_seguro']) && $data['venc_seguro'] !== '') {
        $fecha = importacion_parsear_fecha($data['venc_seguro']);
        if ($fecha === null) {
            $errors[] = "Fecha venc. seguro inválida: '{$data['venc_seguro']}'";
        } else {
            $data['venc_seguro'] = $fecha;
        }
    }

    if (isset($data['sucursal_id']) && $data['sucursal_id'] !== '') {
        $sid = filter_var($data['sucursal_id'], FILTER_VALIDATE_INT);
        if ($sid === false || $sid <= 0) {
            $errors[] = "Sucursal ID inválido: '{$data['sucursal_id']}'";
        } else {
            $data['sucursal_id'] = $sid;
        }
    }

    // Validar estado si viene informado
    if (isset($data['estado']) && $data['estado'] !== '') {
        $estadosValidos = ['Activo', 'En mantenimiento', 'Fuera de servicio'];
        if (!in_array($data['estado'], $estadosValidos)) {
            $errors[] = "Estado inválido: '{$data['estado']}'. Válidos: " . implode(', ', $estadosValidos);
        }
    }

    return [
        'valid'  => empty($errors),
        'data'   => $data,
        'errors' => $errors,
    ];
}

/**
 * Intenta parsear diversos formatos de fecha al formato YYYY-MM-DD
 */
function importacion_parsear_fecha(string $valor): ?string {
    $valor = trim($valor);
    if ($valor === '') return null;

    // ISO: YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        $ts = strtotime($valor);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    // DD/MM/YYYY o DD-MM-YYYY
    if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})$#', $valor, $m)) {
        $ts = mktime(0, 0, 0, (int)$m[2], (int)$m[1], (int)$m[3]);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    // MM/DD/YYYY (fallback)
    if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})$#', $valor, $m)) {
        if ((int)$m[1] > 12) {
            // Día primero (DD/MM/YYYY ya cubierto arriba)
            return null;
        }
    }

    // Excel serial date (número entero grande)
    if (is_numeric($valor) && (int)$valor > 40000 && (int)$valor < 60000) {
        $unixDate = ((int)$valor - 25569) * 86400;
        return date('Y-m-d', $unixDate);
    }

    // Último intento con strtotime
    $ts = strtotime($valor);
    if ($ts !== false && $ts > 0) {
        return date('Y-m-d', $ts);
    }

    return null;
}

/**
 * Ejecuta la importación masiva de vehículos
 *
 * @param array $rows Filas de datos crudos del archivo
 * @param array $headers Encabezados del archivo
 * @param array $mapping Mapeo headerIndex => campoDestino
 * @param int $userId ID del usuario que importa
 * @param string $fileName Nombre original del archivo
 * @return array Resultado con estadísticas y errores
 */
function importacion_ejecutar(array $rows, array $headers, array $mapping, int $userId, string $fileName): array {
    $db = getDB();
    $campos = importacion_campos_destino();

    // Extender timeout para importaciones grandes
    set_time_limit(300);

    $resultado = [
        'total'        => count($rows),
        'creados'      => 0,
        'errores'      => 0,
        'detalle'      => [],
        'import_run_id' => null,
    ];

    // Crear registro de importación
    $stmt = $db->prepare("INSERT INTO import_runs (tipo_importacion, nombre_archivo, usuario_id, total_filas, estado) VALUES ('vehiculos', ?, ?, ?, 'procesando')");
    $stmt->execute([$fileName, $userId, count($rows)]);
    $runId = (int)$db->lastInsertId();
    $resultado['import_run_id'] = $runId;

    // Pre-cargar placas existentes para detección de duplicados
    $placasExistentes = [];
    $placasStmt = $db->query("SELECT UPPER(placa) AS placa FROM vehiculos WHERE deleted_at IS NULL");
    foreach ($placasStmt->fetchAll() as $p) {
        $placasExistentes[$p['placa']] = true;
    }

    // Control de duplicados dentro del mismo archivo
    $placasEnArchivo = [];

    foreach ($rows as $i => $row) {
        $fila = $i + 2; // +1 por header, +1 por base-1

        // Validar fila
        $validacion = importacion_validar_fila($row, $headers, $mapping, $campos);

        if (!$validacion['valid']) {
            $resultado['errores']++;
            $resultado['detalle'][] = [
                'fila'    => $fila,
                'tipo'    => 'validacion',
                'errores' => $validacion['errors'],
                'placa'   => $validacion['data']['placa'] ?? '—',
            ];
            continue;
        }

        $data = $validacion['data'];
        $placa = $data['placa'] ?? '';

        // Verificar duplicado en BD
        if (isset($placasExistentes[$placa])) {
            $resultado['errores']++;
            $resultado['detalle'][] = [
                'fila'    => $fila,
                'tipo'    => 'duplicado_bd',
                'errores' => ["Placa '$placa' ya existe en el sistema"],
                'placa'   => $placa,
            ];
            continue;
        }

        // Verificar duplicado dentro del archivo
        if (isset($placasEnArchivo[$placa])) {
            $resultado['errores']++;
            $resultado['detalle'][] = [
                'fila'    => $fila,
                'tipo'    => 'duplicado_archivo',
                'errores' => ["Placa '$placa' duplicada en el archivo (primera vez en fila {$placasEnArchivo[$placa]})"],
                'placa'   => $placa,
            ];
            continue;
        }

        // Insertar vehículo
        try {
            $db->beginTransaction();

            $insertStmt = $db->prepare("INSERT INTO vehiculos
                (placa, marca, modelo, anio, tipo, combustible, km_actual, color, vin, estado, venc_seguro, notas, sucursal_id, costo_adquisicion, aseguradora, poliza_numero)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $insertStmt->execute([
                $placa,
                $data['marca']             ?? '',
                $data['modelo']            ?? '',
                !empty($data['anio'])               ? (int)$data['anio']   : null,
                !empty($data['tipo'])               ? $data['tipo']        : 'Automovil',
                !empty($data['combustible'])        ? $data['combustible'] : 'Gasolina',
                !empty($data['km_actual'])           ? (float)$data['km_actual'] : 0,
                !empty($data['color'])              ? $data['color']       : null,
                !empty($data['vin'])                ? $data['vin']         : null,
                !empty($data['estado'])             ? $data['estado']      : 'Activo',
                !empty($data['venc_seguro'])        ? $data['venc_seguro'] : null,
                !empty($data['notas'])              ? $data['notas']       : null,
                !empty($data['sucursal_id'])        ? (int)$data['sucursal_id'] : null,
                isset($data['costo_adquisicion']) && $data['costo_adquisicion'] !== '' ? (float)$data['costo_adquisicion'] : null,
                !empty($data['aseguradora'])        ? $data['aseguradora'] : null,
                !empty($data['poliza_numero'])      ? $data['poliza_numero'] : null,
            ]);

            $newId = (int)$db->lastInsertId();

            // Registrar odómetro si hay km
            $km = (float)($data['km_actual'] ?? 0);
            if ($km > 0) {
                odometro_registrar($db, $newId, $km, 'importacion', $userId);
            }

            // Auditoría
            audit_log('vehiculos', 'create', $newId, [], array_merge($data, ['_via' => 'importacion', '_import_run' => $runId]));

            $db->commit();

            $resultado['creados']++;
            $placasExistentes[$placa] = true;
            $placasEnArchivo[$placa] = $fila;

        } catch (Throwable $e) {
            $db->rollBack();

            $mensaje = str_contains($e->getMessage(), 'Duplicate')
                ? "Placa '$placa' ya existe en el sistema"
                : "Error de BD: " . $e->getMessage();

            $resultado['errores']++;
            $resultado['detalle'][] = [
                'fila'    => $fila,
                'tipo'    => 'error_bd',
                'errores' => [$mensaje],
                'placa'   => $placa,
            ];
        }
    }

    // Actualizar registro de importación
    $estado = $resultado['errores'] > 0 && $resultado['creados'] === 0 ? 'fallido' : 'completado';
    $detalleJson = !empty($resultado['detalle']) ? json_encode($resultado['detalle'], JSON_UNESCAPED_UNICODE) : null;

    $updateStmt = $db->prepare("UPDATE import_runs SET creados=?, errores=?, estado=?, detalle_errores=?, completed_at=NOW() WHERE id=?");
    $updateStmt->execute([$resultado['creados'], $resultado['errores'], $estado, $detalleJson, $runId]);

    // Invalidar caché
    cache_invalidate_prefix('dashboard');

    return $resultado;
}

/**
 * Limpia archivo temporal de importación
 */
function importacion_limpiar_archivo(string $path): void {
    if (file_exists($path) && str_contains($path, '/uploads/importaciones/')) {
        @unlink($path);
    }
}
