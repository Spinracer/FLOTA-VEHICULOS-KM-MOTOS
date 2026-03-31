<?php
/**
 * API Endpoint: Sincronización OC↔OT
 * GET: Listar historial de sincronizaciones
 * POST: (Admin) Generar reporte de sincronizaciones
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if (!can('view', 'ordenes_compra') || !can('view', 'mantenimientos')) {
    http_response_code(403);
    die(json_encode(['error' => 'Acceso denegado']));
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');

try {
    if ($method === 'GET') {
        // Listar sincronizaciones
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = min(100, (int)($_GET['per_page'] ?? 50));
        $from_date = trim($_GET['from'] ?? '');
        $to_date = trim($_GET['to'] ?? '');
        $tipo = trim($_GET['tipo'] ?? ''); // sync, desync, all

        $query = "
            SELECT 
                al.id,
                al.accion as tipo,
                al.usuario_id,
                u.nombre as usuario_nombre,
                JSON_EXTRACT(al.meta, '$.orden_compra_id') as oc_id,
                JSON_EXTRACT(al.meta, '$.mantenimiento_id') as ot_id,
                JSON_EXTRACT(al.meta, '$.items_count') as items_sync,
                JSON_EXTRACT(al.meta, '$.razon') as razon,
                al.created_at
            FROM audit_logs al
            LEFT JOIN usuarios u ON u.id = al.usuario_id
            WHERE al.entidad='oc_to_ot_sync'
        ";

        $params = [];
        if ($tipo === 'sync') {
            $query .= " AND al.accion = 'sync'";
        } elseif ($tipo === 'desync') {
            $query .= " AND al.accion IN ('delete', 'cancel')";
        }

        if ($from_date) {
            $query .= " AND DATE(al.created_at) >= ?";
            $params[] = $from_date;
        }
        if ($to_date) {
            $query .= " AND DATE(al.created_at) <= ?";
            $params[] = $to_date;
        }

        // Total count
        $count_query = str_replace('SELECT al.id,*', 'SELECT COUNT(*) as total', $query);
        $count_stmt = $db->prepare($count_query);
        $count_stmt->execute($params);
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Fetch latest
        $query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = ($page - 1) * $per_page;

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize JSON fields
        foreach ($rows as &$row) {
            $row['oc_id'] = json_decode($row['oc_id'], true) ?? $row['oc_id'];
            $row['ot_id'] = json_decode($row['ot_id'], true) ?? $row['ot_id'];
            $row['items_sync'] = json_decode($row['items_sync'], true) ?? 0;
            $row['razon'] = json_decode($row['razon'], true) ?? '';
        }

        http_response_code(200);
        echo json_encode([
            'ok' => true,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'rows' => $rows
        ]);

    } elseif ($method === 'POST' && $action === 'stats') {
        // Estadísticas de sincronización
        if (!can('view', 'auditoria')) {
            http_response_code(403);
            die(json_encode(['error' => 'Acceso denegado a auditoría']));
        }

        $from_date = trim($_POST['from'] ?? '');
        $to_date = trim($_POST['to'] ?? '');

        $query = "
            SELECT 
                COUNT(*) as total_eventos,
                SUM(CASE WHEN accion='sync' THEN 1 ELSE 0 END) as sincronizaciones,
                SUM(CASE WHEN accion IN ('delete','cancel') THEN 1 ELSE 0 END) as desincronizaciones,
                COUNT(DISTINCT DATE(created_at)) as dias_activos,
                COUNT(DISTINCT JSON_EXTRACT(meta, '$.orden_compra_id')) as oc_afectadas,
                COUNT(DISTINCT JSON_EXTRACT(meta, '$.mantenimiento_id')) as ot_afectadas,
                AVG(JSON_EXTRACT(meta, '$.items_count')) as promedio_items_syncrados
            FROM audit_logs
            WHERE entidad='oc_to_ot_sync'
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
        echo json_encode(['error' => 'Invalid request']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
