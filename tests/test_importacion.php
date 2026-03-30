<?php
/**
 * Test end-to-end de importación de vehículos
 * Ejecutar: php tests/test_importacion.php
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/odometro.php';
require_once __DIR__ . '/../includes/cache.php';
require_once __DIR__ . '/../includes/importacion_vehiculos.php';

// Simular sesión mínima
$_SESSION = ['user_id' => 2, 'email' => 'test@test.com', 'nombre' => 'Test'];

echo "=== Test Importación E2E ===\n\n";

// Crear CSV de prueba
$csvPath = '/tmp/test_import.csv';
$csv  = "Placa,Marca,Modelo,Anio,Tipo,Combustible,KM\n";
$csv .= "TEST-001,Toyota,Hilux,2023,Camioneta,Diesel,15000\n";
$csv .= "TEST-002,Honda,CR-V,2024,SUV,Gasolina,8000\n";
$csv .= "TEST-003,Nissan,Frontier,2022,Camioneta,Diesel,45000\n";
$csv .= "TEST-001,Toyota,Hilux,2023,Camioneta,Diesel,15000\n"; // Duplicado en archivo
$csv .= "TEST-004,,,2020,,,\n"; // Sin marca/modelo
file_put_contents($csvPath, $csv);

// Step 1: Leer archivo
$data = importacion_leer_csv($csvPath);
echo "Headers: " . implode(', ', $data['headers']) . "\n";
echo "Total filas: " . $data['total_rows'] . "\n\n";

// Step 2: Mapeo
$mapping = [0=>'placa',1=>'marca',2=>'modelo',3=>'anio',4=>'tipo',5=>'combustible',6=>'km_actual'];

// Step 3: Ejecutar importación
$result = importacion_ejecutar($data['rows'], $data['headers'], $mapping, 2, 'test_import.csv');

echo "Resultado:\n";
echo "  Total: " . $result['total'] . "\n";
echo "  Creados: " . $result['creados'] . "\n";
echo "  Errores: " . $result['errores'] . "\n";
echo "  Run ID: " . $result['import_run_id'] . "\n\n";

// Validar expectativas
$passed = 0;
$failed = 0;

function assert_eq($label, $expected, $actual) {
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  PASS: $label\n";
        $passed++;
    } else {
        echo "  FAIL: $label (expected: $expected, got: $actual)\n";
        $failed++;
    }
}

echo "=== Assertions ===\n";
assert_eq('Total filas', 5, $result['total']);
assert_eq('Creados', 3, $result['creados']);
assert_eq('Errores', 2, $result['errores']);

// Error types
$errorTypes = array_column($result['detalle'], 'tipo');
assert_eq('Tiene error duplicado', true, in_array('duplicado_bd', $errorTypes) || in_array('duplicado_archivo', $errorTypes));
assert_eq('Tiene error validación', true, in_array('validacion', $errorTypes));

// Verificar vehículos en BD
$db = getDB();
$stmt = $db->prepare("SELECT placa, marca, modelo, km_actual FROM vehiculos WHERE placa LIKE 'TEST-%' AND deleted_at IS NULL ORDER BY placa");
$stmt->execute();
$vehiculos = $stmt->fetchAll();
assert_eq('Vehículos en BD', 3, count($vehiculos));

echo "\nVehículos creados:\n";
foreach ($vehiculos as $v) {
    echo "  {$v['placa']} | {$v['marca']} {$v['modelo']} | KM: {$v['km_actual']}\n";
}

// Verificar odómetro
$odoStmt = $db->query("SELECT v.placa, o.reading_km, o.source FROM odometer_logs o JOIN vehiculos v ON v.id = o.vehicle_id WHERE o.source = 'importacion' ORDER BY v.placa");
$odos = $odoStmt->fetchAll();
assert_eq('Registros odómetro', 3, count($odos));

echo "\nOdómetro registrado:\n";
foreach ($odos as $o) {
    echo "  {$o['placa']} | {$o['reading_km']} km | {$o['source']}\n";
}

// Verificar import_run
$runStmt = $db->prepare("SELECT * FROM import_runs WHERE id = ?");
$runStmt->execute([$result['import_run_id']]);
$run = $runStmt->fetch();
assert_eq('Import run estado', 'completado', $run['estado']);
assert_eq('Import run creados', 3, (int)$run['creados']);

// Errores detallados
echo "\nErrores detallados:\n";
foreach ($result['detalle'] as $d) {
    echo "  Fila {$d['fila']} [{$d['placa']}]: {$d['tipo']} - " . implode('; ', $d['errores']) . "\n";
}

// Cleanup
echo "\n=== Limpieza ===\n";
$db->exec("DELETE FROM odometer_logs WHERE source = 'importacion'");
$db->prepare("DELETE FROM vehiculos WHERE placa LIKE 'TEST-%'")->execute();
$db->prepare("DELETE FROM import_runs WHERE id = ?")->execute([$result['import_run_id']]);
$db->exec("DELETE FROM audit_logs WHERE entidad = 'vehiculos' AND despues_json LIKE '%importacion%'");
echo "Datos de prueba eliminados\n";

echo "\n=== RESULTADO: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
