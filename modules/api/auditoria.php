<?php
/**
 * API: Consulta de Auditoría (audit_logs)
 *
 * GET  → listar con filtros: entidad, accion, user_id, desde, hasta, q
 * Solo accesible por coordinador_it / admin
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_login();
require_role('coordinador_it', 'admin');
header('Content-Type: application/json');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

try {
    $q       = '%' . trim($_GET['q'] ?? '') . '%';
    $entidad = trim($_GET['entidad'] ?? '');
    $accion  = trim($_GET['accion'] ?? '');
    $userId  = (int)($_GET['user_id'] ?? 0);
    $desde   = trim($_GET['desde'] ?? '');
    $hasta   = trim($_GET['hasta'] ?? '');
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $per     = min(200, max(10, (int)($_GET['per'] ?? 50)));
    $off     = ($page - 1) * $per;

    $where  = "WHERE (a.entidad LIKE ? OR a.accion LIKE ? OR a.user_email LIKE ? OR CAST(a.entidad_id AS CHAR) LIKE ?)";
    $params = [$q, $q, $q, $q];

    if ($entidad !== '') {
        $where   .= " AND a.entidad = ?";
        $params[] = $entidad;
    }
    if ($accion !== '') {
        $where   .= " AND a.accion = ?";
        $params[] = $accion;
    }
    if ($userId > 0) {
        $where   .= " AND a.user_id = ?";
        $params[] = $userId;
    }
    if ($desde !== '') {
        $where   .= " AND a.created_at >= ?";
        $params[] = $desde . ' 00:00:00';
    }
    if ($hasta !== '') {
        $where   .= " AND a.created_at <= ?";
        $params[] = $hasta . ' 23:59:59';
    }

    // Total
    $totalStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs a $where");
    $totalStmt->execute($params);
    $total = (int)$totalStmt->fetchColumn();

    // Rows
    $stmt = $db->prepare("
        SELECT a.*
        FROM audit_logs a
        $where
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$per, $off]));
    $rows = $stmt->fetchAll();

    // Decodificar JSON
    foreach ($rows as &$row) {
        $row['antes']   = $row['antes_json'] ? json_decode($row['antes_json'], true) : null;
        $row['despues'] = $row['despues_json'] ? json_decode($row['despues_json'], true) : null;
        $row['meta']    = $row['meta_json'] ? json_decode($row['meta_json'], true) : null;
        unset($row['antes_json'], $row['despues_json'], $row['meta_json']);
    }
    unset($row);

    // Entidades y acciones únicas para filtros
    $entidades = $db->query("SELECT DISTINCT entidad FROM audit_logs ORDER BY entidad")->fetchAll(PDO::FETCH_COLUMN);
    $acciones  = $db->query("SELECT DISTINCT accion FROM audit_logs ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'total'     => $total,
        'rows'      => $rows,
        'entidades' => $entidades,
        'acciones'  => $acciones,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
