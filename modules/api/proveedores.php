<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$user = current_user();
$action = $_GET['action'] ?? '';
try {

    // ──── Evaluaciones de proveedores ────
    if ($action === 'evaluaciones') {
        if ($method === 'GET') {
            $provId = (int)($_GET['proveedor_id'] ?? 0);
            if ($provId <= 0) { http_response_code(400); echo json_encode(['error'=>'proveedor_id requerido']); exit; }
            $stmt = $db->prepare("SELECT e.*, u.nombre AS evaluador FROM proveedor_evaluaciones e LEFT JOIN usuarios u ON u.id=e.usuario_id WHERE e.proveedor_id=? ORDER BY e.created_at DESC");
            $stmt->execute([$provId]);
            echo json_encode(['rows'=>$stmt->fetchAll()]);
        } elseif ($method === 'POST') {
            if (!can('create')) { http_response_code(403); echo json_encode(['error'=>'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            if (empty($d['proveedor_id']) || empty($d['periodo'])) {
                http_response_code(422); echo json_encode(['error'=>'proveedor_id y periodo obligatorios']); exit;
            }
            $db->prepare("INSERT INTO proveedor_evaluaciones (proveedor_id,periodo,calidad,puntualidad,precio,servicio,comentario,usuario_id) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([(int)$d['proveedor_id'],$d['periodo'],(int)($d['calidad']??3),(int)($d['puntualidad']??3),(int)($d['precio']??3),(int)($d['servicio']??3),$d['comentario']??null,$user['id']??null]);
            $newId = (int)$db->lastInsertId();
            audit_log('proveedor_evaluaciones','create',$newId,[],$d);
            echo json_encode(['id'=>$newId,'ok'=>true]);
        } elseif ($method === 'DELETE') {
            if (!can('delete')) { http_response_code(403); echo json_encode(['error'=>'Sin permisos']); exit; }
            $db->prepare("DELETE FROM proveedor_evaluaciones WHERE id=?")->execute([(int)($_GET['id']??0)]);
            echo json_encode(['ok'=>true]);
        }
        exit;
    }

    // ──── Contratos de proveedores ────
    if ($action === 'contratos') {
        if ($method === 'GET') {
            $provId = (int)($_GET['proveedor_id'] ?? 0);
            if ($provId <= 0) { http_response_code(400); echo json_encode(['error'=>'proveedor_id requerido']); exit; }
            $stmt = $db->prepare("SELECT * FROM proveedor_contratos WHERE proveedor_id=? ORDER BY fecha_inicio DESC");
            $stmt->execute([$provId]);
            echo json_encode(['rows'=>$stmt->fetchAll()]);
        } elseif ($method === 'POST') {
            if (!can('create')) { http_response_code(403); echo json_encode(['error'=>'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            if (empty($d['proveedor_id']) || empty($d['titulo']) || empty($d['fecha_inicio'])) {
                http_response_code(422); echo json_encode(['error'=>'proveedor_id, titulo y fecha_inicio obligatorios']); exit;
            }
            $db->prepare("INSERT INTO proveedor_contratos (proveedor_id,titulo,numero_contrato,fecha_inicio,fecha_fin,monto,tipo,estado,notas) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([(int)$d['proveedor_id'],$d['titulo'],$d['numero_contrato']??null,$d['fecha_inicio'],$d['fecha_fin']??null,(float)($d['monto']??0),$d['tipo']??'Servicio',$d['estado']??'Vigente',$d['notas']??null]);
            $newId = (int)$db->lastInsertId();
            audit_log('proveedor_contratos','create',$newId,[],$d);
            echo json_encode(['id'=>$newId,'ok'=>true]);
        } elseif ($method === 'PUT') {
            if (!can('edit')) { http_response_code(403); echo json_encode(['error'=>'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            $db->prepare("UPDATE proveedor_contratos SET titulo=?,numero_contrato=?,fecha_inicio=?,fecha_fin=?,monto=?,tipo=?,estado=?,notas=? WHERE id=?")
               ->execute([$d['titulo'],$d['numero_contrato']??null,$d['fecha_inicio'],$d['fecha_fin']??null,(float)($d['monto']??0),$d['tipo']??'Servicio',$d['estado']??'Vigente',$d['notas']??null,(int)$d['id']]);
            audit_log('proveedor_contratos','update',(int)$d['id'],[],['estado'=>$d['estado']]);
            echo json_encode(['ok'=>true]);
        } elseif ($method === 'DELETE') {
            if (!can('delete')) { http_response_code(403); echo json_encode(['error'=>'Sin permisos']); exit; }
            $db->prepare("DELETE FROM proveedor_contratos WHERE id=?")->execute([(int)($_GET['id']??0)]);
            echo json_encode(['ok'=>true]);
        }
        exit;
    }

    // ──── Ranking de proveedores ────
    if ($action === 'ranking') {
        $stmt = $db->query("SELECT p.id, p.nombre, p.tipo,
            COUNT(e.id) AS evaluaciones,
            ROUND(AVG(e.calidad),1) AS avg_calidad,
            ROUND(AVG(e.puntualidad),1) AS avg_puntualidad,
            ROUND(AVG(e.precio),1) AS avg_precio,
            ROUND(AVG(e.servicio),1) AS avg_servicio,
            ROUND(AVG(e.promedio),2) AS avg_total
            FROM proveedores p
            JOIN proveedor_evaluaciones e ON e.proveedor_id=p.id
            WHERE p.deleted_at IS NULL
            GROUP BY p.id
            ORDER BY avg_total DESC");
        echo json_encode(['rows'=>$stmt->fetchAll()]);
        exit;
    }

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
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>safe_error_msg($e)]); }
