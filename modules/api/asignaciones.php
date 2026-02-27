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
                $stmt = $db->prepare("INSERT INTO asignaciones (vehiculo_id,operador_id,start_at,start_km,start_notes,estado,override_reason,created_by)
                    VALUES (?,?,?,?,?,'Activa',?,?)");
                $stmt->execute([
                    $vehiculoId,
                    $operadorId,
                    $d['start_at'] ?: date('Y-m-d H:i:s'),
                    $startKm,
                    $d['start_notes'] ?: null,
                    $allowOverride ? $overrideReason : null,
                    (int)($_SESSION['user_id'] ?? 0)
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
                $stmt = $db->prepare("UPDATE asignaciones
                    SET end_at=?, end_km=?, end_notes=?, estado='Cerrada', override_reason=COALESCE(?,override_reason), closed_by=?
                    WHERE id=?");
                $stmt->execute([
                    $d['end_at'] ?: date('Y-m-d H:i:s'),
                    $endKm,
                    $d['end_notes'] ?: null,
                    $allowOverride ? $overrideReason : null,
                    (int)($_SESSION['user_id'] ?? 0),
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
            $db->prepare("UPDATE asignaciones SET estado = 'Cerrada' WHERE id = ?")->execute([$id]);
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
