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
            $q    = '%'.trim($_GET['q']??'').'%';
            $vid  = (int)($_GET['vehiculo_id'] ?? 0);
            $page = max(1,(int)($_GET['page']??1));
            $per  = min(100,max(5,(int)($_GET['per']??25)));
            $off  = ($page-1)*$per;

            $where = "WHERE (v.placa LIKE :q OR v.marca LIKE :q OR p.nombre LIKE :q)";
            $params = [':q' => $q];
            if ($vid) { $where .= " AND c.vehiculo_id = :vid"; $params[':vid'] = $vid; }

            $totalStmt = $db->prepare("SELECT COUNT(*) FROM combustible c
                LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                LEFT JOIN proveedores p ON p.id=c.proveedor_id $where");
            $totalStmt->execute($params);

            $statsStmt = $db->prepare("SELECT COALESCE(SUM(c.litros),0) as litros, COALESCE(SUM(c.total),0) as gasto
                FROM combustible c LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                LEFT JOIN proveedores p ON p.id=c.proveedor_id $where");
            $statsStmt->execute($params);
            $stats = $statsStmt->fetch();

            $params[':per'] = $per; $params[':off'] = $off;
            $stmt = $db->prepare("SELECT c.*, v.placa, v.marca, p.nombre AS proveedor_nombre
                FROM combustible c
                LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                LEFT JOIN proveedores p ON p.id=c.proveedor_id
                $where ORDER BY c.fecha DESC, c.id DESC LIMIT :per OFFSET :off");
            $stmt->execute($params);
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
            require_role('admin','operador');
            $d = json_decode(file_get_contents('php://input'), true);
            $l = (float)$d['litros']; $c = (float)$d['costo_litro'];
            $stmt = $db->prepare("INSERT INTO combustible (fecha,vehiculo_id,litros,costo_litro,total,km,proveedor_id,tipo_carga,notas) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$d['fecha'],$d['vehiculo_id'],$l,$c,round($l*$c,2),$d['km']?:null,$d['proveedor_id']?:null,$d['tipo_carga']??'Lleno',$d['notas']?:null]);
            // Actualizar km del vehículo si es mayor
            if ($d['km']) $db->prepare("UPDATE vehiculos SET km_actual=GREATEST(km_actual,?) WHERE id=?")->execute([$d['km'],$d['vehiculo_id']]);
            echo json_encode(['id'=>$db->lastInsertId(),'ok'=>true]);
            break;

        case 'PUT':
            require_role('admin','operador');
            $d = json_decode(file_get_contents('php://input'), true);
            $l=(float)$d['litros']; $c=(float)$d['costo_litro'];
            $stmt = $db->prepare("UPDATE combustible SET fecha=?,vehiculo_id=?,litros=?,costo_litro=?,total=?,km=?,proveedor_id=?,tipo_carga=?,notas=? WHERE id=?");
            $stmt->execute([$d['fecha'],$d['vehiculo_id'],$l,$c,round($l*$c,2),$d['km']?:null,$d['proveedor_id']?:null,$d['tipo_carga'],$d['notas']?:null,$d['id']]);
            echo json_encode(['ok'=>true]);
            break;

        case 'DELETE':
            require_role('admin');
            $db->prepare("DELETE FROM combustible WHERE id=?")->execute([(int)$_GET['id']]);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
