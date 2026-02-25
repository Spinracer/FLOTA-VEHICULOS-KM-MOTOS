<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
try {
    switch ($method) {
        case 'GET':
            $q='%'.trim($_GET['q']??'').'%';
            $page=max(1,(int)($_GET['page']??1)); $per=min(100,max(5,(int)($_GET['per']??25))); $off=($page-1)*$per;
            $total=$db->prepare("SELECT COUNT(*) FROM recordatorios r LEFT JOIN vehiculos v ON v.id=r.vehiculo_id WHERE v.placa LIKE ? OR r.tipo LIKE ? OR r.descripcion LIKE ?");
            $total->execute([$q,$q,$q]);
            $stmt=$db->prepare("SELECT r.*, v.placa, v.marca, DATEDIFF(r.fecha_limite, CURDATE()) as dias
                FROM recordatorios r LEFT JOIN vehiculos v ON v.id=r.vehiculo_id
                WHERE v.placa LIKE ? OR r.tipo LIKE ? OR r.descripcion LIKE ?
                ORDER BY r.fecha_limite ASC LIMIT ? OFFSET ?");
            $stmt->execute([$q,$q,$q,$per,$off]);
            echo json_encode(['total'=>(int)$total->fetchColumn(),'rows'=>$stmt->fetchAll()]);
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
            echo json_encode(['id'=>$db->lastInsertId(),'ok'=>true]);
            break;
        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para editar recordatorios.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
            $db->prepare("UPDATE recordatorios SET vehiculo_id=?,tipo=?,descripcion=?,fecha_limite=?,estado=? WHERE id=?")
               ->execute([$d['vehiculo_id'],$d['tipo'],$d['descripcion']?:null,$d['fecha_limite'],$d['estado'],$d['id']]);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar recordatorios.']);
                break;
            }
            $db->prepare("DELETE FROM recordatorios WHERE id=?")->execute([(int)$_GET['id']]);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
