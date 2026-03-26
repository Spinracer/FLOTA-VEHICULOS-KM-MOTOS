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
    // ───────────── ITEMS (partidas) de OC ─────────────
    $action = $_GET['action'] ?? '';
    if ($action === 'items') {
        $ocId = (int)($_GET['orden_compra_id'] ?? 0);
        if ($ocId <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'orden_compra_id es obligatorio.']);
            exit;
        }
        switch ($method) {
            case 'GET':
                $stmt = $db->prepare("SELECT * FROM orden_compra_items WHERE orden_compra_id = ? ORDER BY id ASC");
                $stmt->execute([$ocId]);
                $items = $stmt->fetchAll();
                $totStmt = $db->prepare("SELECT COALESCE(SUM(subtotal),0) AS total FROM orden_compra_items WHERE orden_compra_id = ?");
                $totStmt->execute([$ocId]);
                $total = (float)$totStmt->fetchColumn();
                echo json_encode(['items' => $items, 'total' => $total]);
                break;

            case 'POST':
                if (!can('create')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
                $d = json_decode(file_get_contents('php://input'), true);
                if (empty($d['descripcion'])) { http_response_code(422); echo json_encode(['error' => 'La descripción es obligatoria.']); break; }

                $db->prepare("INSERT INTO orden_compra_items (orden_compra_id, descripcion, cantidad, unidad, precio_unitario, notas, component_id) VALUES (?,?,?,?,?,?,?)")
                   ->execute([
                       $ocId, $d['descripcion'],
                       (float)($d['cantidad'] ?? 1),
                       $d['unidad'] ?? 'PZA',
                       (float)($d['precio_unitario'] ?? 0),
                       $d['notas'] ?? null,
                       !empty($d['component_id']) ? (int)$d['component_id'] : null,
                   ]);
                $newId = (int)$db->lastInsertId();
                // Auto-recalcular monto_estimado
                $db->prepare("UPDATE ordenes_compra SET monto_estimado = (SELECT COALESCE(SUM(subtotal),0) FROM orden_compra_items WHERE orden_compra_id = ?) WHERE id = ?")
                   ->execute([$ocId, $ocId]);
                audit_log('orden_compra_items', 'create', $newId, [], $d);
                echo json_encode(['id' => $newId, 'ok' => true]);
                break;

            case 'PUT':
                if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
                $d = json_decode(file_get_contents('php://input'), true);
                $db->prepare("UPDATE orden_compra_items SET descripcion=?, cantidad=?, unidad=?, precio_unitario=?, notas=?, component_id=? WHERE id=?")
                   ->execute([
                       $d['descripcion'], (float)($d['cantidad'] ?? 1), $d['unidad'] ?? 'PZA',
                       (float)($d['precio_unitario'] ?? 0), $d['notas'] ?? null,
                       !empty($d['component_id']) ? (int)$d['component_id'] : null,
                       (int)$d['id'],
                   ]);
                $db->prepare("UPDATE ordenes_compra SET monto_estimado = (SELECT COALESCE(SUM(subtotal),0) FROM orden_compra_items WHERE orden_compra_id = ?) WHERE id = ?")
                   ->execute([$ocId, $ocId]);
                audit_log('orden_compra_items', 'update', (int)$d['id'], [], $d);
                echo json_encode(['ok' => true]);
                break;

            case 'DELETE':
                if (!can('delete')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
                $itemId = (int)$_GET['item_id'];
                $db->prepare("DELETE FROM orden_compra_items WHERE id = ?")->execute([$itemId]);
                $db->prepare("UPDATE ordenes_compra SET monto_estimado = (SELECT COALESCE(SUM(subtotal),0) FROM orden_compra_items WHERE orden_compra_id = ?) WHERE id = ?")
                   ->execute([$ocId, $ocId]);
                audit_log('orden_compra_items', 'delete', $itemId, [], []);
                echo json_encode(['ok' => true]);
                break;
        }
        exit;
    }

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
            SELECT oc.*, u.nombre AS solicitante_nombre, v.placa, v.marca, p.nombre AS proveedor_nombre,
                   (SELECT COUNT(*) FROM orden_compra_items oci WHERE oci.orden_compra_id = oc.id) AS items_count
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
        $vehiculoId = (int)($d['vehiculo_id'] ?? 0);
        if ($vehiculoId <= 0) { http_response_code(400); echo json_encode(['error' => 'Debes seleccionar un vehículo.']); break; }

        $st = $db->prepare("INSERT INTO ordenes_compra (solicitante_id, vehiculo_id, proveedor_id, descripcion, monto_estimado, urgencia, estado, notas) VALUES (?,?,?,?,?,?,?,?)");
        $st->execute([
            $_SESSION['user_id'],
            $vehiculoId,
            ((int)($d['proveedor_id'] ?? 0)) ?: null,
            $desc,
            ((float)($d['monto_estimado'] ?? 0)) ?: null,
            $d['urgencia'] ?? 'Normal',
            'Pendiente',
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

        // Check if it's an approval/rejection action or quick status change
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
        if ($accion === 'cambiar_estado') {
            $rol = current_user()['rol'];
            if (!in_array($rol, ['coordinador_it', 'admin'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Solo el coordinador puede cambiar estados.']);
                break;
            }
            $estadosValidos = ['Pendiente', 'Aprobada', 'Rechazada', 'Completada', 'Cancelada'];
            $nuevoEstado = trim($d['estado'] ?? '');
            if (!in_array($nuevoEstado, $estadosValidos)) {
                http_response_code(400);
                echo json_encode(['error' => 'Estado no válido.']);
                break;
            }
            $updates = "estado = ?";
            $params = [$nuevoEstado];
            if ($nuevoEstado === 'Aprobada' || $nuevoEstado === 'Rechazada') {
                $updates .= ", aprobado_por = ?, fecha_aprobacion = NOW(), notas_aprobacion = ?";
                $params[] = $_SESSION['user_id'];
                $params[] = trim($d['notas_aprobacion'] ?? '') ?: null;
            }
            $params[] = $id;
            $st = $db->prepare("UPDATE ordenes_compra SET {$updates} WHERE id = ? AND deleted_at IS NULL");
            $st->execute($params);
            audit_log('ordenes_compra', 'cambiar_estado', $id, [], ['estado' => $nuevoEstado]);
            echo json_encode(['ok' => true]);
            break;
        }

        // Regular update (no estado change here, use cambiar_estado action)
        $vehiculoId = (int)($d['vehiculo_id'] ?? 0);
        if ($vehiculoId <= 0) { http_response_code(400); echo json_encode(['error' => 'Debes seleccionar un vehículo.']); break; }
        $st = $db->prepare("UPDATE ordenes_compra SET vehiculo_id=?, proveedor_id=?, descripcion=?, monto_estimado=?, urgencia=?, notas=? WHERE id=? AND deleted_at IS NULL");
        $st->execute([
            $vehiculoId,
            ((int)($d['proveedor_id'] ?? 0)) ?: null,
            trim($d['descripcion'] ?? ''),
            ((float)($d['monto_estimado'] ?? 0)) ?: null,
            $d['urgencia'] ?? 'Normal',
            trim($d['notas'] ?? '') ?: null,
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
