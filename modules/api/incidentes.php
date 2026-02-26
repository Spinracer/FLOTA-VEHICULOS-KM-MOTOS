<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
try {
    switch ($method) {
        case 'GET':
            $q='%'.trim($_GET['q']??'').'%'; $vid=(int)($_GET['vehiculo_id']??0);
            $page=max(1,(int)($_GET['page']??1)); $per=min(100,max(5,(int)($_GET['per']??25))); $off=($page-1)*$per;
            $where="WHERE (v.placa LIKE ? OR i.tipo LIKE ? OR i.descripcion LIKE ? OR i.estado LIKE ?)";
            $params=[$q,$q,$q,$q];
            if ($vid){$where.=" AND i.vehiculo_id=?"; $params[] = $vid;}
            $total=$db->prepare("SELECT COUNT(*) FROM incidentes i LEFT JOIN vehiculos v ON v.id=i.vehiculo_id $where");
            $total->execute($params);
            $listParams = $params;
            $listParams[] = $per;
            $listParams[] = $off;
            $stmt=$db->prepare("SELECT i.*,v.placa,v.marca FROM incidentes i LEFT JOIN vehiculos v ON v.id=i.vehiculo_id $where ORDER BY i.fecha DESC,i.id DESC LIMIT ? OFFSET ?");
            $stmt->execute($listParams);
            echo json_encode(['total'=>(int)$total->fetchColumn(),'rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear incidentes.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
            $db->prepare("INSERT INTO incidentes (fecha,vehiculo_id,tipo,descripcion,severidad,estado,costo_est) VALUES (?,?,?,?,?,?,?)")
               ->execute([$d['fecha'],$d['vehiculo_id'],$d['tipo'],$d['descripcion'],$d['severidad'],$d['estado'],(float)$d['costo_est']]);
                $newId = (int)$db->lastInsertId();
                audit_log('incidentes', 'create', $newId, [], $d);
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
            $db->prepare("UPDATE incidentes SET fecha=?,vehiculo_id=?,tipo=?,descripcion=?,severidad=?,estado=?,costo_est=? WHERE id=?")
               ->execute([$d['fecha'],$d['vehiculo_id'],$d['tipo'],$d['descripcion'],$d['severidad'],$d['estado'],(float)$d['costo_est'],$d['id']]);
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
            $db->prepare("DELETE FROM incidentes WHERE id=?")->execute([$id]);
            audit_log('incidentes', 'delete', $id, $prev, []);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
