<?php
/**
 * API de permisos granulares por usuario
 * GET                         → retorna la matriz completa [user_id][modulo] = [permisos] + lista de usuarios
 * PUT                         → actualiza permisos de un usuario/módulo
 * POST ?action=init_user      → inicializa permisos de un usuario desde su rol
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
require_admin();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

$MODULOS  = ['vehiculos','asignaciones','mantenimientos','combustible','incidentes','recordatorios','operadores','proveedores','preventivos','reportes','usuarios','auditoria','sucursales','notificaciones'];
$PERMISOS = ['view','create','edit','delete'];

try {
    $action = trim($_GET['action'] ?? '');

    switch ($method) {
        case 'GET':
            // Lista de todos los usuarios activos
            $usersStmt = $db->query("SELECT id, nombre, email, rol FROM usuarios WHERE activo=1 ORDER BY nombre");
            $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

            // Matriz de permisos por usuario
            $stmt = $db->query("SELECT user_id, modulo, permiso FROM user_module_permissions ORDER BY user_id, modulo, permiso");
            $rows = $stmt->fetchAll();
            $matrix = [];
            foreach ($rows as $r) {
                $matrix[(int)$r['user_id']][$r['modulo']][] = $r['permiso'];
            }

            // Para usuarios sin permisos personalizados, cargar los de su rol como referencia
            $roleStmt = $db->query("SELECT rol, modulo, permiso FROM role_module_permissions ORDER BY rol, modulo, permiso");
            $roleMatrix = [];
            foreach ($roleStmt->fetchAll() as $r) {
                $roleMatrix[$r['rol']][$r['modulo']][] = $r['permiso'];
            }

            echo json_encode([
                'matrix'     => $matrix,
                'roleMatrix' => $roleMatrix,
                'modulos'    => $MODULOS,
                'permisos'   => $PERMISOS,
                'users'      => $users,
            ]);
            break;

        case 'POST':
            // Inicializar permisos de un usuario desde su rol
            if ($action === 'init_user') {
                $d = json_decode(file_get_contents('php://input'), true);
                $userId = (int)($d['user_id'] ?? 0);
                if ($userId <= 0) { http_response_code(400); echo json_encode(['error' => 'user_id requerido']); break; }

                $userStmt = $db->prepare("SELECT rol FROM usuarios WHERE id=? AND activo=1");
                $userStmt->execute([$userId]);
                $user = $userStmt->fetch();
                if (!$user) { http_response_code(404); echo json_encode(['error' => 'Usuario no encontrado']); break; }

                // Copiar permisos del rol a permisos del usuario
                $rolPerms = $db->prepare("SELECT modulo, permiso FROM role_module_permissions WHERE rol=?");
                $rolPerms->execute([$user['rol']]);
                $db->beginTransaction();
                $db->prepare("DELETE FROM user_module_permissions WHERE user_id=?")->execute([$userId]);
                $ins = $db->prepare("INSERT INTO user_module_permissions (user_id, modulo, permiso) VALUES (?,?,?)");
                $count = 0;
                foreach ($rolPerms->fetchAll() as $rp) {
                    $ins->execute([$userId, $rp['modulo'], $rp['permiso']]);
                    $count++;
                }
                $db->commit();
                audit_log('permisos', 'init_user', $userId, [], ['user_id' => $userId, 'count' => $count]);
                echo json_encode(['ok' => true, 'count' => $count]);
                break;
            }
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
            break;

        case 'PUT':
            $d = json_decode(file_get_contents('php://input'), true);
            $userId = (int)($d['user_id'] ?? 0);
            $modulo = trim($d['modulo'] ?? '');
            $perms  = $d['permisos'] ?? [];

            if ($userId <= 0 || !$modulo) {
                http_response_code(400);
                echo json_encode(['error' => 'user_id y modulo son obligatorios.']);
                break;
            }
            if (!in_array($modulo, $MODULOS)) {
                http_response_code(400);
                echo json_encode(['error' => 'Módulo inválido.']);
                break;
            }

            // Capturar antes
            $prevStmt = $db->prepare("SELECT permiso FROM user_module_permissions WHERE user_id=? AND modulo=?");
            $prevStmt->execute([$userId, $modulo]);
            $prevPerms = $prevStmt->fetchAll(PDO::FETCH_COLUMN);

            $db->beginTransaction();
            $db->prepare("DELETE FROM user_module_permissions WHERE user_id=? AND modulo=?")->execute([$userId, $modulo]);
            $ins = $db->prepare("INSERT INTO user_module_permissions (user_id, modulo, permiso) VALUES (?,?,?)");
            $validPerms = [];
            foreach ($perms as $p) {
                if (in_array($p, $PERMISOS)) {
                    $ins->execute([$userId, $modulo, $p]);
                    $validPerms[] = $p;
                }
            }
            $db->commit();

            audit_log('permisos', 'update_user', $userId,
                ['user_id' => $userId, 'modulo' => $modulo, 'permisos' => $prevPerms],
                ['user_id' => $userId, 'modulo' => $modulo, 'permisos' => $validPerms]
            );

            echo json_encode(['ok' => true, 'user_id' => $userId, 'modulo' => $modulo, 'permisos' => $validPerms]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
