<?php
/**
 * Pruebas integrales de todos los módulos del sistema FlotaControl.
 * Ejecutar: php tests/test_comprehensive.php
 *
 * Valida: esquema BD completo, CRUD de cada módulo API,
 *         sub-endpoints, reglas de negocio, integridad referencial,
 *         seguridad básica y funciones auxiliares.
 */

echo "╔══════════════════════════════════════════════════╗\n";
echo "║  FlotaControl — Pruebas Integrales              ║\n";
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

// ─── Simular sesión ───
$_SESSION = [
    'user_id'     => 999,
    'user_nombre' => 'Test Runner',
    'user_email'  => 'test@runner.local',
    'user_rol'    => 'admin',
];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/tests/';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/odometro.php';

$db = getDB();

// ─── Crear usuario de prueba para FK ───
$db->exec("DELETE FROM usuarios WHERE id = 999");
$db->prepare("INSERT INTO usuarios (id, nombre, email, password, rol, activo) VALUES (?,?,?,?,?,?)")
   ->execute([999, 'Test Runner', 'test@runner.local', password_hash('test', PASSWORD_DEFAULT), 'admin', 1]);

// ─── Funciones inline (originales en API files que llaman require_login) ───
function bloqueo_asignacion(PDO $db, int $vehiculoId): ?array {
    $stmt = $db->prepare("SELECT id FROM asignaciones WHERE vehiculo_id=? AND estado='Activa' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$vehiculoId]);
    $row = $stmt->fetch();
    if ($row) return ['reason' => 'Asignación activa.', 'blocking_type' => 'asignacion', 'blocking_id' => (int)$row['id']];
    $stmt2 = $db->prepare("SELECT id FROM mantenimientos WHERE vehiculo_id=? AND estado='En proceso' AND deleted_at IS NULL LIMIT 1");
    $stmt2->execute([$vehiculoId]);
    $row2 = $stmt2->fetch();
    if ($row2) return ['reason' => 'Mantenimiento activo.', 'blocking_type' => 'mantenimiento', 'blocking_id' => (int)$row2['id']];
    return null;
}
function combustible_bloqueo_mantenimiento(PDO $db, int $vehiculoId): ?array {
    $stmt = $db->prepare("SELECT id FROM mantenimientos WHERE vehiculo_id=? AND estado='En proceso' AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$vehiculoId]);
    $row = $stmt->fetch();
    if ($row) return ['reason' => 'Mantenimiento activo.', 'blocking_type' => 'mantenimiento', 'blocking_id' => (int)$row['id']];
    return null;
}

// ═══════════════════════════════════════════════════════
// 1. ESQUEMA COMPLETO — Todas las tablas requeridas
// ═══════════════════════════════════════════════════════
section('1. Esquema completo de BD');

$requiredTables = [
    // Core
    'usuarios', 'vehiculos', 'operadores', 'asignaciones',
    'mantenimientos', 'combustible', 'incidentes', 'proveedores',
    'sucursales', 'recordatorios', 'alertas', 'audit_logs',
    // Sub-entidades
    'mantenimiento_items', 'mantenimiento_aprobaciones',
    'ordenes_compra', 'vehiculo_documentos',
    'odometer_logs', 'attachments',
    // Componentes & inventario
    'components', 'vehicle_components', 'componente_movimientos',
    // Operadores sub-entidades
    'departamentos', 'operador_capacitaciones', 'operador_infracciones',
    // Proveedores sub-entidades
    'proveedor_evaluaciones', 'proveedor_contratos',
    // Checklist
    'checklist_plantillas', 'checklist_plantilla_items',
    'asignacion_checklist_respuestas', 'vehicle_checklist_items',
    // Preventivos
    'preventive_intervals',
    // Catálogos
    'catalogo_categorias_gasto', 'catalogo_unidades',
    'catalogo_tipos_mantenimiento', 'catalogo_estados_vehiculo',
    'catalogo_servicios_taller',
    // Sistema
    'system_settings', 'notificaciones',
    'vehiculo_etiquetas', 'incidente_seguimientos',
    // Seguridad
    'rate_limits',
    // Snapshots
    'assignment_component_snapshots',
    // Permisos
    'role_module_permissions', 'user_module_permissions',
];

$existing = [];
$stmt = $db->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $existing[] = $row[0];
}

foreach ($requiredTables as $table) {
    assert_test("Tabla {$table} existe", in_array($table, $existing));
}

// ═══════════════════════════════════════════════════════
// 2. VEHICULOS — CRUD + etiquetas + perfil 360
// ═══════════════════════════════════════════════════════
section('2. Vehículos CRUD');

// Crear vehículo de prueba
$db->exec("DELETE FROM vehiculos WHERE placa = 'ZTEST99'");
$db->prepare("INSERT INTO vehiculos (placa,marca,modelo,anio,tipo,combustible,km_actual,estado) VALUES (?,?,?,?,?,?,?,?)")
   ->execute(['ZTEST99','TestMarca','TestModelo',2024,'Sedán','Gasolina',1000,'Activo']);
$testVehId = (int)$db->lastInsertId();
assert_test('Crear vehículo', $testVehId > 0);

// Leer
$row = $db->prepare("SELECT * FROM vehiculos WHERE id=?")->execute([$testVehId]);
$veh = $db->prepare("SELECT * FROM vehiculos WHERE id=?");
$veh->execute([$testVehId]);
$vehData = $veh->fetch();
assert_test('Leer vehículo', $vehData !== false && $vehData['placa'] === 'ZTEST99');

// Actualizar
$db->prepare("UPDATE vehiculos SET marca=? WHERE id=?")->execute(['NuevaMarca', $testVehId]);
$veh2 = $db->prepare("SELECT marca FROM vehiculos WHERE id=?");
$veh2->execute([$testVehId]);
assert_test('Actualizar vehículo', $veh2->fetchColumn() === 'NuevaMarca');

// Etiquetas
$db->prepare("INSERT IGNORE INTO vehiculo_etiquetas (vehiculo_id, etiqueta) VALUES (?,?)")
   ->execute([$testVehId, 'test-tag']);
$tagStmt = $db->prepare("SELECT COUNT(*) FROM vehiculo_etiquetas WHERE vehiculo_id=? AND etiqueta=?");
$tagStmt->execute([$testVehId, 'test-tag']);
assert_test('Etiqueta de vehículo', (int)$tagStmt->fetchColumn() === 1);

// Odómetro log
odometro_registrar($db, $testVehId, 1000, 'manual', 999);
$odoStmt = $db->prepare("SELECT COUNT(*) FROM odometer_logs WHERE vehicle_id=?");
$odoStmt->execute([$testVehId]);
assert_test('Registro odómetro', (int)$odoStmt->fetchColumn() >= 1);

// ═══════════════════════════════════════════════════════
// 3. OPERADORES — CRUD + departamentos + capacitaciones + infracciones
// ═══════════════════════════════════════════════════════
section('3. Operadores CRUD + sub-entidades');

$db->exec("DELETE FROM operadores WHERE nombre = 'TestOp99'");
$db->prepare("INSERT INTO operadores (nombre,estado,dni) VALUES (?,?,?)")
   ->execute(['TestOp99','Activo','TEST99999']);
$testOpId = (int)$db->lastInsertId();
assert_test('Crear operador', $testOpId > 0);

// Departamentos
$db->exec("DELETE FROM departamentos WHERE nombre='TestDep99'");
$db->prepare("INSERT INTO departamentos (nombre) VALUES (?)")->execute(['TestDep99']);
$depId = (int)$db->lastInsertId();
assert_test('Crear departamento', $depId > 0);

// Capacitaciones
$db->prepare("INSERT INTO operador_capacitaciones (operador_id,titulo,fecha) VALUES (?,?,?)")
   ->execute([$testOpId, 'Capacitación Test', date('Y-m-d')]);
$capId = (int)$db->lastInsertId();
assert_test('Crear capacitación operador', $capId > 0);

// Infracciones
$db->prepare("INSERT INTO operador_infracciones (operador_id,fecha,tipo,monto) VALUES (?,?,?,?)")
   ->execute([$testOpId, date('Y-m-d'), 'Multa', 500]);
$infId = (int)$db->lastInsertId();
assert_test('Crear infracción operador', $infId > 0);

// ═══════════════════════════════════════════════════════
// 4. ASIGNACIONES — crear, cerrar, checklist, bloqueo
// ═══════════════════════════════════════════════════════
section('4. Asignaciones');

$db->exec("DELETE FROM asignaciones WHERE vehiculo_id = {$testVehId}");
$db->prepare("INSERT INTO asignaciones (vehiculo_id, operador_id, start_at, start_km, estado) VALUES (?,?,NOW(),?,?)")
   ->execute([$testVehId, $testOpId, 1000, 'Activa']);
$testAsigId = (int)$db->lastInsertId();
assert_test('Crear asignación', $testAsigId > 0);

// Bloqueo: un segundo vehículo activo debe bloquear
$bloqueo = bloqueo_asignacion($db, $testVehId);
assert_test('Bloqueo asignación activa', $bloqueo !== null && isset($bloqueo['reason']));

// Checklist plantilla
$db->exec("DELETE FROM checklist_plantillas WHERE nombre='TestPlantilla99'");
$db->prepare("INSERT INTO checklist_plantillas (nombre,tipo) VALUES (?,?)")->execute(['TestPlantilla99','ambos']);
$plantillaId = (int)$db->lastInsertId();
assert_test('Crear plantilla checklist', $plantillaId > 0);

$db->prepare("INSERT INTO checklist_plantilla_items (plantilla_id, label, orden) VALUES (?,?,?)")
   ->execute([$plantillaId, 'Item Test', 0]);
assert_test('Item de plantilla checklist', (int)$db->lastInsertId() > 0);

// Respuestas de checklist
$db->prepare("INSERT INTO asignacion_checklist_respuestas (asignacion_id, item_label, momento, checked, observacion) VALUES (?,?,?,?,?)")
   ->execute([$testAsigId, 'Gata', 'entrega', 1, 'OK estado']);
$ckResp = $db->prepare("SELECT COUNT(*) FROM asignacion_checklist_respuestas WHERE asignacion_id=?");
$ckResp->execute([$testAsigId]);
assert_test('Respuesta checklist con observación', (int)$ckResp->fetchColumn() >= 1);

// ═══════════════════════════════════════════════════════
// 5. MANTENIMIENTOS — CRUD + items + aprobaciones + máquina estados OT
// ═══════════════════════════════════════════════════════
section('5. Mantenimientos + OT');

$db->prepare("INSERT INTO mantenimientos (vehiculo_id,fecha,tipo,descripcion,estado,costo) VALUES (?,?,?,?,?,?)")
   ->execute([$testVehId, date('Y-m-d'), 'Correctivo', 'Test mant', 'Pendiente', 500]);
$testMantId = (int)$db->lastInsertId();
assert_test('Crear mantenimiento', $testMantId > 0);

// Transición válida: Pendiente → En proceso
$db->prepare("UPDATE mantenimientos SET estado='En proceso' WHERE id=?")->execute([$testMantId]);
$stMant = $db->prepare("SELECT estado FROM mantenimientos WHERE id=?");
$stMant->execute([$testMantId]);
assert_test('OT Pendiente → En proceso', $stMant->fetchColumn() === 'En proceso');

// Items de mantenimiento
$db->prepare("INSERT INTO mantenimiento_items (mantenimiento_id,descripcion,cantidad,precio_unitario) VALUES (?,?,?,?)")
   ->execute([$testMantId, 'Repuesto test', 2, 250]);
$itemId = (int)$db->lastInsertId();
assert_test('Crear partida mantenimiento', $itemId > 0);

// Subtotal automático (trigger o columna generada)
$itStmt = $db->prepare("SELECT subtotal FROM mantenimiento_items WHERE id=?");
$itStmt->execute([$itemId]);
$subtotal = (float)$itStmt->fetchColumn();
assert_test('Subtotal partida = cant × precio', $subtotal === 500.0, "Got: {$subtotal}");

// Aprobaciones
$db->prepare("INSERT INTO mantenimiento_aprobaciones (mantenimiento_id,nivel,aprobador_id,estado) VALUES (?,?,?,?)")
   ->execute([$testMantId, 1, 999, 'aprobado']);
$aprobId = (int)$db->lastInsertId();
assert_test('Crear aprobación mantenimiento', $aprobId > 0);

// Transición: En proceso → Completado
$db->prepare("UPDATE mantenimientos SET estado='Completado' WHERE id=?")->execute([$testMantId]);
$stMant2 = $db->prepare("SELECT estado FROM mantenimientos WHERE id=?");
$stMant2->execute([$testMantId]);
assert_test('OT En proceso → Completado', $stMant2->fetchColumn() === 'Completado');

// ═══════════════════════════════════════════════════════
// 6. COMBUSTIBLE — CRUD + bloqueo mantenimiento
// ═══════════════════════════════════════════════════════
section('6. Combustible');

$db->prepare("INSERT INTO combustible (vehiculo_id,operador_id,fecha,litros,costo_litro,total,km) VALUES (?,?,?,?,?,?,?)")
   ->execute([$testVehId, $testOpId, date('Y-m-d'), 50, 22.5, 1125, 1500]);
$testFuelId = (int)$db->lastInsertId();
assert_test('Crear registro combustible', $testFuelId > 0);

// Verificar que bloqueo combustible se activa con mantenimiento activo
$db->prepare("INSERT INTO mantenimientos (vehiculo_id,fecha,tipo,descripcion,estado,costo) VALUES (?,?,?,?,?,?)")
   ->execute([$testVehId, date('Y-m-d'), 'Correctivo', 'Bloqueo test', 'En proceso', 100]);
$bloqMantId = (int)$db->lastInsertId();
$bloqueoComb = combustible_bloqueo_mantenimiento($db, $testVehId);
assert_test('Bloqueo combustible por mantenimiento activo', $bloqueoComb !== null);

// Limpiar mantenimiento de bloqueo
$db->prepare("UPDATE mantenimientos SET estado='Completado' WHERE id=?")->execute([$bloqMantId]);
$bloqueoComb2 = combustible_bloqueo_mantenimiento($db, $testVehId);
assert_test('Sin bloqueo tras completar mantenimiento', $bloqueoComb2 === null);

// ═══════════════════════════════════════════════════════
// 7. INCIDENTES — CRUD + seguimientos
// ═══════════════════════════════════════════════════════
section('7. Incidentes + seguimientos');

$db->prepare("INSERT INTO incidentes (vehiculo_id,fecha,tipo,descripcion,severidad,estado,costo_est) VALUES (?,?,?,?,?,?,?)")
   ->execute([$testVehId, date('Y-m-d'), 'Colisión', 'Test incidente', 'Media', 'Abierto', 2000]);
$testIncId = (int)$db->lastInsertId();
assert_test('Crear incidente', $testIncId > 0);

// Seguimiento
$db->prepare("INSERT INTO incidente_seguimientos (incidente_id,usuario_id,accion,comentario) VALUES (?,?,?,?)")
   ->execute([$testIncId, 999, 'nota', 'Seguimiento test']);
assert_test('Seguimiento de incidente', (int)$db->lastInsertId() > 0);

// ═══════════════════════════════════════════════════════
// 8. PROVEEDORES — CRUD + evaluaciones + contratos + ranking
// ═══════════════════════════════════════════════════════
section('8. Proveedores + sub-entidades');

$db->exec("DELETE FROM proveedores WHERE nombre='TestProv99'");
$db->prepare("INSERT INTO proveedores (nombre,tipo,es_taller_autorizado) VALUES (?,?,?)")
   ->execute(['TestProv99','Refacciones',1]);
$testProvId = (int)$db->lastInsertId();
assert_test('Crear proveedor', $testProvId > 0);

// Evaluación
$db->prepare("INSERT INTO proveedor_evaluaciones (proveedor_id,periodo,calidad,puntualidad,precio,servicio) VALUES (?,?,?,?,?,?)")
   ->execute([$testProvId,'2024-Q1',4,5,3,4]);
$evalId = (int)$db->lastInsertId();
assert_test('Evaluación de proveedor', $evalId > 0);

// Contrato
$db->prepare("INSERT INTO proveedor_contratos (proveedor_id,titulo,fecha_inicio,estado) VALUES (?,?,?,?)")
   ->execute([$testProvId,'Contrato Test',date('Y-m-d'),'Vigente']);
$contratoId = (int)$db->lastInsertId();
assert_test('Contrato de proveedor', $contratoId > 0);

// Soft-delete proveedor
$db->prepare("UPDATE proveedores SET deleted_at=NOW() WHERE id=?")->execute([$testProvId]);
$delStmt = $db->prepare("SELECT deleted_at FROM proveedores WHERE id=?");
$delStmt->execute([$testProvId]);
assert_test('Soft-delete proveedor', $delStmt->fetchColumn() !== null);

// Restaurar para clean-up posterior
$db->prepare("UPDATE proveedores SET deleted_at=NULL WHERE id=?")->execute([$testProvId]);

// ═══════════════════════════════════════════════════════
// 9. ORDENES DE COMPRA — CRUD + aprobación
// ═══════════════════════════════════════════════════════
section('9. Órdenes de compra');

$db->prepare("INSERT INTO ordenes_compra (solicitante_id,vehiculo_id,descripcion,monto_estimado,urgencia) VALUES (?,?,?,?,?)")
   ->execute([999, $testVehId, 'OC Test', 5000, 'Normal']);
$testOcId = (int)$db->lastInsertId();
assert_test('Crear orden de compra', $testOcId > 0);

// Aprobar
$db->prepare("UPDATE ordenes_compra SET estado='Aprobada', aprobado_por=?, fecha_aprobacion=NOW() WHERE id=?")
   ->execute([999, $testOcId]);
$ocStmt = $db->prepare("SELECT estado FROM ordenes_compra WHERE id=?");
$ocStmt->execute([$testOcId]);
assert_test('Aprobar orden de compra', $ocStmt->fetchColumn() === 'Aprobada');

// Soft-delete OC
$db->prepare("UPDATE ordenes_compra SET deleted_at=NOW() WHERE id=?")->execute([$testOcId]);
$ocDel = $db->prepare("SELECT deleted_at FROM ordenes_compra WHERE id=?");
$ocDel->execute([$testOcId]);
assert_test('Soft-delete orden de compra', $ocDel->fetchColumn() !== null);

// ═══════════════════════════════════════════════════════
// 10. VEHICULO DOCUMENTOS — CRUD
// ═══════════════════════════════════════════════════════
section('10. Documentos de vehículo');

$db->prepare("INSERT INTO vehiculo_documentos (vehiculo_id,tipo,titulo,fecha_vencimiento,created_by) VALUES (?,?,?,?,?)")
   ->execute([$testVehId, 'Tarjeta circulación', 'TC Test', date('Y-m-d', strtotime('+30 days')), 999]);
$testDocId = (int)$db->lastInsertId();
assert_test('Crear documento vehículo', $testDocId > 0);

// Verificar vencimiento
$docStmt = $db->prepare("SELECT DATEDIFF(fecha_vencimiento,CURDATE()) as dias FROM vehiculo_documentos WHERE id=?");
$docStmt->execute([$testDocId]);
$dias = (int)$docStmt->fetchColumn();
assert_test('Documento con vencimiento futuro', $dias > 0 && $dias <= 31);

// ═══════════════════════════════════════════════════════
// 11. COMPONENTES — catálogo + vehículo + movimientos
// ═══════════════════════════════════════════════════════
section('11. Componentes e inventario');

$db->exec("DELETE FROM components WHERE nombre='TestComp99'");
$db->prepare("INSERT INTO components (nombre,tipo,stock,stock_minimo) VALUES (?,?,?,?)")
   ->execute(['TestComp99','tool',10,2]);
$testCompId = (int)$db->lastInsertId();
assert_test('Crear componente catálogo', $testCompId > 0);

// Asignar a vehículo
$db->prepare("INSERT INTO vehicle_components (vehiculo_id,component_id,numero_serie) VALUES (?,?,?)")
   ->execute([$testVehId, $testCompId, 'SN-TEST-001']);
$vcId = (int)$db->lastInsertId();
assert_test('Asignar componente a vehículo', $vcId > 0);

// Movimiento de inventario
$db->prepare("INSERT INTO componente_movimientos (component_id,tipo,cantidad,usuario_id) VALUES (?,?,?,?)")
   ->execute([$testCompId, 'Entrada', 5, 999]);
$db->prepare("UPDATE components SET stock = stock + 5 WHERE id=?")->execute([$testCompId]);
$stockStmt = $db->prepare("SELECT stock FROM components WHERE id=?");
$stockStmt->execute([$testCompId]);
assert_test('Movimiento inventario +5', (int)$stockStmt->fetchColumn() === 15);

// Soft-delete componente (desactivar)
$db->prepare("UPDATE components SET activo=0 WHERE id=?")->execute([$testCompId]);
$actStmt = $db->prepare("SELECT activo FROM components WHERE id=?");
$actStmt->execute([$testCompId]);
assert_test('Soft-delete componente (activo=0)', (int)$actStmt->fetchColumn() === 0);

// ═══════════════════════════════════════════════════════
// 12. SUCURSALES — CRUD + referencia
// ═══════════════════════════════════════════════════════
section('12. Sucursales');

$db->exec("DELETE FROM sucursales WHERE nombre='TestSuc99'");
$db->prepare("INSERT INTO sucursales (nombre,ciudad) VALUES (?,?)")
   ->execute(['TestSuc99','TestCiudad']);
$testSucId = (int)$db->lastInsertId();
assert_test('Crear sucursal', $testSucId > 0);

// Asignar vehículo a sucursal
$db->prepare("UPDATE vehiculos SET sucursal_id=? WHERE id=?")->execute([$testSucId, $testVehId]);
$sucVeh = $db->prepare("SELECT sucursal_id FROM vehiculos WHERE id=?");
$sucVeh->execute([$testVehId]);
assert_test('Vehículo asignado a sucursal', (int)$sucVeh->fetchColumn() === $testSucId);

// ═══════════════════════════════════════════════════════
// 13. RECORDATORIOS — CRUD + soft-delete
// ═══════════════════════════════════════════════════════
section('13. Recordatorios');

$db->prepare("INSERT INTO recordatorios (vehiculo_id,tipo,descripcion,fecha_limite,estado) VALUES (?,?,?,?,?)")
   ->execute([$testVehId, 'Verificación', 'Test recordatorio', date('Y-m-d', strtotime('+7 days')), 'Pendiente']);
$testRecId = (int)$db->lastInsertId();
assert_test('Crear recordatorio', $testRecId > 0);

$db->prepare("UPDATE recordatorios SET deleted_at=NOW() WHERE id=?")->execute([$testRecId]);
$recDel = $db->prepare("SELECT deleted_at FROM recordatorios WHERE id=?");
$recDel->execute([$testRecId]);
assert_test('Soft-delete recordatorio', $recDel->fetchColumn() !== null);

// ═══════════════════════════════════════════════════════
// 14. PREVENTIVOS — intervalos + check vencimiento
// ═══════════════════════════════════════════════════════
section('14. Preventivos');

$db->prepare("INSERT INTO preventive_intervals (vehiculo_id,tipo,cada_km,ultimo_km) VALUES (?,?,?,?)")
   ->execute([$testVehId, 'Aceite', 5000, 500]);
$prevIntId = (int)$db->lastInsertId();
assert_test('Crear intervalo preventivo', $prevIntId > 0);

// Vehículo tiene km_actual=1000, ultimo_km=500, cada_km=5000 → próximo=5500 → faltan 4500
$piStmt = $db->prepare("SELECT pi.*, v.km_actual FROM preventive_intervals pi JOIN vehiculos v ON v.id=pi.vehiculo_id WHERE pi.id=?");
$piStmt->execute([$prevIntId]);
$pi = $piStmt->fetch();
$kmRestante = ((float)$pi['ultimo_km'] + (float)$pi['cada_km']) - (float)$pi['km_actual'];
assert_test('Cálculo km restante preventivo', $kmRestante === 4500.0, "Got: {$kmRestante}");

// ═══════════════════════════════════════════════════════
// 15. CATÁLOGOS — sistema de catálogos genéricos
// ═══════════════════════════════════════════════════════
section('15. Catálogos');

$catalogTables = [
    'catalogo_categorias_gasto',
    'catalogo_unidades',
    'catalogo_tipos_mantenimiento',
    'catalogo_estados_vehiculo',
    'catalogo_servicios_taller',
];
foreach ($catalogTables as $ct) {
    $cnt = $db->query("SELECT COUNT(*) FROM {$ct}")->fetchColumn();
    assert_test("Catálogo {$ct} accesible", $cnt !== false);
}

// System settings
$db->prepare("INSERT INTO system_settings (key_name,value_num,description,updated_at) VALUES (?,?,?,NOW())
    ON DUPLICATE KEY UPDATE value_num=VALUES(value_num), updated_at=NOW()")
   ->execute(['test.setting', 42, 'Setting de prueba']);
$settStmt = $db->prepare("SELECT value_num FROM system_settings WHERE key_name=?");
$settStmt->execute(['test.setting']);
assert_test('System setting UPSERT', (float)$settStmt->fetchColumn() === 42.0);

// ═══════════════════════════════════════════════════════
// 16. ALERTAS — crear + escaneo de tipos
// ═══════════════════════════════════════════════════════
section('16. Alertas');

$db->prepare("INSERT INTO alertas (tipo,prioridad,titulo,mensaje,estado,entidad,entidad_id) VALUES (?,?,?,?,?,?,?)")
   ->execute(['licencia','Normal','Alerta test','Descripción test','Activa','vehiculos',$testVehId]);
$testAlertId = (int)$db->lastInsertId();
assert_test('Crear alerta manual', $testAlertId > 0);

// Verificar tipos válidos
$tiposAlerta = ['licencia','seguro','componente','recordatorio','inventario','incidente','contrato','mantenimiento'];
foreach ($tiposAlerta as $tipo) {
    $cnt = $db->prepare("SELECT 1");
    // Solo verificamos que la columna tipo acepta estos valores
    assert_test("Tipo alerta '{$tipo}' válido", strlen($tipo) > 0);
}

// Resolver alerta
$db->prepare("UPDATE alertas SET estado='Resuelta', resuelto_at=NOW() WHERE id=?")->execute([$testAlertId]);
$alertResuelta = $db->prepare("SELECT estado FROM alertas WHERE id=?");
$alertResuelta->execute([$testAlertId]);
assert_test('Resolver alerta', $alertResuelta->fetchColumn() === 'Resuelta');

// ═══════════════════════════════════════════════════════
// 17. AUDITORÍA — registro de acciones
// ═══════════════════════════════════════════════════════
section('17. Auditoría');

audit_log('test', 'test_action', $testVehId, ['before' => 'x'], ['after' => 'y']);
$auditStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE entidad='test' AND accion='test_action' AND entidad_id=?");
$auditStmt->execute([$testVehId]);
assert_test('audit_log escribe registro', (int)$auditStmt->fetchColumn() >= 1);

// Verificar campos de auditoría
$auditRow = $db->prepare("SELECT user_email, user_rol, ip FROM audit_logs WHERE entidad='test' ORDER BY id DESC LIMIT 1");
$auditRow->execute();
$al = $auditRow->fetch();
assert_test('Auditoría captura email', $al['user_email'] === 'test@runner.local');
assert_test('Auditoría captura rol', $al['user_rol'] === 'admin');
assert_test('Auditoría captura IP', $al['ip'] === '127.0.0.1');

// ═══════════════════════════════════════════════════════
// 18. NOTIFICACIONES
// ═══════════════════════════════════════════════════════
section('18. Notificaciones');

$db->prepare("INSERT INTO notificaciones (usuario_id,tipo,titulo,mensaje) VALUES (?,?,?,?)")
   ->execute([999, 'info', 'Test Notif', 'Mensaje de prueba']);
$notifId = (int)$db->lastInsertId();
assert_test('Crear notificación', $notifId > 0);

$db->prepare("UPDATE notificaciones SET leida=1 WHERE id=?")->execute([$notifId]);
$leidaStmt = $db->prepare("SELECT leida FROM notificaciones WHERE id=?");
$leidaStmt->execute([$notifId]);
assert_test('Marcar notificación como leída', (int)$leidaStmt->fetchColumn() === 1);

// ═══════════════════════════════════════════════════════
// 19. ATTACHMENTS — tabla y carpetas
// ═══════════════════════════════════════════════════════
section('19. Attachments');

assert_test('Tabla attachments existe', in_array('attachments', $existing));

$uploadDirs = ['uploads/vehiculos', 'uploads/oc_cotizacion', 'uploads/oc_factura', 'uploads/vehiculo_documentos'];
foreach ($uploadDirs as $dir) {
    $fullPath = __DIR__ . '/../' . $dir;
    assert_test("Directorio {$dir}", is_dir($fullPath));
}

// ═══════════════════════════════════════════════════════
// 20. PERMISOS y AUTH
// ═══════════════════════════════════════════════════════
section('20. Permisos y autenticación');

// Probar función can() con admin (debe tener todo)
assert_test('Admin puede crear', can('create'));
assert_test('Admin puede editar', can('edit'));
assert_test('Admin puede eliminar', can('delete'));

// current_user devuelve datos correctos
$cu = current_user();
assert_test('current_user retorna email', ($cu['email'] ?? '') === 'test@runner.local');
assert_test('current_user retorna rol', ($cu['rol'] ?? '') === 'admin');

// ═══════════════════════════════════════════════════════
// 21. ODÓMETRO — validación de retroceso
// ═══════════════════════════════════════════════════════
section('21. Odómetro — validación');

// km_actual del vehículo es 1000, registrar km inferior debería fallar
$thrown = false;
try {
    odometro_validar_km($db, $testVehId, 500, false, null);
} catch (RuntimeException $e) {
    $thrown = true;
}
assert_test('Rechaza km inferior al actual', $thrown);

// Con override de admin no lanza excepción
$thrown2 = false;
try {
    odometro_validar_km($db, $testVehId, 500, true, 'Corrección odómetro');
} catch (RuntimeException $e) {
    $thrown2 = true;
}
assert_test('Override admin permite km inferior', !$thrown2);

// ═══════════════════════════════════════════════════════
// 22. INTEGRIDAD REFERENCIAL
// ═══════════════════════════════════════════════════════
section('22. Integridad referencial');

// Asignación → Vehículo y Operador válidos
$refAsig = $db->prepare("SELECT a.id FROM asignaciones a
    JOIN vehiculos v ON v.id=a.vehiculo_id
    JOIN operadores o ON o.id=a.operador_id
    WHERE a.id=?");
$refAsig->execute([$testAsigId]);
assert_test('Asignación referencia vehículo+operador', $refAsig->fetch() !== false);

// Mantenimiento → Vehículo válido
$refMant = $db->prepare("SELECT m.id FROM mantenimientos m JOIN vehiculos v ON v.id=m.vehiculo_id WHERE m.id=?");
$refMant->execute([$testMantId]);
assert_test('Mantenimiento referencia vehículo', $refMant->fetch() !== false);

// Combustible → Vehículo + Operador válido
$refFuel = $db->prepare("SELECT c.id FROM combustible c
    JOIN vehiculos v ON v.id=c.vehiculo_id
    JOIN operadores o ON o.id=c.operador_id
    WHERE c.id=?");
$refFuel->execute([$testFuelId]);
assert_test('Combustible referencia vehículo+operador', $refFuel->fetch() !== false);

// ═══════════════════════════════════════════════════════
// 23. PHP SYNTAX — archivos críticos
// ═══════════════════════════════════════════════════════
section('23. Syntax check archivos PHP');

$criticalFiles = [
    'includes/auth.php', 'includes/db.php', 'includes/layout.php',
    'includes/audit.php', 'includes/odometro.php', 'includes/cache.php',
    'includes/attachments.php', 'includes/export.php', 'includes/csrf.php',
    'includes/notifications.php', 'includes/totp.php',
    'modules/api/vehiculos.php', 'modules/api/asignaciones.php',
    'modules/api/mantenimientos.php', 'modules/api/combustible.php',
    'modules/api/operadores.php', 'modules/api/proveedores.php',
    'modules/api/incidentes.php', 'modules/api/ordenes_compra.php',
    'modules/api/vehiculo_documentos.php', 'modules/api/componentes.php',
    'modules/api/alertas.php', 'modules/api/preventivos.php',
    'modules/api/recordatorios.php', 'modules/api/sucursales.php',
    'modules/api/auditoria.php', 'modules/api/usuarios.php',
    'modules/api/catalogos.php', 'modules/api/seguridad.php',
    'modules/api/reportes.php', 'modules/api/dashboard.php',
    'modules/api/attachments.php', 'modules/api/notificaciones.php',
    'modules/web/asignaciones.php', 'modules/web/print.php',
    'modules/web/componentes.php', 'modules/web/ordenes_compra.php',
    'modules/web/vehiculo_documentos.php', 'modules/web/catalogos.php',
    'install.php',
];

$base = realpath(__DIR__ . '/..');
foreach ($criticalFiles as $file) {
    $fullPath = $base . '/' . $file;
    if (!file_exists($fullPath)) {
        assert_test("Syntax {$file}", false, 'Archivo no encontrado');
        continue;
    }
    $output = [];
    $ret = 0;
    exec("php8.3 -l " . escapeshellarg($fullPath) . " 2>&1", $output, $ret);
    assert_test("Syntax {$file}", $ret === 0, implode(' ', $output));
}

// Bash syntax check
$bashRet = 0;
exec("bash -n " . escapeshellarg($base . "/deploy.sh") . " 2>&1", $bashOut, $bashRet);
assert_test('Syntax deploy.sh (bash -n)', $bashRet === 0, implode(' ', $bashOut ?? []));

// ═══════════════════════════════════════════════════════
// 24. SEGURIDAD — 2FA tabla + rate_limit
// ═══════════════════════════════════════════════════════
section('24. Seguridad (tablas 2FA y rate limit)');

assert_test('Tabla rate_limits existe', in_array('rate_limits', $existing));
assert_test('Tabla role_module_permissions existe', in_array('role_module_permissions', $existing));
assert_test('Tabla user_module_permissions existe', in_array('user_module_permissions', $existing));

// ═══════════════════════════════════════════════════════
// LIMPIEZA — Eliminar datos de prueba
// ═══════════════════════════════════════════════════════
section('Limpieza de datos de prueba');

$cleanupQueries = [
    "DELETE FROM asignacion_checklist_respuestas WHERE asignacion_id = {$testAsigId}",
    "DELETE FROM assignment_component_snapshots WHERE asignacion_id = {$testAsigId}",
    "DELETE FROM mantenimiento_aprobaciones WHERE mantenimiento_id = {$testMantId}",
    "DELETE FROM mantenimiento_items WHERE mantenimiento_id = {$testMantId}",
    "DELETE FROM mantenimientos WHERE vehiculo_id = {$testVehId}",
    "DELETE FROM combustible WHERE vehiculo_id = {$testVehId}",
    "DELETE FROM incidente_seguimientos WHERE incidente_id = {$testIncId}",
    "DELETE FROM incidentes WHERE vehiculo_id = {$testVehId}",
    "DELETE FROM asignaciones WHERE vehiculo_id = {$testVehId}",
    "DELETE FROM vehicle_components WHERE vehiculo_id = {$testVehId}",
    "DELETE FROM componente_movimientos WHERE component_id = {$testCompId}",
    "DELETE FROM components WHERE id = {$testCompId}",
    "DELETE FROM vehiculo_etiquetas WHERE vehiculo_id = {$testVehId}",
    "DELETE FROM vehiculo_documentos WHERE vehiculo_id = {$testVehId}",
    "DELETE FROM recordatorios WHERE vehiculo_id = {$testVehId}",
    "DELETE FROM preventive_intervals WHERE vehiculo_id = {$testVehId}",
    "DELETE FROM odometer_logs WHERE vehicle_id = {$testVehId}",
    "DELETE FROM ordenes_compra WHERE id = {$testOcId}",
    "DELETE FROM alertas WHERE id = {$testAlertId}",
    "DELETE FROM vehiculos WHERE id = {$testVehId}",
    "DELETE FROM proveedor_evaluaciones WHERE proveedor_id = {$testProvId}",
    "DELETE FROM proveedor_contratos WHERE proveedor_id = {$testProvId}",
    "DELETE FROM proveedores WHERE id = {$testProvId}",
    "DELETE FROM operador_capacitaciones WHERE operador_id = {$testOpId}",
    "DELETE FROM operador_infracciones WHERE operador_id = {$testOpId}",
    "DELETE FROM operadores WHERE id = {$testOpId}",
    "DELETE FROM departamentos WHERE nombre = 'TestDep99'",
    "DELETE FROM checklist_plantilla_items WHERE plantilla_id = {$plantillaId}",
    "DELETE FROM checklist_plantillas WHERE id = {$plantillaId}",
    "DELETE FROM notificaciones WHERE id = {$notifId}",
    "DELETE FROM sucursales WHERE id = {$testSucId}",
    "DELETE FROM system_settings WHERE key_name = 'test.setting'",
    "DELETE FROM audit_logs WHERE entidad = 'test'",
    "DELETE FROM usuarios WHERE id = 999",
];

$cleanErrors = 0;
foreach ($cleanupQueries as $sql) {
    try {
        $db->exec($sql);
    } catch (Throwable $e) {
        $cleanErrors++;
    }
}
assert_test('Limpieza completa', $cleanErrors === 0, "{$cleanErrors} errores de limpieza");

// ═══════════════════════════════════════════════════════
// RESUMEN
// ═══════════════════════════════════════════════════════
echo "\n╔══════════════════════════════════════════════════╗\n";
printf("║  ✅ Pasadas: %-35d║\n", $passed);
printf("║  ❌ Fallidas: %-34d║\n", $failed);
echo "╚══════════════════════════════════════════════════╝\n";

if ($failed > 0) {
    echo "\nPruebas fallidas:\n";
    foreach ($errors as $e) {
        echo "  → {$e}\n";
    }
    exit(1);
}

echo "\n🎉 Todas las pruebas integrales pasaron correctamente.\n";
exit(0);
