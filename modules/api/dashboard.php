<?php
/**
 * API Dashboard Ejecutivo — Objetivo 7
 * GET /api/dashboard.php
 *
 * Params: sucursal_id, vehiculo_id, periodo (mes|trimestre|semestre|anio), from, to
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cache.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

try {

$db = getDB();
$sucursal = intval($_GET['sucursal_id'] ?? 0);
$vehiculo = intval($_GET['vehiculo_id'] ?? 0);
$periodo  = $_GET['periodo'] ?? 'anio';

$cacheKey = "dashboard:{$sucursal}:{$vehiculo}:{$periodo}:" . ($_GET['from'] ?? '') . ':' . ($_GET['to'] ?? '');

$today = date('Y-m-d');
switch ($periodo) {
    case 'mes':       $from = date('Y-m-01'); break;
    case 'trimestre': $from = date('Y-m-d', strtotime('-3 months')); break;
    case 'semestre':  $from = date('Y-m-d', strtotime('-6 months')); break;
    default:          $from = date('Y-01-01'); break;
}
$to = $today;
if (!empty($_GET['from'])) $from = $_GET['from'];
if (!empty($_GET['to']))   $to   = $_GET['to'];

$response = cache_remember($cacheKey, function() use ($db, $sucursal, $vehiculo, $from, $to) {

// ════════════════════════════════════════════════
// 1. KPIs
// ════════════════════════════════════════════════

// Total vehículos
$vSql = "SELECT COUNT(*) FROM vehiculos WHERE deleted_at IS NULL";
$vP = [];
if ($sucursal) { $vSql .= " AND sucursal_id = ?"; $vP[] = $sucursal; }
if ($vehiculo) { $vSql .= " AND id = ?"; $vP[] = $vehiculo; }
$total_veh = $db->prepare($vSql); $total_veh->execute($vP);
$total_veh = $total_veh->fetchColumn();

// Operadores activos
$opSql = "SELECT COUNT(*) FROM operadores WHERE estado='Activo'";
$opP = [];
if ($sucursal) { $opSql .= " AND sucursal_id = ?"; $opP[] = $sucursal; }
$total_op = $db->prepare($opSql); $total_op->execute($opP);
$total_op = $total_op->fetchColumn();

// Incidentes abiertos
$incSql = "SELECT COUNT(*) FROM incidentes i JOIN vehiculos v ON v.id = i.vehiculo_id WHERE i.estado='Abierto' AND i.fecha BETWEEN ? AND ?";
$incP = [$from, $to];
if ($sucursal) { $incSql .= " AND v.sucursal_id = ?"; $incP[] = $sucursal; }
if ($vehiculo) { $incSql .= " AND i.vehiculo_id = ?"; $incP[] = $vehiculo; }
$inc_abiertos = $db->prepare($incSql); $inc_abiertos->execute($incP);
$inc_abiertos = $inc_abiertos->fetchColumn();

// Gasto combustible & litros
$cSql = "SELECT COALESCE(SUM(c.litros),0) as litros, COALESCE(SUM(c.total),0) as gasto
    FROM combustible c JOIN vehiculos v ON v.id = c.vehiculo_id
    WHERE c.fecha BETWEEN ? AND ?";
$cP = [$from, $to];
if ($sucursal) { $cSql .= " AND v.sucursal_id = ?"; $cP[] = $sucursal; }
if ($vehiculo) { $cSql .= " AND c.vehiculo_id = ?"; $cP[] = $vehiculo; }
$cStmt = $db->prepare($cSql); $cStmt->execute($cP);
$comb = $cStmt->fetch();

// Gasto mantenimiento
$mSql = "SELECT COALESCE(SUM(m.costo),0) as gasto, COUNT(*) as total
    FROM mantenimientos m JOIN vehiculos v ON v.id = m.vehiculo_id
    WHERE m.deleted_at IS NULL AND m.fecha BETWEEN ? AND ?";
$mP = [$from, $to];
if ($sucursal) { $mSql .= " AND v.sucursal_id = ?"; $mP[] = $sucursal; }
if ($vehiculo) { $mSql .= " AND m.vehiculo_id = ?"; $mP[] = $vehiculo; }
$mStmt = $db->prepare($mSql); $mStmt->execute($mP);
$mant = $mStmt->fetch();

// KM recorridos (MAX-MIN de km en combustible + mantenimientos por vehículo)
$kmSql = "SELECT COALESCE(SUM(sub.km_diff),0) as km FROM (
    SELECT v.id, (
        MAX(GREATEST(COALESCE(c2.km,0), COALESCE(m2.km,0)))
        - MIN(LEAST(COALESCE(NULLIF(c2.km,0),999999999), COALESCE(NULLIF(m2.km,0),999999999)))
    ) as km_diff
    FROM vehiculos v
    LEFT JOIN combustible c2 ON c2.vehiculo_id = v.id AND c2.fecha BETWEEN ? AND ?
    LEFT JOIN mantenimientos m2 ON m2.vehiculo_id = v.id AND m2.deleted_at IS NULL AND m2.fecha BETWEEN ? AND ?
    WHERE v.deleted_at IS NULL";
$kmP = [$from, $to, $from, $to];
if ($sucursal) { $kmSql .= " AND v.sucursal_id = ?"; $kmP[] = $sucursal; }
if ($vehiculo) { $kmSql .= " AND v.id = ?"; $kmP[] = $vehiculo; }
$kmSql .= " GROUP BY v.id HAVING km_diff > 0 AND km_diff < 999999999) sub";
$km_recorridos = $db->prepare($kmSql); $km_recorridos->execute($kmP);
$km_recorridos = $km_recorridos->fetchColumn();

// Alertas activas
$alertas_activas = 0;
try { $alertas_activas = $db->query("SELECT COUNT(*) FROM alertas WHERE estado='Activa'")->fetchColumn(); } catch(Throwable $e) {}

// OTs pendientes
$otSql = "SELECT COUNT(*) FROM mantenimientos m JOIN vehiculos v ON v.id = m.vehiculo_id
    WHERE m.deleted_at IS NULL AND m.estado IN ('Pendiente','En proceso')";
$otP = [];
if ($sucursal) { $otSql .= " AND v.sucursal_id = ?"; $otP[] = $sucursal; }
if ($vehiculo) { $otSql .= " AND m.vehiculo_id = ?"; $otP[] = $vehiculo; }
$ots_pendientes = $db->prepare($otSql); $ots_pendientes->execute($otP);
$ots_pendientes = $ots_pendientes->fetchColumn();

// Eficiencia promedio (km/L)
$efSql = "SELECT AVG(sub.kml) FROM (
    SELECT c3.vehiculo_id, (MAX(c3.km) - MIN(c3.km)) / NULLIF(SUM(c3.litros),0) as kml
    FROM combustible c3 JOIN vehiculos v3 ON v3.id = c3.vehiculo_id
    WHERE c3.km > 0 AND c3.fecha BETWEEN ? AND ?";
$efP = [$from, $to];
if ($sucursal) { $efSql .= " AND v3.sucursal_id = ?"; $efP[] = $sucursal; }
if ($vehiculo) { $efSql .= " AND c3.vehiculo_id = ?"; $efP[] = $vehiculo; }
$efSql .= " GROUP BY c3.vehiculo_id HAVING COUNT(*) >= 2 AND kml > 0 AND kml < 100) sub";
try {
    $efStmt = $db->prepare($efSql); $efStmt->execute($efP);
    $eficiencia = round($efStmt->fetchColumn() ?: 0, 1);
} catch(Throwable $e) { $eficiencia = 0; }

// ── Tendencias vs periodo anterior ──
$diff = strtotime($to) - strtotime($from);
$prev_to   = date('Y-m-d', strtotime($from) - 1);
$prev_from = date('Y-m-d', strtotime($from) - $diff);

$cpSql = "SELECT COALESCE(SUM(c.total),0) FROM combustible c JOIN vehiculos v ON v.id = c.vehiculo_id WHERE c.fecha BETWEEN ? AND ?";
$cpP = [$prev_from, $prev_to];
if ($sucursal) { $cpSql .= " AND v.sucursal_id = ?"; $cpP[] = $sucursal; }
if ($vehiculo) { $cpSql .= " AND c.vehiculo_id = ?"; $cpP[] = $vehiculo; }
$prev_gasto_comb = $db->prepare($cpSql); $prev_gasto_comb->execute($cpP);
$prev_gasto_comb = $prev_gasto_comb->fetchColumn();

$mpSql = "SELECT COALESCE(SUM(m.costo),0) FROM mantenimientos m JOIN vehiculos v ON v.id = m.vehiculo_id WHERE m.deleted_at IS NULL AND m.fecha BETWEEN ? AND ?";
$mpP = [$prev_from, $prev_to];
if ($sucursal) { $mpSql .= " AND v.sucursal_id = ?"; $mpP[] = $sucursal; }
if ($vehiculo) { $mpSql .= " AND m.vehiculo_id = ?"; $mpP[] = $vehiculo; }
$prev_gasto_mant = $db->prepare($mpSql); $prev_gasto_mant->execute($mpP);
$prev_gasto_mant = $prev_gasto_mant->fetchColumn();

$trend_comb = $prev_gasto_comb > 0 ? round(($comb['gasto'] - $prev_gasto_comb) / $prev_gasto_comb * 100, 1) : 0;
$trend_mant = $prev_gasto_mant > 0 ? round(($mant['gasto'] - $prev_gasto_mant) / $prev_gasto_mant * 100, 1) : 0;

$kpis = [
    'vehiculos'       => (int)$total_veh,
    'operadores'      => (int)$total_op,
    'inc_abiertos'    => (int)$inc_abiertos,
    'litros'          => round($comb['litros'], 1),
    'gasto_comb'      => round($comb['gasto'], 2),
    'gasto_mant'      => round($mant['gasto'], 2),
    'total_mant'      => (int)$mant['total'],
    'km_recorridos'   => (int)$km_recorridos,
    'alertas_activas' => (int)$alertas_activas,
    'ots_pendientes'  => (int)$ots_pendientes,
    'eficiencia_kml'  => $eficiencia,
    'trend_comb'      => $trend_comb,
    'trend_mant'      => $trend_mant,
];

// ════════════════════════════════════════════════
// 2. Gráficas
// ════════════════════════════════════════════════

// 2a. Gasto mensual (combustible + mantenimiento)
$gasto_mensual = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $gasto_mensual[] = ['mes' => $m, 'combustible' => 0, 'mantenimiento' => 0];
}

$gmcSql = "SELECT DATE_FORMAT(c.fecha,'%Y-%m') as mes, SUM(c.total) as total
    FROM combustible c JOIN vehiculos v ON v.id = c.vehiculo_id
    WHERE c.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
$gmcP = [];
if ($sucursal) { $gmcSql .= " AND v.sucursal_id = ?"; $gmcP[] = $sucursal; }
if ($vehiculo) { $gmcSql .= " AND c.vehiculo_id = ?"; $gmcP[] = $vehiculo; }
$gmcSql .= " GROUP BY mes ORDER BY mes";
$gmcStmt = $db->prepare($gmcSql); $gmcStmt->execute($gmcP);
$cByMonth = [];
foreach ($gmcStmt->fetchAll() as $r) $cByMonth[$r['mes']] = round($r['total'], 2);

$gmmSql = "SELECT DATE_FORMAT(m.fecha,'%Y-%m') as mes, SUM(m.costo) as total
    FROM mantenimientos m JOIN vehiculos v ON v.id = m.vehiculo_id
    WHERE m.deleted_at IS NULL AND m.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
$gmmP = [];
if ($sucursal) { $gmmSql .= " AND v.sucursal_id = ?"; $gmmP[] = $sucursal; }
if ($vehiculo) { $gmmSql .= " AND m.vehiculo_id = ?"; $gmmP[] = $vehiculo; }
$gmmSql .= " GROUP BY mes ORDER BY mes";
$gmmStmt = $db->prepare($gmmSql); $gmmStmt->execute($gmmP);
$mByMonth = [];
foreach ($gmmStmt->fetchAll() as $r) $mByMonth[$r['mes']] = round($r['total'], 2);

foreach ($gasto_mensual as &$gm) {
    $gm['combustible']   = $cByMonth[$gm['mes']] ?? 0;
    $gm['mantenimiento'] = $mByMonth[$gm['mes']] ?? 0;
}
unset($gm);

// 2b. Incidentes por mes
$imSql = "SELECT DATE_FORMAT(i.fecha,'%Y-%m') as mes, COUNT(*) as total
    FROM incidentes i JOIN vehiculos v ON v.id = i.vehiculo_id
    WHERE i.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
$imP = [];
if ($sucursal) { $imSql .= " AND v.sucursal_id = ?"; $imP[] = $sucursal; }
if ($vehiculo) { $imSql .= " AND i.vehiculo_id = ?"; $imP[] = $vehiculo; }
$imSql .= " GROUP BY mes ORDER BY mes";
$imStmt = $db->prepare($imSql); $imStmt->execute($imP);
$imData = [];
foreach ($imStmt->fetchAll() as $r) $imData[$r['mes']] = (int)$r['total'];
$inc_mensual = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $inc_mensual[] = ['mes' => $m, 'total' => $imData[$m] ?? 0];
}

// 2c. Top 10 vehículos por costo total
$topSql = "SELECT v.id, v.placa, v.marca,
    COALESCE((SELECT SUM(c.total) FROM combustible c WHERE c.vehiculo_id = v.id AND c.fecha BETWEEN ? AND ?),0) as gasto_comb,
    COALESCE((SELECT SUM(m.costo) FROM mantenimientos m WHERE m.vehiculo_id = v.id AND m.deleted_at IS NULL AND m.fecha BETWEEN ? AND ?),0) as gasto_mant
    FROM vehiculos v WHERE v.deleted_at IS NULL";
$topP = [$from, $to, $from, $to];
if ($sucursal) { $topSql .= " AND v.sucursal_id = ?"; $topP[] = $sucursal; }
if ($vehiculo) { $topSql .= " AND v.id = ?"; $topP[] = $vehiculo; }
$topSql .= " HAVING (gasto_comb + gasto_mant) > 0 ORDER BY (gasto_comb + gasto_mant) DESC LIMIT 10";
$topStmt = $db->prepare($topSql); $topStmt->execute($topP);
$top_vehiculos = $topStmt->fetchAll();

// 2d. Distribución mantenimiento (Correctivo vs Preventivo)
$distSql = "SELECT m.tipo, COUNT(*) as total, SUM(m.costo) as gasto
    FROM mantenimientos m JOIN vehiculos v ON v.id = m.vehiculo_id
    WHERE m.deleted_at IS NULL AND m.fecha BETWEEN ? AND ?";
$distP = [$from, $to];
if ($sucursal) { $distSql .= " AND v.sucursal_id = ?"; $distP[] = $sucursal; }
if ($vehiculo) { $distSql .= " AND m.vehiculo_id = ?"; $distP[] = $vehiculo; }
$distSql .= " GROUP BY m.tipo";
$distStmt = $db->prepare($distSql); $distStmt->execute($distP);
$dist_mant = $distStmt->fetchAll();

// 2e. Top operadores eficiencia (km/L)
$efTopSql = "SELECT o.nombre,
    (MAX(c.km) - MIN(c.km)) / NULLIF(SUM(c.litros),0) as kml,
    SUM(c.litros) as litros
    FROM combustible c
    JOIN vehiculos v ON v.id = c.vehiculo_id
    JOIN asignaciones a ON a.vehiculo_id = c.vehiculo_id AND c.fecha BETWEEN a.start_at AND COALESCE(a.end_at, CURDATE())
    JOIN operadores o ON o.id = a.operador_id
    WHERE c.km > 0 AND c.fecha BETWEEN ? AND ?";
$efTopP = [$from, $to];
if ($sucursal) { $efTopSql .= " AND v.sucursal_id = ?"; $efTopP[] = $sucursal; }
$efTopSql .= " GROUP BY o.id, o.nombre HAVING COUNT(*) >= 2 AND kml > 0 AND kml < 100 ORDER BY kml DESC LIMIT 10";
try {
    $efTopStmt = $db->prepare($efTopSql); $efTopStmt->execute($efTopP);
    $top_eficiencia = $efTopStmt->fetchAll();
} catch(Throwable $e) { $top_eficiencia = []; }

// ════════════════════════════════════════════════
// 3. Listas
// ════════════════════════════════════════════════

// Recordatorios próximos
$recSql = "SELECT r.*, v.placa, v.marca, DATEDIFF(r.fecha_limite, CURDATE()) as dias
    FROM recordatorios r JOIN vehiculos v ON v.id = r.vehiculo_id
    WHERE r.estado='Pendiente' AND r.fecha_limite <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$recP = [];
if ($sucursal) { $recSql .= " AND v.sucursal_id = ?"; $recP[] = $sucursal; }
if ($vehiculo) { $recSql .= " AND r.vehiculo_id = ?"; $recP[] = $vehiculo; }
$recSql .= " ORDER BY r.fecha_limite LIMIT 8";
$recStmt = $db->prepare($recSql); $recStmt->execute($recP);
$recordatorios = $recStmt->fetchAll();

// OTs activas
$otListSql = "SELECT m.id, m.tipo, m.estado, m.fecha, m.costo, v.placa, v.marca, p.nombre AS proveedor
    FROM mantenimientos m JOIN vehiculos v ON v.id = m.vehiculo_id LEFT JOIN proveedores p ON p.id = m.proveedor_id
    WHERE m.estado IN ('Pendiente','En proceso') AND m.deleted_at IS NULL";
$otListP = [];
if ($sucursal) { $otListSql .= " AND v.sucursal_id = ?"; $otListP[] = $sucursal; }
if ($vehiculo) { $otListSql .= " AND m.vehiculo_id = ?"; $otListP[] = $vehiculo; }
$otListSql .= " ORDER BY m.fecha DESC LIMIT 8";
$otListStmt = $db->prepare($otListSql); $otListStmt->execute($otListP);
$ots_list = $otListStmt->fetchAll();

// Alertas recientes
$alertas_list = [];
try {
    $alertas_list = $db->query("SELECT a.*, v.placa FROM alertas a LEFT JOIN vehiculos v ON v.id = a.vehiculo_id WHERE a.estado='Activa' ORDER BY FIELD(a.prioridad,'Urgente','Alta','Normal','Baja'), a.created_at DESC LIMIT 8")->fetchAll();
} catch(Throwable $e) {}

// ════════════════════════════════════════════════
// 4. Selects para filtros
// ════════════════════════════════════════════════
$sucursales = $db->query("SELECT id, nombre FROM sucursales ORDER BY nombre")->fetchAll();

$vlSql = "SELECT id, placa, marca FROM vehiculos WHERE deleted_at IS NULL";
$vlP = [];
if ($sucursal) { $vlSql .= " AND sucursal_id = ?"; $vlP[] = $sucursal; }
$vlSql .= " ORDER BY placa";
$vlStmt = $db->prepare($vlSql); $vlStmt->execute($vlP);
$vehiculos_list = $vlStmt->fetchAll();

return [
    'ok'       => true,
    'periodo'  => ['from' => $from, 'to' => $to],
    'kpis'     => $kpis,
    'charts'   => [
        'gasto_mensual'    => $gasto_mensual,
        'inc_mensual'      => $inc_mensual,
        'top_vehiculos'    => $top_vehiculos,
        'dist_mant'        => $dist_mant,
        'top_eficiencia'   => $top_eficiencia,
    ],
    'lists'    => [
        'recordatorios'    => $recordatorios,
        'ots'              => $ots_list,
        'alertas'          => $alertas_list,
    ],
    'filters'  => [
        'sucursales'       => $sucursales,
        'vehiculos'        => $vehiculos_list,
    ],
];
}, 'dashboard');

echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
