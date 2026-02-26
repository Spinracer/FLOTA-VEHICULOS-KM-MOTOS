<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
require_admin();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
try {
    switch ($method) {
        case 'GET':
            $stmt = $db->query("SELECT id,nombre,email,rol,activo,proveedor_id,ultimo_acceso,created_at FROM usuarios ORDER BY nombre");
            echo json_encode(['rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            $d=json_decode(file_get_contents('php://input'),true);
            if (!$d['email']||!$d['password']) { http_response_code(400); echo json_encode(['error'=>'Email y contraseña requeridos']); break; }
            $proveedorId = isset($d['proveedor_id']) && $d['proveedor_id'] !== '' ? (int)$d['proveedor_id'] : null;
            if (($d['rol'] ?? '') === 'taller' && !$proveedorId) { http_response_code(400); echo json_encode(['error'=>'Para rol Taller debes seleccionar un proveedor autorizado.']); break; }
            $hash = password_hash($d['password'], PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO usuarios (nombre,email,password,rol,activo,proveedor_id) VALUES (?,?,?,?,?,?)")
               ->execute([$d['nombre'],$d['email'],$hash,$d['rol'],(int)($d['activo']??1),$proveedorId]);
            $newId = (int)$db->lastInsertId();
            $payload = $d;
            unset($payload['password']);
            audit_log('usuarios', 'create', $newId, [], $payload);
            echo json_encode(['id'=>$newId,'ok'=>true]);
            break;
        case 'PUT':
            $d=json_decode(file_get_contents('php://input'),true);
                $prevStmt = $db->prepare("SELECT id,nombre,email,rol,activo,proveedor_id FROM usuarios WHERE id=? LIMIT 1");
            $prevStmt->execute([(int)$d['id']]);
            $prev = $prevStmt->fetch() ?: [];
                $proveedorId = isset($d['proveedor_id']) && $d['proveedor_id'] !== '' ? (int)$d['proveedor_id'] : null;
                if (($d['rol'] ?? '') === 'taller' && !$proveedorId) { http_response_code(400); echo json_encode(['error'=>'Para rol Taller debes seleccionar un proveedor autorizado.']); break; }
            if ($d['password']) {
                $hash = password_hash($d['password'], PASSWORD_DEFAULT);
                     $db->prepare("UPDATE usuarios SET nombre=?,email=?,password=?,rol=?,activo=?,proveedor_id=? WHERE id=?")
                         ->execute([$d['nombre'],$d['email'],$hash,$d['rol'],(int)$d['activo'],$proveedorId,$d['id']]);
            } else {
                     $db->prepare("UPDATE usuarios SET nombre=?,email=?,rol=?,activo=?,proveedor_id=? WHERE id=?")
                         ->execute([$d['nombre'],$d['email'],$d['rol'],(int)$d['activo'],$proveedorId,$d['id']]);
            }
            $after = $d;
            unset($after['password']);
            audit_log('usuarios', 'update', (int)$d['id'], $prev, $after);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            $id=(int)$_GET['id'];
            if ($id==current_user()['id']) { http_response_code(400); echo json_encode(['error'=>'No puedes eliminarte a ti mismo']); break; }
            $prevStmt = $db->prepare("SELECT id,nombre,email,rol,activo FROM usuarios WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];
            $db->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
            audit_log('usuarios', 'delete', $id, $prev, []);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    $msg = str_contains($e->getMessage(),'Duplicate') ? 'Ya existe un usuario con ese email.' : $e->getMessage();
    echo json_encode(['error'=>$msg]);
}
