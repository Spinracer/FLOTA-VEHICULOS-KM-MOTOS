<?php
// FlotaControl — Diagnóstico rápido de tablas y escritura
require_once __DIR__ . '/../includes/db.php';
$db = getDB();
echo "DB OK\n";

$tables = ['asignaciones','vehiculos','operadores','odometer_logs','attachments',
           'components','vehicle_components','ordenes_compra','vehiculo_documentos'];
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
echo "Done.\n";
