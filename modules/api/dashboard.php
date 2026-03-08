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

$db = getDB();
$sucursal = intval($_GET['sucursal_id'] ?? 0);
$vehiculo = intval($_GET['vehiculo_id'] ?? 0);
$periodo  = $_GET['periodo'] ?? 'anio';

// Cache key based on filters
$cacheKey = "dashboard:{$sucursal}:{$vehiculo}:{$periodo}:" . ($_GET['from'] ?? '') . ':' . ($_GET['to'] ?? '');

// Período → rango de fechas
$today = date('Y-m-d');
switch ($periodo) {
    case 'mes':       $from = date('Y-m-01'); break;
    case 'trimestre': $from = date('Y-m-d', strtotime('-3 months')); break;
    case 'semestre':  $from = date('Y-m-d', strtotime('-6 months')); break;
    default:          $from = date('Y-01-01'); break; // anio
}
$to = $today;
if (!empty($_GET['from'])) $from = $_GET['from'];
if (!empty($_GET['to']))   $to   = $_GET['to'];

$response = cache_remember($cacheKey, function() use ($db, $sucursal, $vehiculo, $from, $to) {

// ── Helpers para filtros WHERE ──
$buildWhere = function($table_alias, $sucursal, $vehiculo, $from, $to, $date_col = 'fecha') {
    $w = []; $p = [];
    if ($sucursal) {
        $w[] = "$table_alias.sucursal_id = ?"; $p[] = $sucursal;
    }
    if ($vehiculo) {
        $w[] = "$table_alias.vehiculo_id = ?"; $p[] = $vehiculo;
    }
    if ($from) {
        $w[] = "$table_alias.$date_col >= ?"; $p[] = $from;
    }
    if ($to) {
        $w[] = "$table_alias.$date_col <= ?"; $p[] = $to;
    }
    return [$w ? 'AND ' . implode(' AND ', $w) : '', $p];
};

// ════════════════════════════════════════════════
// 1. KPIs
// ════════════════════════════════════════════════

// Total vehículos (filtered by sucursal)
$vSql = "SELECT COUNT(*) FROM vehiculos WHERE deleted_at IS NULL";
$vParams = [];
if ($sucursal) { $vSql .= " AND sucursal_id = ?"; $vParams[] = $sucursal; }
$total_veh = $db->prepare($vSql); $total_veh->execute($vParams);
$total_veh = $total_veh->fetchColumn();

// Operadores activos
$opSql = "SELECT COUNT(*) FROM operadores WHERE estado='Activo'";
$opParams = [];
if ($sucursal) { $opSql .= " AND sucursal_id = ?"; $opParams[] = $sucursal; }
$total_op = $db->prepare($opSql); $total_op->execute($opParams);
$total_op = $total_op->fetchColumn();

// Incidentes abiertos (periodo + filtros)
[$wInc, $pInc] = $buildWhere('i', $sucursal, $vehiculo, $from, $to);
$inc_q = $db->prepare("SELECT COUNT(*) FROM incidentes i WHERE i.estado='Abierto' $wInc");
$inc_q->execute($pInc);
$inc_abiertos = $inc_q->fetchColumn();

// Gasto combustible & litros (periodo)
[$wC, $pC] = $buildWhere('c', $sucursal, $vehiculo, $from, $to);
$cStmt = $db->prepare("SELECT COALESCE(SUM(c.litros),0) as litros, COALESCE(SUM(c.total),0) as gasto FROM combustible c $wC");
// remove leading AND
$cSql = "SELECT COALESCE(SUM(c.litros),0) as litros, COALESCE(SUM(c.total),0) as gasto FROM combustible c WHERE 1=1 $wC";
$cStmt = $db->prepare($cSql); $cStmt->execute($pC);
$comb = $cStmt->fetch();

// Gasto mantenimiento (periodo)
[$wM, $pM] = $buildWhere('m', $sucursal, $vehiculo, $from, $to);
$mSql = "SELECT COALESCE(SUM(m.costo),0) as gasto, COUNT(*) as total FROM mantenimientos m WHERE m.deleted_at IS NULL $wM";
$mStmt = $db->prepare($mSql); $mStmt->execute($pM);
$mant = $mStmt->fetch();

// KM recorridos (diferencia max-min de odómetro en periodo)
$kmSql = "SELECT COALESCE(SUM(sub.km_diff),0) as km FROM (
    SELECT v.id, (MAX(GREATEST(COALESCE(c2.odometro,0), COALESCE(m2.km_actual,0))) - MIN(LEAST(COALESCE(NULLIF(c2.odometro,0),999999999), COALESCE(NULLIF(m2.km_actual,0),999999999)))) as km_diff
    FROM vehiculos v
    LEFT JOIN combustible c2 ON c2.vehiculo_id = v.id AND c2.fecha BETWEEN ? AND ?
    LEFT JOIN (SELECT vehiculo_id, km_actual FROM mantenimientos WHERE deleted_at IS NULL AND fecha BETWEEN ? AND ?) m2 ON m2.vehiculo_id = v.id
    WHERE v.deleted_at IS NULL " . ($sucursal ? "AND v.sucursal_id = ? " : "") . ($vehiculo ? "AND v.id = ? " : "") . "
    GROUP BY v.id HAVING km_diff > 0 AND km_diff < 999999999
) sub";
$kmParams = [$from, $to, $from, $to];
if ($sucursal) $kmParams[] = $sucursal;
if ($vehiculo) $kmParams[] = $vehiculo;
$kmStmt = $db->prepare($kmSql); $kmStmt->execute($kmParams);
$km_recorridos = $kmStmt->fetchColumn();

// Alertas activas
$alertas_activas = 0;
try { $alertas_activas = $db->query("SELECT COUNT(*) FROM alertas WHERE estado='Activa'")->fetchColumn(); } catch(Throwable $e) {}

// OTs pendientes
$otSql = "SELECT COUNT(*) FROM mantenimientos m WHERE m.deleted_at IS NULL AND m.estado IN ('Pendiente','En proceso')";
$otParams = [];
if ($sucursal) { $otSql .= " AND m.sucursal_id = ?"; $otParams[] = $sucursal; }
if ($vehiculo) { $otSql .= " AND m.vehiculo_id = ?"; $otParams[] = $vehiculo; }
$otStmt = $db->prepare($otSql); $otStmt->execute($otParams);
$ots_pendientes = $otStmt->fetchColumn();

// Eficiencia promedio (km/L) — solo vehículos con ≥2 cargas
$efSql = "SELECT AVG(sub.kml) as avg_kml FROM (
    SELECT c3.vehiculo_id, SUM(c3.km_recorridos)/NULLIF(SUM(c3.litros),0) as kml
    FROM combustible c3
    WHERE c3.km_recorridos > 0 AND c3.fecha BETWEEN ? AND ?
    " . ($sucursal ? "AND c3.sucursal_id = ? " : "") . ($vehiculo ? "AND c3.vehiculo_id = ? " : "") . "
    GROUP BY c3.vehiculo_id HAVING COUNT(*) >= 2
) sub";
$efParams = [$from, $to];
if ($sucursal) $efParams[] = $sucursal;
if ($vehiculo) $efParams[] = $vehiculo;
try {
    $efStmt = $db->prepare($efSql); $efStmt->execute($efParams);
    $eficiencia = round($efStmt->fetchColumn() ?: 0, 1);
} catch(Throwable $e) { $eficiencia = 0; }

