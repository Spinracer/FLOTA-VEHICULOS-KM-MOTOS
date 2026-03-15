<?php
/**
 * API — Órdenes de Compra
 * GET    → listar (con filtros: q, estado, solicitante_id, page, per)
 * GET    ?detail=ID → detalle de una orden
 * POST   → crear nueva orden
 * PUT    → actualizar / aprobar / rechazar
 * DELETE ?id=ID → soft-delete
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();

header('Content-Type: application/json');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

    // ─── GET ───
    case 'GET':
        // Detalle
        if (isset($_GET['detail'])) {
            $id = (int)$_GET['detail'];
            $st = $db->prepare("
                SELECT oc.*,
                       u.nombre AS solicitante_nombre,
                       ua.nombre AS aprobador_nombre,
                       v.placa, v.marca, v.modelo,
                       p.nombre AS proveedor_nombre
                FROM ordenes_compra oc
                LEFT JOIN usuarios u ON u.id = oc.solicitante_id
                LEFT JOIN usuarios ua ON ua.id = oc.aprobado_por
                LEFT JOIN vehiculos v ON v.id = oc.vehiculo_id
                LEFT JOIN proveedores p ON p.id = oc.proveedor_id
                WHERE oc.id = ? AND oc.deleted_at IS NULL
            ");
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) { http_response_code(404); echo json_encode(['error' => 'Orden no encontrada.']); break; }
            echo json_encode($row);
            break;
        }

        // Listado
        $q      = trim($_GET['q'] ?? '');
        $estado = trim($_GET['estado'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $per    = max(1, min(100, (int)($_GET['per'] ?? 25)));
        $offset = ($page - 1) * $per;

        $where = ['oc.deleted_at IS NULL'];
        $params = [];

        if ($q !== '') {
            $where[] = "(oc.descripcion LIKE ? OR u.nombre LIKE ? OR v.placa LIKE ? OR p.nombre LIKE ?)";
            $params = array_merge($params, ["%$q%", "%$q%", "%$q%", "%$q%"]);
        }
        if ($estado !== '') {
            $where[] = "oc.estado = ?";
            $params[] = $estado;
        }

        $wSQL = implode(' AND ', $where);

        $cnt = $db->prepare("SELECT COUNT(*) FROM ordenes_compra oc LEFT JOIN usuarios u ON u.id=oc.solicitante_id LEFT JOIN vehiculos v ON v.id=oc.vehiculo_id LEFT JOIN proveedores p ON p.id=oc.proveedor_id WHERE {$wSQL}");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $st = $db->prepare("
            SELECT oc.*, u.nombre AS solicitante_nombre, v.placa, v.marca, p.nombre AS proveedor_nombre
            FROM ordenes_compra oc
            LEFT JOIN usuarios u ON u.id = oc.solicitante_id
            LEFT JOIN vehiculos v ON v.id = oc.vehiculo_id
            LEFT JOIN proveedores p ON p.id = oc.proveedor_id
            WHERE {$wSQL}
            ORDER BY oc.created_at DESC
            LIMIT {$per} OFFSET {$offset}
        ");
        $st->execute($params);
        echo json_encode(['rows' => $st->fetchAll(), 'total' => $total]);
        break;

    // ─── POST ───
    case 'POST':
        if (!can('create')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
        $d = json_decode(file_get_contents('php://input'), true) ?: [];

        $desc = trim($d['descripcion'] ?? '');
        if ($desc === '') { http_response_code(400); echo json_encode(['error' => 'La descripción es obligatoria.']); break; }

        $st = $db->prepare("INSERT INTO ordenes_compra (solicitante_id, vehiculo_id, proveedor_id, descripcion, monto_estimado, urgencia, notas) VALUES (?,?,?,?,?,?,?)");
        $st->execute([
            $_SESSION['user_id'],
            ((int)($d['vehiculo_id'] ?? 0)) ?: null,
            ((int)($d['proveedor_id'] ?? 0)) ?: null,
            $desc,
            ((float)($d['monto_estimado'] ?? 0)) ?: null,
            $d['urgencia'] ?? 'Normal',
            trim($d['notas'] ?? '') ?: null,
        ]);
        $id = (int)$db->lastInsertId();
        audit_log('ordenes_compra', 'create', $id, [], $d);
        echo json_encode(['ok' => true, 'id' => $id]);
        break;

    // ─── PUT ───
    case 'PUT':
        if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = (int)($d['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido.']); break; }

        // Check if it's an approval/rejection action
        $accion = trim($d['_accion'] ?? '');
        if ($accion === 'aprobar' || $accion === 'rechazar') {
            $rol = current_user()['rol'];
            if (!in_array($rol, ['coordinador_it', 'admin'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Solo el coordinador puede aprobar/rechazar órdenes.']);
                break;
            }
            $nuevoEstado = $accion === 'aprobar' ? 'Aprobada' : 'Rechazada';
            $st = $db->prepare("UPDATE ordenes_compra SET estado = ?, aprobado_por = ?, fecha_aprobacion = NOW(), notas_aprobacion = ? WHERE id = ? AND deleted_at IS NULL");
            $st->execute([$nuevoEstado, $_SESSION['user_id'], trim($d['notas_aprobacion'] ?? '') ?: null, $id]);
            audit_log('ordenes_compra', $accion, $id, [], $d);
            echo json_encode(['ok' => true]);
            break;
        }

        // Regular update
        $st = $db->prepare("UPDATE ordenes_compra SET vehiculo_id=?, proveedor_id=?, descripcion=?, monto_estimado=?, urgencia=?, notas=?, estado=? WHERE id=? AND deleted_at IS NULL");
        $st->execute([
            ((int)($d['vehiculo_id'] ?? 0)) ?: null,
            ((int)($d['proveedor_id'] ?? 0)) ?: null,
            trim($d['descripcion'] ?? ''),
            ((float)($d['monto_estimado'] ?? 0)) ?: null,
            $d['urgencia'] ?? 'Normal',
            trim($d['notas'] ?? '') ?: null,
            $d['estado'] ?? 'Pendiente',
            $id,
        ]);
        audit_log('ordenes_compra', 'update', $id, [], $d);
        echo json_encode(['ok' => true]);
        break;

    // ─── DELETE ───
    case 'DELETE':
        if (!can('delete')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido.']); break; }
        $db->prepare("UPDATE ordenes_compra SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
        audit_log('ordenes_compra', 'delete', $id, [], []);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => safe_error_msg($e)]);
}
