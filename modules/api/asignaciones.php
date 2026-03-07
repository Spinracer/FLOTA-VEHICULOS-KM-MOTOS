<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/odometro.php';
require_login();

header('Content-Type: application/json');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Captura un snapshot de todos los componentes asignados al vehículo
 */
function snapshot_componentes(PDO $db, int $asignacionId, int $vehiculoId, string $momento, int $userId, ?array $overrides = null): int {
    $stmt = $db->prepare("
        SELECT vc.component_id, c.nombre, c.tipo, vc.estado, vc.cantidad, vc.numero_serie
        FROM vehicle_components vc
        JOIN components c ON c.id = vc.component_id
        WHERE vc.vehiculo_id = ?
        ORDER BY c.tipo, c.nombre
    ");
    $stmt->execute([$vehiculoId]);
    $items = $stmt->fetchAll();
    $count = 0;

    $ins = $db->prepare("INSERT INTO assignment_component_snapshots
        (asignacion_id, vehiculo_id, momento, component_id, componente_nombre, componente_tipo, estado, cantidad, numero_serie, observaciones, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)");

    foreach ($items as $item) {
        $compId = (int)$item['component_id'];
        // Si hay overrides del usuario (para retorno con observaciones), aplicar
        $estado = $item['estado'];
        $obs    = null;
        if ($overrides && isset($overrides[$compId])) {
            $estado = $overrides[$compId]['estado'] ?? $estado;
            $obs    = $overrides[$compId]['observaciones'] ?? null;
        }
        $ins->execute([
            $asignacionId, $vehiculoId, $momento,
            $compId, $item['nombre'], $item['tipo'],
            $estado, (int)$item['cantidad'], $item['numero_serie'],
            $obs, $userId
        ]);
        $count++;
    }
    return $count;
}

function bloqueo_asignacion(PDO $db, int $vehiculoId): ?array {
    $stmt = $db->prepare("SELECT id FROM asignaciones WHERE vehiculo_id=? AND estado='Activa' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$vehiculoId]);
    $row = $stmt->fetch();
    if ($row) {
        return ['reason' => 'El vehículo ya tiene una asignación activa.', 'blocking_type' => 'asignacion', 'blocking_id' => (int)$row['id']];
    }

    $stmt2 = $db->prepare("SELECT id FROM mantenimientos WHERE vehiculo_id=? AND estado IN ('En proceso','Pendiente') ORDER BY id DESC LIMIT 1");
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

    if (($veh['estado'] ?? 'Activo') !== 'Activo') {
        return ['reason' => 'El vehículo no está disponible para asignación (estado no activo).', 'blocking_type' => 'estado_vehiculo', 'blocking_id' => $vehiculoId];
    }

    return null;
}

try {
    // ─── Sub-endpoint: snapshots de componentes ───
    $subAction = trim($_GET['action'] ?? '');

    // ─── Sub-endpoint: calendario de asignaciones ───
    if ($subAction === 'calendar' && $method === 'GET') {
        $from = trim($_GET['from'] ?? '');
        $to = trim($_GET['to'] ?? '');
        if (!$from) $from = date('Y-m-01');
        if (!$to) $to = date('Y-m-t');
        $vid = (int)($_GET['vehiculo_id'] ?? 0);
        $where = "WHERE a.deleted_at IS NULL AND a.start_at <= ? AND (a.end_at >= ? OR a.end_at IS NULL)";
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
        if ($asigId <= 0) { http_response_code(400); echo json_encode(['error' => 'ID inválido.']); exit; }
        $token = bin2hex(random_bytes(32));
        $db->prepare("UPDATE asignaciones SET firma_token = ? WHERE id = ?")->execute([$token, $asigId]);
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                 . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $link = $baseUrl . '/firma.php?token=' . $token;
        echo json_encode(['ok' => true, 'link' => $link, 'token' => $token]);
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
            $overrides = $d['items'] ?? [];
            $ovMap = [];
            foreach ($overrides as $o) {
                $cid = (int)($o['component_id'] ?? 0);
                if ($cid > 0) $ovMap[$cid] = $o;
            }
            $cnt = snapshot_componentes($db, $asigId, (int)$asigRow['vehiculo_id'], 'retorno', (int)($_SESSION['user_id'] ?? 0), $ovMap);
            // Actualizar vehicle_components con los nuevos estados reportados
            foreach ($ovMap as $cid => $ov) {
                if (!empty($ov['estado'])) {
                    $db->prepare("UPDATE vehicle_components SET estado = ? WHERE vehiculo_id = ? AND component_id = ?")
                       ->execute([$ov['estado'], (int)$asigRow['vehiculo_id'], $cid]);
                }
            }
            audit_log('assignment_snapshots', 'retorno_manual', $asigId, [], ['items' => count($ovMap)]);
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

            $where = "WHERE (v.placa LIKE ? OR v.marca LIKE ? OR o.nombre LIKE ? OR a.estado LIKE ? )";
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

            $bloqueo = bloqueo_asignacion($db, $vehiculoId);
            if ($bloqueo && !$allowOverride) {
                http_response_code(409);
                echo json_encode(['error' => $bloqueo['reason'], 'reason' => $bloqueo['reason'], 'blocking_type' => $bloqueo['blocking_type'], 'blocking_id' => $bloqueo['blocking_id']]);
                break;
            }

            odometro_validar_km($db, $vehiculoId, $startKm, $allowOverride, $overrideReason ?: null);

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("INSERT INTO asignaciones (vehiculo_id,operador_id,start_at,start_km,start_notes,estado,override_reason,created_by,checklist_gata,checklist_herramientas,checklist_llanta,checklist_bac,checklist_revision,checklist_detalles)
                    VALUES (?,?,?,?,?,'Activa',?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $vehiculoId,
                    $operadorId,
                    $d['start_at'] ?: date('Y-m-d H:i:s'),
                    $startKm,
                    $d['start_notes'] ?: null,
                    $allowOverride ? $overrideReason : null,
                    (int)($_SESSION['user_id'] ?? 0),
                    (int)($d['checklist_gata'] ?? 0),
                    (int)($d['checklist_herramientas'] ?? 0),
                    (int)($d['checklist_llanta'] ?? 0),
                    (int)($d['checklist_bac'] ?? 0),
                    (int)($d['checklist_revision'] ?? 0),
                    $d['checklist_detalles'] ?: null,
                ]);

                $id = (int)$db->lastInsertId();
                if ($startKm) {
                    odometro_registrar($db, $vehiculoId, $startKm, 'assignment_start', (int)($_SESSION['user_id'] ?? 0));
                }
                // Snapshot de componentes al momento de entrega
                snapshot_componentes($db, $id, $vehiculoId, 'entrega', (int)($_SESSION['user_id'] ?? 0));
                $db->commit();
            } catch (Throwable $txe) {
                $db->rollBack();
                throw $txe;
            }

            if ($allowOverride) {
                audit_log('asignaciones', 'override_used', $id, [], ['reason' => $overrideReason, 'bloqueo' => $bloqueo]);
            }

            audit_log('asignaciones', 'create', $id, [], $d);
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
            odometro_validar_km($db, $vehiculoId, $endKm, $allowOverride, $overrideReason ?: null);

            $db->beginTransaction();
            try {
                // Generate firma token if digital signature requested
                $firmaToken = null;
                $firmaTipo = $d['firma_tipo'] ?? 'ninguna';
                if (!in_array($firmaTipo, ['digital', 'fisica', 'ninguna'], true)) {
                    $firmaTipo = 'ninguna';
                }
                $firmaData = $d['firma_data'] ?? null;
                if ($firmaTipo === 'digital' && !$firmaData) {
                    $firmaToken = bin2hex(random_bytes(32));
                }

                $stmt = $db->prepare("UPDATE asignaciones
                    SET end_at=?, end_km=?, end_notes=?, estado='Cerrada', override_reason=COALESCE(?,override_reason), closed_by=?,
                        end_checklist_gata=?, end_checklist_herramientas=?, end_checklist_llanta=?, end_checklist_bac=?, end_checklist_revision=?, end_checklist_detalles=?,
                        firma_tipo=?, firma_data=?, firma_token=?, firma_fecha=IF(?='ninguna',NULL,NOW()), firma_ip=?
                    WHERE id=?");
                $stmt->execute([
                    $d['end_at'] ?: date('Y-m-d H:i:s'),
                    $endKm,
                    $d['end_notes'] ?: null,
                    $allowOverride ? $overrideReason : null,
                    (int)($_SESSION['user_id'] ?? 0),
                    (int)($d['end_checklist_gata'] ?? 0),
                    (int)($d['end_checklist_herramientas'] ?? 0),
                    (int)($d['end_checklist_llanta'] ?? 0),
                    (int)($d['end_checklist_bac'] ?? 0),
                    (int)($d['end_checklist_revision'] ?? 0),
                    $d['end_checklist_detalles'] ?: null,
                    $firmaTipo,
                    $firmaData ?: null,
                    $firmaToken,
                    $firmaTipo,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $id
                ]);

                odometro_registrar($db, $vehiculoId, $endKm, 'assignment_end', (int)($_SESSION['user_id'] ?? 0));
                // Snapshot de componentes al momento de retorno
                $componentOverrides = [];
                if (!empty($d['component_overrides']) && is_array($d['component_overrides'])) {
                    foreach ($d['component_overrides'] as $o) {
                        $cid = (int)($o['component_id'] ?? 0);
                        if ($cid > 0) $componentOverrides[$cid] = $o;
                    }
                }
                snapshot_componentes($db, $id, $vehiculoId, 'retorno', (int)($_SESSION['user_id'] ?? 0), $componentOverrides ?: null);
                // Si hay overrides, actualizar vehicle_components
                foreach ($componentOverrides as $cid => $ov) {
                    if (!empty($ov['estado'])) {
                        $db->prepare("UPDATE vehicle_components SET estado = ? WHERE vehiculo_id = ? AND component_id = ?")
                           ->execute([$ov['estado'], $vehiculoId, $cid]);
                    }
                }
                $db->commit();
            } catch (Throwable $txe) {
                $db->rollBack();
                throw $txe;
            }

            if ($allowOverride) {
                audit_log('asignaciones', 'override_used', $id, ['km_anterior' => $prev['end_km'] ?? null], ['km_nuevo' => $endKm], ['reason' => $overrideReason]);
            }

            audit_log('asignaciones', 'close', $id, $prev, $d);
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
            // If active, close it properly with current timestamp
            if (($prev['estado'] ?? '') === 'Activa') {
                $db->prepare("UPDATE asignaciones SET estado = 'Cerrada', end_at = NOW(), closed_by = ?, deleted_at = NOW() WHERE id = ?")
                   ->execute([(int)($_SESSION['user_id'] ?? 0), $id]);
            } else {
                $db->prepare("UPDATE asignaciones SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            }
            audit_log('asignaciones', 'soft_delete', $id, $prev, []);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
