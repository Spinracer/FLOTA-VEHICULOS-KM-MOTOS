<?php
/**
 * API Endpoint: Reportes de Importación
 * GET: Listar importaciones con filtros
 * POST: Generar reporte de importación (CSV/XLS)
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/export.php';

header('Content-Type: application/json');

if (!can('view', 'importacion_vehiculos')) {
    http_response_code(403);
    die(json_encode(['error' => 'Acceso denegado']));
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');

try {
    if ($method === 'GET' && $action === 'list') {
        // Listar importaciones
        $usuario_id = (int)($_GET['usuario_id'] ?? 0);
        $resultado = trim($_GET['resultado'] ?? '');
        $from_date = trim($_GET['from'] ?? '');
        $to_date = trim($_GET['to'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = min(100, (int)($_GET['per_page'] ?? 50));

        $query = "
            SELECT 
                al.id,
                al.usuario_id,
                u.nombre as usuario_nombre,
                al.accion as resultado,
                JSON_EXTRACT(al.meta, '$.archivo') as archivo,
                JSON_EXTRACT(al.meta, '$.insertados') as insertados,
                JSON_EXTRACT(al.meta, '$.actualizados') as actualizados,
                JSON_EXTRACT(al.meta, '$.errores') as errores,
                JSON_EXTRACT(al.meta, '$.update_key_field') as campo_actualizar,
                JSON_EXTRACT(al.meta, '$.duracion_segundos') as duracion,
                al.created_at
            FROM audit_logs al
            JOIN usuarios u ON u.id = al.usuario_id
            WHERE al.entidad='importacion_vehiculos' 
            AND al.accion IN ('import', 'import_partial', 'import_error')
        ";

        $params = [];
        if ($usuario_id) {
            $query .= " AND al.usuario_id = ?";
            $params[] = $usuario_id;
        }

        if ($resultado === 'success') {
            $query .= " AND al.accion = 'import'";
        } elseif ($resultado === 'partial') {
            $query .= " AND al.accion = 'import_partial'";
        } elseif ($resultado === 'error') {
            $query .= " AND al.accion = 'import_error'";
        }

        if ($from_date) {
            $query .= " AND DATE(al.created_at) >= ?";
            $params[] = $from_date;
        }
        if ($to_date) {
            $query .= " AND DATE(al.created_at) <= ?";
            $params[] = $to_date;
        }

        // Count total
        $count_query = "SELECT COUNT(*) as cnt FROM ($query) as subquery";
        $count_stmt = $db->prepare(str_replace(
            "SELECT al.id, al.usuario_id, u.nombre as usuario_nombre, al.accion as resultado, " .
            "JSON_EXTRACT(al.meta, '$.archivo') as archivo, " .
            "JSON_EXTRACT(al.meta, '$.insertados') as insertados, " .
            "JSON_EXTRACT(al.meta, '$.actualizados') as actualizados, " .
            "JSON_EXTRACT(al.meta, '$.errores') as errores, " .
            "JSON_EXTRACT(al.meta, '$.update_key_field') as campo_actualizar, " .
            "JSON_EXTRACT(al.meta, '$.duracion_segundos') as duracion, " .
            "al.created_at FROM audit_logs al JOIN usuarios u ON u.id = al.usuario_id WHERE al.entidad='importacion_vehiculos' AND al.accion IN ('import', 'import_partial', 'import_error')",
            "SELECT COUNT(*) as cnt FROM audit_logs al JOIN usuarios u ON u.id = al.usuario_id WHERE al.entidad='importacion_vehiculos' AND al.accion IN ('import', 'import_partial', 'import_error')",
            $query
        ));
        $count_stmt->execute($params);
        $total = $count_stmt->fetchColumn() ?? 0;

        // Fetch paginated
        $query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = ($page - 1) * $per_page;

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize JSON
        foreach ($rows as &$row) {
            $row['insertados'] = json_decode($row['insertados'], true) ?? 0;
            $row['actualizados'] = json_decode($row['actualizados'], true) ?? 0;
            $row['errores'] = json_decode($row['errores'], true) ?? 0;
            $row['archivo'] = json_decode($row['archivo'], true) ?? '';
            $row['campo_actualizar'] = json_decode($row['campo_actualizar'], true) ?? 'placa';
            $row['duracion'] = json_decode($row['duracion'], true) ?? 0;
        }

        http_response_code(200);
        echo json_encode([
            'ok' => true,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'rows' => $rows
        ]);

    } elseif ($method === 'GET' && $action === 'stats') {
        // Estadísticas
        $from_date = trim($_GET['from'] ?? '');
        $to_date = trim($_GET['to'] ?? '');

        $query = "
            SELECT 
                COUNT(*) as total,
                COUNT(DISTINCT usuario_id) as usuarios,
                SUM(CASE WHEN accion='import' THEN 1 ELSE 0 END) as exitosas,
                SUM(CASE WHEN accion='import_partial' THEN 1 ELSE 0 END) as parciales,
                SUM(CASE WHEN accion='import_error' THEN 1 ELSE 0 END) as fallidas,
                SUM(JSON_EXTRACT(meta, '$.insertados')) as total_insertados,
                SUM(JSON_EXTRACT(meta, '$.actualizados')) as total_actualizados,
                AVG(JSON_EXTRACT(meta, '$.duracion_segundos')) as duracion_promedio
            FROM audit_logs
            WHERE entidad='importacion_vehiculos'
            AND accion IN ('import', 'import_partial', 'import_error')
        ";

        $params = [];
        if ($from_date) {
            $query .= " AND DATE(created_at) >= ?";
            $params[] = $from_date;
        }
        if ($to_date) {
            $query .= " AND DATE(created_at) <= ?";
            $params[] = $to_date;
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            'ok' => true,
            'stats' => $stats
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
