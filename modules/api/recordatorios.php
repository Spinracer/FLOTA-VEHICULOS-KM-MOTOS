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
            $q='%'.trim($_GET['q']??'').'%';
            $estado = trim($_GET['estado'] ?? '');
            $page=max(1,(int)($_GET['page']??1)); $per=min(100,max(5,(int)($_GET['per']??25))); $off=($page-1)*$per;

            $where = "WHERE r.deleted_at IS NULL AND (v.placa LIKE ? OR r.tipo LIKE ? OR r.descripcion LIKE ?)";
            $params = [$q,$q,$q];
            if ($estado !== '') { $where .= " AND r.estado = ?"; $params[] = $estado; }

            $total=$db->prepare("SELECT COUNT(*) FROM recordatorios r LEFT JOIN vehiculos v ON v.id=r.vehiculo_id $where");
            $total->execute($params);
            $totalCount = (int)$total->fetchColumn();

            $listParams = $params;
            $listParams[] = $per;
            $listParams[] = $off;
            $stmt=$db->prepare("SELECT r.*, v.placa, v.marca, DATEDIFF(r.fecha_limite, CURDATE()) as dias
                FROM recordatorios r LEFT JOIN vehiculos v ON v.id=r.vehiculo_id
                $where
                ORDER BY r.fecha_limite ASC LIMIT ? OFFSET ?");
            $stmt->execute($listParams);
            echo json_encode(['total'=>$totalCount,'rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear recordatorios.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
            $db->prepare("INSERT INTO recordatorios (vehiculo_id,tipo,descripcion,fecha_limite,estado) VALUES (?,?,?,?,?)")
               ->execute([$d['vehiculo_id'],$d['tipo'],$d['descripcion']?:null,$d['fecha_limite'],$d['estado']]);
                $newId = (int)$db->lastInsertId();
                audit_log('recordatorios', 'create', $newId, [], $d);
                echo json_encode(['id'=>$newId,'ok'=>true]);
            break;
        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para editar recordatorios.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
                $prevStmt = $db->prepare("SELECT * FROM recordatorios WHERE id=? LIMIT 1");
                $prevStmt->execute([(int)$d['id']]);
                $prev = $prevStmt->fetch() ?: [];
            $db->prepare("UPDATE recordatorios SET vehiculo_id=?,tipo=?,descripcion=?,fecha_limite=?,estado=? WHERE id=?")
               ->execute([$d['vehiculo_id'],$d['tipo'],$d['descripcion']?:null,$d['fecha_limite'],$d['estado'],$d['id']]);
                audit_log('recordatorios', 'update', (int)$d['id'], $prev, $d);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar recordatorios.']);
                break;
            }
            $id = (int)$_GET['id'];
            $prevStmt = $db->prepare("SELECT * FROM recordatorios WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];
            $db->prepare("UPDATE recordatorios SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            audit_log('recordatorios', 'soft_delete', $id, $prev, []);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>safe_error_msg($e)]); }
