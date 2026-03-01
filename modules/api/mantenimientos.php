<?php
// api/mantenimientos.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/odometro.php';
require_login();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$user = current_user();
$rol = $user['rol'] ?? '';
$action = trim($_GET['action'] ?? '');

// ─── Máquina de estados OT ───
// Pendiente → En proceso → Completado
// Pendiente → Cancelado
// En proceso → Cancelado (solo admin)
const OT_TRANSITIONS = [
    'Pendiente'  => ['En proceso', 'Cancelado'],
    'En proceso' => ['Completado', 'Cancelado'],
    'Completado' => [],
    'Cancelado'  => [],
];

function validate_transition(string $from, string $to, string $rol): bool {
    $allowed = OT_TRANSITIONS[$from] ?? [];
    if (!in_array($to, $allowed, true)) return false;
    // Solo admin puede cancelar desde "En proceso"
    if ($from === 'En proceso' && $to === 'Cancelado' && !in_array($rol, ['coordinador_it', 'admin'], true)) return false;
    return true;
}

function taller_context(PDO $db, int $userId): ?array {
    $stmt = $db->prepare("SELECT u.id, u.proveedor_id, p.es_taller_autorizado
        FROM usuarios u
        LEFT JOIN proveedores p ON p.id = u.proveedor_id
        WHERE u.id=? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'proveedor_id' => isset($row['proveedor_id']) ? (int)$row['proveedor_id'] : 0,
        'autorizado'   => (int)($row['es_taller_autorizado'] ?? 0) === 1,
    ];
}

