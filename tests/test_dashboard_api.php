<?php
// Quick test: load dashboard API logic and verify no SQL errors
error_reporting(E_ALL);
ini_set('display_errors','1');

require __DIR__.'/../includes/db.php';

$pdo = getDB();
echo "DB OK\n";

// Test the main queries from dashboard API
$periodo = 'anio';
$now = date('Y-m-d');
$start = date('Y-01-01');
$params = [$start, $now];

try {
    // KPI: total vehicles
    $st = $pdo->query("SELECT COUNT(*) c FROM vehiculos WHERE deleted_at IS NULL");
    echo "vehiculos count: ".$st->fetchColumn()."\n";

    // KPI: combustible with JOIN
    $st = $pdo->prepare("SELECT COALESCE(SUM(c.total),0) gasto, COALESCE(SUM(c.litros),0) litros FROM combustible c JOIN vehiculos v ON v.id=c.vehiculo_id WHERE c.deleted_at IS NULL AND c.fecha BETWEEN ? AND ?");
    $st->execute($params);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    echo "combustible gasto: {$r['gasto']}, litros: {$r['litros']}\n";

    // KPI: mantenimientos with JOIN
    $st = $pdo->prepare("SELECT COALESCE(SUM(m.costo),0) costo FROM mantenimientos m JOIN vehiculos v ON v.id=m.vehiculo_id WHERE m.deleted_at IS NULL AND m.fecha BETWEEN ? AND ?");
    $st->execute($params);
    echo "mantenimiento costo: ".$st->fetchColumn()."\n";

    // KPI: incidentes with JOIN
    $st = $pdo->prepare("SELECT COUNT(*) c FROM incidentes i JOIN vehiculos v ON v.id=i.vehiculo_id WHERE i.deleted_at IS NULL AND i.fecha BETWEEN ? AND ?");
    $st->execute($params);
    echo "incidentes count: ".$st->fetchColumn()."\n";

    // KPI: asignaciones
    $st = $pdo->prepare("SELECT COUNT(*) c FROM asignaciones a JOIN vehiculos v ON v.id=a.vehiculo_id WHERE a.start_at BETWEEN ? AND ?");
    $st->execute($params);
    echo "asignaciones count: ".$st->fetchColumn()."\n";

    echo "\n✅ ALL DASHBOARD QUERIES OK\n";
} catch (PDOException $e) {
    echo "❌ SQL ERROR: ".$e->getMessage()."\n";
}
