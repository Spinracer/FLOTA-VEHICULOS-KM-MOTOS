<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/odometro.php';
require_login();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

try {
    switch ($method) {
        case 'GET':
            // Perfil 360: ?action=profile&id=X
            if (($_GET['action'] ?? '') === 'profile') {
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID inválido']); break; }

                $veh = $db->prepare("SELECT v.*, o.nombre AS operador_nombre FROM vehiculos v LEFT JOIN operadores o ON o.id=v.operador_id WHERE v.id=? LIMIT 1");
                $veh->execute([$id]);
                $vehiculo = $veh->fetch();
                if (!$vehiculo) { http_response_code(404); echo json_encode(['error' => 'Vehículo no encontrado']); break; }

                // Asignación activa
                $asgStmt = $db->prepare("SELECT a.*, o.nombre AS operador_nombre FROM asignaciones a JOIN operadores o ON o.id=a.operador_id WHERE a.vehiculo_id=? AND a.estado='Activa' ORDER BY a.id DESC LIMIT 1");
                $asgStmt->execute([$id]);
                $asignacionActiva = $asgStmt->fetch() ?: null;

                // Mantenimiento activo
                $mntStmt = $db->prepare("SELECT m.*, p.nombre AS proveedor_nombre FROM mantenimientos m LEFT JOIN proveedores p ON p.id=m.proveedor_id WHERE m.vehiculo_id=? AND m.estado IN ('En proceso','Pendiente') ORDER BY m.id DESC LIMIT 1");
                $mntStmt->execute([$id]);
                $mantenimientoActivo = $mntStmt->fetch() ?: null;

                // Último odómetro
                $odoStmt = $db->prepare("SELECT reading_km, source, recorded_at FROM odometer_logs WHERE vehicle_id=? ORDER BY recorded_at DESC LIMIT 1");
                $odoStmt->execute([$id]);
                $ultimoOdo = $odoStmt->fetch() ?: null;

                // Último combustible
                $fuelStmt = $db->prepare("SELECT fecha, litros, total, km FROM combustible WHERE vehiculo_id=? ORDER BY fecha DESC, id DESC LIMIT 1");
                $fuelStmt->execute([$id]);
                $ultimoComb = $fuelStmt->fetch() ?: null;

                // Totales
                $totalesStmt = $db->prepare("SELECT
                    (SELECT COUNT(*) FROM asignaciones WHERE vehiculo_id=?) AS total_asignaciones,
                    (SELECT COUNT(*) FROM mantenimientos WHERE vehiculo_id=?) AS total_mantenimientos,
                    (SELECT COALESCE(SUM(costo),0) FROM mantenimientos WHERE vehiculo_id=?) AS gasto_mantenimiento,
                    (SELECT COUNT(*) FROM combustible WHERE vehiculo_id=?) AS total_cargas,
                    (SELECT COALESCE(SUM(litros),0) FROM combustible WHERE vehiculo_id=?) AS total_litros,
                    (SELECT COALESCE(SUM(total),0) FROM combustible WHERE vehiculo_id=?) AS gasto_combustible,
                    (SELECT COUNT(*) FROM incidentes WHERE vehiculo_id=?) AS total_incidentes
                ");
                $totalesStmt->execute([$id,$id,$id,$id,$id,$id,$id]);
                $totales = $totalesStmt->fetch();

                // Historial reciente mantenimientos
                $histMnt = $db->prepare("SELECT m.id,m.fecha,m.tipo,m.costo,m.estado,p.nombre AS proveedor_nombre FROM mantenimientos m LEFT JOIN proveedores p ON p.id=m.proveedor_id WHERE m.vehiculo_id=? ORDER BY m.fecha DESC LIMIT 10");
                $histMnt->execute([$id]);

                // Historial reciente combustible
                $histFuel = $db->prepare("SELECT id,fecha,litros,total,km FROM combustible WHERE vehiculo_id=? ORDER BY fecha DESC LIMIT 10");
                $histFuel->execute([$id]);

                echo json_encode([
                    'vehiculo' => $vehiculo,
                    'asignacion_activa' => $asignacionActiva,
                    'mantenimiento_activo' => $mantenimientoActivo,
                    'ultimo_odometro' => $ultimoOdo,
                    'ultimo_combustible' => $ultimoComb,
                    'totales' => $totales,
                    'historial_mantenimientos' => $histMnt->fetchAll(),
                    'historial_combustible' => $histFuel->fetchAll(),
                ]);
                break;
            }

            $q    = '%' . trim($_GET['q']    ?? '') . '%';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per  = min(100, max(5, (int)($_GET['per'] ?? 20)));
            $off  = ($page - 1) * $per;
            $sucId = (int)($_GET['sucursal_id'] ?? 0);

            $where = "WHERE v.deleted_at IS NULL AND (v.placa LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? OR v.tipo LIKE ?)";
            $params = [$q,$q,$q,$q];
            if ($sucId) { $where .= " AND v.sucursal_id = ?"; $params[] = $sucId; }

            $total = $db->prepare("SELECT COUNT(*) FROM vehiculos v LEFT JOIN operadores o ON o.id = v.operador_id $where");
            $total->execute($params);

            $listParams = $params;
            $listParams[] = $per;
            $listParams[] = $off;
            $stmt = $db->prepare("SELECT v.*, o.nombre AS operador_nombre
                FROM vehiculos v
                LEFT JOIN operadores o ON o.id = v.operador_id
                $where
                ORDER BY v.placa ASC
                LIMIT ? OFFSET ?");
            $stmt->execute($listParams);

            echo json_encode(['total' => (int)$total->fetchColumn(), 'rows' => $stmt->fetchAll()]);
            break;

        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear vehículos.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $kmNuevo = isset($d['km_actual']) ? (float)$d['km_actual'] : 0;
            $stmt = $db->prepare("INSERT INTO vehiculos (placa,marca,modelo,anio,tipo,combustible,km_actual,color,vin,estado,operador_id,venc_seguro,notas,sucursal_id,tiene_gata,tiene_herramientas,tiene_llanta_repuesto,tiene_bac_flota,revision_ok,detalles_checklist)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                strtoupper(trim($d['placa'])), $d['marca'], $d['modelo'],
                $d['anio'] ?: null, $d['tipo'], $d['combustible'],
                $d['km_actual'] ?: 0, $d['color'] ?: null, $d['vin'] ?: null,
                $d['estado'], $d['operador_id'] ?: null,
                $d['venc_seguro'] ?: null, $d['notas'] ?: null,
                $d['sucursal_id'] ?: null,
                (int)($d['tiene_gata'] ?? 0), (int)($d['tiene_herramientas'] ?? 0),
                (int)($d['tiene_llanta_repuesto'] ?? 0), (int)($d['tiene_bac_flota'] ?? 0),
                (int)($d['revision_ok'] ?? 0), $d['detalles_checklist'] ?: null
            ]);
            $newId = (int)$db->lastInsertId();
            if ($kmNuevo > 0) {
                odometro_registrar($db, $newId, $kmNuevo, 'manual', (int)($_SESSION['user_id'] ?? 0));
            }
            audit_log('vehiculos', 'create', $newId, [], $d);
            echo json_encode(['id' => $newId, 'ok' => true]);
            break;

        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para editar vehículos.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $prevStmt = $db->prepare("SELECT * FROM vehiculos WHERE id=? LIMIT 1");
            $prevStmt->execute([(int)$d['id']]);
            $prev = $prevStmt->fetch() ?: [];
            $kmNuevo = isset($d['km_actual']) ? (float)$d['km_actual'] : 0;
            $allowOverride = can('manage_permissions') && !empty($d['override_reason']);
            odometro_validar_km($db, (int)$d['id'], $kmNuevo, $allowOverride, trim((string)($d['override_reason'] ?? '')) ?: null);
            $stmt = $db->prepare("UPDATE vehiculos SET placa=?,marca=?,modelo=?,anio=?,tipo=?,combustible=?,km_actual=?,color=?,vin=?,estado=?,operador_id=?,venc_seguro=?,notas=?,sucursal_id=?,tiene_gata=?,tiene_herramientas=?,tiene_llanta_repuesto=?,tiene_bac_flota=?,revision_ok=?,detalles_checklist=? WHERE id=?");
            $stmt->execute([
                strtoupper(trim($d['placa'])), $d['marca'], $d['modelo'],
                $d['anio'] ?: null, $d['tipo'], $d['combustible'],
                $d['km_actual'] ?: 0, $d['color'] ?: null, $d['vin'] ?: null,
                $d['estado'], $d['operador_id'] ?: null,
                $d['venc_seguro'] ?: null, $d['notas'] ?: null,
                $d['sucursal_id'] ?: null,
                (int)($d['tiene_gata'] ?? 0), (int)($d['tiene_herramientas'] ?? 0),
                (int)($d['tiene_llanta_repuesto'] ?? 0), (int)($d['tiene_bac_flota'] ?? 0),
                (int)($d['revision_ok'] ?? 0), $d['detalles_checklist'] ?: null,
                $d['id']
            ]);
            if ($kmNuevo > 0) {
                odometro_registrar($db, (int)$d['id'], $kmNuevo, 'manual', (int)($_SESSION['user_id'] ?? 0));
            }
            if ($allowOverride) {
                audit_log('vehiculos', 'odometro_override', (int)$d['id'], ['km_anterior' => $prev['km_actual'] ?? null], ['km_nuevo' => $kmNuevo], ['reason' => $d['override_reason']]);
            }
            audit_log('vehiculos', 'update', (int)$d['id'], $prev, $d);
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar vehículos.']);
                break;
            }
            $id = (int)($_GET['id'] ?? 0);
            $prevStmt = $db->prepare("SELECT * FROM vehiculos WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];
            // Soft-delete: marcar como eliminado en vez de borrar
            $db->prepare("UPDATE vehiculos SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            audit_log('vehiculos', 'soft_delete', $id, $prev, []);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    $msg = str_contains($e->getMessage(), 'Duplicate') ? 'Ya existe un vehículo con esa placa.' : $e->getMessage();
    echo json_encode(['error' => $msg]);
}
