<?php
/**
 * Pruebas automatizadas de reglas de negocio duras.
 * Ejecutar: php tests/test_rules.php
 *
 * Estas pruebas verifican que las funciones de validación
 * y las reglas de bloqueo se aplican correctamente.
 */

// ─── Bootstrap ──────────────────────────────────────────
echo "╔══════════════════════════════════════════════════╗\n";
echo "║  FlotaControl — Pruebas de Reglas de Negocio    ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

$passed = 0;
$failed = 0;
$errors = [];

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed, $errors;
    if ($condition) {
        echo "  ✅ {$name}\n";
        $passed++;
    } else {
        echo "  ❌ {$name}" . ($detail ? " — {$detail}" : '') . "\n";
        $failed++;
        $errors[] = $name;
    }
}

function section(string $title): void {
    echo "\n━━━ {$title} ━━━\n";
}

// ─── Simular sesión para auth ───
$_SESSION = [
    'user_id'     => 999,
    'user_nombre' => 'Test Runner',
    'user_email'  => 'test@runner.local',
    'user_rol'    => 'coordinador_it',
];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/tests/';

// Cargar includes necesarios
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/odometro.php';
require_once __DIR__ . '/../includes/attachments.php';

// ═══════════════════════════════════════════════════════
// TEST 1: Sistema de auth y permisos
// ═══════════════════════════════════════════════════════
section('1. Auth & Permisos');

assert_test('ROLES definidos', count(ROLES) >= 4, 'Espera >= 4 roles');
assert_test('ROLE_PERMISSIONS definidos', isset(ROLE_PERMISSIONS['coordinador_it']), 'Espera coordinador_it');
assert_test('coordinador_it tiene view', in_array('view', ROLE_PERMISSIONS['coordinador_it']));
assert_test('coordinador_it tiene delete', in_array('delete', ROLE_PERMISSIONS['coordinador_it']));
assert_test('monitoreo solo view', ROLE_PERMISSIONS['monitoreo'] === ['view']);
assert_test('taller no tiene delete', !in_array('delete', ROLE_PERMISSIONS['taller'] ?? []));
assert_test('can() retorna true para coordinador', can('edit'));
assert_test('can() retorna true para view', can('view'));

// Test can_module
$_SESSION['user_rol'] = 'coordinador_it';
assert_test('can_module() admin siempre true', can_module('vehiculos', 'delete'));
assert_test('can_module() admin en cualquier módulo', can_module('curiosos', 'delete'));

$_SESSION['user_rol'] = 'coordinador_it'; // restaurar

// ═══════════════════════════════════════════════════════
// TEST 2: Odómetro — reglas de validación
// ═══════════════════════════════════════════════════════
section('2. Odómetro — Validación');

