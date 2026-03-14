<?php
/**
 * API Sucursales — FlotaControl v2.9
 * CRUD completo con permisos.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

try {
    $action = $_GET['action'] ?? '';

    // ──── Dashboard comparativo entre sucursales ────
    if ($action === 'dashboard') {
        // Vehículos por sucursal
        $veh = $db->query("SELECT s.id, s.nombre, COUNT(v.id) AS vehiculos
            FROM sucursales s LEFT JOIN vehiculos v ON v.sucursal_id=s.id
            WHERE s.activo=1 GROUP BY s.id ORDER BY s.nombre")->fetchAll();

        // Operadores por sucursal
        $ops = $db->query("SELECT s.id, s.nombre, COUNT(o.id) AS operadores
            FROM sucursales s LEFT JOIN operadores o ON o.sucursal_id=s.id AND o.deleted_at IS NULL
            WHERE s.activo=1 GROUP BY s.id ORDER BY s.nombre")->fetchAll();

        // Gasto mantenimiento por sucursal (últimos 12 meses)
        $mant = $db->query("SELECT s.id, s.nombre,
            COALESCE(SUM(m.costo_total),0) AS gasto_mantenimiento,
            COUNT(m.id) AS ordenes
            FROM sucursales s
            LEFT JOIN vehiculos v ON v.sucursal_id=s.id
            LEFT JOIN mantenimientos m ON m.vehiculo_id=v.id AND m.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            WHERE s.activo=1 GROUP BY s.id ORDER BY s.nombre")->fetchAll();

        // Gasto combustible por sucursal (últimos 12 meses)
        $fuel = $db->query("SELECT s.id, s.nombre,
            COALESCE(SUM(c.total),0) AS gasto_combustible,
            COALESCE(SUM(c.litros),0) AS litros
            FROM sucursales s
            LEFT JOIN vehiculos v ON v.sucursal_id=s.id
            LEFT JOIN combustible c ON c.vehiculo_id=v.id AND c.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            WHERE s.activo=1 GROUP BY s.id ORDER BY s.nombre")->fetchAll();

        // Incidentes por sucursal (últimos 12 meses)
        $inc = $db->query("SELECT s.id, s.nombre,
            COUNT(i.id) AS incidentes,
            SUM(CASE WHEN i.estado='Abierto' THEN 1 ELSE 0 END) AS abiertos
            FROM sucursales s
            LEFT JOIN vehiculos v ON v.sucursal_id=s.id
            LEFT JOIN incidentes i ON i.vehiculo_id=v.id AND i.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            WHERE s.activo=1 GROUP BY s.id ORDER BY s.nombre")->fetchAll();

        echo json_encode([
            'vehiculos' => $veh,
            'operadores' => $ops,
            'mantenimiento' => $mant,
            'combustible' => $fuel,
            'incidentes' => $inc,
        ]);
        exit;
    }

    switch ($method) {
        case 'GET':
            $q = '%' . trim($_GET['q'] ?? '') . '%';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per = min(100, max(5, (int)($_GET['per'] ?? 25)));
            $off = ($page - 1) * $per;
            $total = $db->prepare("SELECT COUNT(*) FROM sucursales WHERE nombre LIKE ? OR ciudad LIKE ?");
            $total->execute([$q, $q]);
            $totalCount = (int)$total->fetchColumn();
            $stmt = $db->prepare("SELECT * FROM sucursales WHERE nombre LIKE ? OR ciudad LIKE ? ORDER BY nombre LIMIT ? OFFSET ?");
            $stmt->execute([$q, $q, $per, $off]);
            echo json_encode(['total' => $totalCount, 'rows' => $stmt->fetchAll()]);
            break;

        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para crear sucursales.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $db->prepare("INSERT INTO sucursales (nombre, direccion, ciudad, telefono, responsable) VALUES (?,?,?,?,?)")
                ->execute([trim($d['nombre']), $d['direccion'] ?? null, $d['ciudad'] ?? null, $d['telefono'] ?? null, $d['responsable'] ?? null]);
            $id = (int)$db->lastInsertId();
            audit_log('sucursales', 'create', $id, [], $d);
            echo json_encode(['id' => $id, 'ok' => true]);
            break;

        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para editar sucursales.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $prev = $db->prepare("SELECT * FROM sucursales WHERE id=? LIMIT 1");
            $prev->execute([(int)$d['id']]);
            $prevData = $prev->fetch() ?: [];
            $db->prepare("UPDATE sucursales SET nombre=?, direccion=?, ciudad=?, telefono=?, responsable=?, activo=? WHERE id=?")
                ->execute([trim($d['nombre']), $d['direccion'] ?? null, $d['ciudad'] ?? null, $d['telefono'] ?? null, $d['responsable'] ?? null, (int)($d['activo'] ?? 1), $d['id']]);
            audit_log('sucursales', 'update', (int)$d['id'], $prevData, $d);
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para eliminar sucursales.']);
                break;
            }
            $id = (int)$_GET['id'];
            // Check for references
            $refs = 0;
            foreach (['vehiculos', 'operadores', 'usuarios'] as $tbl) {
                $chk = $db->prepare("SELECT COUNT(*) FROM {$tbl} WHERE sucursal_id = ?");
                $chk->execute([$id]);
                $refs += (int)$chk->fetchColumn();
            }
            if ($refs > 0) {
                http_response_code(409);
                echo json_encode(['error' => "No se puede eliminar: hay {$refs} registros asignados a esta sucursal."]);
                break;
            }
            $prev = $db->prepare("SELECT * FROM sucursales WHERE id=? LIMIT 1");
            $prev->execute([$id]);
            $prevData = $prev->fetch() ?: [];
            $db->prepare("DELETE FROM sucursales WHERE id = ?")->execute([$id]);
            audit_log('sucursales', 'delete', $id, $prevData, []);
            echo json_encode(['ok' => true]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => safe_error_msg($e)]);
}