// ── Tendencias vs periodo anterior ──
$diff = strtotime($to) - strtotime($from);
$prev_to   = date('Y-m-d', strtotime($from) - 1);
$prev_from = date('Y-m-d', strtotime($from) - $diff);

[$wCp, $pCp] = $buildWhere('c', $sucursal, $vehiculo, $prev_from, $prev_to);
$cpSql = "SELECT COALESCE(SUM(c.total),0) as gasto FROM combustible c WHERE 1=1 $wCp";
$cpStmt = $db->prepare($cpSql); $cpStmt->execute($pCp);
$prev_gasto_comb = $cpStmt->fetchColumn();

[$wMp, $pMp] = $buildWhere('m', $sucursal, $vehiculo, $prev_from, $prev_to);
$mpSql = "SELECT COALESCE(SUM(m.costo),0) as gasto FROM mantenimientos m WHERE m.deleted_at IS NULL $wMp";
$mpStmt = $db->prepare($mpSql); $mpStmt->execute($pMp);
$prev_gasto_mant = $mpStmt->fetchColumn();

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

// 2a. Gasto mensual (combustible + mantenimiento) — líneas
$gasto_mensual = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $gasto_mensual[] = ['mes' => $m, 'combustible' => 0, 'mantenimiento' => 0];
}