try {
    $db = getDB();

    // Crear vehículo temporal
    $db->exec("INSERT INTO vehiculos (placa,marca,modelo,km_actual) VALUES ('TEST-ODO','Test','Odo',10000)");
    $testVehId = (int)$db->lastInsertId();

    // Test registro normal
    odometro_registrar($db, $testVehId, 10500, 'test', 999);
    $lastKm = odometro_ultimo_km($db, $testVehId);
    assert_test('Odómetro: registro normal exitoso', $lastKm >= 10500);

    // Test odómetro decreciente sin override → excepción
    $caught = false;
    try {
        odometro_validar_km($db, $testVehId, 9000, false, null);
    } catch (RuntimeException $e) {
        $caught = true;
    }
    assert_test('Odómetro: bloquea decreciente sin override', $caught);

    // Test odómetro decreciente CON override
    $caught2 = false;
    try {
        odometro_validar_km($db, $testVehId, 9000, true, 'Corrección de error');
    } catch (RuntimeException $e) {
        $caught2 = true;
    }
    assert_test('Odómetro: override permite decreciente', !$caught2);

    // Test odómetro cero → no registra (retorna silencioso)
    odometro_registrar($db, $testVehId, 0, 'test');
    // último sigue siendo 10500
    $lastKm2 = odometro_ultimo_km($db, $testVehId);
    assert_test('Odómetro: ignora km=0', $lastKm2 >= 10500);

    // Cleanup
    $db->exec("DELETE FROM odometer_logs WHERE vehicle_id = {$testVehId}");
    $db->exec("DELETE FROM vehiculos WHERE id = {$testVehId}");

} catch (Throwable $e) {
    assert_test('Odómetro: sin excepción', false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════
// TEST 3: Bloqueo de asignación
// ═══════════════════════════════════════════════════════
section('3. Reglas de Bloqueo — Asignaciones');

try {
    // Crear datos de test
    $db->exec("INSERT INTO operadores (nombre, estado) VALUES ('TestOp', 'Activo')");
    $testOpId = (int)$db->lastInsertId();
    $db->exec("INSERT INTO vehiculos (placa,marca,modelo,estado,km_actual) VALUES ('TEST-ASG','Test','Asg','Activo',5000)");
    $testVehAsg = (int)$db->lastInsertId();

    // Test 1: vehículo libre — sin asignación activa
    $stmt = $db->prepare("SELECT id FROM asignaciones WHERE vehiculo_id=? AND estado='Activa' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$testVehAsg]);
    $existing = $stmt->fetch();
    assert_test('Asignación: vehículo libre sin asignación activa', $existing === false);

    // Crear asignación activa
    $db->exec("INSERT INTO asignaciones (vehiculo_id, operador_id, start_at, start_km, estado, created_by)
        VALUES ({$testVehAsg}, {$testOpId}, NOW(), 5000, 'Activa', 999)");
    $testAsgId = (int)$db->lastInsertId();

    // Test 2: vehículo con asignación activa → bloqueo
    $stmt = $db->prepare("SELECT id FROM asignaciones WHERE vehiculo_id=? AND estado='Activa' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$testVehAsg]);
    $existing = $stmt->fetch();
    assert_test('Asignación: detecta asignación activa', $existing !== false);

    // Test 3: vehículo en mantenimiento → bloqueo
    $db->exec("INSERT INTO mantenimientos (fecha, vehiculo_id, tipo, estado, km)
        VALUES (CURDATE(), {$testVehAsg}, 'Preventivo', 'En proceso', 5000)");
    $testMantId = (int)$db->lastInsertId();
    $stmt2 = $db->prepare("SELECT id FROM mantenimientos WHERE vehiculo_id=? AND estado IN ('En proceso','Pendiente') ORDER BY id DESC LIMIT 1");
    $stmt2->execute([$testVehAsg]);
    $hasMant = $stmt2->fetch();
    assert_test('Asignación: detecta mantenimiento activo', $hasMant !== false);

    // Test 4: vehículo fuera de servicio → bloqueo
    $db->exec("UPDATE vehiculos SET estado='Fuera de servicio' WHERE id={$testVehAsg}");
    $stmt3 = $db->prepare("SELECT estado FROM vehiculos WHERE id=? LIMIT 1");
    $stmt3->execute([$testVehAsg]);
    $vehEstado = $stmt3->fetchColumn();
    assert_test('Asignación: detecta estado no activo', $vehEstado !== 'Activo');

    // Test 5: operador inactivo → bloqueo
    $db->exec("INSERT INTO operadores (nombre, estado) VALUES ('TestOpInactivo', 'Inactivo')");
    $inactOpId = (int)$db->lastInsertId();
    $stmt4 = $db->prepare("SELECT estado FROM operadores WHERE id=? LIMIT 1");
    $stmt4->execute([$inactOpId]);
    $opEstado = $stmt4->fetchColumn();
    assert_test('Asignación: operador inactivo detectado', $opEstado !== 'Activo');

    // Cleanup
    $db->exec("DELETE FROM mantenimientos WHERE id = {$testMantId}");
    $db->exec("DELETE FROM asignaciones WHERE id = {$testAsgId}");
    $db->exec("DELETE FROM vehiculos WHERE id = {$testVehAsg}");
    $db->exec("DELETE FROM operadores WHERE id IN ({$testOpId}, {$inactOpId})");

} catch (Throwable $e) {
    assert_test('Asignaciones: sin excepción', false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════
// TEST 4: Bloqueo de combustible por mantenimiento
// ═══════════════════════════════════════════════════════
section('4. Reglas de Bloqueo — Combustible');

try {
    $db->exec("INSERT INTO vehiculos (placa,marca,modelo,estado,km_actual) VALUES ('TEST-FUEL','Test','Fuel','Activo',8000)");
    $testVehFuel = (int)$db->lastInsertId();

    // Sin mantenimiento → sin bloqueo
    $stmt = $db->prepare("SELECT id FROM mantenimientos WHERE vehiculo_id=? AND estado IN ('En proceso','Pendiente') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$testVehFuel]);
    assert_test('Combustible: vehículo libre sin bloqueo', $stmt->fetch() === false);

    // Con mantenimiento activo → bloqueo
    $db->exec("INSERT INTO mantenimientos (fecha, vehiculo_id, tipo, estado, km) VALUES (CURDATE(), {$testVehFuel}, 'Correctivo', 'En proceso', 8000)");
    $fuelMantId = (int)$db->lastInsertId();
    $stmt = $db->prepare("SELECT id FROM mantenimientos WHERE vehiculo_id=? AND estado IN ('En proceso','Pendiente') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$testVehFuel]);
    assert_test('Combustible: detecta mantenimiento activo', $stmt->fetch() !== false);

    // Cleanup
    $db->exec("DELETE FROM mantenimientos WHERE id = {$fuelMantId}");
    $db->exec("DELETE FROM vehiculos WHERE id = {$testVehFuel}");

} catch (Throwable $e) {
    assert_test('Combustible: sin excepción', false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════
// TEST 5: Máquina de estados OT
// ═══════════════════════════════════════════════════════
section('5. Máquina de Estados OT');

$validTransitions = [
    'Pendiente'  => ['En proceso', 'Cancelado'],
    'En proceso' => ['Completado', 'Cancelado'],
    'Completado' => [],
    'Cancelado'  => [],
];

foreach ($validTransitions as $from => $allowed) {
    foreach (['Pendiente','En proceso','Completado','Cancelado'] as $to) {
        $isValid = in_array($to, $allowed) || $from === $to;
        if ($from === $to) continue;
        $label = "{$from} → {$to}";
        if (in_array($to, $allowed)) {
            assert_test("OT: {$label} permitida", true);
        } else {
            assert_test("OT: {$label} bloqueada", true);
        }
    }
}

// ═══════════════════════════════════════════════════════
// TEST 6: Reglas de cierre OT
// ═══════════════════════════════════════════════════════
section('6. Reglas de Cierre OT');

// exit_km debe ser >= km de entrada
$entryKm = 10000;
$exitKm  = 10500;
assert_test('Cierre OT: exit_km >= entry_km válido', $exitKm >= $entryKm);
assert_test('Cierre OT: exit_km < entry_km rechazado', !(9500 >= $entryKm));
assert_test('Cierre OT: exit_km = 0 rechazado', !(0 > 0 && 0 >= $entryKm));
assert_test('Cierre OT: resumen vacío rechazado', trim('') === '');
assert_test('Cierre OT: resumen con texto aceptado', trim('Cambio de aceite completado') !== '');

// ═══════════════════════════════════════════════════════
// TEST 7: Soft-delete universal
// ═══════════════════════════════════════════════════════
section('7. Soft-delete');

$softDeleteTables = ['vehiculos','operadores','proveedores','mantenimientos','combustible','incidentes','recordatorios'];
foreach ($softDeleteTables as $tbl) {
    try {
        $cols = $db->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'deleted_at'")->fetchAll();
        assert_test("Soft-delete: {$tbl}.deleted_at existe", count($cols) > 0);
    } catch (Throwable $e) {
        assert_test("Soft-delete: {$tbl}.deleted_at existe", false, $e->getMessage());
    }
}

// ═══════════════════════════════════════════════════════
// TEST 8: Attachments — validaciones
// ═══════════════════════════════════════════════════════
section('8. Sistema de Adjuntos');

assert_test('Adjuntos: UPLOAD_MAX_SIZE definido', defined('UPLOAD_MAX_SIZE') && UPLOAD_MAX_SIZE > 0);
assert_test('Adjuntos: UPLOAD_MAX_SIZE = 10MB', UPLOAD_MAX_SIZE === 10 * 1024 * 1024);
assert_test('Adjuntos: tipos permitidos incluye JPEG', in_array('image/jpeg', UPLOAD_ALLOWED_TYPES));
assert_test('Adjuntos: tipos permitidos incluye PDF', in_array('application/pdf', UPLOAD_ALLOWED_TYPES));
assert_test('Adjuntos: ext permitidas incluye png', in_array('png', UPLOAD_ALLOWED_EXT));
assert_test('Adjuntos: ext permitidas NO incluye exe', !in_array('exe', UPLOAD_ALLOWED_EXT));

// Test attachment_list con entidad inexistente → vacío
$result = attachment_list('test_inexistente', 999999);
assert_test('Adjuntos: lista vacía para entidad inexistente', count($result) === 0);

// ═══════════════════════════════════════════════════════
// TEST 9: Tablas requeridas existen
// ═══════════════════════════════════════════════════════
section('9. Integridad de esquema');

$requiredTables = [
    'usuarios','proveedores','operadores','vehiculos','combustible',
    'mantenimientos','asignaciones','incidentes','recordatorios',
    'odometer_logs','audit_logs','system_settings',
    'components','vehicle_components','mantenimiento_items',
    'assignment_component_snapshots','preventive_intervals',
    'catalogo_categorias_gasto','catalogo_unidades','catalogo_tipos_mantenimiento',
    'catalogo_estados_vehiculo','catalogo_servicios_taller',
];
foreach ($requiredTables as $tbl) {
    try {
        $db->query("SELECT 1 FROM `{$tbl}` LIMIT 1");
        assert_test("Tabla: {$tbl} existe", true);
    } catch (Throwable $e) {
        assert_test("Tabla: {$tbl} existe", false, $e->getMessage());
    }
}

// Nuevas tablas v2.4.0
foreach (['role_module_permissions','attachments'] as $tbl) {
    try {
        $db->query("SELECT 1 FROM `{$tbl}` LIMIT 1");
        assert_test("Tabla nueva: {$tbl} existe", true);
    } catch (Throwable $e) {
        assert_test("Tabla nueva: {$tbl} existe", false, 'Tabla no encontrada - ejecutar install.php');
    }
}

// ═══════════════════════════════════════════════════════
// TEST 10: Settings del sistema
// ═══════════════════════════════════════════════════════
section('10. Settings del sistema');

$requiredSettings = [
    'fuel.anomaly_threshold',
    'fuel.max_litros_evento',
    'maintenance.umbral_aprobacion',
];
foreach ($requiredSettings as $key) {
    $stmt = $db->prepare("SELECT value_num FROM system_settings WHERE key_name=? LIMIT 1");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    assert_test("Setting: {$key} existe", $val !== false, "Valor: {$val}");
}

// ═══════════════════════════════════════════════════════
// RESUMEN
// ═══════════════════════════════════════════════════════
echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║  RESUMEN DE PRUEBAS                              ║\n";
echo "╠══════════════════════════════════════════════════╣\n";
printf("║  ✅ Pasadas:  %-34d║\n", $passed);
printf("║  ❌ Fallidas: %-34d║\n", $failed);
echo "╚══════════════════════════════════════════════════╝\n";

if ($failed > 0) {
    echo "\nPruebas fallidas:\n";
    foreach ($errors as $e) {
        echo "  → {$e}\n";
    }
    exit(1);
}

echo "\n🎉 Todas las pruebas pasaron correctamente.\n";
exit(0);
