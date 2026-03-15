<?php
/**
 * API — Documentos Vehiculares
 * GET    → listar (filtros: vehiculo_id, tipo, q, vencidos, page, per)
 * GET    ?detail=ID → detalle
 * POST   → crear documento
 * PUT    → actualizar documento
 * DELETE ?id=ID → soft-delete
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();

header('Content-Type: application/json');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

    // ─── GET ───
    case 'GET':
        if (isset($_GET['detail'])) {
            $id = (int)$_GET['detail'];
            $st = $db->prepare("
                SELECT vd.*, v.placa, v.marca, v.modelo, u.nombre AS creador_nombre
                FROM vehiculo_documentos vd
                LEFT JOIN vehiculos v ON v.id = vd.vehiculo_id
                LEFT JOIN usuarios u ON u.id = vd.created_by
                WHERE vd.id = ? AND vd.deleted_at IS NULL
            ");
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) { http_response_code(404); echo json_encode(['error' => 'Documento no encontrado.']); break; }
            echo json_encode($row);
            break;
        }

        // Listado
        $q       = trim($_GET['q'] ?? '');
        $vid     = (int)($_GET['vehiculo_id'] ?? 0);
        $tipo    = trim($_GET['tipo'] ?? '');
        $venc    = trim($_GET['vencidos'] ?? '');
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $per     = max(1, min(100, (int)($_GET['per'] ?? 25)));
        $offset  = ($page - 1) * $per;

        $where = ['vd.deleted_at IS NULL'];
        $params = [];

        if ($vid > 0) { $where[] = "vd.vehiculo_id = ?"; $params[] = $vid; }
        if ($tipo !== '') { $where[] = "vd.tipo = ?"; $params[] = $tipo; }
        if ($q !== '') {
            $where[] = "(vd.titulo LIKE ? OR vd.numero_documento LIKE ? OR v.placa LIKE ?)";
            $params = array_merge($params, ["%$q%", "%$q%", "%$q%"]);
        }
        if ($venc === '1') {
            $where[] = "vd.fecha_vencimiento IS NOT NULL AND vd.fecha_vencimiento < CURDATE()";
        } elseif ($venc === 'proximo') {
            $where[] = "vd.fecha_vencimiento IS NOT NULL AND vd.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        }

        $wSQL = implode(' AND ', $where);

        $cnt = $db->prepare("SELECT COUNT(*) FROM vehiculo_documentos vd LEFT JOIN vehiculos v ON v.id=vd.vehiculo_id WHERE {$wSQL}");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $st = $db->prepare("
            SELECT vd.*, v.placa, v.marca, v.modelo
            FROM vehiculo_documentos vd
            LEFT JOIN vehiculos v ON v.id = vd.vehiculo_id
            WHERE {$wSQL}
            ORDER BY vd.created_at DESC
            LIMIT {$per} OFFSET {$offset}
        ");
        $st->execute($params);

        // Counts by status
        $stCnt = $db->query("SELECT
            COUNT(*) AS total_docs,
            SUM(CASE WHEN fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE() THEN 1 ELSE 0 END) AS vencidos,
            SUM(CASE WHEN fecha_vencimiento IS NOT NULL AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS por_vencer
            FROM vehiculo_documentos WHERE deleted_at IS NULL");
        $counts = $stCnt->fetch();

        echo json_encode([
            'rows' => $st->fetchAll(),
            'total' => $total,
            'stats' => $counts,
        ]);
        break;

    // ─── POST ───
    case 'POST':
        if (!can('create')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
        $d = json_decode(file_get_contents('php://input'), true) ?: [];

        $vid = (int)($d['vehiculo_id'] ?? 0);
        $titulo = trim($d['titulo'] ?? '');
        $tipo = trim($d['tipo'] ?? '');
        if (!$vid || !$titulo || !$tipo) {
            http_response_code(400);
            echo json_encode(['error' => 'Vehículo, título y tipo son obligatorios.']);
            break;
        }

        $st = $db->prepare("INSERT INTO vehiculo_documentos (vehiculo_id, tipo, titulo, numero_documento, fecha_emision, fecha_vencimiento, notas, created_by) VALUES (?,?,?,?,?,?,?,?)");
        $st->execute([
            $vid, $tipo, $titulo,
            trim($d['numero_documento'] ?? '') ?: null,
            $d['fecha_emision'] ?: null,
            $d['fecha_vencimiento'] ?: null,
            trim($d['notas'] ?? '') ?: null,
            $_SESSION['user_id'],
        ]);
        $id = (int)$db->lastInsertId();
        audit_log('vehiculo_documentos', 'create', $id, [], $d);
        echo json_encode(['ok' => true, 'id' => $id]);
        break;

    // ─── PUT ───
    case 'PUT':
        if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = (int)($d['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido.']); break; }

        $st = $db->prepare("UPDATE vehiculo_documentos SET vehiculo_id=?, tipo=?, titulo=?, numero_documento=?, fecha_emision=?, fecha_vencimiento=?, notas=? WHERE id=? AND deleted_at IS NULL");
        $st->execute([
            (int)($d['vehiculo_id'] ?? 0),
            trim($d['tipo'] ?? ''),
            trim($d['titulo'] ?? ''),
            trim($d['numero_documento'] ?? '') ?: null,
            $d['fecha_emision'] ?: null,
            $d['fecha_vencimiento'] ?: null,
            trim($d['notas'] ?? '') ?: null,
            $id,
        ]);
        audit_log('vehiculo_documentos', 'update', $id, [], $d);
        echo json_encode(['ok' => true]);
        break;

    // ─── DELETE ───
    case 'DELETE':
        if (!can('delete')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido.']); break; }
        $db->prepare("UPDATE vehiculo_documentos SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
        audit_log('vehiculo_documentos', 'delete', $id, [], []);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => safe_error_msg($e)]);
}