$gmcSql = "SELECT DATE_FORMAT(c.fecha,'%Y-%m') as mes, SUM(c.total) as total
    FROM combustible c WHERE c.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    " . ($sucursal ? "AND c.sucursal_id = ? " : "") . ($vehiculo ? "AND c.vehiculo_id = ? " : "") . "
    GROUP BY mes ORDER BY mes";
$gmcP = [];
if ($sucursal) $gmcP[] = $sucursal;
if ($vehiculo) $gmcP[] = $vehiculo;
$gmcStmt = $db->prepare($gmcSql); $gmcStmt->execute($gmcP);
$cByMonth = [];
foreach ($gmcStmt->fetchAll() as $r) $cByMonth[$r['mes']] = round($r['total'], 2);

$gmmSql = "SELECT DATE_FORMAT(m.fecha,'%Y-%m') as mes, SUM(m.costo) as total
    FROM mantenimientos m WHERE m.deleted_at IS NULL AND m.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    " . ($sucursal ? "AND m.sucursal_id = ? " : "") . ($vehiculo ? "AND m.vehiculo_id = ? " : "") . "
    GROUP BY mes ORDER BY mes";
$gmmP = [];
if ($sucursal) $gmmP[] = $sucursal;
if ($vehiculo) $gmmP[] = $vehiculo;
$gmmStmt = $db->prepare($gmmSql); $gmmStmt->execute($gmmP);
$mByMonth = [];
foreach ($gmmStmt->fetchAll() as $r) $mByMonth[$r['mes']] = round($r['total'], 2);

foreach ($gasto_mensual as &$gm) {
    $gm['combustible']   = $cByMonth[$gm['mes']] ?? 0;
    $gm['mantenimiento'] = $mByMonth[$gm['mes']] ?? 0;
}
unset($gm);

// 2b. Incidentes por mes (línea)
$inc_mensual = [];
$imSql = "SELECT DATE_FORMAT(i.fecha,'%Y-%m') as mes, COUNT(*) as total
    FROM incidentes i WHERE i.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    " . ($sucursal ? "AND i.sucursal_id = ? " : "") . ($vehiculo ? "AND i.vehiculo_id = ? " : "") . "
    GROUP BY mes ORDER BY mes";
$imP = [];
if ($sucursal) $imP[] = $sucursal;
if ($vehiculo) $imP[] = $vehiculo;
$imStmt = $db->prepare($imSql); $imStmt->execute($imP);
$imData = [];
foreach ($imStmt->fetchAll() as $r) $imData[$r['mes']] = (int)$r['total'];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $inc_mensual[] = ['mes' => $m, 'total' => $imData[$m] ?? 0];
}

// 2c. Top 10 vehículos por costo total (mantenimiento + combustible)
$topSql = "SELECT v.id, v.placa, v.marca,
    COALESCE((SELECT SUM(c.total) FROM combustible c WHERE c.vehiculo_id = v.id AND c.fecha BETWEEN ? AND ?),0) as gasto_comb,
    COALESCE((SELECT SUM(m.costo) FROM mantenimientos m WHERE m.vehiculo_id = v.id AND m.deleted_at IS NULL AND m.fecha BETWEEN ? AND ?),0) as gasto_mant
    FROM vehiculos v WHERE v.deleted_at IS NULL
    " . ($sucursal ? "AND v.sucursal_id = ? " : "") . ($vehiculo ? "AND v.id = ? " : "") . "
    HAVING (gasto_comb + gasto_mant) > 0
    ORDER BY (gasto_comb + gasto_mant) DESC LIMIT 10";
$topP = [$from, $to, $from, $to];
if ($sucursal) $topP[] = $sucursal;
if ($vehiculo) $topP[] = $vehiculo;
$topStmt = $db->prepare($topSql); $topStmt->execute($topP);
$top_vehiculos = $topStmt->fetchAll();

