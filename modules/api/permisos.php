<?php
/**
 * API de permisos granulares por módulo
 * GET    → retorna la matriz completa [rol][modulo] = [permisos]
 * PUT    → actualiza permisos de un rol/módulo
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
require_admin();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

$MODULOS  = ['vehiculos','asignaciones','mantenimientos','combustible','incidentes','recordatorios','operadores','proveedores','componentes','preventivos','reportes','catalogos','usuarios','auditoria'];
$PERMISOS = ['view','create','edit','delete'];

try {
    switch ($method) {
        case 'GET':
            $stmt = $db->query("SELECT rol, modulo, permiso FROM role_module_permissions ORDER BY rol, modulo, permiso");
            $rows = $stmt->fetchAll();
            $matrix = [];
            foreach ($rows as $r) {
                $matrix[$r['rol']][$r['modulo']][] = $r['permiso'];
            }
            echo json_encode([
                'matrix'   => $matrix,
                'modulos'  => $MODULOS,
                'permisos' => $PERMISOS,
                'roles'    => array_keys(ROLES),
            ]);
            break;

        case 'PUT':
            $d = json_decode(file_get_contents('php://input'), true);
            $rol    = trim($d['rol'] ?? '');
            $modulo = trim($d['modulo'] ?? '');
            $perms  = $d['permisos'] ?? []; // array of strings

            if (!$rol || !$modulo) {
                http_response_code(400);
                echo json_encode(['error' => 'rol y modulo son obligatorios.']);
                break;
            }
            if (!in_array($modulo, $MODULOS)) {
                http_response_code(400);
                echo json_encode(['error' => 'Módulo inválido.']);
                break;
            }
            if (!isset(ROLES[$rol]) && !in_array($rol, ['admin','operador','lectura'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Rol inválido.']);
                break;
            }

            // Capturar antes
            $prevStmt = $db->prepare("SELECT permiso FROM role_module_permissions WHERE rol=? AND modulo=?");
            $prevStmt->execute([$rol, $modulo]);
            $prevPerms = $prevStmt->fetchAll(PDO::FETCH_COLUMN);

            $db->beginTransaction();
            // Borrar existentes
            $db->prepare("DELETE FROM role_module_permissions WHERE rol=? AND modulo=?")->execute([$rol, $modulo]);
            // Insertar nuevos
            $ins = $db->prepare("INSERT INTO role_module_permissions (rol, modulo, permiso) VALUES (?,?,?)");
            $validPerms = [];
            foreach ($perms as $p) {
                if (in_array($p, $PERMISOS)) {
                    $ins->execute([$rol, $modulo, $p]);
                    $validPerms[] = $p;
                }
            }
            $db->commit();

            audit_log('permisos', 'update', null,
                ['rol' => $rol, 'modulo' => $modulo, 'permisos' => $prevPerms],
                ['rol' => $rol, 'modulo' => $modulo, 'permisos' => $validPerms]
            );

            echo json_encode(['ok' => true, 'rol' => $rol, 'modulo' => $modulo, 'permisos' => $validPerms]);
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
