<?php
/**
 * API: Componentes e inventario por vehículo
 *
 * GET    ?section=catalog            → catálogo maestro de componentes
 * GET    ?section=vehicle&vehiculo_id=X → componentes asignados al vehículo
 * POST   ?section=catalog            → crear componente en catálogo
 * PUT    ?section=catalog            → editar componente en catálogo
 * DELETE ?section=catalog&id=X       → desactivar componente del catálogo
 * POST   ?section=vehicle            → asignar componente a vehículo
 * PUT    ?section=vehicle            → actualizar estado/datos de componente asignado
 * DELETE ?section=vehicle&id=X       → quitar componente de vehículo
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
header('Content-Type: application/json');

$method  = $_SERVER['REQUEST_METHOD'];
$section = trim($_GET['section'] ?? 'catalog');
$db      = getDB();
$user    = current_user();

try {
    // ───────────────────── MOVIMIENTOS DE INVENTARIO ─────────────────────
    if ($section === 'movimientos') {
        if ($method === 'GET') {
            $compId = (int)($_GET['component_id'] ?? 0);
            $where = "WHERE 1=1";
            $params = [];
            if ($compId > 0) { $where .= " AND m.component_id=?"; $params[] = $compId; }
            $vehId = (int)($_GET['vehiculo_id'] ?? 0);
            if ($vehId > 0) { $where .= " AND m.vehiculo_id=?"; $params[] = $vehId; }
            $page = max(1,(int)($_GET['page']??1)); $per = min(100,max(5,(int)($_GET['per']??50))); $off = ($page-1)*$per;
            $total = $db->prepare("SELECT COUNT(*) FROM componente_movimientos m $where");
            $total->execute($params);
            $stmt = $db->prepare("SELECT m.*, c.nombre AS comp_nombre, v.placa, u.nombre AS usuario_nombre
                FROM componente_movimientos m
                JOIN components c ON c.id=m.component_id
                LEFT JOIN vehiculos v ON v.id=m.vehiculo_id
                LEFT JOIN usuarios u ON u.id=m.usuario_id
                $where ORDER BY m.created_at DESC LIMIT ? OFFSET ?");
            $stmt->execute(array_merge($params, [$per, $off]));
            echo json_encode(['total'=>(int)$total->fetchColumn(),'rows'=>$stmt->fetchAll()]);
        } elseif ($method === 'POST') {
            if (!can('create')) { http_response_code(403); echo json_encode(['error'=>'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            if (empty($d['component_id']) || empty($d['tipo'])) {
                http_response_code(422); echo json_encode(['error'=>'component_id y tipo son obligatorios']); exit;
            }
            $db->beginTransaction();
            $db->prepare("INSERT INTO componente_movimientos (component_id,vehiculo_id,tipo,cantidad,referencia,notas,usuario_id) VALUES (?,?,?,?,?,?,?)")
               ->execute([(int)$d['component_id'],$d['vehiculo_id']?:null,$d['tipo'],(int)($d['cantidad']??1),$d['referencia']??null,$d['notas']??null,$user['id']??null]);
            $newId = (int)$db->lastInsertId();
            // Actualizar stock consolidado
            $delta = (int)($d['cantidad'] ?? 1);
            if (in_array($d['tipo'], ['Salida'])) $delta = -$delta;
            $db->prepare("UPDATE components SET stock = stock + ? WHERE id = ?")->execute([$delta, (int)$d['component_id']]);
            $db->commit();
            audit_log('componente_movimientos','create',$newId,[],$d);
            echo json_encode(['id'=>$newId,'ok'=>true]);
        }
        exit;
    }

    // ───────────────────── ALERTAS DE VENCIMIENTO ─────────────────────
    if ($section === 'alertas_vencimiento') {
        $dias = (int)($_GET['dias'] ?? 30);
        $stmt = $db->prepare("SELECT vc.*, c.nombre AS comp_nombre, c.tipo AS comp_tipo, v.placa, v.marca,
            DATEDIFF(vc.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM vehicle_components vc
            JOIN components c ON c.id=vc.component_id
            JOIN vehiculos v ON v.id=vc.vehiculo_id
            WHERE vc.fecha_vencimiento IS NOT NULL AND vc.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY vc.fecha_vencimiento ASC");
        $stmt->execute([$dias]);
        echo json_encode(['rows'=>$stmt->fetchAll()]);
        exit;
    }

    // ───────────────────── CATÁLOGO MAESTRO ─────────────────────
    if ($section === 'catalog') {
        switch ($method) {
            case 'GET':
                $q    = '%' . trim($_GET['q'] ?? '') . '%';
                $tipo = trim($_GET['tipo'] ?? '');
                $activo = trim($_GET['activo'] ?? '');
                $page = max(1, (int)($_GET['page'] ?? 1));
                $per  = min(100, max(5, (int)($_GET['per'] ?? 25)));
                $off  = ($page - 1) * $per;

                $where  = "WHERE (nombre LIKE ? OR descripcion LIKE ?)";
                $params = [$q, $q];

                if ($activo !== '') {
                    $where   .= " AND activo = ?";
                    $params[] = (int)$activo;
                }

                if ($tipo !== '') {
                    $where   .= " AND tipo = ?";
                    $params[] = $tipo;
                }

                $total = $db->prepare("SELECT COUNT(*) FROM components $where");
                $total->execute($params);
                $totalCount = (int)$total->fetchColumn();

                $stmt = $db->prepare("SELECT * FROM components $where ORDER BY nombre ASC LIMIT ? OFFSET ?");
                $stmt->execute(array_merge($params, [$per, $off]));

                echo json_encode(['total' => $totalCount, 'rows' => $stmt->fetchAll()]);
                break;

            case 'POST':
                if (!can('create')) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sin permisos para crear componentes.']);
                    break;
                }
                $d = json_decode(file_get_contents('php://input'), true);
                if (empty($d['nombre'])) {
                    http_response_code(422);
                    echo json_encode(['error' => 'El nombre es obligatorio.']);
                    break;
                }
                $db->prepare("INSERT INTO components (nombre, tipo, descripcion) VALUES (?, ?, ?)")
                   ->execute([$d['nombre'], $d['tipo'] ?? 'tool', $d['descripcion'] ?? null]);
                $newId = (int)$db->lastInsertId();
                audit_log('components', 'create', $newId, [], $d);
                echo json_encode(['id' => $newId, 'ok' => true]);
                break;

            case 'PUT':
                if (!can('edit')) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sin permisos para editar componentes.']);
                    break;
                }
                $d = json_decode(file_get_contents('php://input'), true);
                $prev = $db->prepare("SELECT * FROM components WHERE id = ?");
                $prev->execute([(int)$d['id']]);
                $prevData = $prev->fetch() ?: [];

                // Toggle activo only
                if (isset($d['_toggle'])) {
                    $db->prepare("UPDATE components SET activo = ? WHERE id = ?")
                       ->execute([(int)$d['activo'], (int)$d['id']]);
                    audit_log('components', 'toggle_activo', (int)$d['id'], $prevData, ['activo' => (int)$d['activo']]);
                    echo json_encode(['ok' => true]);
                    break;
                }

                $db->prepare("UPDATE components SET nombre = ?, tipo = ?, descripcion = ?, stock_minimo = ? WHERE id = ?")
                   ->execute([$d['nombre'], $d['tipo'] ?? 'tool', $d['descripcion'] ?? null, (int)($d['stock_minimo'] ?? 0), (int)$d['id']]);
                audit_log('components', 'update', (int)$d['id'], $prevData, $d);
                echo json_encode(['ok' => true]);
                break;

            case 'DELETE':
                if (!can('delete')) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sin permisos para eliminar componentes.']);
                    break;
                }
                $id = (int)$_GET['id'];
                $prev = $db->prepare("SELECT * FROM components WHERE id = ?");
                $prev->execute([$id]);
                $prevData = $prev->fetch() ?: [];

                // Soft-delete: desactivar en vez de borrar
                $db->prepare("UPDATE components SET activo = 0 WHERE id = ?")->execute([$id]);
                audit_log('components', 'soft_delete', $id, $prevData, ['activo' => 0]);
                echo json_encode(['ok' => true]);
                break;
        }

    // ───────────────── COMPONENTES POR VEHÍCULO ─────────────────
    } elseif ($section === 'vehicle') {
        switch ($method) {
            case 'GET':
                $vehiculoId = (int)($_GET['vehiculo_id'] ?? 0);
                if ($vehiculoId <= 0) {
                    http_response_code(422);
                    echo json_encode(['error' => 'vehiculo_id es obligatorio.']);
                    break;
                }
                $q    = '%' . trim($_GET['q'] ?? '') . '%';
                $estado = trim($_GET['estado'] ?? '');
                $page = max(1, (int)($_GET['page'] ?? 1));
                $per  = min(100, max(5, (int)($_GET['per'] ?? 50)));
                $off  = ($page - 1) * $per;

                $where  = "WHERE vc.vehiculo_id = ? AND (c.nombre LIKE ? OR vc.numero_serie LIKE ? OR vc.notas LIKE ?)";
                $params = [$vehiculoId, $q, $q, $q];

                if ($estado !== '') {
                    $where   .= " AND vc.estado = ?";
                    $params[] = $estado;
                }

                $totalStmt = $db->prepare("SELECT COUNT(*) FROM vehicle_components vc JOIN components c ON c.id = vc.component_id $where");
                $totalStmt->execute($params);
                $totalCount = (int)$totalStmt->fetchColumn();

                $stmt = $db->prepare("
                    SELECT vc.*, c.nombre AS componente_nombre, c.tipo AS componente_tipo
                    FROM vehicle_components vc
                    JOIN components c ON c.id = vc.component_id
                    $where
                    ORDER BY c.tipo ASC, c.nombre ASC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute(array_merge($params, [$per, $off]));

                // Resumen de estados
                $resumenStmt = $db->prepare("
                    SELECT vc.estado, COUNT(*) AS cnt
                    FROM vehicle_components vc
                    WHERE vc.vehiculo_id = ?
                    GROUP BY vc.estado
                ");
                $resumenStmt->execute([$vehiculoId]);
                $resumen = [];
                while ($r = $resumenStmt->fetch()) {
                    $resumen[$r['estado']] = (int)$r['cnt'];
                }

                echo json_encode([
                    'total'   => $totalCount,
                    'rows'    => $stmt->fetchAll(),
                    'resumen' => $resumen
                ]);
                break;

            case 'POST':
                if (!can('create')) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sin permisos para asignar componentes.']);
                    break;
                }
                $d = json_decode(file_get_contents('php://input'), true);
                if (empty($d['vehiculo_id']) || empty($d['component_id'])) {
                    http_response_code(422);
                    echo json_encode(['error' => 'vehiculo_id y component_id son obligatorios.']);
                    break;
                }

                // Verificar que no esté duplicado
                $exists = $db->prepare("SELECT id FROM vehicle_components WHERE vehiculo_id = ? AND component_id = ? LIMIT 1");
                $exists->execute([(int)$d['vehiculo_id'], (int)$d['component_id']]);
                if ($exists->fetch()) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Este componente ya está asignado a este vehículo.']);
                    break;
                }

                $db->prepare("INSERT INTO vehicle_components (vehiculo_id, component_id, cantidad, estado, numero_serie, proveedor, fecha_instalacion, fecha_vencimiento, notas) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([
                       (int)$d['vehiculo_id'],
                       (int)$d['component_id'],
                       (int)($d['cantidad'] ?? 1),
                       $d['estado'] ?? 'Bueno',
                       $d['numero_serie'] ?? null,
                       $d['proveedor'] ?? null,
                       $d['fecha_instalacion'] ?? null,
                       $d['fecha_vencimiento'] ?? null,
                       $d['notas'] ?? null,
                   ]);
                $newId = (int)$db->lastInsertId();
                audit_log('vehicle_components', 'create', $newId, [], $d);
                echo json_encode(['id' => $newId, 'ok' => true]);
                break;

            case 'PUT':
                if (!can('edit')) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sin permisos para editar componentes del vehículo.']);
                    break;
                }
                $d = json_decode(file_get_contents('php://input'), true);
                $prev = $db->prepare("SELECT * FROM vehicle_components WHERE id = ?");
                $prev->execute([(int)$d['id']]);
                $prevData = $prev->fetch() ?: [];

                $db->prepare("UPDATE vehicle_components SET cantidad = ?, estado = ?, numero_serie = ?, proveedor = ?, fecha_instalacion = ?, fecha_vencimiento = ?, notas = ? WHERE id = ?")
                   ->execute([
                       (int)($d['cantidad'] ?? 1),
                       $d['estado'] ?? 'Bueno',
                       $d['numero_serie'] ?? null,
                       $d['proveedor'] ?? null,
                       $d['fecha_instalacion'] ?? null,
                       $d['fecha_vencimiento'] ?? null,
                       $d['notas'] ?? null,
                       (int)$d['id'],
                   ]);
                audit_log('vehicle_components', 'update', (int)$d['id'], $prevData, $d);
                echo json_encode(['ok' => true]);
                break;

            case 'DELETE':
                if (!can('delete')) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sin permisos para quitar componentes del vehículo.']);
                    break;
                }
                $id = (int)$_GET['id'];
                $prev = $db->prepare("SELECT * FROM vehicle_components WHERE id = ?");
                $prev->execute([$id]);
                $prevData = $prev->fetch() ?: [];

                $db->prepare("DELETE FROM vehicle_components WHERE id = ?")->execute([$id]);
                audit_log('vehicle_components', 'delete', $id, $prevData, []);
                echo json_encode(['ok' => true]);
                break;
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Sección inválida. Usar: catalog o vehicle']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => safe_error_msg($e)]);
}
