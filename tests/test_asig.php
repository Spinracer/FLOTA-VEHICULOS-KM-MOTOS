<?php
// FlotaControl — Diagnóstico rápido de tablas y escritura
require_once __DIR__ . '/../includes/db.php';
$db = getDB();
echo "DB OK\n";

$tables = ['asignaciones','vehiculos','operadores','odometer_logs','attachments',
           'components','vehicle_components','ordenes_compra','vehiculo_documentos',
           'proveedores','usuarios','sucursales'];
foreach ($tables as $t) {
    try {
        $db->query("SELECT 1 FROM `$t` LIMIT 1");
        echo "OK: $t\n";
    } catch (Throwable $e) {
        echo "MISSING: $t\n";
    }
}

$uploadDir = __DIR__ . '/../uploads';
echo "\nuploads/ writable: " . (is_writable($uploadDir) ? 'YES' : 'NO') . "\n";

// Test ordenes_compra page queries
try {
    $v = $db->query("SELECT id,placa,marca,modelo FROM vehiculos WHERE deleted_at IS NULL ORDER BY placa")->fetchAll();
    echo "vehiculos query OK: " . count($v) . " rows\n";
} catch(Throwable $e) { echo "vehiculos query FAIL: " . $e->getMessage() . "\n"; }
try {
    $p = $db->query("SELECT id,nombre FROM proveedores ORDER BY nombre")->fetchAll();
    echo "proveedores query OK: " . count($p) . " rows\n";
} catch(Throwable $e) { echo "proveedores query FAIL: " . $e->getMessage() . "\n"; }

echo "Done.\n";

// Validate deploy.sh syntax
$deployScript = __DIR__ . '/../deploy.sh';
if (file_exists($deployScript)) {
    $out = shell_exec("bash -n " . escapeshellarg($deployScript) . " 2>&1");
    echo "deploy.sh syntax: " . (empty($out) ? "OK" : "ERROR: $out") . "\n";
    echo "deploy.sh lines: " . count(file($deployScript)) . "\n";
} else {
    echo "deploy.sh: NOT FOUND\n";
}
