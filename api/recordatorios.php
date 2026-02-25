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
            $total=$db->prepare("SELECT COUNT(*) FROM recordatorios r LEFT JOIN vehiculos v ON v.id=r.vehiculo_id WHERE v.placa LIKE :q OR r.tipo LIKE :q OR r.descripcion LIKE :q");
            $total->execute([':q'=>$q]);
            $stmt=$db->prepare("SELECT r.*, v.placa, v.marca, DATEDIFF(r.fecha_limite, CURDATE()) as dias
                FROM recordatorios r LEFT JOIN vehiculos v ON v.id=r.vehiculo_id
                WHERE v.placa LIKE :q OR r.tipo LIKE :q OR r.descripcion LIKE :q
                ORDER BY r.fecha_limite ASC LIMIT :per OFFSET :off");
            $stmt->execute([':q'=>$q,':per'=>$per,':off'=>$off]);
            echo json_encode(['total'=>(int)$total->fetchColumn(),'rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            require_role('admin','operador');
            $d=json_decode(file_get_contents('php://input'),true);
            $db->prepare("INSERT INTO recordatorios (vehiculo_id,tipo,descripcion,fecha_limite,estado) VALUES (?,?,?,?,?)")
               ->execute([$d['vehiculo_id'],$d['tipo'],$d['descripcion']?:null,$d['fecha_limite'],$d['estado']]);
            echo json_encode(['id'=>$db->lastInsertId(),'ok'=>true]);
            break;
        case 'PUT':
            require_role('admin','operador');
            $d=json_decode(file_get_contents('php://input'),true);
            $db->prepare("UPDATE recordatorios SET vehiculo_id=?,tipo=?,descripcion=?,fecha_limite=?,estado=? WHERE id=?")
               ->execute([$d['vehiculo_id'],$d['tipo'],$d['descripcion']?:null,$d['fecha_limite'],$d['estado'],$d['id']]);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            require_role('admin');
            $db->prepare("DELETE FROM recordatorios WHERE id=?")->execute([(int)$_GET['id']]);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
