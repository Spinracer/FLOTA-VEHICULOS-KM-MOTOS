<?php
// api/mantenimientos.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
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
            $where = "WHERE (v.placa LIKE :q OR m.tipo LIKE :q OR m.descripcion LIKE :q)";
            $params = [':q'=>$q];
            if ($vid) { $where .= " AND m.vehiculo_id=:vid"; $params[':vid']=$vid; }
            $total = $db->prepare("SELECT COUNT(*) FROM mantenimientos m LEFT JOIN vehiculos v ON v.id=m.vehiculo_id $where");
            $total->execute($params);
            $params[':per']=$per; $params[':off']=$off;
            $stmt = $db->prepare("SELECT m.*, v.placa, v.marca, p.nombre AS proveedor_nombre
                FROM mantenimientos m
                LEFT JOIN vehiculos v ON v.id=m.vehiculo_id
                LEFT JOIN proveedores p ON p.id=m.proveedor_id
                $where ORDER BY m.fecha DESC, m.id DESC LIMIT :per OFFSET :off");
            $stmt->execute($params);
            echo json_encode(['total'=>(int)$total->fetchColumn(),'rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            require_role('admin','operador');
            $d = json_decode(file_get_contents('php://input'),true);
            $stmt = $db->prepare("INSERT INTO mantenimientos (fecha,vehiculo_id,tipo,descripcion,costo,km,proximo_km,proveedor_id,estado) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$d['fecha'],$d['vehiculo_id'],$d['tipo'],$d['descripcion']?:null,(float)$d['costo'],$d['km']?:null,$d['proximo_km']?:null,$d['proveedor_id']?:null,$d['estado']]);
            if ($d['km']) $db->prepare("UPDATE vehiculos SET km_actual=GREATEST(km_actual,?) WHERE id=?")->execute([$d['km'],$d['vehiculo_id']]);
            echo json_encode(['id'=>$db->lastInsertId(),'ok'=>true]);
            break;
        case 'PUT':
            require_role('admin','operador');
            $d = json_decode(file_get_contents('php://input'),true);
            $stmt = $db->prepare("UPDATE mantenimientos SET fecha=?,vehiculo_id=?,tipo=?,descripcion=?,costo=?,km=?,proximo_km=?,proveedor_id=?,estado=? WHERE id=?");
            $stmt->execute([$d['fecha'],$d['vehiculo_id'],$d['tipo'],$d['descripcion']?:null,(float)$d['costo'],$d['km']?:null,$d['proximo_km']?:null,$d['proveedor_id']?:null,$d['estado'],$d['id']]);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            require_role('admin');
            $db->prepare("DELETE FROM mantenimientos WHERE id=?")->execute([(int)$_GET['id']]);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
