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
            $soloAut = (int)($_GET['solo_autorizados'] ?? 0);
            $page=max(1,(int)($_GET['page']??1)); $per=min(100,max(5,(int)($_GET['per']??25))); $off=($page-1)*$per;
            $where = "WHERE deleted_at IS NULL AND (nombre LIKE ? OR tipo LIKE ? OR telefono LIKE ?)";
            $params = [$q, $q, $q];
            if ($soloAut === 1) {
                $where .= " AND es_taller_autorizado=1";
            }
            $total=$db->prepare("SELECT COUNT(*) FROM proveedores $where");
            $total->execute($params);
            $stmt=$db->prepare("SELECT * FROM proveedores $where ORDER BY nombre ASC LIMIT ? OFFSET ?");
            $stmt->execute(array_merge($params, [$per,$off]));
            echo json_encode(['total'=>(int)$total->fetchColumn(),'rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear proveedores.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
                $db->prepare("INSERT INTO proveedores (nombre,tipo,es_taller_autorizado,telefono,email,direccion,notas) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$d['nombre'],$d['tipo'],(int)($d['es_taller_autorizado'] ?? 0),$d['telefono']?:null,$d['email']?:null,$d['direccion']?:null,$d['notas']?:null]);
                $newId = (int)$db->lastInsertId();
                audit_log('proveedores', 'create', $newId, [], $d);
                echo json_encode(['id'=>$newId,'ok'=>true]);
            break;
        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para editar proveedores.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
                $prevStmt = $db->prepare("SELECT * FROM proveedores WHERE id=? LIMIT 1");
                $prevStmt->execute([(int)$d['id']]);
                $prev = $prevStmt->fetch() ?: [];
            $db->prepare("UPDATE proveedores SET nombre=?,tipo=?,es_taller_autorizado=?,telefono=?,email=?,direccion=?,notas=? WHERE id=?")
               ->execute([$d['nombre'],$d['tipo'],(int)($d['es_taller_autorizado'] ?? 0),$d['telefono']?:null,$d['email']?:null,$d['direccion']?:null,$d['notas']?:null,$d['id']]);
                audit_log('proveedores', 'update', (int)$d['id'], $prev, $d);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar proveedores.']);
                break;
            }
            $id = (int)$_GET['id'];
            $prevStmt = $db->prepare("SELECT * FROM proveedores WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];
            $db->prepare("UPDATE proveedores SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            audit_log('proveedores', 'soft_delete', $id, $prev, []);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
