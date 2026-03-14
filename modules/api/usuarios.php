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
            $stmt = $db->query("SELECT u.id,u.nombre,u.email,u.rol,u.activo,u.dni,u.departamento_id,
                COALESCE(dep.nombre,'') AS departamento,u.ultimo_acceso,u.created_at
                FROM usuarios u LEFT JOIN departamentos dep ON dep.id=u.departamento_id ORDER BY u.nombre");
            echo json_encode(['rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            $d=json_decode(file_get_contents('php://input'),true);
            if (!$d['email']||!$d['password']) { http_response_code(400); echo json_encode(['error'=>'Email y contraseña requeridos']); break; }
            $hash = password_hash($d['password'], PASSWORD_DEFAULT);
            $depId = isset($d['departamento_id']) && $d['departamento_id'] !== '' ? (int)$d['departamento_id'] : null;
            $dni = isset($d['dni']) && trim($d['dni']) !== '' ? trim($d['dni']) : null;
            $db->prepare("INSERT INTO usuarios (nombre,email,password,rol,activo,departamento_id,dni) VALUES (?,?,?,?,?,?,?)")
               ->execute([$d['nombre'],$d['email'],$hash,$d['rol'],(int)($d['activo']??1),$depId,$dni]);
            $newId = (int)$db->lastInsertId();
            $payload = $d;
            unset($payload['password']);
            audit_log('usuarios', 'create', $newId, [], $payload);
            echo json_encode(['id'=>$newId,'ok'=>true]);
            break;
        case 'PUT':
            $d=json_decode(file_get_contents('php://input'),true);
            $prevStmt = $db->prepare("SELECT id,nombre,email,rol,activo,departamento_id,dni FROM usuarios WHERE id=? LIMIT 1");
            $prevStmt->execute([(int)$d['id']]);
            $prev = $prevStmt->fetch() ?: [];
            $depId = isset($d['departamento_id']) && $d['departamento_id'] !== '' ? (int)$d['departamento_id'] : null;
            $dni = isset($d['dni']) && trim($d['dni']) !== '' ? trim($d['dni']) : null;
            if ($d['password']) {
                $hash = password_hash($d['password'], PASSWORD_DEFAULT);
                $db->prepare("UPDATE usuarios SET nombre=?,email=?,password=?,rol=?,activo=?,departamento_id=?,dni=? WHERE id=?")
                   ->execute([$d['nombre'],$d['email'],$hash,$d['rol'],(int)$d['activo'],$depId,$dni,$d['id']]);
            } else {
                $db->prepare("UPDATE usuarios SET nombre=?,email=?,rol=?,activo=?,departamento_id=?,dni=? WHERE id=?")
                   ->execute([$d['nombre'],$d['email'],$d['rol'],(int)$d['activo'],$depId,$dni,$d['id']]);
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
