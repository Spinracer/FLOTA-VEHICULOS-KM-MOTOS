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

try {
    switch ($method) {
        case 'GET':
            $q    = '%'.trim($_GET['q']??'').'%';
            $vid  = (int)($_GET['vehiculo_id']??0);
            $page = max(1,(int)($_GET['page']??1));
            $per  = min(100,max(5,(int)($_GET['per']??25)));
            $off  = ($page-1)*$per;
            $where = "WHERE (v.placa LIKE ? OR m.tipo LIKE ? OR m.descripcion LIKE ?)";
            $params = [$q, $q, $q];
            if ($vid) { $where .= " AND m.vehiculo_id=?"; $params[] = $vid; }
            $total = $db->prepare("SELECT COUNT(*) FROM mantenimientos m LEFT JOIN vehiculos v ON v.id=m.vehiculo_id $where");
            $total->execute($params);
            $listParams = $params;
            $listParams[] = $per;
            $listParams[] = $off;
            $stmt = $db->prepare("SELECT m.*, v.placa, v.marca, p.nombre AS proveedor_nombre
                FROM mantenimientos m
                LEFT JOIN vehiculos v ON v.id=m.vehiculo_id
                LEFT JOIN proveedores p ON p.id=m.proveedor_id
                $where ORDER BY m.fecha DESC, m.id DESC LIMIT ? OFFSET ?");
            $stmt->execute($listParams);
            echo json_encode(['total'=>(int)$total->fetchColumn(),'rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear mantenimientos.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'),true);
            $km = isset($d['km']) && $d['km'] !== '' ? (float)$d['km'] : null;
            $allowOverride = can('manage_permissions') && !empty($d['override_reason']);
            odometro_validar_km($db, (int)$d['vehiculo_id'], $km, $allowOverride, trim((string)($d['override_reason'] ?? '')) ?: null);
            $stmt = $db->prepare("INSERT INTO mantenimientos (fecha,vehiculo_id,tipo,descripcion,costo,km,proximo_km,proveedor_id,estado) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$d['fecha'],$d['vehiculo_id'],$d['tipo'],$d['descripcion']?:null,(float)$d['costo'],$d['km']?:null,$d['proximo_km']?:null,$d['proveedor_id']?:null,$d['estado']]);
            if ($km) {
                odometro_registrar($db, (int)$d['vehiculo_id'], $km, 'maintenance', (int)($_SESSION['user_id'] ?? 0));
            }
            $newId = (int)$db->lastInsertId();
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
            $km = isset($d['km']) && $d['km'] !== '' ? (float)$d['km'] : null;
            $allowOverride = can('manage_permissions') && !empty($d['override_reason']);
            odometro_validar_km($db, (int)$d['vehiculo_id'], $km, $allowOverride, trim((string)($d['override_reason'] ?? '')) ?: null);
            $stmt = $db->prepare("UPDATE mantenimientos SET fecha=?,vehiculo_id=?,tipo=?,descripcion=?,costo=?,km=?,proximo_km=?,proveedor_id=?,estado=? WHERE id=?");
            $stmt->execute([$d['fecha'],$d['vehiculo_id'],$d['tipo'],$d['descripcion']?:null,(float)$d['costo'],$d['km']?:null,$d['proximo_km']?:null,$d['proveedor_id']?:null,$d['estado'],$d['id']]);
            if ($km) {
                odometro_registrar($db, (int)$d['vehiculo_id'], $km, 'maintenance', (int)($_SESSION['user_id'] ?? 0));
            }
            if ($allowOverride) {
                audit_log('mantenimientos', 'odometro_override', (int)$d['id'], ['km_anterior' => $prev['km'] ?? null], ['km_nuevo' => $km], ['reason' => $d['override_reason']]);
            }
            audit_log('mantenimientos', 'update', (int)$d['id'], $prev, $d);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar mantenimientos.']);
                break;
            }
            $id = (int)$_GET['id'];
            $prevStmt = $db->prepare("SELECT * FROM mantenimientos WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];
            $db->prepare("DELETE FROM mantenimientos WHERE id=?")->execute([$id]);
            audit_log('mantenimientos', 'delete', $id, $prev, []);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
