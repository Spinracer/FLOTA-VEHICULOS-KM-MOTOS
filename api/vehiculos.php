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
            $q    = '%' . trim($_GET['q']    ?? '') . '%';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per  = min(100, max(5, (int)($_GET['per'] ?? 20)));
            $off  = ($page - 1) * $per;

            $total = $db->prepare("SELECT COUNT(*) FROM vehiculos v
                LEFT JOIN operadores o ON o.id = v.operador_id
                WHERE v.placa LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? OR v.tipo LIKE ?");
            $total->execute([$q,$q,$q,$q]);

            $stmt = $db->prepare("SELECT v.*, o.nombre AS operador_nombre
                FROM vehiculos v
                LEFT JOIN operadores o ON o.id = v.operador_id
                WHERE v.placa LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? OR v.tipo LIKE ?
                ORDER BY v.placa ASC
                LIMIT ? OFFSET ?");
            $stmt->execute([$q,$q,$q,$q,$per,$off]);

            echo json_encode(['total' => (int)$total->fetchColumn(), 'rows' => $stmt->fetchAll()]);
            break;

        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear vehículos.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $stmt = $db->prepare("INSERT INTO vehiculos (placa,marca,modelo,anio,tipo,combustible,km_actual,color,vin,estado,operador_id,venc_seguro,notas)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                strtoupper(trim($d['placa'])), $d['marca'], $d['modelo'],
                $d['anio'] ?: null, $d['tipo'], $d['combustible'],
                $d['km_actual'] ?: 0, $d['color'] ?: null, $d['vin'] ?: null,
                $d['estado'], $d['operador_id'] ?: null,
                $d['venc_seguro'] ?: null, $d['notas'] ?: null
            ]);
            echo json_encode(['id' => $db->lastInsertId(), 'ok' => true]);
            break;

        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para editar vehículos.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $stmt = $db->prepare("UPDATE vehiculos SET placa=?,marca=?,modelo=?,anio=?,tipo=?,combustible=?,km_actual=?,color=?,vin=?,estado=?,operador_id=?,venc_seguro=?,notas=? WHERE id=?");
            $stmt->execute([
                strtoupper(trim($d['placa'])), $d['marca'], $d['modelo'],
                $d['anio'] ?: null, $d['tipo'], $d['combustible'],
                $d['km_actual'] ?: 0, $d['color'] ?: null, $d['vin'] ?: null,
                $d['estado'], $d['operador_id'] ?: null,
                $d['venc_seguro'] ?: null, $d['notas'] ?: null, $d['id']
            ]);
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar vehículos.']);
                break;
            }
            $id = (int)($_GET['id'] ?? 0);
            $db->prepare("DELETE FROM vehiculos WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    $msg = str_contains($e->getMessage(), 'Duplicate') ? 'Ya existe un vehículo con esa placa.' : $e->getMessage();
    echo json_encode(['error' => $msg]);
}
