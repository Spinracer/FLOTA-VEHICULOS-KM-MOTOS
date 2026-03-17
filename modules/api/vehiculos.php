<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/odometro.php';
require_once __DIR__ . '/../../includes/cache.php';
require_login();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

try {
    switch ($method) {
        case 'GET':
            // ── Etiquetas CRUD: ?action=tags&id=X ──
            if (($_GET['action'] ?? '') === 'tags') {
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID inválido']); break; }
                $stmt = $db->prepare("SELECT id, etiqueta, created_at FROM vehiculo_etiquetas WHERE vehiculo_id=? ORDER BY etiqueta");
                $stmt->execute([$id]);
                echo json_encode(['tags' => $stmt->fetchAll()]);
                break;
            }

            // ── Perfil 360: ?action=profile&id=X ──
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
                    (SELECT COUNT(*) FROM incidentes WHERE vehiculo_id=?) AS total_incidentes,
                    (SELECT COALESCE(SUM(costo_est),0) FROM incidentes WHERE vehiculo_id=?) AS gasto_incidentes
                ");
                $totalesStmt->execute([$id,$id,$id,$id,$id,$id,$id,$id]);
                $totales = $totalesStmt->fetch();

                // Costo por kilómetro
                $km = (float)($vehiculo['km_actual'] ?? 0);
                $gastoTotal = (float)$totales['gasto_mantenimiento'] + (float)$totales['gasto_combustible'] + (float)$totales['gasto_incidentes'];
                $totales['costo_por_km'] = $km > 0 ? round($gastoTotal / $km, 2) : 0;
                $totales['gasto_total'] = round($gastoTotal, 2);

                // Historial reciente mantenimientos
                $histMnt = $db->prepare("SELECT m.id,m.fecha,m.tipo,m.costo,m.estado,p.nombre AS proveedor_nombre FROM mantenimientos m LEFT JOIN proveedores p ON p.id=m.proveedor_id WHERE m.vehiculo_id=? ORDER BY m.fecha DESC LIMIT 10");
                $histMnt->execute([$id]);

                // Historial reciente combustible
                $histFuel = $db->prepare("SELECT id,fecha,litros,total,km FROM combustible WHERE vehiculo_id=? ORDER BY fecha DESC LIMIT 10");
                $histFuel->execute([$id]);

                // Historial reciente asignaciones
                $histAsg = $db->prepare("SELECT a.id,a.start_at,a.end_at,a.start_km,a.end_km,a.estado,o.nombre AS operador_nombre FROM asignaciones a LEFT JOIN operadores o ON o.id=a.operador_id WHERE a.vehiculo_id=? ORDER BY a.start_at DESC LIMIT 10");
                $histAsg->execute([$id]);

                // Historial odómetro (últimos 30 registros para gráfica)
                $histOdo = $db->prepare("SELECT reading_km, source, recorded_at FROM odometer_logs WHERE vehicle_id=? ORDER BY recorded_at ASC LIMIT 30");
                $histOdo->execute([$id]);

                // Etiquetas del vehículo
                $tagsStmt = $db->prepare("SELECT id, etiqueta FROM vehiculo_etiquetas WHERE vehiculo_id=? ORDER BY etiqueta");
                $tagsStmt->execute([$id]);

                // Telemetría resumen (últimos registros por tipo)
                $telStmt = $db->prepare("SELECT tipo, valor, unidad, recorded_at FROM telemetria_logs WHERE vehiculo_id=? ORDER BY recorded_at DESC LIMIT 20");
                try { $telStmt->execute([$id]); $telemetria = $telStmt->fetchAll(); } catch (Throwable $e) { $telemetria = []; }

                echo json_encode([
                    'vehiculo' => $vehiculo,
                    'asignacion_activa' => $asignacionActiva,
                    'mantenimiento_activo' => $mantenimientoActivo,
                    'ultimo_odometro' => $ultimoOdo,
                    'ultimo_combustible' => $ultimoComb,
                    'totales' => $totales,
                    'historial_mantenimientos' => $histMnt->fetchAll(),
                    'historial_combustible' => $histFuel->fetchAll(),
                    'historial_asignaciones' => $histAsg->fetchAll(),
                    'historial_odometro' => $histOdo->fetchAll(),
                    'etiquetas' => $tagsStmt->fetchAll(),
                    'telemetria' => $telemetria,
                ]);
                break;
            }

            // ── Listado con etiquetas ──
            $q    = '%' . trim($_GET['q']    ?? '') . '%';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per  = min(100, max(5, (int)($_GET['per'] ?? 20)));
            $off  = ($page - 1) * $per;
            $sucId = (int)($_GET['sucursal_id'] ?? 0);
            $tag  = trim($_GET['tag'] ?? '');

            $where = "WHERE v.deleted_at IS NULL AND (v.placa LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? OR v.tipo LIKE ?)";
            $params = [$q,$q,$q,$q];
            if ($sucId) { $where .= " AND v.sucursal_id = ?"; $params[] = $sucId; }
            if ($tag !== '') { $where .= " AND v.id IN (SELECT vehiculo_id FROM vehiculo_etiquetas WHERE etiqueta = ?)"; $params[] = $tag; }

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
            $rows = $stmt->fetchAll();

            // Attach etiquetas to each row
            if (!empty($rows)) {
                $vehIds = array_column($rows, 'id');
                $placeholders = implode(',', array_fill(0, count($vehIds), '?'));
                $tagStmt = $db->prepare("SELECT vehiculo_id, etiqueta FROM vehiculo_etiquetas WHERE vehiculo_id IN ($placeholders) ORDER BY etiqueta");
                $tagStmt->execute($vehIds);
                $tagMap = [];
                foreach ($tagStmt->fetchAll() as $t) {
                    $tagMap[$t['vehiculo_id']][] = $t['etiqueta'];
                }
                foreach ($rows as &$r) {
                    $r['etiquetas'] = $tagMap[$r['id']] ?? [];
                }
                unset($r);
            }

            echo json_encode(['total' => (int)$total->fetchColumn(), 'rows' => $rows]);
            break;

        case 'POST':
            // ── Agregar etiqueta: action=add_tag ──
            $d = json_decode(file_get_contents('php://input'), true);
            if (($_GET['action'] ?? '') === 'add_tag') {
                if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos']); break; }
                $vehId = (int)($d['vehiculo_id'] ?? 0);
                $tag = trim($d['etiqueta'] ?? '');
                if ($vehId <= 0 || $tag === '') { http_response_code(400); echo json_encode(['error' => 'vehiculo_id y etiqueta requeridos']); break; }
                $stmt = $db->prepare("INSERT IGNORE INTO vehiculo_etiquetas (vehiculo_id, etiqueta, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$vehId, $tag]);
                audit_log('vehiculo_etiquetas', 'create', $vehId, [], ['etiqueta' => $tag]);
                echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId()]);
                break;
            }

            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear vehículos.']);
                break;
            }
            $kmNuevo = isset($d['km_actual']) ? (float)$d['km_actual'] : 0;
            $stmt = $db->prepare("INSERT INTO vehiculos (placa,marca,modelo,anio,tipo,combustible,km_actual,color,vin,estado,operador_id,venc_seguro,notas,sucursal_id,tiene_gata,tiene_herramientas,tiene_llanta_repuesto,tiene_bac_flota,revision_ok,tiene_luces,tiene_liquidos,tiene_motor_ok,tiene_parabrisas,tiene_documentacion,tiene_frenos,tiene_espejos,detalles_checklist,costo_adquisicion,aseguradora,poliza_numero)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                strtoupper(trim($d['placa'])), $d['marca'], $d['modelo'],
                $d['anio'] ?: null, $d['tipo'], $d['combustible'],
                $d['km_actual'] ?: 0, $d['color'] ?: null, $d['vin'] ?: null,
                $d['estado'], $d['operador_id'] ?: null,
                $d['venc_seguro'] ?: null, $d['notas'] ?: null,
                $d['sucursal_id'] ?: null,
                (int)($d['tiene_gata'] ?? 0), (int)($d['tiene_herramientas'] ?? 0),
                (int)($d['tiene_llanta_repuesto'] ?? 0), (int)($d['tiene_bac_flota'] ?? 0),
                (int)($d['revision_ok'] ?? 0),
                (int)($d['tiene_luces'] ?? 0), (int)($d['tiene_liquidos'] ?? 0),
                (int)($d['tiene_motor_ok'] ?? 0), (int)($d['tiene_parabrisas'] ?? 0),
                (int)($d['tiene_documentacion'] ?? 0), (int)($d['tiene_frenos'] ?? 0),
                (int)($d['tiene_espejos'] ?? 0),
                $d['detalles_checklist'] ?: null,
                isset($d['costo_adquisicion']) && $d['costo_adquisicion'] !== '' ? (float)$d['costo_adquisicion'] : null,
                $d['aseguradora'] ?? null,
                $d['poliza_numero'] ?? null
            ]);
            $newId = (int)$db->lastInsertId();
            if ($kmNuevo > 0) {
                odometro_registrar($db, $newId, $kmNuevo, 'manual', (int)($_SESSION['user_id'] ?? 0));
            }
            audit_log('vehiculos', 'create', $newId, [], $d);
            cache_invalidate_prefix('dashboard');
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
            $stmt = $db->prepare("UPDATE vehiculos SET placa=?,marca=?,modelo=?,anio=?,tipo=?,combustible=?,km_actual=?,color=?,vin=?,estado=?,operador_id=?,venc_seguro=?,notas=?,sucursal_id=?,tiene_gata=?,tiene_herramientas=?,tiene_llanta_repuesto=?,tiene_bac_flota=?,revision_ok=?,tiene_luces=?,tiene_liquidos=?,tiene_motor_ok=?,tiene_parabrisas=?,tiene_documentacion=?,tiene_frenos=?,tiene_espejos=?,detalles_checklist=?,costo_adquisicion=?,aseguradora=?,poliza_numero=? WHERE id=?");
            $stmt->execute([
                strtoupper(trim($d['placa'])), $d['marca'], $d['modelo'],
                $d['anio'] ?: null, $d['tipo'], $d['combustible'],
                $d['km_actual'] ?: 0, $d['color'] ?: null, $d['vin'] ?: null,
                $d['estado'], $d['operador_id'] ?: null,
                $d['venc_seguro'] ?: null, $d['notas'] ?: null,
                $d['sucursal_id'] ?: null,
                (int)($d['tiene_gata'] ?? 0), (int)($d['tiene_herramientas'] ?? 0),
                (int)($d['tiene_llanta_repuesto'] ?? 0), (int)($d['tiene_bac_flota'] ?? 0),
                (int)($d['revision_ok'] ?? 0),
                (int)($d['tiene_luces'] ?? 0), (int)($d['tiene_liquidos'] ?? 0),
                (int)($d['tiene_motor_ok'] ?? 0), (int)($d['tiene_parabrisas'] ?? 0),
                (int)($d['tiene_documentacion'] ?? 0), (int)($d['tiene_frenos'] ?? 0),
                (int)($d['tiene_espejos'] ?? 0),
                $d['detalles_checklist'] ?: null,
                isset($d['costo_adquisicion']) && $d['costo_adquisicion'] !== '' ? (float)$d['costo_adquisicion'] : null,
                $d['aseguradora'] ?? null,
                $d['poliza_numero'] ?? null,
                $d['id']
            ]);
            if ($kmNuevo > 0) {
                odometro_registrar($db, (int)$d['id'], $kmNuevo, 'manual', (int)($_SESSION['user_id'] ?? 0));
            }
            if ($allowOverride) {
                audit_log('vehiculos', 'odometro_override', (int)$d['id'], ['km_anterior' => $prev['km_actual'] ?? null], ['km_nuevo' => $kmNuevo], ['reason' => $d['override_reason']]);
            }
            audit_log('vehiculos', 'update', (int)$d['id'], $prev, $d);
            cache_invalidate_prefix('dashboard');
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE':
            // ── Eliminar etiqueta: ?action=remove_tag&id=X ──
            if (($_GET['action'] ?? '') === 'remove_tag') {
                if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos']); break; }
                $tagId = (int)($_GET['id'] ?? 0);
                if ($tagId <= 0) { http_response_code(400); echo json_encode(['error' => 'ID de etiqueta inválido']); break; }
                $prev = $db->prepare("SELECT * FROM vehiculo_etiquetas WHERE id=? LIMIT 1");
                $prev->execute([$tagId]);
                $prevTag = $prev->fetch();
                $db->prepare("DELETE FROM vehiculo_etiquetas WHERE id=?")->execute([$tagId]);
                if ($prevTag) { audit_log('vehiculo_etiquetas', 'delete', $prevTag['vehiculo_id'], $prevTag, []); }
                echo json_encode(['ok' => true]);
                break;
            }

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
            cache_invalidate_prefix('dashboard');
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    $msg = str_contains($e->getMessage(), 'Duplicate') ? 'Ya existe un vehículo con esa placa.' : safe_error_msg($e);
    echo json_encode(['error' => $msg]);
}
