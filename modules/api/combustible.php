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
            $q    = '%'.trim($_GET['q']??'').'%';
            $vid  = (int)($_GET['vehiculo_id'] ?? 0);
            $page = max(1,(int)($_GET['page']??1));
            $per  = min(100,max(5,(int)($_GET['per']??25)));
            $off  = ($page-1)*$per;

            $where = "WHERE (v.placa LIKE ? OR v.marca LIKE ? OR p.nombre LIKE ?)";
            $params = [$q, $q, $q];
            if ($vid) { $where .= " AND c.vehiculo_id = ?"; $params[] = $vid; }

            $totalStmt = $db->prepare("SELECT COUNT(*) FROM combustible c
                LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                LEFT JOIN proveedores p ON p.id=c.proveedor_id $where");
            $totalStmt->execute($params);

            $statsStmt = $db->prepare("SELECT COALESCE(SUM(c.litros),0) as litros, COALESCE(SUM(c.total),0) as gasto
                FROM combustible c LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                LEFT JOIN proveedores p ON p.id=c.proveedor_id $where");
            $statsStmt->execute($params);
            $stats = $statsStmt->fetch();

            $listParams = $params;
            $listParams[] = $per;
            $listParams[] = $off;
            $stmt = $db->prepare("SELECT c.*, v.placa, v.marca, p.nombre AS proveedor_nombre
                FROM combustible c
                LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                LEFT JOIN proveedores p ON p.id=c.proveedor_id
                $where ORDER BY c.fecha DESC, c.id DESC LIMIT ? OFFSET ?");
            $stmt->execute($listParams);
            $rows = $stmt->fetchAll();

            // Calcular rendimiento para cada fila
            foreach ($rows as &$row) {
                if ($row['km'] > 0) {
                    $prev = $db->prepare("SELECT km, litros FROM combustible WHERE vehiculo_id=? AND km>0 AND km<? AND litros>0 ORDER BY km DESC LIMIT 1");
                    $prev->execute([$row['vehiculo_id'], $row['km']]);
                    $p = $prev->fetch();
                    $row['rendimiento'] = ($p && $row['litros'] > 0) ? ($row['km'] - $p['km']) / $row['litros'] : null;
                } else {
                    $row['rendimiento'] = null;
                }
            }

            echo json_encode(['total'=>(int)$totalStmt->fetchColumn() ?: count($rows)+$off, 'stats'=>$stats, 'rows'=>$rows]);
            break;

        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear registros de combustible.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $km = isset($d['km']) && $d['km'] !== '' ? (float)$d['km'] : null;
            $allowOverride = can('manage_permissions') && !empty($d['override_reason']);
            odometro_validar_km($db, (int)$d['vehiculo_id'], $km, $allowOverride, trim((string)($d['override_reason'] ?? '')) ?: null);
            $l = (float)$d['litros']; $c = (float)$d['costo_litro'];
            $stmt = $db->prepare("INSERT INTO combustible (fecha,vehiculo_id,litros,costo_litro,total,km,proveedor_id,tipo_carga,notas) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$d['fecha'],$d['vehiculo_id'],$l,$c,round($l*$c,2),$d['km']?:null,$d['proveedor_id']?:null,$d['tipo_carga']??'Lleno',$d['notas']?:null]);
            // Actualizar km del vehículo si es mayor
            if ($km) {
                odometro_registrar($db, (int)$d['vehiculo_id'], $km, 'fuel', (int)($_SESSION['user_id'] ?? 0));
            }
            $newId = (int)$db->lastInsertId();
            if ($allowOverride) {
                audit_log('combustible', 'odometro_override', $newId, [], ['km_nuevo' => $km], ['reason' => $d['override_reason']]);
            }
            audit_log('combustible', 'create', $newId, [], $d);
            echo json_encode(['id'=>$newId,'ok'=>true]);
            break;

        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para editar registros de combustible.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $prevStmt = $db->prepare("SELECT * FROM combustible WHERE id=? LIMIT 1");
            $prevStmt->execute([(int)$d['id']]);
            $prev = $prevStmt->fetch() ?: [];
            $km = isset($d['km']) && $d['km'] !== '' ? (float)$d['km'] : null;
            $allowOverride = can('manage_permissions') && !empty($d['override_reason']);
            odometro_validar_km($db, (int)$d['vehiculo_id'], $km, $allowOverride, trim((string)($d['override_reason'] ?? '')) ?: null);
            $l=(float)$d['litros']; $c=(float)$d['costo_litro'];
            $stmt = $db->prepare("UPDATE combustible SET fecha=?,vehiculo_id=?,litros=?,costo_litro=?,total=?,km=?,proveedor_id=?,tipo_carga=?,notas=? WHERE id=?");
            $stmt->execute([$d['fecha'],$d['vehiculo_id'],$l,$c,round($l*$c,2),$d['km']?:null,$d['proveedor_id']?:null,$d['tipo_carga'],$d['notas']?:null,$d['id']]);
            if ($km) {
                odometro_registrar($db, (int)$d['vehiculo_id'], $km, 'fuel', (int)($_SESSION['user_id'] ?? 0));
            }
            if ($allowOverride) {
                audit_log('combustible', 'odometro_override', (int)$d['id'], ['km_anterior' => $prev['km'] ?? null], ['km_nuevo' => $km], ['reason' => $d['override_reason']]);
            }
            audit_log('combustible', 'update', (int)$d['id'], $prev, $d);
            echo json_encode(['ok'=>true]);
            break;

        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar registros de combustible.']);
                break;
            }
            $id = (int)$_GET['id'];
            $prevStmt = $db->prepare("SELECT * FROM combustible WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];
            $db->prepare("DELETE FROM combustible WHERE id=?")->execute([$id]);
            audit_log('combustible', 'delete', $id, $prev, []);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