try {
    // ───────────── ITEMS (partidas) de mantenimiento ─────────────
    if ($action === 'items') {
        $mantId = (int)($_GET['mantenimiento_id'] ?? 0);
        if ($mantId <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'mantenimiento_id es obligatorio.']);
            exit;
        }
        switch ($method) {
            case 'GET':
                $stmt = $db->prepare("SELECT mi.*, cu.nombre AS unidad_nombre
                    FROM mantenimiento_items mi
                    LEFT JOIN catalogo_unidades cu ON cu.clave = mi.unidad
                    WHERE mi.mantenimiento_id = ?
                    ORDER BY mi.id ASC");
                $stmt->execute([$mantId]);
                $items = $stmt->fetchAll();

                // Totales
                $totStmt = $db->prepare("SELECT COALESCE(SUM(subtotal),0) AS total_items FROM mantenimiento_items WHERE mantenimiento_id = ?");
                $totStmt->execute([$mantId]);
                $totalItems = (float)$totStmt->fetchColumn();

                echo json_encode(['items' => $items, 'total_items' => $totalItems]);
                break;

            case 'POST':
                if (!can('create')) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sin permisos.']);
                    break;
                }
                // Verificar que el mantenimiento no esté completado
                $mantCheck = $db->prepare("SELECT estado FROM mantenimientos WHERE id = ?");
                $mantCheck->execute([$mantId]);
                $mantEstado = $mantCheck->fetchColumn();
                if ($mantEstado === 'Completado') {
                    http_response_code(409);
                    echo json_encode(['error' => 'No se pueden agregar partidas a un mantenimiento completado.']);
                    break;
                }

                $d = json_decode(file_get_contents('php://input'), true);
                if (empty($d['descripcion'])) {
                    http_response_code(422);
                    echo json_encode(['error' => 'La descripción es obligatoria.']);
                    break;
                }

                $db->beginTransaction();
                try {
                    $db->prepare("INSERT INTO mantenimiento_items (mantenimiento_id, descripcion, cantidad, unidad, precio_unitario, notas) VALUES (?,?,?,?,?,?)")
                       ->execute([
                           $mantId,
                           $d['descripcion'],
                           (float)($d['cantidad'] ?? 1),
                           $d['unidad'] ?? 'PZA',
                           (float)($d['precio_unitario'] ?? 0),
                           $d['notas'] ?? null,
                       ]);
                    $newId = (int)$db->lastInsertId();

                    // Actualizar costo total del mantenimiento
                    $db->prepare("UPDATE mantenimientos SET costo = (SELECT COALESCE(SUM(subtotal),0) FROM mantenimiento_items WHERE mantenimiento_id = ?) WHERE id = ?")
                       ->execute([$mantId, $mantId]);

                    $db->commit();
                } catch (Throwable $txe) {
                    $db->rollBack();
                    throw $txe;
                }
                audit_log('mantenimiento_items', 'create', $newId, [], $d);
                echo json_encode(['id' => $newId, 'ok' => true]);
                break;

            case 'PUT':
                if (!can('edit')) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sin permisos.']);
                    break;
                }
                $d = json_decode(file_get_contents('php://input'), true);
                $prev = $db->prepare("SELECT * FROM mantenimiento_items WHERE id = ?");
                $prev->execute([(int)$d['id']]);
                $prevData = $prev->fetch() ?: [];

                // Bloquear edición si completado
                $mantCheck = $db->prepare("SELECT estado FROM mantenimientos WHERE id = ?");
                $mantCheck->execute([$mantId]);
                if ($mantCheck->fetchColumn() === 'Completado') {
                    http_response_code(409);
                    echo json_encode(['error' => 'No se pueden editar partidas de un mantenimiento completado.']);
                    break;
                }

                $db->beginTransaction();
                try {
                    $db->prepare("UPDATE mantenimiento_items SET descripcion = ?, cantidad = ?, unidad = ?, precio_unitario = ?, notas = ? WHERE id = ?")
                       ->execute([
                           $d['descripcion'],
                           (float)($d['cantidad'] ?? 1),
                           $d['unidad'] ?? 'PZA',
                           (float)($d['precio_unitario'] ?? 0),
                           $d['notas'] ?? null,
                           (int)$d['id'],
                       ]);
                    // Recalcular costo total
                    $db->prepare("UPDATE mantenimientos SET costo = (SELECT COALESCE(SUM(subtotal),0) FROM mantenimiento_items WHERE mantenimiento_id = ?) WHERE id = ?")
                       ->execute([$mantId, $mantId]);
                    $db->commit();
                } catch (Throwable $txe) {
                    $db->rollBack();
                    throw $txe;
                }
                audit_log('mantenimiento_items', 'update', (int)$d['id'], $prevData, $d);
                echo json_encode(['ok' => true]);
                break;

            case 'DELETE':
                if (!can('delete')) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sin permisos.']);
                    break;
                }
                $id = (int)$_GET['item_id'];
                $prev = $db->prepare("SELECT * FROM mantenimiento_items WHERE id = ?");
                $prev->execute([$id]);
                $prevData = $prev->fetch() ?: [];

                $db->beginTransaction();
                try {
                    $db->prepare("DELETE FROM mantenimiento_items WHERE id = ?")->execute([$id]);
                    $db->prepare("UPDATE mantenimientos SET costo = (SELECT COALESCE(SUM(subtotal),0) FROM mantenimiento_items WHERE mantenimiento_id = ?) WHERE id = ?")
                       ->execute([$mantId, $mantId]);
                    $db->commit();
                } catch (Throwable $txe) {
                    $db->rollBack();
                    throw $txe;
                }
                audit_log('mantenimiento_items', 'delete', $id, $prevData, []);
                echo json_encode(['ok' => true]);
                break;
        }
        exit;
    }

    // ───────────── CRUD principal de mantenimientos ─────────────
    switch ($method) {
        case 'GET':
            $q    = '%'.trim($_GET['q']??'').'%';
            $vid  = (int)($_GET['vehiculo_id']??0);
            $estado = trim($_GET['estado'] ?? '');
            $tipo   = trim($_GET['tipo'] ?? '');
            $provId = (int)($_GET['proveedor_id'] ?? 0);
            $costoMin = trim($_GET['costo_min'] ?? '');
            $costoMax = trim($_GET['costo_max'] ?? '');
            $from = trim($_GET['from'] ?? '');
            $to   = trim($_GET['to'] ?? '');
            $page = max(1,(int)($_GET['page']??1));
            $per  = min(100,max(5,(int)($_GET['per']??25)));
            $off  = ($page-1)*$per;
            $where = "WHERE m.deleted_at IS NULL AND (v.placa LIKE ? OR m.tipo LIKE ? OR m.descripcion LIKE ?)";
            $params = [$q, $q, $q];
            if ($vid)    { $where .= " AND m.vehiculo_id=?"; $params[] = $vid; }
            if ($estado !== '') { $where .= " AND m.estado=?";  $params[] = $estado; }
            if ($tipo !== '')   { $where .= " AND m.tipo=?";    $params[] = $tipo; }
            if ($provId)        { $where .= " AND m.proveedor_id=?"; $params[] = $provId; }
            if ($costoMin !== '') { $where .= " AND m.costo >= ?"; $params[] = (float)$costoMin; }
            if ($costoMax !== '') { $where .= " AND m.costo <= ?"; $params[] = (float)$costoMax; }
            if ($from !== '') { $where .= " AND m.fecha >= ?"; $params[] = $from; }
            if ($to   !== '') { $where .= " AND m.fecha <= ?"; $params[] = $to; }
            if ($vid) { $where .= " AND m.vehiculo_id=?"; $params[] = $vid; }
            if ($estado !== '') { $where .= " AND m.estado=?"; $params[] = $estado; }
            if ($rol === 'taller') {
                $ctx = taller_context($db, (int)($user['id'] ?? 0));
                if (!$ctx || !$ctx['proveedor_id'] || !$ctx['autorizado']) {
                    echo json_encode(['total' => 0, 'rows' => []]);
                    break;
                }
                $where .= " AND m.proveedor_id=?";
                $params[] = $ctx['proveedor_id'];
            }
            $total = $db->prepare("SELECT COUNT(*) FROM mantenimientos m LEFT JOIN vehiculos v ON v.id=m.vehiculo_id $where");
            $total->execute($params);
            $totalCount = (int)$total->fetchColumn();

            $listParams = array_merge($params, [$per, $off]);
            $stmt = $db->prepare("SELECT m.*, v.placa, v.marca, p.nombre AS proveedor_nombre,
                (SELECT COUNT(*) FROM mantenimiento_items mi WHERE mi.mantenimiento_id = m.id) AS items_count,
                (SELECT COALESCE(SUM(mi2.subtotal),0) FROM mantenimiento_items mi2 WHERE mi2.mantenimiento_id = m.id) AS items_total
                FROM mantenimientos m
                LEFT JOIN vehiculos v ON v.id=m.vehiculo_id
                LEFT JOIN proveedores p ON p.id=m.proveedor_id
                $where ORDER BY m.fecha DESC, m.id DESC LIMIT ? OFFSET ?");
            $stmt->execute($listParams);
            echo json_encode(['total'=>$totalCount,'rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear mantenimientos.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'),true);
            if ($rol === 'taller') {
                $ctx = taller_context($db, (int)($user['id'] ?? 0));
                if (!$ctx || !$ctx['proveedor_id']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Usuario de taller sin proveedor asignado.']);
                    break;
                }
                if (!$ctx['autorizado']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Tu proveedor no está autorizado para registrar mantenimientos.']);
                    break;
                }
                $d['proveedor_id'] = $ctx['proveedor_id'];
            }
            $km = isset($d['km']) && $d['km'] !== '' ? (float)$d['km'] : null;
            $allowOverride = can('manage_permissions') && !empty($d['override_reason']);
            odometro_validar_km($db, (int)$d['vehiculo_id'], $km, $allowOverride, trim((string)($d['override_reason'] ?? '')) ?: null);
            $db->beginTransaction();
            try {
                // Estado inicial siempre es Pendiente (flujo OT)
                $estadoInicial = $d['estado'] ?? 'Pendiente';
                $stmt = $db->prepare("INSERT INTO mantenimientos (fecha,vehiculo_id,tipo,descripcion,costo,km,proximo_km,proveedor_id,estado) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$d['fecha'],$d['vehiculo_id'],$d['tipo'],$d['descripcion']?:null,(float)($d['costo']??0),$d['km']?:null,$d['proximo_km']?:null,$d['proveedor_id']?:null,$estadoInicial]);
                if ($km) {
                    odometro_registrar($db, (int)$d['vehiculo_id'], $km, 'maintenance', (int)($_SESSION['user_id'] ?? 0));
                }
                $newId = (int)$db->lastInsertId();

                // Si se marca "En proceso", actualizar estado del vehículo
                if ($estadoInicial === 'En proceso') {
                    $db->prepare("UPDATE vehiculos SET estado = 'En mantenimiento' WHERE id = ? AND estado = 'Activo'")
                       ->execute([(int)$d['vehiculo_id']]);
                }

                $db->commit();
            } catch (Throwable $txe) {
                $db->rollBack();
                throw $txe;
            }
            if ($allowOverride) {
                audit_log('mantenimientos', 'odometro_override', $newId, [], ['km_nuevo' => $km], ['reason' => $d['override_reason']]);
            }
            audit_log('mantenimientos', 'create', $newId, [], $d);
            echo json_encode(['id'=>$newId,'ok'=>true]);
            break;
        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para editar mantenimientos.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'),true);
            $prevStmt = $db->prepare("SELECT * FROM mantenimientos WHERE id=? LIMIT 1");
            $prevStmt->execute([(int)$d['id']]);
            $prev = $prevStmt->fetch() ?: [];

            // Validar transición de estados OT
            $estadoAnterior = $prev['estado'] ?? 'Pendiente';
            $estadoNuevo    = $d['estado'] ?? $estadoAnterior;
            if ($estadoAnterior !== $estadoNuevo) {
                if (!validate_transition($estadoAnterior, $estadoNuevo, $rol)) {
                    http_response_code(409);
                    echo json_encode(['error' => "Transición de estado no permitida: {$estadoAnterior} → {$estadoNuevo}"]);
                    break;
                }
            }

            // Bloquear edición si está completado (excepto cambio de estado a Cancelado por admin)
            if ($estadoAnterior === 'Completado' && $estadoAnterior === $estadoNuevo) {
                http_response_code(409);
                echo json_encode(['error' => 'No se puede editar un mantenimiento completado.']);
                break;
            }

            // ═══ Reglas de cierre: validar al completar ═══
            if ($estadoNuevo === 'Completado' && $estadoAnterior !== 'Completado') {
                $exitKm = isset($d['exit_km']) && $d['exit_km'] !== '' ? (float)$d['exit_km'] : null;
                $resumen = trim((string)($d['resumen'] ?? ''));
                if ($exitKm === null || $exitKm <= 0) {
                    http_response_code(422);
                    echo json_encode(['error' => 'El km de salida (exit_km) es obligatorio para completar la OT.']);
                    break;
                }
                $entryKm = (float)($prev['km'] ?? 0);
                if ($entryKm > 0 && $exitKm < $entryKm) {
                    http_response_code(422);
                    echo json_encode(['error' => "El km de salida ({$exitKm}) no puede ser menor al km de entrada ({$entryKm})."]);
                    break;
                }
                if ($resumen === '') {
                    http_response_code(422);
                    echo json_encode(['error' => 'El resumen de trabajo es obligatorio para completar la OT.']);
                    break;
                }

                // ═══ Adjuntos obligatorios sobre umbral de costo ═══
                $umbralAdjuntos = 0;
                try {
                    $stUmb = $db->prepare("SELECT value_num FROM system_settings WHERE key_name='maintenance.umbral_adjuntos' LIMIT 1");
                    $stUmb->execute();
                    $umbralAdjuntos = (float)($stUmb->fetchColumn() ?: 0);
                } catch (Exception $e) {}
                if ($umbralAdjuntos > 0) {
                    $costoOT = (float)($d['costo'] ?? $prev['costo'] ?? 0);
                    if ($costoOT >= $umbralAdjuntos) {
                        require_once __DIR__ . '/../../includes/attachments.php';
                        $adjuntos = attachment_list('mantenimientos', $id);
                        if (count($adjuntos) === 0) {
                            http_response_code(422);
                            echo json_encode(['error' => "OTs con costo ≥ \${$umbralAdjuntos} requieren al menos un adjunto (diagnóstico, cotización o factura)."]);
                            break;
                        }
                    }
                }
            }

            if ($rol === 'taller') {
                $ctx = taller_context($db, (int)($user['id'] ?? 0));
                if (!$ctx || !$ctx['proveedor_id'] || !$ctx['autorizado']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'No autorizado para editar este mantenimiento.']);
                    break;
                }
                if ((int)($prev['proveedor_id'] ?? 0) !== $ctx['proveedor_id']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Solo puedes editar mantenimientos de tu taller.']);
                    break;
                }
                $d['proveedor_id'] = $ctx['proveedor_id'];
            }
            $km = isset($d['km']) && $d['km'] !== '' ? (float)$d['km'] : null;
            $allowOverride = can('manage_permissions') && !empty($d['override_reason']);
            odometro_validar_km($db, (int)$d['vehiculo_id'], $km, $allowOverride, trim((string)($d['override_reason'] ?? '')) ?: null);
            $db->beginTransaction();
            try {
                $completedAt = null;
                $completedBy = null;
                if ($estadoNuevo === 'Completado' && $estadoAnterior !== 'Completado') {
                    $completedAt = date('Y-m-d H:i:s');
                    $completedBy = (int)($_SESSION['user_id'] ?? 0);
                }
                $stmt = $db->prepare("UPDATE mantenimientos SET fecha=?,vehiculo_id=?,tipo=?,descripcion=?,costo=?,km=?,exit_km=?,proximo_km=?,proveedor_id=?,estado=?,resumen=?,completed_at=COALESCE(?,completed_at),completed_by=COALESCE(?,completed_by) WHERE id=?");
                $stmt->execute([
                    $d['fecha'], $d['vehiculo_id'], $d['tipo'], $d['descripcion'] ?: null,
                    (float)($d['costo'] ?? 0), $d['km'] ?: null,
                    $d['exit_km'] ?? null,
                    $d['proximo_km'] ?: null, $d['proveedor_id'] ?: null, $estadoNuevo,
                    $d['resumen'] ?? null,
                    $completedAt, $completedBy,
                    $d['id']
                ]);
                if ($km) {
                    odometro_registrar($db, (int)$d['vehiculo_id'], $km, 'maintenance', (int)($_SESSION['user_id'] ?? 0));
                }
                // Auto-registrar exit_km en odómetro al completar
                if ($estadoNuevo === 'Completado' && $estadoAnterior !== 'Completado') {
                    $exitKm = isset($d['exit_km']) ? (float)$d['exit_km'] : null;
                    if ($exitKm && $exitKm > 0) {
                        odometro_registrar($db, (int)$d['vehiculo_id'], $exitKm, 'maintenance_exit', (int)($_SESSION['user_id'] ?? 0));
                    }
                }

                // Transiciones de estado → actualizar vehículo
                $vehiculoId = (int)$d['vehiculo_id'];
                if ($estadoAnterior !== $estadoNuevo) {
                    if ($estadoNuevo === 'En proceso') {
                        $db->prepare("UPDATE vehiculos SET estado = 'En mantenimiento' WHERE id = ? AND estado = 'Activo'")
                           ->execute([$vehiculoId]);
                    }
                    if (in_array($estadoNuevo, ['Completado', 'Cancelado'], true)) {
                        // Verificar si no hay otro mantenimiento activo
                        $otherActive = $db->prepare("SELECT COUNT(*) FROM mantenimientos WHERE vehiculo_id = ? AND estado = 'En proceso' AND id != ?");
                        $otherActive->execute([$vehiculoId, (int)$d['id']]);
                        if ((int)$otherActive->fetchColumn() === 0) {
                            // Restablecer a Activo si no hay asignación activa, sino mantener como está
                            $activeAsig = $db->prepare("SELECT COUNT(*) FROM asignaciones WHERE vehiculo_id = ? AND estado = 'Activa'");
                            $activeAsig->execute([$vehiculoId]);
                            $newVehEstado = (int)$activeAsig->fetchColumn() > 0 ? 'Activo' : 'Activo';
                            $db->prepare("UPDATE vehiculos SET estado = ? WHERE id = ? AND estado = 'En mantenimiento'")
                               ->execute([$newVehEstado, $vehiculoId]);
                        }
                    }
                    audit_log('mantenimientos', 'estado_change', (int)$d['id'], ['estado' => $estadoAnterior], ['estado' => $estadoNuevo]);

                    // Notificación al cambiar estado
                    try {
                        require_once __DIR__ . '/../../includes/notifications.php';
                        $vPlaca = $db->prepare("SELECT placa FROM vehiculos WHERE id=? LIMIT 1");
                        $vPlaca->execute([(int)$d['vehiculo_id']]);
                        $placaOT = $vPlaca->fetchColumn() ?: '?';
                        if ($estadoNuevo === 'Completado') {
                            notify_roles($db, ['coordinador_it','admin','soporte'], 'exito', "OT #{$d['id']} Completada", "Mantenimiento {$d['tipo']} del vehículo {$placaOT} ha sido completado.", 'mantenimientos', (int)$d['id']);
                        } elseif ($estadoNuevo === 'Cancelado') {
                            notify_roles($db, ['coordinador_it','admin'], 'warning', "OT #{$d['id']} Cancelada", "Mantenimiento {$d['tipo']} del vehículo {$placaOT} fue cancelado.", 'mantenimientos', (int)$d['id']);
                        }
                    } catch (Throwable $ne) { /* no bloquear */ }
                }

                $db->commit();
            } catch (Throwable $txe) {
                $db->rollBack();
                throw $txe;
            }
            if ($allowOverride) {
                audit_log('mantenimientos', 'odometro_override', (int)$d['id'], ['km_anterior' => $prev['km'] ?? null], ['km_nuevo' => $km], ['reason' => $d['override_reason']]);
            }
            audit_log('mantenimientos', 'update', (int)$d['id'], $prev, $d);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            if ($rol === 'taller') {
                http_response_code(403);
                echo json_encode(['error' => 'El rol taller no puede eliminar mantenimientos.']);
                break;
            }
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar mantenimientos.']);
                break;
            }
            $id = (int)$_GET['id'];
            $prevStmt = $db->prepare("SELECT * FROM mantenimientos WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];
            $db->prepare("UPDATE mantenimientos SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            audit_log('mantenimientos', 'soft_delete', $id, $prev, []);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
