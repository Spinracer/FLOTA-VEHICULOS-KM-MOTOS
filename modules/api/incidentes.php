<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$user = current_user();

// ─── Transiciones de estado válidas ───
const INC_TRANSITIONS = [
    'Abierto'    => ['En proceso', 'Cerrado'],
    'En proceso' => ['Cerrado', 'Abierto'],
    'Cerrado'    => ['Abierto'], // Reabrir
];

try {
    $action = trim($_GET['action'] ?? '');

    // ─── Sub-endpoint: Seguimientos de un incidente ───
    if ($action === 'seguimientos') {
        $incId = (int)($_GET['incidente_id'] ?? 0);
        if ($method === 'GET' && $incId > 0) {
            $stmt = $db->prepare("SELECT s.*, u.nombre AS usuario_nombre FROM incidente_seguimientos s LEFT JOIN usuarios u ON u.id = s.usuario_id WHERE s.incidente_id = ? ORDER BY s.created_at DESC");
            $stmt->execute([$incId]);
            echo json_encode(['seguimientos' => $stmt->fetchAll()]);
            exit;
        }
        if ($method === 'POST') {
            if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            $incId = (int)($d['incidente_id'] ?? 0);
            $comentario = trim($d['comentario'] ?? '');
            if ($incId <= 0) { http_response_code(400); echo json_encode(['error' => 'incidente_id requerido']); exit; }
            $db->prepare("INSERT INTO incidente_seguimientos (incidente_id, usuario_id, accion, comentario) VALUES (?,?,?,?)")
                ->execute([$incId, (int)($user['id'] ?? 0), 'nota', $comentario]);
            audit_log('incidente_seguimientos', 'create', $incId, [], ['comentario' => $comentario]);
            echo json_encode(['ok' => true]);
            exit;
        }
    }

    // ─── Sub-endpoint: Dashboard de seguridad ───
    if ($action === 'dashboard' && $method === 'GET') {
        $year = (int)($_GET['year'] ?? date('Y'));

        // Por severidad
        $bySev = $db->prepare("SELECT severidad, COUNT(*) AS total FROM incidentes WHERE deleted_at IS NULL AND YEAR(fecha) = ? GROUP BY severidad ORDER BY FIELD(severidad,'Crítica','Alta','Media','Baja')");
        $bySev->execute([$year]);

        // Por tipo
        $byType = $db->prepare("SELECT tipo, COUNT(*) AS total FROM incidentes WHERE deleted_at IS NULL AND YEAR(fecha) = ? GROUP BY tipo ORDER BY total DESC");
        $byType->execute([$year]);

        // Por mes
        $byMonth = $db->prepare("SELECT MONTH(fecha) AS mes, COUNT(*) AS total, SUM(costo_est) AS costo FROM incidentes WHERE deleted_at IS NULL AND YEAR(fecha) = ? GROUP BY mes ORDER BY mes");
        $byMonth->execute([$year]);

        // Por estado
        $byStatus = $db->prepare("SELECT estado, COUNT(*) AS total FROM incidentes WHERE deleted_at IS NULL AND YEAR(fecha) = ? GROUP BY estado");
        $byStatus->execute([$year]);

        // Top vehículos con más incidentes
        $topVeh = $db->prepare("SELECT i.vehiculo_id, v.placa, v.marca, COUNT(*) AS total, SUM(i.costo_est) AS costo_total
            FROM incidentes i JOIN vehiculos v ON v.id = i.vehiculo_id
            WHERE i.deleted_at IS NULL AND YEAR(i.fecha) = ?
            GROUP BY i.vehiculo_id, v.placa, v.marca ORDER BY total DESC LIMIT 10");
        $topVeh->execute([$year]);

        // Reclamos
        $reclamos = $db->prepare("SELECT estado_reclamo, COUNT(*) AS total, SUM(monto_reclamo) AS monto FROM incidentes WHERE deleted_at IS NULL AND tiene_reclamo = 1 AND YEAR(fecha) = ? GROUP BY estado_reclamo");
        $reclamos->execute([$year]);

        // Tiempo promedio de resolución (days between fecha and resolved_at)
        $avgResolve = $db->prepare("SELECT AVG(DATEDIFF(COALESCE(resolved_at, NOW()), fecha)) AS avg_days FROM incidentes WHERE deleted_at IS NULL AND YEAR(fecha) = ? AND estado = 'Cerrado'");
        $avgResolve->execute([$year]);
        $avgDays = $avgResolve->fetchColumn();

        echo json_encode([
            'by_severity' => $bySev->fetchAll(),
            'by_type' => $byType->fetchAll(),
            'by_month' => $byMonth->fetchAll(),
            'by_status' => $byStatus->fetchAll(),
            'top_vehicles' => $topVeh->fetchAll(),
            'reclamos' => $reclamos->fetchAll(),
            'avg_resolve_days' => $avgDays !== false ? round((float)$avgDays, 1) : null,
            'year' => $year,
        ]);
        exit;
    }

    switch ($method) {
        case 'GET':
            // Detail single by id
            if (!empty($_GET['detail'])) {
                $row = $db->prepare("SELECT i.*,v.placa,v.marca,v.modelo,v.venc_seguro AS vehiculo_venc_seguro FROM incidentes i LEFT JOIN vehiculos v ON v.id=i.vehiculo_id WHERE i.id=? AND i.deleted_at IS NULL LIMIT 1");
                $row->execute([(int)$_GET['detail']]);
                echo json_encode($row->fetch() ?: []);
                break;
            }
            $q='%'.trim($_GET['q']??'').'%'; $vid=(int)($_GET['vehiculo_id']??0);
            $estado = trim($_GET['estado'] ?? '');
            $tieneReclamo = $_GET['tiene_reclamo'] ?? '';
            $page=max(1,(int)($_GET['page']??1)); $per=min(100,max(5,(int)($_GET['per']??25))); $off=($page-1)*$per;
            $where="WHERE i.deleted_at IS NULL AND (v.placa LIKE ? OR i.tipo LIKE ? OR i.descripcion LIKE ? OR i.aseguradora LIKE ? OR i.poliza_numero LIKE ?)";
            $params=[$q,$q,$q,$q,$q];
            if ($vid){$where.=" AND i.vehiculo_id=?"; $params[] = $vid;}
            if ($estado !== ''){$where.=" AND i.estado=?"; $params[] = $estado;}
            if ($tieneReclamo !== ''){$where.=" AND i.tiene_reclamo=?"; $params[] = (int)$tieneReclamo;}
            $total=$db->prepare("SELECT COUNT(*) FROM incidentes i LEFT JOIN vehiculos v ON v.id=i.vehiculo_id $where");
            $total->execute($params);
            $totalCount = (int)$total->fetchColumn();
            $listParams = $params;
            $listParams[] = $per;
            $listParams[] = $off;
            $stmt=$db->prepare("SELECT i.*,v.placa,v.marca,v.venc_seguro AS vehiculo_venc_seguro FROM incidentes i LEFT JOIN vehiculos v ON v.id=i.vehiculo_id $where ORDER BY i.fecha DESC,i.id DESC LIMIT ? OFFSET ?");
            $stmt->execute($listParams);
            echo json_encode(['total'=>$totalCount,'rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear incidentes.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
            $db->prepare("INSERT INTO incidentes (fecha,vehiculo_id,tipo,descripcion,severidad,estado,costo_est,aseguradora,poliza_numero,tiene_reclamo,estado_reclamo,monto_reclamo,fecha_reclamo,referencia_reclamo,notas_seguro) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([
                   $d['fecha'], $d['vehiculo_id'], $d['tipo'], $d['descripcion'],
                   $d['severidad'], $d['estado'], (float)($d['costo_est'] ?? 0),
                   $d['aseguradora'] ?? null, $d['poliza_numero'] ?? null,
                   (int)($d['tiene_reclamo'] ?? 0),
                   $d['estado_reclamo'] ?? 'N/A', (float)($d['monto_reclamo'] ?? 0),
                   $d['fecha_reclamo'] ?: null, $d['referencia_reclamo'] ?? null,
                   $d['notas_seguro'] ?? null
               ]);
            $newId = (int)$db->lastInsertId();
            audit_log('incidentes', 'create', $newId, [], $d);
            // Notificación si severidad alta/crítica
            if (in_array($d['severidad'] ?? '', ['Alta','Crítica'])) {
                try {
                    require_once __DIR__ . '/../../includes/notifications.php';
                    $veh = $db->prepare("SELECT placa FROM vehiculos WHERE id=? LIMIT 1");
                    $veh->execute([(int)$d['vehiculo_id']]);
                    $placa = $veh->fetchColumn() ?: '?';
                    notify_roles($db, ['coordinador_it','admin','soporte'], 'alerta', "Incidente {$d['severidad']}", "Se reportó incidente de severidad {$d['severidad']} en vehículo {$placa}: {$d['tipo']}", 'incidentes', $newId);
                } catch (Throwable $e) { /* no bloquear */ }
            }
            echo json_encode(['id'=>$newId,'ok'=>true]);
            break;
        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para editar incidentes.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
            $prevStmt = $db->prepare("SELECT * FROM incidentes WHERE id=? LIMIT 1");
            $prevStmt->execute([(int)$d['id']]);
            $prev = $prevStmt->fetch() ?: [];

            // Validar transición de estados
            $estadoAnterior = $prev['estado'] ?? 'Abierto';
            $estadoNuevo = $d['estado'] ?? $estadoAnterior;
            if ($estadoAnterior !== $estadoNuevo) {
                $allowed = INC_TRANSITIONS[$estadoAnterior] ?? [];
                if (!in_array($estadoNuevo, $allowed, true)) {
                    http_response_code(409);
                    echo json_encode(['error' => "Transición no permitida: {$estadoAnterior} → {$estadoNuevo}"]);
                    break;
                }
                // Registrar seguimiento automático
                try {
                    $db->prepare("INSERT INTO incidente_seguimientos (incidente_id, usuario_id, accion, estado_anterior, estado_nuevo, comentario) VALUES (?,?,?,?,?,?)")
                        ->execute([(int)$d['id'], (int)($user['id'] ?? 0), 'estado_change', $estadoAnterior, $estadoNuevo, $d['estado_comentario'] ?? null]);
                } catch (Throwable $e) { /* tabla puede no existir aún */ }
            }

            // Track resolved_at/resolved_by
            $resolvedAt = null;
            $resolvedBy = null;
            if ($estadoNuevo === 'Cerrado' && $estadoAnterior !== 'Cerrado') {
                $resolvedAt = date('Y-m-d H:i:s');
                $resolvedBy = (int)($user['id'] ?? 0);
            }

            $db->prepare("UPDATE incidentes SET fecha=?,vehiculo_id=?,tipo=?,descripcion=?,severidad=?,estado=?,costo_est=?,aseguradora=?,poliza_numero=?,tiene_reclamo=?,estado_reclamo=?,monto_reclamo=?,fecha_reclamo=?,referencia_reclamo=?,notas_seguro=?,prioridad=COALESCE(?,prioridad),resolved_at=COALESCE(?,resolved_at),resolved_by=COALESCE(?,resolved_by) WHERE id=?")
               ->execute([
                   $d['fecha'], $d['vehiculo_id'], $d['tipo'], $d['descripcion'],
                   $d['severidad'], $estadoNuevo, (float)($d['costo_est'] ?? 0),
                   $d['aseguradora'] ?? null, $d['poliza_numero'] ?? null,
                   (int)($d['tiene_reclamo'] ?? 0),
                   $d['estado_reclamo'] ?? 'N/A', (float)($d['monto_reclamo'] ?? 0),
                   $d['fecha_reclamo'] ?: null, $d['referencia_reclamo'] ?? null,
                   $d['notas_seguro'] ?? null,
                   $d['prioridad'] ?? null, $resolvedAt, $resolvedBy,
                   $d['id']
               ]);
            audit_log('incidentes', 'update', (int)$d['id'], $prev, $d);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar incidentes.']);
                break;
            }
            $id = (int)$_GET['id'];
            $prevStmt = $db->prepare("SELECT * FROM incidentes WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];
            $db->prepare("UPDATE incidentes SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            audit_log('incidentes', 'soft_delete', $id, $prev, []);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
