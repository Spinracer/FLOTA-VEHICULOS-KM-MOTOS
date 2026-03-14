<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
require_admin();

header('Content-Type: application/json');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

$catalogMap = [
    'categorias_gasto'     => ['table' => 'catalogo_categorias_gasto', 'fields' => ['nombre', 'descripcion', 'activo']],
    'unidades'             => ['table' => 'catalogo_unidades', 'fields' => ['clave', 'nombre', 'activo']],
    'tipos_mantenimiento'  => ['table' => 'catalogo_tipos_mantenimiento', 'fields' => ['nombre', 'activo']],
    'estados_vehiculo'     => ['table' => 'catalogo_estados_vehiculo', 'fields' => ['nombre', 'activo']],
    'servicios_taller'     => ['table' => 'catalogo_servicios_taller', 'fields' => ['nombre', 'activo']],
];

try {
    if ($method === 'GET') {
        $type = $_GET['type'] ?? 'catalogs';

        if ($type === 'catalogs') {
            echo json_encode([
                'rows' => [
                    ['key' => 'categorias_gasto', 'label' => 'Categorías de gasto'],
                    ['key' => 'unidades', 'label' => 'Unidades'],
                    ['key' => 'tipos_mantenimiento', 'label' => 'Tipos de mantenimiento'],
                    ['key' => 'estados_vehiculo', 'label' => 'Estados de vehículo'],
                    ['key' => 'servicios_taller', 'label' => 'Servicios de taller'],
                ]
            ]);
            exit;
        }

        if ($type === 'items') {
            $catalog = $_GET['catalog'] ?? '';
            if (!isset($catalogMap[$catalog])) {
                http_response_code(400);
                echo json_encode(['error' => 'Catálogo inválido']);
                exit;
            }
            $meta = $catalogMap[$catalog];
            $q = '%' . trim($_GET['q'] ?? '') . '%';
            $sql = "SELECT * FROM {$meta['table']} WHERE nombre LIKE ? ORDER BY nombre ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$q]);
            echo json_encode(['rows' => $stmt->fetchAll()]);
            exit;
        }

        if ($type === 'settings') {
            $stmt = $db->query("SELECT id,key_name,value_text,value_num,description,updated_at FROM system_settings ORDER BY key_name ASC");
            echo json_encode(['rows' => $stmt->fetchAll()]);
            exit;
        }
    }

    if ($method === 'POST') {
        $d = json_decode(file_get_contents('php://input'), true);
        $type = $d['type'] ?? '';

        if ($type !== 'item') {
            http_response_code(400);
            echo json_encode(['error' => 'Tipo de operación inválido']);
            exit;
        }

        $catalog = $d['catalog'] ?? '';
        if (!isset($catalogMap[$catalog])) {
            http_response_code(400);
            echo json_encode(['error' => 'Catálogo inválido']);
            exit;
        }

        $meta = $catalogMap[$catalog];
        $table = $meta['table'];

        $fields = [];
        $values = [];
        foreach ($meta['fields'] as $field) {
            if (array_key_exists($field, $d)) {
                $fields[] = $field;
                if ($field === 'activo') {
                    $values[] = (int)$d[$field];
                } else {
                    $values[] = trim((string)$d[$field]) ?: null;
                }
            }
        }

        if (!in_array('nombre', $fields, true) && $catalog !== 'unidades') {
            http_response_code(400);
            echo json_encode(['error' => 'El campo nombre es obligatorio']);
            exit;
        }

        if ($catalog === 'unidades' && !in_array('nombre', $fields, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'El campo nombre es obligatorio']);
            exit;
        }

        $cols = implode(',', $fields);
        $ph = implode(',', array_fill(0, count($fields), '?'));

        $stmt = $db->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$ph})");
        $stmt->execute($values);
        $id = (int)$db->lastInsertId();
        audit_log('catalogos', 'create', $id, [], ['catalog' => $catalog, 'data' => $d]);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    if ($method === 'PUT') {
        $d = json_decode(file_get_contents('php://input'), true);
        $type = $d['type'] ?? '';

        if ($type === 'item') {
            $catalog = $d['catalog'] ?? '';
            if (!isset($catalogMap[$catalog])) {
                http_response_code(400);
                echo json_encode(['error' => 'Catálogo inválido']);
                exit;
            }
            $id = (int)($d['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ID inválido']);
                exit;
            }

            $meta = $catalogMap[$catalog];
            $table = $meta['table'];
            $prevStmt = $db->prepare("SELECT * FROM {$table} WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];

            $sets = [];
            $vals = [];
            foreach ($meta['fields'] as $field) {
                if (array_key_exists($field, $d)) {
                    $sets[] = "{$field}=?";
                    if ($field === 'activo') {
                        $vals[] = (int)$d[$field];
                    } else {
                        $vals[] = trim((string)$d[$field]) ?: null;
                    }
                }
            }
            if (!$sets) {
                http_response_code(400);
                echo json_encode(['error' => 'Sin campos para actualizar']);
                exit;
            }
            $vals[] = $id;
            $sql = "UPDATE {$table} SET " . implode(',', $sets) . " WHERE id=?";
            $db->prepare($sql)->execute($vals);
            audit_log('catalogos', 'update', $id, $prev, ['catalog' => $catalog, 'data' => $d]);
            echo json_encode(['ok' => true]);
            exit;
        }

        if ($type === 'setting') {
            $key = trim((string)($d['key_name'] ?? ''));
            if ($key === '') {
                http_response_code(400);
                echo json_encode(['error' => 'key_name es obligatorio']);
                exit;
            }
            $prevStmt = $db->prepare("SELECT * FROM system_settings WHERE key_name=? LIMIT 1");
            $prevStmt->execute([$key]);
            $prev = $prevStmt->fetch() ?: [];

            $stmt = $db->prepare("INSERT INTO system_settings (key_name,value_text,value_num,description,updated_at)
                VALUES (?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE value_text=VALUES(value_text), value_num=VALUES(value_num), description=VALUES(description), updated_at=NOW()");
            $stmt->execute([
                $key,
                trim((string)($d['value_text'] ?? '')) ?: null,
                ($d['value_num'] ?? '') === '' ? null : (float)$d['value_num'],
                trim((string)($d['description'] ?? '')) ?: null,
            ]);
            audit_log('settings', 'upsert', null, $prev, $d);
            echo json_encode(['ok' => true]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Tipo de operación inválido']);
        exit;
    }

    if ($method === 'DELETE') {
        $catalog = $_GET['catalog'] ?? '';
        $id = (int)($_GET['id'] ?? 0);
        if (!isset($catalogMap[$catalog]) || $id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetros inválidos']);
            exit;
        }
        $table = $catalogMap[$catalog]['table'];
        $prevStmt = $db->prepare("SELECT * FROM {$table} WHERE id=? LIMIT 1");
        $prevStmt->execute([$id]);
        $prev = $prevStmt->fetch() ?: [];

        $db->prepare("DELETE FROM {$table} WHERE id=?")->execute([$id]);
        audit_log('catalogos', 'delete', $id, $prev, ['catalog' => $catalog]);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => safe_error_msg($e)]);
}
