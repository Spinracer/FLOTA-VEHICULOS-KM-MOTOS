<?php
/**
 * Diagnóstico de errores del sistema
 * Ejecutar: php8.3 tests/diagnose.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== DIAGNÓSTICO FLOTACONTROL ===\n\n";

// 1. Test DB connection
echo "1. Conexión BD: ";
try {
    require_once __DIR__ . '/../includes/db.php';
    $db = getDB();
    echo "OK\n";
} catch (Throwable $e) {
    echo "FALLO: {$e->getMessage()}\n";
    exit(1);
}

// 2. Check tables exist
echo "\n2. Verificando tablas:\n";
$tables = ['vehiculos','asignaciones','mantenimientos','combustible','incidentes',
    'operadores','proveedores','sucursales','componentes','component_catalog',
    'vehicle_components','recordatorios','notificaciones','usuarios','audit_logs',
    'attachments','role_module_permissions','system_settings','preventive_intervals',
    'alertas','alerta_historial','rate_limits',
    'operador_capacitaciones','operador_infracciones',
    'component_movements','proveedor_evaluaciones','proveedor_contratos',
    'checklist_plantillas','checklist_items','checklist_respuestas',
    'mantenimiento_aprobaciones','incidente_seguimientos','vehiculo_etiquetas'];

foreach ($tables as $t) {
    try {
        $db->query("SELECT 1 FROM `$t` LIMIT 0");
        echo "  ✅ $t\n";
    } catch (Throwable $e) {
        echo "  ❌ $t — {$e->getMessage()}\n";
    }
}

// 3. Test reportes queries (the main failure)
echo "\n3. Reporte combustible:\n";
try {
    $stmt = $db->prepare("SELECT COUNT(*) as registros, COALESCE(SUM(c.litros),0) as total_litros, COALESCE(SUM(c.total),0) as total_gasto,
        COALESCE(AVG(c.litros),0) as avg_litros, COALESCE(AVG(c.total),0) as avg_gasto
        FROM combustible c WHERE 1=1");
    $stmt->execute([]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  OK: registros={$r['registros']}, litros={$r['total_litros']}, gasto={$r['total_gasto']}\n";
} catch (Throwable $e) {
    echo "  FALLO: {$e->getMessage()}\n";
}

echo "\n4. Reporte mantenimiento:\n";
try {
    $stmt = $db->prepare("SELECT COUNT(*) as registros, COALESCE(SUM(m.costo),0) as total_costo, COALESCE(AVG(m.costo),0) as avg_costo
        FROM mantenimientos m WHERE 1=1");
    $stmt->execute([]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  OK: registros={$r['registros']}, costo={$r['total_costo']}\n";
} catch (Throwable $e) {
    echo "  FALLO: {$e->getMessage()}\n";
}

// 5. Check combustible table columns  
echo "\n5. Columnas tabla combustible:\n";
try {
    $cols = $db->query("SHOW COLUMNS FROM combustible")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo "  {$c['Field']} ({$c['Type']})\n";
} catch (Throwable $e) {
    echo "  FALLO: {$e->getMessage()}\n";
}

// 6. Check mantenimientos table columns
echo "\n6. Columnas tabla mantenimientos:\n";
try {
    $cols = $db->query("SHOW COLUMNS FROM mantenimientos")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo "  {$c['Field']} ({$c['Type']})\n";
} catch (Throwable $e) {
    echo "  FALLO: {$e->getMessage()}\n";
}

// 7. Check asignaciones firma_token column
echo "\n7. Asignaciones firma_token:\n";
try {
    $cols = $db->query("SHOW COLUMNS FROM asignaciones LIKE 'firma_%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo "  {$c['Field']} ({$c['Type']})\n";
} catch (Throwable $e) {
    echo "  FALLO: {$e->getMessage()}\n";
}

// 8. Check por_vehiculo query (common failure)
echo "\n8. Reporte por_vehiculo combustible:\n";
try {
    $stmt = $db->prepare("SELECT v.placa, v.marca, SUM(c.litros) as litros, SUM(c.total) as gasto, COUNT(*) as cargas,
        MAX(c.km) - MIN(c.km) as km_recorridos
        FROM combustible c JOIN vehiculos v ON v.id=c.vehiculo_id
        WHERE 1=1 GROUP BY v.id, v.placa, v.marca ORDER BY gasto DESC");
    $stmt->execute([]);
    echo "  OK: " . count($stmt->fetchAll()) . " rows\n";
} catch (Throwable $e) {
    echo "  FALLO: {$e->getMessage()}\n";
}

// 9. Check por_mes query
echo "\n9. Reporte por_mes combustible:\n";
try {
    $stmt = $db->prepare("SELECT DATE_FORMAT(c.fecha, '%Y-%m') as mes, SUM(c.litros) as litros, SUM(c.total) as gasto, COUNT(*) as cargas
        FROM combustible c WHERE 1=1 GROUP BY mes ORDER BY mes DESC LIMIT 12");
    $stmt->execute([]);
    echo "  OK: " . count($stmt->fetchAll()) . " rows\n";
} catch (Throwable $e) {
    echo "  FALLO: {$e->getMessage()}\n";
}

// 10. Check if uploads dir is writable
echo "\n10. Directorio uploads: ";
$upDir = __DIR__ . '/../uploads';
if (!is_dir($upDir)) {
    echo "NO EXISTE — Creando... ";
    mkdir($upDir, 0755, true);
    echo "OK\n";
} else {
    echo is_writable($upDir) ? "OK (escribible)\n" : "⚠️ NO escribible\n";
}

// 11. Check if rate_limits table works
echo "\n11. Rate limits:\n";
try {
    $db->query("SELECT COUNT(*) FROM rate_limits")->fetchColumn();
    echo "  OK\n";
} catch (Throwable $e) {
    echo "  FALLO: {$e->getMessage()}\n";
}

// 12. Check totp columns in usuarios
echo "\n12. Columnas 2FA en usuarios:\n";
try {
    $cols = $db->query("SHOW COLUMNS FROM usuarios LIKE 'totp_%'")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($cols)) echo "  ⚠️ No existen columnas totp_*\n";
    foreach ($cols as $c) echo "  {$c['Field']} ({$c['Type']})\n";
} catch (Throwable $e) {
    echo "  FALLO: {$e->getMessage()}\n";
}

echo "\n=== FIN DIAGNÓSTICO ===\n";
