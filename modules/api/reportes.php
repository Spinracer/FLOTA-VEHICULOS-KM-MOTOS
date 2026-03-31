<?php
/**
 * API de Reportes y Exportaciones
 * Endpoints:
 *   GET ?report=combustible      → JSON resumen
 *   GET ?report=mantenimiento    → JSON resumen
 *   GET ?report=vehiculos        → JSON utilización
 *   GET ?report=top_costosos     → JSON top vehículos por gasto
 *   GET ?report=talleres         → JSON desempeño por taller
 *   GET ?export=combustible&format=csv|xlsx|pdf  → Descarga
 *   GET ?export=mantenimiento&format=csv|xlsx|pdf → Descarga
 *   GET ?export=asignaciones&format=csv|xlsx|pdf  → Descarga
 *   GET ?export=incidentes&format=csv|xlsx|pdf    → Descarga
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
$groupBy = trim($_GET['group_by'] ?? '');
$orderBy = trim($_GET['order_by'] ?? '');
$orderDir = strtoupper(trim($_GET['order_dir'] ?? 'DESC'));
if (!in_array($orderDir, ['ASC','DESC'])) $orderDir = 'DESC';

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
                audit_log('reportes', 'export_' . $format, null, [], ['tipo' => 'combustible', 'formato' => $format, 'filtros' => compact('from','to','vid')]);
                $totalLitros = 0; $totalGasto = 0;
                foreach ($rows as $r) { $totalLitros += (float)($r[4] ?? 0); $totalGasto += (float)($r[6] ?? 0); }
                $totals = ['label' => 'TOTAL', 'values' => ['','','','', number_format($totalLitros,1).' L', '', 'L '.number_format($totalGasto,2), '','','','','','']];
                export_dispatch($format, 'reporte_combustible',
                    ['Fecha','Placa','Marca','Conductor','Litros','Costo/L','Total','KM','Tipo Carga','Proveedor','Método Pago','Recibo','Notas'],
                    $rows, 'Reporte de Combustible', $totals
                );
                break;

            case 'mantenimiento':
                $where = "WHERE m.deleted_at IS NULL";
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
                audit_log('reportes', 'export_' . $format, null, [], ['tipo' => 'mantenimiento', 'formato' => $format, 'filtros' => compact('from','to','vid','provId')]);
                $totalCosto = 0;
                foreach ($rows as $r) { $totalCosto += (float)($r[5] ?? 0); }
                $totals = ['label' => 'TOTAL', 'values' => ['','','','','','L '.number_format($totalCosto,2),'','','','']];
                export_dispatch($format, 'reporte_mantenimiento',
                    ['Fecha','Placa','Marca','Tipo','Descripción','Costo','KM','Próx. KM','Proveedor','Estado'],
                    $rows, 'Reporte de Mantenimiento', $totals
                );
                break;

            case 'asignaciones':
                $where = "WHERE 1=1";
                $params = [];
                if ($from) { $where .= " AND a.start_at >= ?"; $params[] = $from; }
                if ($to) { $where .= " AND a.start_at <= ?"; $params[] = $to; }
                if ($vid) { $where .= " AND a.vehiculo_id = ?"; $params[] = $vid; }
                $stmt = $db->prepare("SELECT a.start_at, a.end_at, v.placa, v.marca, o.nombre AS operador, o.dni, COALESCE(dep.nombre,'') AS departamento, a.start_km, a.end_km, a.estado, a.override_reason
                    FROM asignaciones a
                    JOIN vehiculos v ON v.id=a.vehiculo_id
                    JOIN operadores o ON o.id=a.operador_id
                    LEFT JOIN departamentos dep ON dep.id=o.departamento_id
                    $where ORDER BY a.start_at DESC");
                $stmt->execute($params);
                $rows = [];
                while ($r = $stmt->fetch()) {
                    $rows[] = array_values($r);
                }
                audit_log('reportes', 'export_' . $format, null, [], ['tipo' => 'asignaciones', 'formato' => $format, 'filtros' => compact('from','to','vid')]);
                export_dispatch($format, 'reporte_asignaciones',
                    ['Inicio','Fin','Placa','Marca','Operador','DNI','Departamento','KM Inicio','KM Fin','Estado','Override'],
                    $rows, 'Reporte de Asignaciones'
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
                audit_log('reportes', 'export_' . $format, null, [], ['tipo' => 'incidentes', 'formato' => $format, 'filtros' => compact('from','to','vid')]);
                $totalCosto = 0;
                foreach ($rows as $r) { $totalCosto += (float)($r[7] ?? 0); }
                $totals = ['label' => 'TOTAL', 'values' => ['','','','','','','','L '.number_format($totalCosto,2)]];
                export_dispatch($format, 'reporte_incidentes',
                    ['Fecha','Placa','Marca','Tipo','Descripción','Severidad','Estado','Costo Est.'],
                    $rows, 'Reporte de Incidentes', $totals
                );
                break;

            case 'importaciones':
                $where = "WHERE entidad='importacion_vehiculos' AND accion IN ('import','import_partial','import_error')";
                $params = [];
                if ($from) { $where .= " AND DATE(created_at) >= ?"; $params[] = $from; }
                if ($to) { $where .= " AND DATE(created_at) <= ?"; $params[] = $to; }
                $stmt = $db->prepare("SELECT al.id, al.usuario_id, u.nombre AS usuario_nombre, al.accion AS resultado,
                    JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.archivo')) AS archivo,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.insertados')),0)+0 AS insertados,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.actualizados')),0)+0 AS actualizados,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.errores')),0)+0 AS errores,
                    JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.update_key_field')) AS campo_actualizar,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.duracion_segundos')),0)+0 AS duracion,
                    al.created_at
                    FROM audit_logs al
                    LEFT JOIN usuarios u ON u.id=al.usuario_id
                    $where ORDER BY al.created_at DESC");
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                $exportRows = [];
                foreach ($rows as $r) {
                    $exportRows[] = [
                        $r['usuario_nombre'],
                        $r['resultado'],
                        $r['archivo'],
                        $r['insertados'],
                        $r['actualizados'],
                        $r['errores'],
                        $r['campo_actualizar'],
                        $r['duracion'],
                        $r['created_at'],
                    ];
                }
                audit_log('reportes', 'export_' . $format, null, [], ['tipo' => 'importaciones', 'formato' => $format, 'filtros' => compact('from','to')]);
                export_dispatch($format, 'reporte_importaciones',
                    ['Usuario','Resultado','Archivo','Insertados','Actualizados','Errores','Campo Actualizar','Duración (s)','Fecha'],
                    $exportRows, 'Reporte de Importaciones de Vehículos'
                );
                break;

            case 'historial_asignaciones':
                $where = "WHERE 1=1";
                $params = [];
                $opId = (int)($_GET['operador_id'] ?? 0);
                if ($from) { $where .= " AND a.start_at >= ?"; $params[] = $from; }
                if ($to) { $where .= " AND a.start_at <= ?"; $params[] = $to; }
                if ($vid) { $where .= " AND a.vehiculo_id = ?"; $params[] = $vid; }
                if ($opId) { $where .= " AND a.operador_id = ?"; $params[] = $opId; }
                $stmt = $db->prepare("SELECT a.start_at, a.end_at, v.placa, CONCAT(v.marca,' ',v.modelo) AS vehiculo, o.nombre AS operador, o.dni, COALESCE(dep.nombre,'') AS departamento, a.start_km, a.end_km, a.estado,
                    CASE WHEN a.end_km IS NOT NULL AND a.start_km IS NOT NULL THEN ROUND(a.end_km - a.start_km,1) ELSE NULL END AS km_recorridos
                    FROM asignaciones a
                    JOIN vehiculos v ON v.id=a.vehiculo_id
                    JOIN operadores o ON o.id=a.operador_id
                    LEFT JOIN departamentos dep ON dep.id=o.departamento_id
                    $where ORDER BY a.start_at DESC");
                $stmt->execute($params);
                $rows = [];
                while ($r = $stmt->fetch()) { $rows[] = array_values($r); }
                $totalKm = 0;
                foreach ($rows as $r) { $totalKm += (float)($r[10] ?? 0); }
                $totals = ['label'=>'TOTAL','values'=>['','','','','','','','','','',number_format($totalKm,1).' km']];
                audit_log('reportes', 'export_' . $format, null, [], ['tipo' => 'historial_asignaciones', 'formato' => $format]);
                export_dispatch($format, 'historial_asignaciones',
                    ['Inicio','Fin','Placa','Vehículo','Operador','DNI','Departamento','KM Inicio','KM Fin','Estado','KM Recorridos'],
                    $rows, 'Historial de Asignaciones', $totals
                );
                break;

            case 'vehiculos':
                $where = "WHERE v.deleted_at IS NULL";
                $params = [];
                if ($vid) { $where .= " AND v.id = ?"; $params[] = $vid; }
                $stmt = $db->prepare("SELECT v.placa, v.marca, v.modelo, v.anio, v.estado, v.km_actual,
                    (SELECT COUNT(*) FROM asignaciones a WHERE a.vehiculo_id=v.id) AS asignaciones,
                    (SELECT COUNT(*) FROM mantenimientos m WHERE m.vehiculo_id=v.id AND m.deleted_at IS NULL) AS mantenimientos,
                    (SELECT COALESCE(SUM(c.litros),0) FROM combustible c WHERE c.vehiculo_id=v.id) AS litros,
                    (SELECT COALESCE(SUM(c.total),0) FROM combustible c WHERE c.vehiculo_id=v.id) AS gasto_comb,
                    (SELECT COALESCE(SUM(m2.costo),0) FROM mantenimientos m2 WHERE m2.vehiculo_id=v.id AND m2.deleted_at IS NULL) AS gasto_mant,
                    (SELECT COUNT(*) FROM incidentes i WHERE i.vehiculo_id=v.id) AS incidentes
                    FROM vehiculos v $where ORDER BY v.placa");
                $stmt->execute($params);
                $rows = [];
                while ($r = $stmt->fetch()) { $rows[] = array_values($r); }
                audit_log('reportes', 'export_' . $format, null, [], ['tipo' => 'vehiculos', 'formato' => $format]);
                $tLit = 0; $tGC = 0; $tGM = 0;
                foreach ($rows as $r) { $tLit += (float)($r[8] ?? 0); $tGC += (float)($r[9] ?? 0); $tGM += (float)($r[10] ?? 0); }
                $totals = ['label' => 'TOTAL', 'values' => ['','','','','','','','',number_format($tLit,0).' L','L '.number_format($tGC,2),'L '.number_format($tGM,2),'']];
                export_dispatch($format, 'reporte_vehiculos',
                    ['Placa','Marca','Modelo','Año','Estado','KM','Asignaciones','Mantenimientos','Litros','Gasto Comb.','Gasto Mant.','Incidentes'],
                    $rows, 'Reporte de Vehículos', $totals
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
            $opId = (int)($_GET['operador_id'] ?? 0);
            if ($opId) { $where .= " AND c.operador_id = ?"; $params[] = $opId; }

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

            // Agrupación avanzada
            $agrupado = null;
            $validGroups = ['vehiculo','mes','semana','proveedor','tipo_carga','metodo_pago'];
            if ($groupBy && in_array($groupBy, $validGroups)) {
                $groupCol = match($groupBy) {
                    'vehiculo'    => 'v.placa',
                    'mes'         => "DATE_FORMAT(c.fecha, '%Y-%m')",
                    'semana'      => "DATE_FORMAT(c.fecha, '%x-W%v')",
                    'proveedor'   => "COALESCE(p.nombre, 'Sin proveedor')",
                    'tipo_carga'  => 'c.tipo_carga',
                    'metodo_pago' => 'c.metodo_pago',
                };
                $orderCol = match($orderBy) {
                    'cargas'  => 'cargas',
                    'litros'  => 'litros',
                    'gasto'   => 'gasto',
                    default   => 'gasto',
                };
                $stmt = $db->prepare("SELECT {$groupCol} as grupo, SUM(c.litros) as litros, SUM(c.total) as gasto, COUNT(*) as cargas
                    FROM combustible c
                    LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                    LEFT JOIN proveedores p ON p.id=c.proveedor_id
                    $where GROUP BY grupo ORDER BY {$orderCol} {$orderDir}");
                $stmt->execute($params);
                $agrupado = $stmt->fetchAll();
            }

            // Por operador
            $stmt = $db->prepare("SELECT COALESCE(o.nombre,'Sin operador') AS operador, SUM(c.litros) as litros, SUM(c.total) as gasto, COUNT(*) as cargas
                FROM combustible c LEFT JOIN operadores o ON o.id=c.operador_id
                $where GROUP BY o.id, o.nombre ORDER BY gasto DESC");
            $stmt->execute($params);
            $porOperador = $stmt->fetchAll();

            // Detalle individual
            $stmt = $db->prepare("SELECT c.fecha, v.placa, COALESCE(o.nombre,'—') AS operador, c.litros, c.costo_litro, c.total, c.km,
                COALESCE(p.nombre,'—') AS proveedor, c.metodo_pago
                FROM combustible c
                LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                LEFT JOIN operadores o ON o.id=c.operador_id
                LEFT JOIN proveedores p ON p.id=c.proveedor_id
                $where ORDER BY c.fecha DESC, c.id DESC LIMIT 500");
            $stmt->execute($params);
            $detalle = $stmt->fetchAll();

            $totales['vehiculos'] = count($porVehiculo);
            $totales['operadores'] = count($porOperador);
            $totales['avg_costo_litro'] = $totales['total_litros'] > 0 ? round($totales['total_gasto'] / $totales['total_litros'], 2) : 0;

            $result = ['totales' => $totales, 'por_vehiculo' => $porVehiculo, 'por_operador' => $porOperador, 'por_mes' => $porMes, 'detalle' => $detalle];
            if ($agrupado !== null) $result['agrupado'] = $agrupado;
            echo json_encode($result);
            break;

        case 'mantenimiento':
            $where = "WHERE m.deleted_at IS NULL";
            $params = [];
            if ($from) { $where .= " AND m.fecha >= ?"; $params[] = $from; }
            if ($to) { $where .= " AND m.fecha <= ?"; $params[] = $to; }
            if ($vid) { $where .= " AND m.vehiculo_id = ?"; $params[] = $vid; }
            if ($provId) { $where .= " AND m.proveedor_id = ?"; $params[] = $provId; }

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

            // Agrupación avanzada
            $agrupado = null;
            $validGroups = ['vehiculo','mes','semana','tipo','proveedor','estado'];
            if ($groupBy && in_array($groupBy, $validGroups)) {
                $groupCol = match($groupBy) {
                    'vehiculo'  => 'v.placa',
                    'mes'       => "DATE_FORMAT(m.fecha, '%Y-%m')",
                    'semana'    => "DATE_FORMAT(m.fecha, '%x-W%v')",
                    'tipo'      => 'm.tipo',
                    'proveedor' => "COALESCE(p.nombre, 'Sin proveedor')",
                    'estado'    => 'm.estado',
                };
                $orderCol = match($orderBy) {
                    'servicios' => 'servicios',
                    'gasto'     => 'gasto',
                    default     => 'gasto',
                };
                $stmt = $db->prepare("SELECT {$groupCol} as grupo, SUM(m.costo) as gasto, COUNT(*) as servicios
                    FROM mantenimientos m
                    LEFT JOIN vehiculos v ON v.id=m.vehiculo_id
                    LEFT JOIN proveedores p ON p.id=m.proveedor_id
                    $where GROUP BY grupo ORDER BY {$orderCol} {$orderDir}");
                $stmt->execute($params);
                $agrupado = $stmt->fetchAll();
            }

            // Por proveedor
            $stmt = $db->prepare("SELECT COALESCE(p.nombre,'Sin proveedor') AS proveedor, SUM(m.costo) as gasto, COUNT(*) as servicios
                FROM mantenimientos m LEFT JOIN proveedores p ON p.id=m.proveedor_id
                $where GROUP BY p.id, p.nombre ORDER BY gasto DESC");
            $stmt->execute($params);
            $porProveedor = $stmt->fetchAll();

            // Detalle individual
            $stmt = $db->prepare("SELECT m.fecha, v.placa, v.marca, m.tipo, m.descripcion, m.costo, m.km, COALESCE(p.nombre,'—') AS proveedor, m.estado
                FROM mantenimientos m
                LEFT JOIN vehiculos v ON v.id=m.vehiculo_id
                LEFT JOIN proveedores p ON p.id=m.proveedor_id
                $where ORDER BY m.fecha DESC, m.id DESC LIMIT 500");
            $stmt->execute($params);
            $detalle = $stmt->fetchAll();

            $completados = 0; $pendientes = 0;
            foreach ($detalle as $d) {
                if ($d['estado'] === 'Completado') $completados++;
                else $pendientes++;
            }
            $totales['completados'] = $completados;
            $totales['pendientes'] = $pendientes;
            $totales['vehiculos'] = count($porVehiculo);
            $totales['proveedores'] = count($porProveedor);

            $result = ['totales' => $totales, 'por_vehiculo' => $porVehiculo, 'por_tipo' => $porTipo, 'por_proveedor' => $porProveedor, 'detalle' => $detalle];
            if ($agrupado !== null) $result['agrupado'] = $agrupado;
            echo json_encode($result);
            break;

        case 'vehiculos':
            $stmt = $db->prepare("SELECT v.id, v.placa, v.marca, v.modelo, v.estado, v.km_actual,
                (SELECT COUNT(*) FROM asignaciones a WHERE a.vehiculo_id=v.id) as total_asignaciones,
                (SELECT COUNT(*) FROM asignaciones a WHERE a.vehiculo_id=v.id AND a.estado='Activa') as asignaciones_activas,
                (SELECT COUNT(*) FROM mantenimientos m WHERE m.vehiculo_id=v.id AND m.deleted_at IS NULL) as total_mantenimientos,
                (SELECT COALESCE(SUM(m.costo),0) FROM mantenimientos m WHERE m.vehiculo_id=v.id AND m.deleted_at IS NULL) as gasto_mantenimiento,
                (SELECT COALESCE(SUM(c.litros),0) FROM combustible c WHERE c.vehiculo_id=v.id) as total_litros,
                (SELECT COALESCE(SUM(c.total),0) FROM combustible c WHERE c.vehiculo_id=v.id) as gasto_combustible,
                (SELECT COUNT(*) FROM incidentes i WHERE i.vehiculo_id=v.id) as total_incidentes
                FROM vehiculos v WHERE v.deleted_at IS NULL ORDER BY v.placa");
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $activos = 0; $totalKm = 0; $totalGC = 0; $totalGM = 0; $totalInc = 0;
            $porEstado = [];
            foreach ($rows as $r) {
                if ($r['estado'] === 'Activo') $activos++;
                $totalKm += (float)$r['km_actual'];
                $totalGC += (float)$r['gasto_combustible'];
                $totalGM += (float)$r['gasto_mantenimiento'];
                $totalInc += (int)$r['total_incidentes'];
                $porEstado[$r['estado']] = ($porEstado[$r['estado']] ?? 0) + 1;
            }
            $porEstadoArr = [];
            foreach ($porEstado as $est => $cnt) { $porEstadoArr[] = ['estado' => $est, 'cantidad' => $cnt]; }
            echo json_encode([
                'rows' => $rows,
                'totales' => ['total'=>count($rows),'activos'=>$activos,'km_total'=>$totalKm,'gasto_combustible'=>$totalGC,'gasto_mantenimiento'=>$totalGM,'incidentes'=>$totalInc],
                'por_estado' => $porEstadoArr,
            ]);
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
                (SELECT COALESCE(SUM(m.costo),0) FROM mantenimientos m WHERE m.vehiculo_id=v.id AND m.deleted_at IS NULL) as gasto_mantenimiento,
                COALESCE(SUM(c.total),0) + (SELECT COALESCE(SUM(m.costo),0) FROM mantenimientos m WHERE m.vehiculo_id=v.id AND m.deleted_at IS NULL) as gasto_total
                FROM vehiculos v
                LEFT JOIN combustible c ON c.vehiculo_id=v.id " . ($from||$to ? "AND ".str_replace('WHERE ','',str_replace('c.','c.',$where)) : "") . "
                WHERE v.deleted_at IS NULL
                GROUP BY v.id, v.placa, v.marca, v.modelo
                ORDER BY gasto_total DESC LIMIT 15");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            $totalGC = 0; $totalGM = 0; $totalG = 0;
            foreach ($rows as $r) { $totalGC += (float)$r['gasto_combustible']; $totalGM += (float)$r['gasto_mantenimiento']; $totalG += (float)$r['gasto_total']; }
            echo json_encode([
                'rows' => $rows,
                'totales' => ['vehiculos'=>count($rows),'gasto_combustible'=>$totalGC,'gasto_mantenimiento'=>$totalGM,'gasto_total'=>$totalG],
            ]);
            break;

        case 'talleres':
            $stmt = $db->prepare("SELECT p.id, p.nombre, p.es_taller_autorizado,
                COUNT(m.id) as total_servicios, COALESCE(SUM(m.costo),0) as gasto_total,
                COALESCE(AVG(m.costo),0) as avg_costo,
                SUM(CASE WHEN m.estado='Completado' THEN 1 ELSE 0 END) as completados,
                SUM(CASE WHEN m.estado IN ('En proceso','Pendiente') THEN 1 ELSE 0 END) as activos
                FROM proveedores p
                LEFT JOIN mantenimientos m ON m.proveedor_id=p.id AND m.deleted_at IS NULL
                WHERE p.es_taller_autorizado=1
                GROUP BY p.id, p.nombre, p.es_taller_autorizado
                ORDER BY gasto_total DESC");
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $totalServ = 0; $totalGasto = 0; $totalComp = 0; $totalAct = 0;
            foreach ($rows as $r) { $totalServ += (int)$r['total_servicios']; $totalGasto += (float)$r['gasto_total']; $totalComp += (int)$r['completados']; $totalAct += (int)$r['activos']; }
            echo json_encode([
                'rows' => $rows,
                'totales' => ['talleres'=>count($rows),'total_servicios'=>$totalServ,'gasto_total'=>$totalGasto,'completados'=>$totalComp,'activos'=>$totalAct],
            ]);
            break;

        case 'overrides':
            // Reporte de overrides: todas las acciones de override registradas en auditoría
            $where = "WHERE (a.accion LIKE '%override%' OR a.meta_json LIKE '%override%' OR a.despues_json LIKE '%override%')";
            $params = [];
            if ($from) { $where .= " AND a.created_at >= ?"; $params[] = $from . ' 00:00:00'; }
            if ($to) { $where .= " AND a.created_at <= ?"; $params[] = $to . ' 23:59:59'; }
            $stmt = $db->prepare("SELECT a.id, a.user_email, a.user_rol, a.entidad, a.entidad_id, a.accion,
                a.antes_json, a.despues_json, a.meta_json, a.ip, a.created_at
                FROM audit_logs a $where ORDER BY a.created_at DESC LIMIT 200");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            // Enriquecer con el motivo de override
            foreach ($rows as &$r) {
                $meta = $r['meta_json'] ? json_decode($r['meta_json'], true) : [];
                $after = $r['despues_json'] ? json_decode($r['despues_json'], true) : [];
                $r['override_reason'] = $meta['override_reason'] ?? $after['override_reason'] ?? $meta['justificacion'] ?? $after['justificacion'] ?? '—';
                $r['antes'] = $r['antes_json'] ? json_decode($r['antes_json'], true) : null;
                $r['despues'] = $r['despues_json'] ? json_decode($r['despues_json'], true) : null;
                $r['meta'] = $meta;
                unset($r['antes_json'], $r['despues_json'], $r['meta_json']);
            }
            unset($r);

            // Resumen
            $byUser = []; $byEntity = [];
            foreach ($rows as $r) {
                $byUser[$r['user_email']] = ($byUser[$r['user_email']] ?? 0) + 1;
                $byEntity[$r['entidad']] = ($byEntity[$r['entidad']] ?? 0) + 1;
            }
            arsort($byUser); arsort($byEntity);

            $porUsuarioArr = [];
            foreach ($byUser as $email => $count) { $porUsuarioArr[] = ['usuario' => $email, 'overrides' => $count]; }
            $porEntidadArr = [];
            foreach ($byEntity as $ent => $count) { $porEntidadArr[] = ['entidad' => $ent, 'overrides' => $count]; }

            echo json_encode([
                'total' => count($rows),
                'rows' => $rows,
                'por_usuario' => $porUsuarioArr,
                'por_entidad' => $porEntidadArr,
            ]);
            break;

        case 'operador_360':
            // Perfil 360 del operador
            $opId = (int)($_GET['operador_id'] ?? 0);
            if ($opId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'operador_id es obligatorio.']);
                break;
            }
            $opStmt = $db->prepare("SELECT id, nombre, estado, licencia, categoria_lic, venc_licencia, telefono, email FROM operadores WHERE id=? LIMIT 1");
            $opStmt->execute([$opId]);
            $operador = $opStmt->fetch();
            if (!$operador) { http_response_code(404); echo json_encode(['error' => 'Operador no encontrado.']); break; }

            // Asignaciones
            $asgStmt = $db->prepare("SELECT a.id, v.placa, v.marca, a.start_at, a.end_at, a.start_km, a.end_km, a.estado FROM asignaciones a JOIN vehiculos v ON v.id=a.vehiculo_id WHERE a.operador_id=? ORDER BY a.start_at DESC LIMIT 50");
            $asgStmt->execute([$opId]);
            $asignaciones = $asgStmt->fetchAll();

            // Combustible asociado
            $fuelStmt = $db->prepare("SELECT c.id, c.fecha, v.placa, c.litros, c.total, c.km FROM combustible c
                JOIN asignaciones a ON a.vehiculo_id=c.vehiculo_id AND a.operador_id=? AND c.fecha BETWEEN DATE(a.start_at) AND DATE(COALESCE(a.end_at, NOW()))
                JOIN vehiculos v ON v.id=c.vehiculo_id ORDER BY c.fecha DESC LIMIT 100");
            $fuelStmt->execute([$opId]);
            $combustible = $fuelStmt->fetchAll();

            // Incidentes
            $incStmt = $db->prepare("SELECT i.id, i.fecha, v.placa, i.tipo, i.severidad, i.estado FROM incidentes i
                JOIN asignaciones a ON a.vehiculo_id=i.vehiculo_id AND a.operador_id=? AND i.fecha BETWEEN DATE(a.start_at) AND DATE(COALESCE(a.end_at, NOW()))
                JOIN vehiculos v ON v.id=i.vehiculo_id ORDER BY i.fecha DESC LIMIT 50");
            $incStmt->execute([$opId]);
            $incidentes = $incStmt->fetchAll();

            // Totales
            $totales = [
                'asignaciones' => count($asignaciones),
                'litros_total' => array_sum(array_column($combustible, 'litros')),
                'gasto_combustible' => array_sum(array_column($combustible, 'total')),
                'incidentes' => count($incidentes),
                'km_total' => 0,
            ];
            foreach ($asignaciones as $a) {
                if ($a['end_km'] && $a['start_km']) {
                    $totales['km_total'] += max(0, (float)$a['end_km'] - (float)$a['start_km']);
                }
            }

            echo json_encode([
                'operador' => $operador,
                'totales' => $totales,
                'asignaciones' => $asignaciones,
                'combustible' => $combustible,
                'incidentes' => $incidentes,
            ]);
            break;

        case 'importaciones':
            $where = "WHERE entidad='importacion_vehiculos' AND accion IN ('import','import_partial','import_error')";
            $params = [];
            if ($from) { $where .= " AND DATE(al.created_at) >= ?"; $params[] = $from; }
            if ($to) { $where .= " AND DATE(al.created_at) <= ?"; $params[] = $to; }
            $usuarioId = (int)($_GET['usuario_id'] ?? 0);
            if ($usuarioId) { $where .= " AND al.usuario_id = ?"; $params[] = $usuarioId; }

            $stmt = $db->prepare("SELECT COUNT(*) as total_importaciones,
                COUNT(DISTINCT al.usuario_id) as usuarios_activos,
                SUM(CASE WHEN al.accion='import' THEN 1 ELSE 0 END) as exitosas,
                SUM(CASE WHEN al.accion='import_partial' THEN 1 ELSE 0 END) as parciales,
                SUM(CASE WHEN al.accion='import_error' THEN 1 ELSE 0 END) as fallidas,
                SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta,'$.insertados')),0)+0) as total_insertados,
                SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta,'$.actualizados')),0)+0) as total_actualizados,
                SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta,'$.errores')),0)+0) as total_errores
                FROM audit_logs al
                $where");
            $stmt->execute($params);
            $totales = $stmt->fetch();

            $stmt = $db->prepare("SELECT u.id as usuario_id, u.nombre,
                COUNT(*) as importaciones,
                SUM(CASE WHEN al.accion='import' THEN 1 ELSE 0 END) as exitosas,
                SUM(CASE WHEN al.accion='import_partial' THEN 1 ELSE 0 END) as parciales,
                SUM(CASE WHEN al.accion='import_error' THEN 1 ELSE 0 END) as fallidas,
                SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta,'$.insertados')),0)+0) as total_insertados,
                SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta,'$.actualizados')),0)+0) as total_actualizados,
                MAX(al.created_at) as ultima_importacion
                FROM audit_logs al
                JOIN usuarios u ON u.id=al.usuario_id
                $where
                GROUP BY u.id, u.nombre
                ORDER BY importaciones DESC");
            $stmt->execute($params);
            $usuarios = $stmt->fetchAll();

            $detailQuery = "SELECT al.id, al.usuario_id, u.nombre AS usuario_nombre, al.accion AS resultado,
                JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.archivo')) AS archivo,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.insertados')),0)+0 AS insertados,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.actualizados')),0)+0 AS actualizados,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.errores')),0)+0 AS errores,
                JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.update_key_field')) AS campo_actualizar,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(al.meta, '$.duracion_segundos')),0)+0 AS duracion,
                al.created_at
                FROM audit_logs al
                LEFT JOIN usuarios u ON u.id=al.usuario_id
                $where
                ORDER BY al.created_at DESC
                LIMIT 500";
            $stmt = $db->prepare($detailQuery);
            $stmt->execute($params);
            $detalle = $stmt->fetchAll();

            echo json_encode([
                'totales' => [
                    'total_importaciones' => (int)($totales['total_importaciones'] ?? 0),
                    'usuarios_activos' => (int)($totales['usuarios_activos'] ?? 0),
                    'exitosas' => (int)($totales['exitosas'] ?? 0),
                    'parciales' => (int)($totales['parciales'] ?? 0),
                    'fallidas' => (int)($totales['fallidas'] ?? 0),
                    'total_insertados' => (int)($totales['total_insertados'] ?? 0),
                    'total_actualizados' => (int)($totales['total_actualizados'] ?? 0),
                    'total_errores' => (int)($totales['total_errores'] ?? 0),
                ],
                'usuarios' => $usuarios,
                'detalle' => $detalle,
            ]);
            break;

        case 'ordenes_compra':
            $where = "WHERE oc.deleted_at IS NULL";
            $params = [];
            $ocOpId = (int)($_GET['operador_id'] ?? 0);
            $ocDepId = (int)($_GET['departamento_id'] ?? 0);
            if ($from) { $where .= " AND oc.created_at >= ?"; $params[] = $from; }
            if ($to) { $where .= " AND oc.created_at <= ?"; $params[] = $to . ' 23:59:59'; }
            if ($vid) { $where .= " AND oc.vehiculo_id = ?"; $params[] = $vid; }
            if ($ocDepId) { $where .= " AND dep2.id = ?"; $params[] = $ocDepId; }
            if ($ocOpId) { $where .= " AND (oc.solicitante_id = ? OR EXISTS (SELECT 1 FROM mantenimientos mt WHERE mt.orden_compra_id=oc.id AND mt.operador_id=?))"; $params[] = $ocOpId; $params[] = $ocOpId; }

            // Detalle
            $stmt = $db->prepare("SELECT oc.id, oc.descripcion, oc.monto_estimado, oc.estado, oc.urgencia, oc.created_at,
                u.nombre AS solicitante_nombre,
                v.id AS vehiculo_id, v.placa, CONCAT(v.marca,' ',v.modelo) AS vehiculo,
                p.nombre AS proveedor_nombre,
                m.id AS mantenimiento_id,
                (SELECT COUNT(*) FROM orden_compra_items WHERE orden_compra_id=oc.id) AS items_count,
                COALESCE(dep2.nombre,'') AS departamento
                FROM ordenes_compra oc
                LEFT JOIN usuarios u ON u.id=oc.solicitante_id
                LEFT JOIN vehiculos v ON v.id=oc.vehiculo_id
                LEFT JOIN proveedores p ON p.id=oc.proveedor_id
                LEFT JOIN mantenimientos m ON m.orden_compra_id=oc.id
                LEFT JOIN operadores op ON op.id=(SELECT operador_id FROM asignaciones WHERE vehiculo_id=oc.vehiculo_id AND estado='Activa' LIMIT 1)
                LEFT JOIN departamentos dep2 ON dep2.id=op.departamento_id
                $where ORDER BY oc.created_at DESC");
            $stmt->execute($params);
            $detalle = $stmt->fetchAll();

            // Totales
            $totalOC = count($detalle);
            $pendientes = 0; $aprobadas = 0; $completadas = 0;
            $totalMonto = 0; $totalItems = 0;
            foreach ($detalle as $r) {
                $totalMonto += (float)($r['monto_estimado'] ?? 0);
                $totalItems += (int)($r['items_count'] ?? 0);
                $est = strtolower($r['estado'] ?? '');
                if ($est === 'pendiente') $pendientes++;
                elseif ($est === 'aprobada') $aprobadas++;
                elseif ($est === 'completada') $completadas++;
            }

            // Formatear detalle con folios
            $detalleFormatted = [];
            foreach ($detalle as $r) {
                $detalleFormatted[] = [
                    'id' => $r['id'],
                    'folio' => 'OC-' . str_pad($r['id'], 6, '0', STR_PAD_LEFT),
                    'fecha' => $r['created_at'],
                    'solicitante' => $r['solicitante_nombre'],
                    'descripcion' => $r['descripcion'],
                    'placa' => $r['placa'],
                    'marca' => $r['vehiculo'],
                    'proveedor' => $r['proveedor_nombre'],
                    'monto' => (float)$r['monto_estimado'],
                    'estado' => $r['estado'],
                    'urgencia' => $r['urgencia'],
                    'mantenimiento_folio' => $r['mantenimiento_id'] ? 'OT-' . str_pad($r['mantenimiento_id'], 6, '0', STR_PAD_LEFT) : null,
                    'items_count' => (int)$r['items_count'],
                    'departamento' => $r['departamento'],
                ];
            }

            // Por vehículo
            $porVehiculo = [];
            foreach ($detalle as $r) {
                if (!$r['vehiculo_id']) continue;
                $key = $r['vehiculo_id'];
                if (!isset($porVehiculo[$key])) {
                    $porVehiculo[$key] = ['vehiculo_id'=>$r['vehiculo_id'], 'placa'=>$r['placa'], 'vehiculo'=>$r['vehiculo'], 'cantidad'=>0, 'monto_total'=>0];
                }
                $porVehiculo[$key]['cantidad']++;
                $porVehiculo[$key]['monto_total'] += (float)($r['monto_estimado'] ?? 0);
            }

            // Por estado
            $porEstado = [];
            foreach ($detalle as $r) {
                $est = $r['estado'] ?? 'Sin estado';
                if (!isset($porEstado[$est])) {
                    $porEstado[$est] = ['estado'=>$est, 'cantidad'=>0];
                }
                $porEstado[$est]['cantidad']++;
            }

            echo json_encode([
                'totales' => ['total_oc'=>$totalOC, 'pendientes'=>$pendientes, 'aprobadas'=>$aprobadas, 'completadas'=>$completadas, 'total_monto'=>$totalMonto, 'total_items'=>$totalItems],
                'detalle' => $detalleFormatted,
                'por_vehiculo' => array_values($porVehiculo),
                'por_estado' => array_values($porEstado),
            ]);
            break;

        default:
            echo json_encode(['error' => 'Reporte no especificado. Use: combustible, mantenimiento, vehiculos, top_costosos, talleres, overrides, operador_360, historial_asignaciones, ordenes_compra']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    $msg = (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : 'Error interno del servidor.';
    echo json_encode(['error' => $msg]);
}
