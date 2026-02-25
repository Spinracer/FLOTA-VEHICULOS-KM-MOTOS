<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_admin();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
try {
    switch ($method) {
        case 'GET':
            $stmt = $db->query("SELECT id,nombre,email,rol,activo,ultimo_acceso,created_at FROM usuarios ORDER BY nombre");
            echo json_encode(['rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            $d=json_decode(file_get_contents('php://input'),true);
            if (!$d['email']||!$d['password']) { http_response_code(400); echo json_encode(['error'=>'Email y contraseña requeridos']); break; }
            $hash = password_hash($d['password'], PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO usuarios (nombre,email,password,rol,activo) VALUES (?,?,?,?,?)")
               ->execute([$d['nombre'],$d['email'],$hash,$d['rol'],(int)($d['activo']??1)]);
            echo json_encode(['id'=>$db->lastInsertId(),'ok'=>true]);
            break;
        case 'PUT':
            $d=json_decode(file_get_contents('php://input'),true);
            if ($d['password']) {
                $hash = password_hash($d['password'], PASSWORD_DEFAULT);
                $db->prepare("UPDATE usuarios SET nombre=?,email=?,password=?,rol=?,activo=? WHERE id=?")
                   ->execute([$d['nombre'],$d['email'],$hash,$d['rol'],(int)$d['activo'],$d['id']]);
            } else {
                $db->prepare("UPDATE usuarios SET nombre=?,email=?,rol=?,activo=? WHERE id=?")
                   ->execute([$d['nombre'],$d['email'],$d['rol'],(int)$d['activo'],$d['id']]);
            }
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            $id=(int)$_GET['id'];
            if ($id==current_user()['id']) { http_response_code(400); echo json_encode(['error'=>'No puedes eliminarte a ti mismo']); break; }
            $db->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    $msg = str_contains($e->getMessage(),'Duplicate') ? 'Ya existe un usuario con ese email.' : $e->getMessage();
    echo json_encode(['error'=>$msg]);
}
