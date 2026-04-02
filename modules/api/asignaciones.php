<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/odometro.php';
require_once __DIR__ . '/../../includes/cache.php';
require_login();

header('Content-Type: application/json');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Captura un snapshot de componentes (deshabilitado — checklist integrado en vehículos)
 */
function snapshot_componentes(PDO $db, int $asignacionId, int $vehiculoId, string $momento, int $userId, ?array $overrides = null): int {
    return 0;
}

function bloqueo_asignacion(PDO $db, int $vehiculoId): ?array {
    $stmt = $db->prepare("SELECT id FROM asignaciones WHERE vehiculo_id=? AND estado='Activa' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$vehiculoId]);
    $row = $stmt->fetch();
    if ($row) {
        return ['reason' => 'El vehículo ya tiene una asignación activa.', 'blocking_type' => 'asignacion', 'blocking_id' => (int)$row['id']];
    }

    $stmt2 = $db->prepare("SELECT id FROM mantenimientos WHERE vehiculo_id=? AND estado = 'En proceso' AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");
    $stmt2->execute([$vehiculoId]);
    $row2 = $stmt2->fetch();
    if ($row2) {
        return ['reason' => 'El vehículo tiene un mantenimiento activo.', 'blocking_type' => 'mantenimiento', 'blocking_id' => (int)$row2['id']];
    }

    $stmt3 = $db->prepare("SELECT estado FROM vehiculos WHERE id=? LIMIT 1");
    $stmt3->execute([$vehiculoId]);
    $veh = $stmt3->fetch();
    if (!$veh) {
        return ['reason' => 'Vehículo no encontrado.', 'blocking_type' => 'vehiculo', 'blocking_id' => $vehiculoId];
    }

    $estadoVeh = trim((string)($veh['estado'] ?? 'Activo'));
    if (strcasecmp($estadoVeh, 'Activo') !== 0) {
        return ['reason' => "El vehículo no está disponible para asignación (estado: {$estadoVeh}).", 'blocking_type' => 'estado_vehiculo', 'blocking_id' => $vehiculoId];
    }

    return null;
}

try {
    // ─── Sub-endpoint: snapshots de componentes ───
    $subAction = trim($_GET['action'] ?? '');

    // ─── Sub-endpoint: último km de un vehículo ───
    if ($subAction === 'last_km' && $method === 'GET') {
        $vid = (int)($_GET['vehiculo_id'] ?? 0);
        if ($vid <= 0) { http_response_code(400); echo json_encode(['error' => 'vehiculo_id requerido']); exit; }
        $stmt = $db->prepare("SELECT end_km FROM asignaciones WHERE vehiculo_id=? AND estado='Cerrada' AND end_km IS NOT NULL ORDER BY end_at DESC LIMIT 1");
        $stmt->execute([$vid]);
        $lastKm = $stmt->fetchColumn();
        if (!$lastKm) {
            $stmtV = $db->prepare("SELECT km_actual FROM vehiculos WHERE id=?");
            $stmtV->execute([$vid]);
            $lastKm = $stmtV->fetchColumn();
        }
        echo json_encode(['km' => $lastKm ? (float)$lastKm : null]);
        exit;
    }

    // ─── Sub-endpoint: calendario de asignaciones ───
    if ($subAction === 'calendar' && $method === 'GET') {
        $from = trim($_GET['from'] ?? '');
        $to = trim($_GET['to'] ?? '');
        if (!$from) $from = date('Y-m-01');
        if (!$to) $to = date('Y-m-t');
        $vid = (int)($_GET['vehiculo_id'] ?? 0);
        $where = "WHERE a.start_at <= ? AND (a.end_at >= ? OR a.end_at IS NULL)";
        $params = [$to . ' 23:59:59', $from . ' 00:00:00'];
        if ($vid) { $where .= " AND a.vehiculo_id = ?"; $params[] = $vid; }
        $stmt = $db->prepare("SELECT a.id, a.vehiculo_id, a.operador_id, a.start_at, a.end_at, a.estado,
            v.placa, v.marca, o.nombre AS operador_nombre
            FROM asignaciones a
            JOIN vehiculos v ON v.id = a.vehiculo_id
            JOIN operadores o ON o.id = a.operador_id
            $where ORDER BY a.start_at ASC");
        $stmt->execute($params);
        $events = [];
        foreach ($stmt->fetchAll() as $r) {
            $events[] = [
                'id' => (int)$r['id'],
                'title' => $r['placa'] . ' — ' . $r['operador_nombre'],
                'start' => $r['start_at'],
                'end' => $r['end_at'] ?: null,
                'color' => $r['estado'] === 'Activa' ? '#2ed573' : '#8892a4',
                'extendedProps' => [
                    'estado' => $r['estado'],
                    'placa' => $r['placa'],
                    'marca' => $r['marca'],
                    'operador' => $r['operador_nombre'],
                ],
            ];
        }
        echo json_encode(['events' => $events]);
        exit;
    }

    // ─── Sub-endpoint: plantillas de checklist ───
    if ($subAction === 'checklist_plantillas') {
        if ($method === 'GET') {
            try { $stmt = $db->query("SELECT id, nombre, tipo FROM checklist_plantillas WHERE activo = 1 ORDER BY nombre"); echo json_encode(['plantillas' => $stmt->fetchAll()]); }
            catch (Throwable $e) { echo json_encode(['plantillas' => []]); }
            exit;
        }
        if ($method === 'POST') {
            if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            $nombre = trim($d['nombre'] ?? '');
            $tipo = $d['tipo'] ?? 'ambos';
            if (!$nombre) { http_response_code(400); echo json_encode(['error' => 'Nombre requerido']); exit; }
            $db->prepare("INSERT INTO checklist_plantillas (nombre, tipo) VALUES (?,?)")->execute([$nombre, $tipo]);
            $pid = (int)$db->lastInsertId();
            $items = $d['items'] ?? [];
            $orden = 0;
            foreach ($items as $item) {
                $label = trim($item['label'] ?? '');
                if ($label === '') continue;
                $db->prepare("INSERT INTO checklist_plantilla_items (plantilla_id, label, orden, requerido) VALUES (?,?,?,?)")
                    ->execute([$pid, $label, $orden++, (int)($item['requerido'] ?? 0)]);
            }
            audit_log('checklist_plantillas', 'create', $pid, [], $d);
            echo json_encode(['ok' => true, 'id' => $pid]);
            exit;
        }
        if ($method === 'DELETE') {
            if (!can('delete')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos']); exit; }
            $pid = (int)($_GET['id'] ?? 0);
            $db->prepare("UPDATE checklist_plantillas SET activo=0 WHERE id=?")->execute([$pid]);
            echo json_encode(['ok' => true]);
            exit;
        }
    }

    // ─── Sub-endpoint: items de una plantilla ───
    if ($subAction === 'checklist_items' && $method === 'GET') {
        $pid = (int)($_GET['plantilla_id'] ?? 0);
        if ($pid <= 0) { http_response_code(400); echo json_encode(['error' => 'plantilla_id requerido']); exit; }
        $stmt = $db->prepare("SELECT id, label, orden, requerido FROM checklist_plantilla_items WHERE plantilla_id=? ORDER BY orden");
        $stmt->execute([$pid]);
        echo json_encode(['items' => $stmt->fetchAll()]);
        exit;
    }

    // ─── Sub-endpoint: respuestas de checklist ───
    if ($subAction === 'checklist_respuestas') {
        $asigId = (int)($_GET['asignacion_id'] ?? 0);
        if ($method === 'GET' && $asigId > 0) {
            $momento = trim($_GET['momento'] ?? '');
            $where = "WHERE asignacion_id = ?";
            $params = [$asigId];
            if ($momento) { $where .= " AND momento = ?"; $params[] = $momento; }
            try { $stmt = $db->prepare("SELECT * FROM asignacion_checklist_respuestas $where ORDER BY id ASC"); $stmt->execute($params); echo json_encode(['respuestas' => $stmt->fetchAll()]); }
            catch (Throwable $e) { echo json_encode(['respuestas' => []]); }
            exit;
        }
        if ($method === 'POST') {
            if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            $asigId = (int)($d['asignacion_id'] ?? 0);
            $momento = $d['momento'] ?? 'entrega';
            $items = $d['items'] ?? [];
            if ($asigId <= 0 || empty($items)) { http_response_code(400); echo json_encode(['error' => 'Datos incompletos']); exit; }
            $db->prepare("DELETE FROM asignacion_checklist_respuestas WHERE asignacion_id=? AND momento=?")->execute([$asigId, $momento]);
            foreach ($items as $item) {
                $db->prepare("INSERT INTO asignacion_checklist_respuestas (asignacion_id, item_label, momento, checked, observacion) VALUES (?,?,?,?,?)")
                    ->execute([$asigId, trim($item['label'] ?? ''), $momento, (int)($item['checked'] ?? 0), $item['observacion'] ?? null]);
            }
            echo json_encode(['ok' => true]);
            exit;
        }
    }

    // ─── Sub-endpoint: generar link de firma ───
    if ($subAction === 'firma_link' && $method === 'POST') {
        if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
        $d = json_decode(file_get_contents('php://input'), true);
        $asigId = (int)($d['id'] ?? 0);
        $momento = trim($d['momento'] ?? 'retorno');
        if ($asigId <= 0) { http_response_code(400); echo json_encode(['error' => 'ID inválido.']); exit; }
        $token = bin2hex(random_bytes(32));
        $colMap = [
            'entrega'      => ['firma_entrega_token', 'firma_entrega_token_created_at'],
            'retorno'      => ['firma_token', 'firma_token_created_at'],
            'guardia'      => ['firma_guardia_token', 'firma_guardia_token_created_at'],
            'responsable'  => ['firma_responsable_token', 'firma_responsable_token_created_at'],
        ];
        if (!isset($colMap[$momento])) { http_response_code(400); echo json_encode(['error' => 'Momento inválido.']); exit; }
        [$col, $colTs] = $colMap[$momento];
        $db->prepare("UPDATE asignaciones SET {$col} = ?, {$colTs} = NOW() WHERE id = ?")->execute([$token, $asigId]);
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                 . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $link = $baseUrl . '/firma.php?token=' . $token;
        echo json_encode(['ok' => true, 'link' => $link, 'token' => $token]);
        exit;
    }

    // ─── Sub-endpoint: save custom item per vehicle ───
    if ($subAction === 'save_vehicle_item' && $method === 'POST') {
        if (!can('create')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos']); exit; }
        $d = json_decode(file_get_contents('php://input'), true);
        $vid = (int)($d['vehiculo_id'] ?? 0);
        $label = trim($d['label'] ?? '');
        $req = (int)($d['requerido'] ?? 0);
        if (!$vid || !$label) { http_response_code(400); echo json_encode(['error' => 'vehiculo_id y label requeridos']); exit; }
        $db->prepare("INSERT INTO vehicle_checklist_items (vehiculo_id, label, requerido) VALUES (?,?,?)")->execute([$vid, $label, $req]);
        echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId()]);
        exit;
    }

    // ─── Sub-endpoint: get custom items per vehicle ───
    if ($subAction === 'vehicle_items' && $method === 'GET') {
        $vid = (int)($_GET['vehiculo_id'] ?? 0);
        if ($vid <= 0) { echo json_encode(['items' => []]); exit; }
        try {
            $stmt = $db->prepare("SELECT id, label, requerido FROM vehicle_checklist_items WHERE vehiculo_id = ? ORDER BY id ASC");
            $stmt->execute([$vid]);
            echo json_encode(['items' => $stmt->fetchAll()]);
        } catch (Throwable $e) { echo json_encode(['items' => []]); }
        exit;
    }

    // ─── Sub-endpoint: vehicle checklist state (for pre-fill) ───
    if ($subAction === 'vehicle_checklist' && $method === 'GET') {
        $vid = (int)($_GET['vehiculo_id'] ?? 0);
        if ($vid <= 0) { echo json_encode(['checklist' => []]); exit; }
        $stmt = $db->prepare("SELECT tiene_gata, tiene_herramientas, tiene_llanta_repuesto, tiene_bac_flota, revision_ok, tiene_luces, tiene_liquidos, tiene_motor_ok, tiene_parabrisas, tiene_documentacion, tiene_frenos, tiene_espejos, detalles_checklist, km_actual FROM vehiculos WHERE id=? LIMIT 1");
        $stmt->execute([$vid]);
        $v = $stmt->fetch();
        if (!$v) { echo json_encode(['checklist' => []]); exit; }
        echo json_encode(['checklist' => $v]);
        exit;
    }

    if ($subAction === 'snapshots') {
        $asigId = (int)($_GET['asignacion_id'] ?? 0);
        if ($method === 'GET' && $asigId > 0) {
            $momento = trim($_GET['momento'] ?? '');
            $where = "WHERE s.asignacion_id = ?";
            $params = [$asigId];
            if ($momento !== '') { $where .= " AND s.momento = ?"; $params[] = $momento; }
            $stmt = $db->prepare("SELECT s.* FROM assignment_component_snapshots s $where ORDER BY s.momento ASC, s.componente_tipo ASC, s.componente_nombre ASC");
            $stmt->execute($params);
            echo json_encode(['snapshots' => $stmt->fetchAll()]);
            exit;
        }
        // POST manual de snapshot retorno con observaciones
        if ($method === 'POST' && $asigId > 0) {
            if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            $asig = $db->prepare("SELECT * FROM asignaciones WHERE id = ? LIMIT 1");
            $asig->execute([$asigId]);
            $asigRow = $asig->fetch();
            if (!$asigRow) { http_response_code(404); echo json_encode(['error' => 'Asignación no encontrada.']); exit; }
            $cnt = snapshot_componentes($db, $asigId, (int)$asigRow['vehiculo_id'], 'retorno', (int)($_SESSION['user_id'] ?? 0));
            audit_log('assignment_snapshots', 'retorno_manual', $asigId, [], []);
            echo json_encode(['ok' => true, 'snapshot_count' => $cnt]);
            exit;
        }
        http_response_code(400);
        echo json_encode(['error' => 'Parámetros inválidos para snapshots.']);
        exit;
    }

    switch ($method) {
        case 'GET':
            $q = '%' . trim($_GET['q'] ?? '') . '%';
            $vid = (int)($_GET['vehiculo_id'] ?? 0);
            $estado = trim($_GET['estado'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per = min(100, max(5, (int)($_GET['per'] ?? 25)));
            $off = ($page - 1) * $per;

            $where = "WHERE a.deleted_at IS NULL AND (v.placa LIKE ? OR v.marca LIKE ? OR o.nombre LIKE ? OR a.estado LIKE ? )";
            $params = [$q, $q, $q, $q];
            if ($vid) { $where .= " AND a.vehiculo_id=?"; $params[] = $vid; }
            if ($estado !== '') { $where .= " AND a.estado=?"; $params[] = $estado; }

            $total = $db->prepare("SELECT COUNT(*) FROM asignaciones a
                JOIN vehiculos v ON v.id=a.vehiculo_id
                JOIN operadores o ON o.id=a.operador_id
                $where");
            $total->execute($params);

            $listParams = $params;
            $listParams[] = $per;
            $listParams[] = $off;
            $stmt = $db->prepare("SELECT a.*, v.placa, v.marca, v.modelo, o.nombre AS operador_nombre
                FROM asignaciones a
                JOIN vehiculos v ON v.id=a.vehiculo_id
                JOIN operadores o ON o.id=a.operador_id
                $where
                ORDER BY a.id DESC
                LIMIT ? OFFSET ?");
            $stmt->execute($listParams);
            echo json_encode(['total' => (int)$total->fetchColumn(), 'rows' => $stmt->fetchAll()]);
            break;

        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear asignaciones.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $vehiculoId = (int)($d['vehiculo_id'] ?? 0);
            $operadorId = (int)($d['operador_id'] ?? 0);
            $startKm = isset($d['start_km']) && $d['start_km'] !== '' ? (float)$d['start_km'] : null;
            $overrideReason = trim((string)($d['override_reason'] ?? ''));
            $allowOverride = can('manage_permissions') && $overrideReason !== '';

            if (!$vehiculoId || !$operadorId) {
                http_response_code(400);
                echo json_encode(['error' => 'Vehículo y operador son obligatorios.']);
                break;
            }

            $opStmt = $db->prepare("SELECT estado FROM operadores WHERE id=? LIMIT 1");
            $opStmt->execute([$operadorId]);
            $op = $opStmt->fetch();
            if (!$op || ($op['estado'] ?? '') !== 'Activo') {
                http_response_code(400);
                echo json_encode(['error' => 'El operador está inactivo/suspendido o no existe.']);
                break;
            }

            // Si el mismo operador ya tiene este vehículo asignado, actualizar registro existente
            $existingStmt = $db->prepare("SELECT id FROM asignaciones WHERE vehiculo_id=? AND operador_id=? AND estado='Activa' LIMIT 1");
            $existingStmt->execute([$vehiculoId, $operadorId]);
            $existingAsig = $existingStmt->fetch();
            if ($existingAsig) {
                // Actualizar la asignación existente (mismo operador, mismo vehículo)
                $updStmt = $db->prepare("UPDATE asignaciones SET start_km=COALESCE(?,start_km), start_notes=COALESCE(?,start_notes), start_combustible=COALESCE(?,start_combustible),
                    checklist_gata=?,checklist_herramientas=?,checklist_llanta=?,checklist_bac=?,checklist_revision=?,
                    checklist_luces=?,checklist_liquidos=?,checklist_motor=?,checklist_parabrisas=?,checklist_documentacion=?,checklist_frenos=?,checklist_espejos=?,
                    checklist_detalles=? WHERE id=?");
                $updStmt->execute([
                    $startKm, $d['start_notes'] ?: null, trim((string)($d['start_combustible'] ?? '')) ?: null,
                    (int)($d['checklist_gata'] ?? 0), (int)($d['checklist_herramientas'] ?? 0),
                    (int)($d['checklist_llanta'] ?? 0), (int)($d['checklist_bac'] ?? 0),
                    (int)($d['checklist_revision'] ?? 0), (int)($d['checklist_luces'] ?? 0),
                    (int)($d['checklist_liquidos'] ?? 0), (int)($d['checklist_motor'] ?? 0),
                    (int)($d['checklist_parabrisas'] ?? 0), (int)($d['checklist_documentacion'] ?? 0),
                    (int)($d['checklist_frenos'] ?? 0), (int)($d['checklist_espejos'] ?? 0),
                    $d['checklist_detalles'] ?: null, (int)$existingAsig['id']
                ]);
                // Sync checklist back to vehicle
                $db->prepare("UPDATE vehiculos SET tiene_gata=?,tiene_herramientas=?,tiene_llanta_repuesto=?,tiene_bac_flota=?,revision_ok=?,tiene_luces=?,tiene_liquidos=?,tiene_motor_ok=?,tiene_parabrisas=?,tiene_documentacion=?,tiene_frenos=?,tiene_espejos=?,detalles_checklist=? WHERE id=?")->execute([
                    (int)($d['checklist_gata'] ?? 0), (int)($d['checklist_herramientas'] ?? 0),
                    (int)($d['checklist_llanta'] ?? 0), (int)($d['checklist_bac'] ?? 0),
                    (int)($d['checklist_revision'] ?? 0), (int)($d['checklist_luces'] ?? 0),
                    (int)($d['checklist_liquidos'] ?? 0), (int)($d['checklist_motor'] ?? 0),
                    (int)($d['checklist_parabrisas'] ?? 0), (int)($d['checklist_documentacion'] ?? 0),
                    (int)($d['checklist_frenos'] ?? 0), (int)($d['checklist_espejos'] ?? 0),
                    $d['checklist_detalles'] ?: null, $vehiculoId
                ]);
                echo json_encode(['ok' => true, 'id' => (int)$existingAsig['id'], 'updated' => true, 'message' => 'Asignación existente actualizada']);
                break;
            }

            $bloqueo = bloqueo_asignacion($db, $vehiculoId);
            if ($bloqueo && !$allowOverride) {
                http_response_code(409);
                echo json_encode(['error' => $bloqueo['reason'], 'reason' => $bloqueo['reason'], 'blocking_type' => $bloqueo['blocking_type'], 'blocking_id' => $bloqueo['blocking_id']]);
                break;
            }

            try {
                odometro_validar_km($db, $vehiculoId, $startKm, $allowOverride, $overrideReason ?: null);
            } catch (RuntimeException $re) {
                http_response_code(400);
                echo json_encode(['error' => $re->getMessage()]);
                break;
            }

            $db->beginTransaction();
            try {
                $firmaEntregaTipoRaw = trim((string)($d['firma_entrega_tipo'] ?? 'ninguna'));
                $firmaEntregaData = ($firmaEntregaTipoRaw === 'digital' && !empty($d['firma_entrega_data'])) ? $d['firma_entrega_data'] : null;
                // Only mark as signed if we have actual data or it's physical
                $firmaEntregaTipo = ($firmaEntregaData || $firmaEntregaTipoRaw === 'fisica') ? $firmaEntregaTipoRaw : 'ninguna';
                $firmaEntregaFecha = ($firmaEntregaTipo !== 'ninguna') ? date('Y-m-d H:i:s') : null;
                $firmaEntregaIp = ($firmaEntregaTipo !== 'ninguna') ? ($_SERVER['REMOTE_ADDR'] ?? null) : null;

                $stmt = $db->prepare("INSERT INTO asignaciones (vehiculo_id,operador_id,start_at,start_km,start_notes,start_combustible,estado,override_reason,created_by,checklist_gata,checklist_herramientas,checklist_llanta,checklist_bac,checklist_revision,checklist_luces,checklist_liquidos,checklist_motor,checklist_parabrisas,checklist_documentacion,checklist_frenos,checklist_espejos,checklist_detalles,firma_entrega_tipo,firma_entrega_data,firma_entrega_fecha,firma_entrega_ip,destino,hora_salida,hora_regreso,observaciones_pase)
                    VALUES (?,?,?,?,?,?,? ,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $vehiculoId,
                    $operadorId,
                    $d['start_at'] ?: date('Y-m-d H:i:s'),
                    $startKm,
                    $d['start_notes'] ?: null,
                    trim((string)($d['start_combustible'] ?? '')) ?: null,
                    'Activa',
                    $allowOverride ? $overrideReason : null,
                    (int)($_SESSION['user_id'] ?? 0),
                    (int)($d['checklist_gata'] ?? 0),
                    (int)($d['checklist_herramientas'] ?? 0),
                    (int)($d['checklist_llanta'] ?? 0),
                    (int)($d['checklist_bac'] ?? 0),
                    (int)($d['checklist_revision'] ?? 0),
                    (int)($d['checklist_luces'] ?? 0),
                    (int)($d['checklist_liquidos'] ?? 0),
                    (int)($d['checklist_motor'] ?? 0),
                    (int)($d['checklist_parabrisas'] ?? 0),
                    (int)($d['checklist_documentacion'] ?? 0),
                    (int)($d['checklist_frenos'] ?? 0),
                    (int)($d['checklist_espejos'] ?? 0),
                    $d['checklist_detalles'] ?: null,
                    $firmaEntregaTipo,
                    $firmaEntregaData,
                    $firmaEntregaFecha,
                    $firmaEntregaIp,
                    trim((string)($d['destino'] ?? '')) ?: null,
                    trim((string)($d['hora_salida'] ?? '')) ?: null,
                    trim((string)($d['hora_regreso'] ?? '')) ?: null,
                    trim((string)($d['observaciones_pase'] ?? '')) ?: null,
                ]);

                $id = (int)$db->lastInsertId();
                if ($startKm) {
                    odometro_registrar($db, $vehiculoId, $startKm, 'assignment_start', (int)($_SESSION['user_id'] ?? 0));
                }
                // Snapshot de componentes al momento de entrega
                snapshot_componentes($db, $id, $vehiculoId, 'entrega', (int)($_SESSION['user_id'] ?? 0));

                // Sync checklist back to vehicle
                $db->prepare("UPDATE vehiculos SET tiene_gata=?,tiene_herramientas=?,tiene_llanta_repuesto=?,tiene_bac_flota=?,revision_ok=?,tiene_luces=?,tiene_liquidos=?,tiene_motor_ok=?,tiene_parabrisas=?,tiene_documentacion=?,tiene_frenos=?,tiene_espejos=?,detalles_checklist=? WHERE id=?")->execute([
                    (int)($d['checklist_gata'] ?? 0), (int)($d['checklist_herramientas'] ?? 0),
                    (int)($d['checklist_llanta'] ?? 0), (int)($d['checklist_bac'] ?? 0),
                    (int)($d['checklist_revision'] ?? 0), (int)($d['checklist_luces'] ?? 0),
                    (int)($d['checklist_liquidos'] ?? 0), (int)($d['checklist_motor'] ?? 0),
                    (int)($d['checklist_parabrisas'] ?? 0), (int)($d['checklist_documentacion'] ?? 0),
                    (int)($d['checklist_frenos'] ?? 0), (int)($d['checklist_espejos'] ?? 0),
                    $d['checklist_detalles'] ?: null, $vehiculoId
                ]);

                // Auto-clear old vehicle photos on new assignment (will be replaced with new ones)
                $db->prepare("UPDATE attachments SET deleted_at=NOW() WHERE entidad='vehiculos' AND entidad_id=? AND deleted_at IS NULL")->execute([$vehiculoId]);

                $db->commit();
            } catch (Throwable $txe) {
                $db->rollBack();
                throw $txe;
            }

            if ($allowOverride) {
                audit_log('asignaciones', 'override_used', $id, [], ['reason' => $overrideReason, 'bloqueo' => $bloqueo]);
            }

            audit_log('asignaciones', 'create', $id, [], $d);
            cache_invalidate_prefix('dashboard');
            echo json_encode(['ok' => true, 'id' => $id]);
            break;

        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para actualizar asignaciones.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $id = (int)($d['id'] ?? 0);
            $action = trim((string)($d['action'] ?? 'close'));
            if ($id <= 0 || $action !== 'close') {
                http_response_code(400);
                echo json_encode(['error' => 'Petición inválida.']);
                break;
            }

            $prevStmt = $db->prepare("SELECT * FROM asignaciones WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch();
            if (!$prev) {
                http_response_code(404);
                echo json_encode(['error' => 'Asignación no encontrada.']);
                break;
            }
            if (($prev['estado'] ?? '') !== 'Activa') {
                http_response_code(400);
                echo json_encode(['error' => 'La asignación ya está cerrada.']);
                break;
            }

            $vehiculoId = (int)$prev['vehiculo_id'];
            $endKm = isset($d['end_km']) && $d['end_km'] !== '' ? (float)$d['end_km'] : null;
            if ($endKm === null) {
                http_response_code(400);
                echo json_encode(['error' => 'El km final es obligatorio al cerrar.']);
                break;
            }

            $overrideReason = trim((string)($d['override_reason'] ?? ''));
            $allowOverride = can('manage_permissions') && $overrideReason !== '';
            try {
                odometro_validar_km($db, $vehiculoId, $endKm, $allowOverride, $overrideReason ?: null);
            } catch (RuntimeException $re) {
                http_response_code(400);
                echo json_encode(['error' => $re->getMessage()]);
                break;
            }

            $db->beginTransaction();
            try {
                // Check if firma already exists (e.g. signed via external link)
                $existingFirma = $db->prepare("SELECT firma_tipo, firma_data, firma_token FROM asignaciones WHERE id = ?");
                $existingFirma->execute([$id]);
                $ef = $existingFirma->fetch();

                $firmaTipo = $d['firma_tipo'] ?? 'ninguna';
                if (!in_array($firmaTipo, ['digital', 'fisica', 'ninguna'], true)) {
                    $firmaTipo = 'ninguna';
                }
                $firmaData = $d['firma_data'] ?? null;
                $firmaToken = null;

                // If user says 'ninguna' but a firma already exists from external link, preserve it
                if ($firmaTipo === 'ninguna' && !empty($ef['firma_data'])) {
                    $firmaTipo = $ef['firma_tipo'] ?: 'digital';
                    $firmaData = $ef['firma_data'];
                    $firmaToken = $ef['firma_token'];
                } elseif ($firmaTipo === 'digital' && !$firmaData) {
                    $firmaToken = bin2hex(random_bytes(32));
                }

                $stmt = $db->prepare("UPDATE asignaciones
                    SET end_at=?, end_km=?, end_notes=?, end_combustible=COALESCE(?,end_combustible), estado='Cerrada', override_reason=COALESCE(?,override_reason), closed_by=?,
                        end_checklist_gata=?, end_checklist_herramientas=?, end_checklist_llanta=?, end_checklist_bac=?, end_checklist_revision=?,
                        end_checklist_luces=?, end_checklist_liquidos=?, end_checklist_motor=?, end_checklist_parabrisas=?, end_checklist_documentacion=?, end_checklist_frenos=?, end_checklist_espejos=?,
                        end_checklist_detalles=?,
                        firma_tipo=?, firma_data=COALESCE(?,firma_data), firma_token=COALESCE(?,firma_token), firma_fecha=COALESCE(firma_fecha,IF(?='ninguna',NULL,NOW())), firma_ip=COALESCE(firma_ip,?),
                        destino=COALESCE(?,destino), hora_salida=COALESCE(?,hora_salida), hora_regreso=COALESCE(?,hora_regreso), observaciones_pase=COALESCE(?,observaciones_pase)
                    WHERE id=?");
                $stmt->execute([
                    $d['end_at'] ?: date('Y-m-d H:i:s'),
                    $endKm,
                    $d['end_notes'] ?: null,
                    trim((string)($d['end_combustible'] ?? '')) ?: null,
                    $allowOverride ? $overrideReason : null,
                    (int)($_SESSION['user_id'] ?? 0),
                    (int)($d['end_checklist_gata'] ?? 0),
                    (int)($d['end_checklist_herramientas'] ?? 0),
                    (int)($d['end_checklist_llanta'] ?? 0),
                    (int)($d['end_checklist_bac'] ?? 0),
                    (int)($d['end_checklist_revision'] ?? 0),
                    (int)($d['end_checklist_luces'] ?? 0),
                    (int)($d['end_checklist_liquidos'] ?? 0),
                    (int)($d['end_checklist_motor'] ?? 0),
                    (int)($d['end_checklist_parabrisas'] ?? 0),
                    (int)($d['end_checklist_documentacion'] ?? 0),
                    (int)($d['end_checklist_frenos'] ?? 0),
                    (int)($d['end_checklist_espejos'] ?? 0),
                    $d['end_checklist_detalles'] ?: null,
                    $firmaTipo,
                    $firmaData ?: null,
                    $firmaToken,
                    $firmaTipo,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    trim((string)($d['destino'] ?? '')) ?: null,
                    trim((string)($d['hora_salida'] ?? '')) ?: null,
                    trim((string)($d['hora_regreso'] ?? '')) ?: null,
                    trim((string)($d['observaciones_pase'] ?? '')) ?: null,
                    $id
                ]);

                odometro_registrar($db, $vehiculoId, $endKm, 'assignment_end', (int)($_SESSION['user_id'] ?? 0));
                snapshot_componentes($db, $id, $vehiculoId, 'retorno', (int)($_SESSION['user_id'] ?? 0));
                $db->commit();
            } catch (Throwable $txe) {
                $db->rollBack();
                throw $txe;
            }

            if ($allowOverride) {
                audit_log('asignaciones', 'override_used', $id, ['km_anterior' => $prev['end_km'] ?? null], ['km_nuevo' => $endKm], ['reason' => $overrideReason]);
            }

            audit_log('asignaciones', 'close', $id, $prev, $d);
            cache_invalidate_prefix('dashboard');
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar asignaciones.']);
                break;
            }
            $id = (int)($_GET['id'] ?? 0);
            $prevStmt = $db->prepare("SELECT * FROM asignaciones WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];
            $db->prepare("UPDATE asignaciones SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            audit_log('asignaciones', 'delete', $id, $prev, []);
            cache_invalidate_prefix('dashboard');
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => safe_error_msg($e)]);
}
