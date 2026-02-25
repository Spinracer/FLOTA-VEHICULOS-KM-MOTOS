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
            $q='%'.trim($_GET['q']??'').'%'; $vid=(int)($_GET['vehiculo_id']??0);
            $page=max(1,(int)($_GET['page']??1)); $per=min(100,max(5,(int)($_GET['per']??25))); $off=($page-1)*$per;
            $where="WHERE (v.placa LIKE :q OR i.tipo LIKE :q OR i.descripcion LIKE :q OR i.estado LIKE :q)";
            $params=[':q'=>$q];
            if ($vid){$where.=" AND i.vehiculo_id=:vid";$params[':vid']=$vid;}
            $total=$db->prepare("SELECT COUNT(*) FROM incidentes i LEFT JOIN vehiculos v ON v.id=i.vehiculo_id $where");
            $total->execute($params);
            $params[':per']=$per;$params[':off']=$off;
            $stmt=$db->prepare("SELECT i.*,v.placa,v.marca FROM incidentes i LEFT JOIN vehiculos v ON v.id=i.vehiculo_id $where ORDER BY i.fecha DESC,i.id DESC LIMIT :per OFFSET :off");
            $stmt->execute($params);
            echo json_encode(['total'=>(int)$total->fetchColumn(),'rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            require_role('admin','operador');
            $d=json_decode(file_get_contents('php://input'),true);
            $db->prepare("INSERT INTO incidentes (fecha,vehiculo_id,tipo,descripcion,severidad,estado,costo_est) VALUES (?,?,?,?,?,?,?)")
               ->execute([$d['fecha'],$d['vehiculo_id'],$d['tipo'],$d['descripcion'],$d['severidad'],$d['estado'],(float)$d['costo_est']]);
            echo json_encode(['id'=>$db->lastInsertId(),'ok'=>true]);
            break;
        case 'PUT':
            require_role('admin','operador');
            $d=json_decode(file_get_contents('php://input'),true);
            $db->prepare("UPDATE incidentes SET fecha=?,vehiculo_id=?,tipo=?,descripcion=?,severidad=?,estado=?,costo_est=? WHERE id=?")
               ->execute([$d['fecha'],$d['vehiculo_id'],$d['tipo'],$d['descripcion'],$d['severidad'],$d['estado'],(float)$d['costo_est'],$d['id']]);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            require_role('admin');
            $db->prepare("DELETE FROM incidentes WHERE id=?")->execute([(int)$_GET['id']]);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