// 2d. Distribución mantenimiento (Correctivo vs Preventivo - doughnut)
$distSql = "SELECT m.tipo, COUNT(*) as total, SUM(m.costo) as gasto
    FROM mantenimientos m WHERE m.deleted_at IS NULL AND m.fecha BETWEEN ? AND ?
    " . ($sucursal ? "AND m.sucursal_id = ? " : "") . ($vehiculo ? "AND m.vehiculo_id = ? " : "") . "
    GROUP BY m.tipo";
$distP = [$from, $to];
if ($sucursal) $distP[] = $sucursal;
if ($vehiculo) $distP[] = $vehiculo;
$distStmt = $db->prepare($distSql); $distStmt->execute($distP);
$dist_mant = $distStmt->fetchAll();

// 2e. Top operadores eficiencia (km/L)
$efTopSql = "SELECT o.nombre, SUM(c.km_recorridos)/NULLIF(SUM(c.litros),0) as kml, SUM(c.litros) as litros
    FROM combustible c
    JOIN asignaciones a ON a.vehiculo_id = c.vehiculo_id AND c.fecha BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
    JOIN operadores o ON o.id = a.operador_id
    WHERE c.km_recorridos > 0 AND c.fecha BETWEEN ? AND ?
    " . ($sucursal ? "AND c.sucursal_id = ? " : "") . "
    GROUP BY o.id, o.nombre HAVING COUNT(*) >= 2
    ORDER BY kml DESC LIMIT 10";
$efTopP = [$from, $to];
if ($sucursal) $efTopP[] = $sucursal;
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
    WHERE r.estado='Pendiente' AND r.fecha_limite <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    " . ($sucursal ? "AND v.sucursal_id = ? " : "") . ($vehiculo ? "AND r.vehiculo_id = ? " : "") . "
    ORDER BY r.fecha_limite LIMIT 8";
$recP = [];
if ($sucursal) $recP[] = $sucursal;
if ($vehiculo) $recP[] = $vehiculo;
$recStmt = $db->prepare($recSql); $recStmt->execute($recP);
$recordatorios = $recStmt->fetchAll();

// OTs activas
$otListSql = "SELECT m.id, m.tipo, m.estado, m.fecha, m.costo, v.placa, v.marca, p.nombre AS proveedor
    FROM mantenimientos m JOIN vehiculos v ON v.id = m.vehiculo_id LEFT JOIN proveedores p ON p.id = m.proveedor_id
    WHERE m.estado IN ('Pendiente','En proceso') AND m.deleted_at IS NULL
    " . ($sucursal ? "AND m.sucursal_id = ? " : "") . ($vehiculo ? "AND m.vehiculo_id = ? " : "") . "
    ORDER BY m.fecha DESC LIMIT 8";
$otListP = [];
if ($sucursal) $otListP[] = $sucursal;
if ($vehiculo) $otListP[] = $vehiculo;
$otListStmt = $db->prepare($otListSql); $otListStmt->execute($otListP);
$ots_list = $otListStmt->fetchAll();

// Alertas recientes
$alListSql = "SELECT a.*, v.placa FROM alertas a LEFT JOIN vehiculos v ON v.id = a.vehiculo_id WHERE a.estado='Activa' ORDER BY FIELD(a.prioridad,'Urgente','Alta','Normal','Baja'), a.created_at DESC LIMIT 8";
$alertas_list = [];
try { $alertas_list = $db->query($alListSql)->fetchAll(); } catch(Throwable $e) {}

// ════════════════════════════════════════════════
// 4. Selects para filtros
// ════════════════════════════════════════════════
$sucursales = $db->query("SELECT id, nombre FROM sucursales WHERE deleted_at IS NULL ORDER BY nombre")->fetchAll();
$vehiculos_list = [];
$vlSql = "SELECT id, placa, marca FROM vehiculos WHERE deleted_at IS NULL " . ($sucursal ? "AND sucursal_id = ? " : "") . "ORDER BY placa";
$vlP = []; if ($sucursal) $vlP[] = $sucursal;
$vlStmt = $db->prepare($vlSql); $vlStmt->execute($vlP);
$vehiculos_list = $vlStmt->fetchAll();

// ════════════════════════════════════════════════
// Respuesta
// ════════════════════════════════════════════════
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
