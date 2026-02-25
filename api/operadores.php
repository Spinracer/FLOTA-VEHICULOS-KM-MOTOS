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
            $total=$db->prepare("SELECT COUNT(*) FROM operadores WHERE nombre LIKE ? OR licencia LIKE ? OR telefono LIKE ?");
            $total->execute([$q,$q,$q]);
            $stmt=$db->prepare("SELECT o.*, v.placa as vehiculo_placa, v.marca as vehiculo_marca,
                DATEDIFF(o.venc_licencia, CURDATE()) as dias_licencia
                FROM operadores o LEFT JOIN vehiculos v ON v.operador_id=o.id
                WHERE o.nombre LIKE ? OR o.licencia LIKE ? OR o.telefono LIKE ?
                ORDER BY o.nombre ASC LIMIT ? OFFSET ?");
            $stmt->execute([$q,$q,$q,$per,$off]);
            echo json_encode(['total'=>(int)$total->fetchColumn(),'rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear operadores.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
            $db->prepare("INSERT INTO operadores (nombre,licencia,categoria_lic,venc_licencia,telefono,email,estado,notas) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$d['nombre'],$d['licencia']?:null,$d['categoria_lic']?:null,$d['venc_licencia']?:null,$d['telefono']?:null,$d['email']?:null,$d['estado'],$d['notas']?:null]);
            echo json_encode(['id'=>$db->lastInsertId(),'ok'=>true]);
            break;
        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para editar operadores.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
            $db->prepare("UPDATE operadores SET nombre=?,licencia=?,categoria_lic=?,venc_licencia=?,telefono=?,email=?,estado=?,notas=? WHERE id=?")
               ->execute([$d['nombre'],$d['licencia']?:null,$d['categoria_lic']?:null,$d['venc_licencia']?:null,$d['telefono']?:null,$d['email']?:null,$d['estado'],$d['notas']?:null,$d['id']]);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar operadores.']);
                break;
            }
            $db->prepare("DELETE FROM operadores WHERE id=?")->execute([(int)$_GET['id']]);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
