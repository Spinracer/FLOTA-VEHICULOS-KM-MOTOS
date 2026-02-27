<?php
/**
 * API de Reportes y Exportaciones
 * Endpoints:
 *   GET ?report=combustible      → JSON resumen
 *   GET ?report=mantenimiento    → JSON resumen
 *   GET ?report=vehiculos        → JSON utilización
 *   GET ?report=top_costosos     → JSON top vehículos por gasto
 *   GET ?report=talleres         → JSON desempeño por taller
 *   GET ?export=combustible&format=csv  → Descarga CSV
 *   GET ?export=mantenimiento&format=csv → Descarga CSV
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/export.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();

$db = getDB();
$report = $_GET['report'] ?? '';
$export = $_GET['export'] ?? '';
$format = $_GET['format'] ?? 'json';

// Filtros comunes
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$vid = (int)($_GET['vehiculo_id'] ?? 0);
$provId = (int)($_GET['proveedor_id'] ?? 0);

try {
    // ─── EXPORTACIONES ───
    if ($export !== '') {
        switch ($export) {
            case 'combustible':
                $where = "WHERE 1=1";
                $params = [];
                if ($from) { $where .= " AND c.fecha >= ?"; $params[] = $from; }
                if ($to) { $where .= " AND c.fecha <= ?"; $params[] = $to; }
                if ($vid) { $where .= " AND c.vehiculo_id = ?"; $params[] = $vid; }
                $stmt = $db->prepare("SELECT c.fecha, v.placa, v.marca, o.nombre AS conductor, c.litros, c.costo_litro, c.total, c.km, c.tipo_carga, p.nombre AS proveedor, c.metodo_pago, c.numero_recibo, c.notas
                    FROM combustible c
                    LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                    LEFT JOIN operadores o ON o.id=c.operador_id
                    LEFT JOIN proveedores p ON p.id=c.proveedor_id
                    $where ORDER BY c.fecha DESC, c.id DESC");
                $stmt->execute($params);
                $rows = [];
                while ($r = $stmt->fetch()) {
                    $rows[] = array_values($r);
                }
                audit_log('reportes', 'export_csv', null, [], ['tipo' => 'combustible', 'filtros' => compact('from','to','vid')]);
                export_csv('reporte_combustible_' . date('Ymd_His') . '.csv',
                    ['Fecha','Placa','Marca','Conductor','Litros','Costo/L','Total','KM','Tipo Carga','Proveedor','Método Pago','Recibo','Notas'],
                    $rows
                );
                break;

            case 'mantenimiento':
                $where = "WHERE 1=1";
                $params = [];
                if ($from) { $where .= " AND m.fecha >= ?"; $params[] = $from; }
                if ($to) { $where .= " AND m.fecha <= ?"; $params[] = $to; }
                if ($vid) { $where .= " AND m.vehiculo_id = ?"; $params[] = $vid; }
                if ($provId) { $where .= " AND m.proveedor_id = ?"; $params[] = $provId; }
                $stmt = $db->prepare("SELECT m.fecha, v.placa, v.marca, m.tipo, m.descripcion, m.costo, m.km, m.proximo_km, p.nombre AS proveedor, m.estado
                    FROM mantenimientos m
                    LEFT JOIN vehiculos v ON v.id=m.vehiculo_id
                    LEFT JOIN proveedores p ON p.id=m.proveedor_id
                    $where ORDER BY m.fecha DESC, m.id DESC");
                $stmt->execute($params);
                $rows = [];
                while ($r = $stmt->fetch()) {
                    $rows[] = array_values($r);
                }
                audit_log('reportes', 'export_csv', null, [], ['tipo' => 'mantenimiento', 'filtros' => compact('from','to','vid','provId')]);
                export_csv('reporte_mantenimiento_' . date('Ymd_His') . '.csv',
                    ['Fecha','Placa','Marca','Tipo','Descripción','Costo','KM','Próx. KM','Proveedor','Estado'],
                    $rows
                );
                break;

            case 'asignaciones':
                $where = "WHERE 1=1";
                $params = [];
                if ($from) { $where .= " AND a.start_at >= ?"; $params[] = $from; }
                if ($to) { $where .= " AND a.start_at <= ?"; $params[] = $to; }
                if ($vid) { $where .= " AND a.vehiculo_id = ?"; $params[] = $vid; }
                $stmt = $db->prepare("SELECT a.start_at, a.end_at, v.placa, v.marca, o.nombre AS operador, a.start_km, a.end_km, a.estado, a.override_reason
                    FROM asignaciones a
                    JOIN vehiculos v ON v.id=a.vehiculo_id
                    JOIN operadores o ON o.id=a.operador_id
                    $where ORDER BY a.start_at DESC");
                $stmt->execute($params);
                $rows = [];
                while ($r = $stmt->fetch()) {
                    $rows[] = array_values($r);
                }
                audit_log('reportes', 'export_csv', null, [], ['tipo' => 'asignaciones', 'filtros' => compact('from','to','vid')]);
                export_csv('reporte_asignaciones_' . date('Ymd_His') . '.csv',
                    ['Inicio','Fin','Placa','Marca','Operador','KM Inicio','KM Fin','Estado','Override'],
                    $rows
                );
                break;

            case 'incidentes':
                $where = "WHERE 1=1";
                $params = [];
                if ($from) { $where .= " AND i.fecha >= ?"; $params[] = $from; }
                if ($to) { $where .= " AND i.fecha <= ?"; $params[] = $to; }
                if ($vid) { $where .= " AND i.vehiculo_id = ?"; $params[] = $vid; }
                $stmt = $db->prepare("SELECT i.fecha, v.placa, v.marca, i.tipo, i.descripcion, i.severidad, i.estado, i.costo_est
                    FROM incidentes i
                    LEFT JOIN vehiculos v ON v.id=i.vehiculo_id
                    $where ORDER BY i.fecha DESC");
                $stmt->execute($params);
                $rows = [];
                while ($r = $stmt->fetch()) {
                    $rows[] = array_values($r);
                }
                audit_log('reportes', 'export_csv', null, [], ['tipo' => 'incidentes', 'filtros' => compact('from','to','vid')]);
                export_csv('reporte_incidentes_' . date('Ymd_His') . '.csv',
                    ['Fecha','Placa','Marca','Tipo','Descripción','Severidad','Estado','Costo Est.'],
                    $rows
                );
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Tipo de exportación inválido']);
        }
        exit;
    }

    // ─── REPORTES JSON ───
    header('Content-Type: application/json');

    switch ($report) {
        case 'combustible':
            $where = "WHERE 1=1";
            $params = [];
            if ($from) { $where .= " AND c.fecha >= ?"; $params[] = $from; }
            if ($to) { $where .= " AND c.fecha <= ?"; $params[] = $to; }
            if ($vid) { $where .= " AND c.vehiculo_id = ?"; $params[] = $vid; }

            // Totales
            $stmt = $db->prepare("SELECT COUNT(*) as registros, COALESCE(SUM(c.litros),0) as total_litros, COALESCE(SUM(c.total),0) as total_gasto,
                COALESCE(AVG(c.litros),0) as avg_litros, COALESCE(AVG(c.total),0) as avg_gasto
                FROM combustible c $where");
            $stmt->execute($params);
            $totales = $stmt->fetch();

            // Por vehículo
            $stmt = $db->prepare("SELECT v.placa, v.marca, SUM(c.litros) as litros, SUM(c.total) as gasto, COUNT(*) as cargas,
                MAX(c.km) - MIN(c.km) as km_recorridos
                FROM combustible c JOIN vehiculos v ON v.id=c.vehiculo_id
                $where GROUP BY v.id, v.placa, v.marca ORDER BY gasto DESC");
            $stmt->execute($params);
            $porVehiculo = $stmt->fetchAll();

            // Por mes
            $stmt = $db->prepare("SELECT DATE_FORMAT(c.fecha, '%Y-%m') as mes, SUM(c.litros) as litros, SUM(c.total) as gasto, COUNT(*) as cargas
                FROM combustible c $where GROUP BY mes ORDER BY mes DESC LIMIT 12");
            $stmt->execute($params);
            $porMes = $stmt->fetchAll();

            echo json_encode(['totales' => $totales, 'por_vehiculo' => $porVehiculo, 'por_mes' => $porMes]);
            break;

        case 'mantenimiento':
            $where = "WHERE 1=1";
            $params = [];
            if ($from) { $where .= " AND m.fecha >= ?"; $params[] = $from; }
            if ($to) { $where .= " AND m.fecha <= ?"; $params[] = $to; }
            if ($vid) { $where .= " AND m.vehiculo_id = ?"; $params[] = $vid; }

            $stmt = $db->prepare("SELECT COUNT(*) as registros, COALESCE(SUM(m.costo),0) as total_costo, COALESCE(AVG(m.costo),0) as avg_costo
                FROM mantenimientos m $where");
            $stmt->execute($params);
            $totales = $stmt->fetch();

            $stmt = $db->prepare("SELECT v.placa, v.marca, SUM(m.costo) as gasto, COUNT(*) as servicios
                FROM mantenimientos m JOIN vehiculos v ON v.id=m.vehiculo_id
                $where GROUP BY v.id, v.placa, v.marca ORDER BY gasto DESC");
            $stmt->execute($params);
            $porVehiculo = $stmt->fetchAll();

            $stmt = $db->prepare("SELECT m.tipo, SUM(m.costo) as gasto, COUNT(*) as servicios
                FROM mantenimientos m $where GROUP BY m.tipo ORDER BY gasto DESC");
            $stmt->execute($params);
            $porTipo = $stmt->fetchAll();

            echo json_encode(['totales' => $totales, 'por_vehiculo' => $porVehiculo, 'por_tipo' => $porTipo]);
            break;

        case 'vehiculos':
            $stmt = $db->prepare("SELECT v.id, v.placa, v.marca, v.modelo, v.estado, v.km_actual,
                (SELECT COUNT(*) FROM asignaciones a WHERE a.vehiculo_id=v.id) as total_asignaciones,
                (SELECT COUNT(*) FROM asignaciones a WHERE a.vehiculo_id=v.id AND a.estado='Activa') as asignaciones_activas,
                (SELECT COUNT(*) FROM mantenimientos m WHERE m.vehiculo_id=v.id) as total_mantenimientos,
                (SELECT COALESCE(SUM(m.costo),0) FROM mantenimientos m WHERE m.vehiculo_id=v.id) as gasto_mantenimiento,
                (SELECT COALESCE(SUM(c.litros),0) FROM combustible c WHERE c.vehiculo_id=v.id) as total_litros,
                (SELECT COALESCE(SUM(c.total),0) FROM combustible c WHERE c.vehiculo_id=v.id) as gasto_combustible,
                (SELECT COUNT(*) FROM incidentes i WHERE i.vehiculo_id=v.id) as total_incidentes
                FROM vehiculos v WHERE v.deleted_at IS NULL ORDER BY v.placa");
            $stmt->execute();
            echo json_encode(['rows' => $stmt->fetchAll()]);
            break;

        case 'top_costosos':
            $where = "";
            $params = [];
            if ($from || $to) {
                $conds = [];
                if ($from) { $conds[] = "c.fecha >= ?"; $params[] = $from; }
                if ($to) { $conds[] = "c.fecha <= ?"; $params[] = $to; }
                $where = "WHERE " . implode(' AND ', $conds);
            }
            $stmt = $db->prepare("SELECT v.placa, v.marca, v.modelo,
                COALESCE(SUM(c.total),0) as gasto_combustible,
                (SELECT COALESCE(SUM(m.costo),0) FROM mantenimientos m WHERE m.vehiculo_id=v.id) as gasto_mantenimiento,
                COALESCE(SUM(c.total),0) + (SELECT COALESCE(SUM(m.costo),0) FROM mantenimientos m WHERE m.vehiculo_id=v.id) as gasto_total
                FROM vehiculos v
                LEFT JOIN combustible c ON c.vehiculo_id=v.id " . ($from||$to ? "AND ".str_replace('WHERE ','',str_replace('c.','c.',$where)) : "") . "
                WHERE v.deleted_at IS NULL
                GROUP BY v.id, v.placa, v.marca, v.modelo
                ORDER BY gasto_total DESC LIMIT 15");
            $stmt->execute($params);
            echo json_encode(['rows' => $stmt->fetchAll()]);
            break;

        case 'talleres':
            $stmt = $db->prepare("SELECT p.id, p.nombre, p.es_taller_autorizado,
                COUNT(m.id) as total_servicios, COALESCE(SUM(m.costo),0) as gasto_total,
                COALESCE(AVG(m.costo),0) as avg_costo,
                SUM(CASE WHEN m.estado='Completado' THEN 1 ELSE 0 END) as completados,
                SUM(CASE WHEN m.estado IN ('En proceso','Pendiente') THEN 1 ELSE 0 END) as activos
                FROM proveedores p
                LEFT JOIN mantenimientos m ON m.proveedor_id=p.id
                WHERE p.es_taller_autorizado=1
                GROUP BY p.id, p.nombre, p.es_taller_autorizado
                ORDER BY gasto_total DESC");
            $stmt->execute();
            echo json_encode(['rows' => $stmt->fetchAll()]);
            break;

        default:
            echo json_encode(['error' => 'Reporte no especificado. Use: combustible, mantenimiento, vehiculos, top_costosos, talleres']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
