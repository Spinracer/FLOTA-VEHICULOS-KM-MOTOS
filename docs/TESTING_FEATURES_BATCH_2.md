# Plan de Testing - Features Avanzadas Batch 2

## 🎯 Objetivos
Validar que las 4 nuevas features funcionen correctamente sin errores.

## ✅ Checklist de Testing

### FEATURE 1: Dashboard de Sincronización OC↔OT

#### Test 1.1: Acceso al Dashboard
- [ ] Ir a URL: `http://localhost:8080/modules/web/sincronizacion_dashboard.php`
- [ ] Página carga correctamente
- [ ] Tarjetas de estadísticas visibles
- [ ] **Resultado Esperado:** Dashboard muestra 0 eventos (normal si es primera vez)

#### Test 1.2: Filtros Funcionan
- [ ] Seleccionar filtro "Desde" = hoy
- [ ] Click "Filtrar"
- [ ] Página recarga con parámetros en URL
- [ ] **Resultado Esperado:** URL contiene `?from=2026-03-30`

#### Test 1.3: API Endpoint
```bash
curl http://localhost:8080/modules/api/sincronizacion.php?page=1&per_page=10
```
- [ ] Respuesta es JSON válido
- [ ] Contiene campos: `ok`, `total`, `page`, `rows`
- [ ] **Resultado Esperado:** `{ "ok": true, "total": 0, "rows": [] }`

---

### FEATURE 2: Reportes de Importación

#### Test 2.1: Acceso a Reportes
- [ ] Ir a URL: `http://localhost:8080/modules/web/importacion_reportes.php`
- [ ] Página carga sin errores
- [ ] Tarjetas globales visibles
- [ ] Tabla "Resumen por Usuario" visible
- [ ] **Resultado Esperado:** Página funcional

#### Test 2.2: Crear una Importación para Testear
- [ ] Ir a Vehículos → Importar
- [ ] Crear archivo CSV de prueba:
```csv
placa,marca,modelo,vin,numero_chasis,numero_motor
TEST-001,Toyota,Corolla,VIN-001,CHASIS-001,MOTOR-001
TEST-002,Honda,Civic,VIN-002,CHASIS-002,MOTOR-002
```
- [ ] Subir archivo
- [ ] Ejecutar importación (sin UPDATE)
- [ ] Debe registrar en audit_logs

#### Test 2.3: Verificar Importación en Reportes
- [ ] Refrescar Reportes
- [ ] Tarjeta "Total Importaciones" incrementó a 1
- [ ] Tarjeta "Vehículos Insertados" muestra 2
- [ ] **Resultado Esperado:** Estadísticas actualizadas

#### Test 2.4: API Reportes
```bash
curl http://localhost:8080/modules/api/importacion_reportes.php?action=stats
```
- [ ] Respuesta contiene `total_importaciones: 1`
- [ ] Contiene `total_insertados: 2`
- [ ] **Resultado Esperado:** JSON con estadísticas

---

### FEATURE 3: Validación Cruzada VIN/Chasis/Motor

#### Test 3.1: Validación de Formato
- [ ] Ir a Vehículos → Importar
- [ ] Crear CSV con VIN inválido (ej: 123):
```csv
placa,marca,modelo,vin
INVALID-01,Test,Test,123
```
- [ ] Intentar importar
- [ ] Debe mostrar error: "VIN inválido"
- [ ] **Resultado Esperado:** Import falla por validación

#### Test 3.2: Validación Cruzada (BD)
- [ ] Crear primer vehículo:
```csv
placa,marca,modelo,vin,numero_chasis
VEH-001,Toyota,Corolla,VIN-UNIQUE-001,CHASIS-UNIQUE-001
```
- [ ] Importar (debe exitoso)
- [ ] Crear segundo CSV donde VIN es igual pero chasis diferente:
```csv
placa,marca,modelo,vin,numero_chasis
VEH-002,Honda,Civic,VIN-UNIQUE-001,CHASIS-DIFERENTE
```
- [ ] Intentar importar
- [ ] Debe mostrar error: "Inconsistencia: VIN ... asociado a CHASIS-UNIQUE-001"
- [ ] **Resultado Esperado:** Import rechazado por inconsistencia

#### Test 3.3: Registro en Reportes
- [ ] Ir a Reportes de Importación
- [ ] Ver la importación rechazada
- [ ] Filtro "resultado" = Error
- [ ] Debe mostrar fila con error de identificadores
- [ ] **Resultado Esperado:** Error registrado correctamente

---

### FEATURE 4: CLI para Importación Batch

#### Test 4.1: Help
```bash
php /workspaces/FLOTA-VEHICULOS-KM-MOTOS/scripts/cli/importacion_batch.php --help
```
- [ ] Muestra ayuda sin errores
- [ ] Contiene ejemplos de uso
- [ ] **Resultado Esperado:** Ayuda visible

#### Test 4.2: Importación Simple
```bash
# Crear archivo de prueba
cat > /tmp/test_batch.csv << 'EOF'
placa,marca,modelo,vin
CLI-TEST-01,Toyota,Corolla,VIN-CLI-001
CLI-TEST-02,Honda,Civic,VIN-CLI-002
CLI-TEST-03,Ford,Fiesta,VIN-CLI-003
EOF

# Ejecutar importación
php /workspaces/FLOTA-VEHICULOS-KM-MOTOS/scripts/cli/importacion_batch.php \
  --archivo=/tmp/test_batch.csv \
  --usuario=1 \
  --verbose
```
- [ ] Muestra configuración
- [ ] Muestra mapping de columnas
- [ ] Muestra resultados (3 creados, 0 errores)
- [ ] Tasa de éxito = 100%
- [ ] **Resultado Esperado:** ✓ Proceso completado

#### Test 4.3: Dry-Run (Validación)
```bash
php /workspaces/FLOTA-VEHICULOS-KM-MOTOS/scripts/cli/importacion_batch.php \
  --archivo=/tmp/test_batch.csv \
  --usuario=1 \
  --dry-run \
  --verbose
```
- [ ] Muestra "Modo DRY RUN: Cambios NO guardados"
- [ ] Los vehículos NO se insertan realmente
- [ ] **Resultado Esperado:** Validación sin insertar

#### Test 4.4: Actualización por VIN
```bash
# Crear archivo de actualización
cat > /tmp/test_update.csv << 'EOF'
placa,marca,modelo,vin,anio
CLI-TEST-01,Toyota,Corolla Premium,VIN-CLI-001,2024
CLI-TEST-04,Mazda,3,VIN-CLI-004,2024
EOF

# Ejecutar actualización
php /workspaces/FLOTA-VEHICULOS-KM-MOTOS/scripts/cli/importacion_batch.php \
  --archivo=/tmp/test_update.csv \
  --usuario=1 \
  --actualizar \
  --campo-clave=vin \
  --verbose
```
- [ ] Muestra "Modo: ACTUALIZAR"
- [ ] Campo clave: vin
- [ ] Resultados muestran 1 actualizado, 1 creado
- [ ] **Resultado Esperado:** Merge correcto (CLI-TEST-01 actualizado a 2024, CLI-TEST-04 nuevo)

#### Test 4.5: Verificar en Reportes
```bash
# Consultar estadísticas de importaciones CLI
curl "http://localhost:8080/modules/api/importacion_reportes.php?action=stats"
```
- [ ] `total_importaciones` incrementó (batch 1 + batch 2 += 2)
- [ ] `total_insertados` >= 4
- [ ] `total_actualizados` >= 1
- [ ] **Resultado Esperado:** Datos consistentes

---

### FEATURE 5: Sincronización OC→OT (Integración)

#### Test 5.1: Crear OC en Pendiente (NO sincroniza)
- [ ] Crear OC nueva en estado "Pendiente"
- [ ] Agregar componente
- [ ] Ir a Dashboard Sincronización
- [ ] NO debe haber registro de sync
- [ ] **Resultado Esperado:** Sin sincronización

#### Test 5.2: Cambiar OC a Aprobada (SÍ sincroniza)
- [ ] OC anterior → marcar Aprobada
- [ ] Crear OT asociada en estado "Pendiente"
- [ ] Agregar componente a OC
- [ ] Dashboard debe mostrar evento de sync
- [ ] OT debe tener el componente
- [ ] **Resultado Esperado:** Sincronización activa

#### Test 5.3: OT Completada (NO sincroniza más)
- [ ] OT anterior → marcar Completada
- [ ] OC → intentar agregar nuevo componente
- [ ] Dashboard debe mostrar evento pero sin sync
- [ ] OT no debe tener nuevo componente
- [ ] **Resultado Esperado:** Sincronización bloqueada

---

## 🔍 Testing de Errores (Validación Negativa)

### Error 1: Archivo no encontrado en CLI
```bash
php scripts/cli/importacion_batch.php --archivo=/inexistente.csv
```
- [ ] **Esperado:** ERROR: Archivo no encontrado

### Error 2: Permisos insuficientes
- [ ] Crear usuario sin permisos de importación
- [ ] Intentar importar vía web
- [ ] **Esperado:** Acceso denegado 403

### Error 3: CSV malformado
```bash
# CSV sin headers
cat > /tmp/bad.csv << 'EOF'
Toyota,Corolla
Honda,Civic
EOF

php scripts/cli/importacion_batch.php --archivo=/tmp/bad.csv
```
- [ ] **Esperado:** Manejo graceful de error

---

## 📊 Resumen de Testing

| # | Feature | Pruebas | Status |
|---|---------|---------|--------|
| 1 | Dashboard Sync | 3 | [ ] |
| 2 | Reportes Import | 4 | [ ] |
| 3 | Validación Cruzada | 3 | [ ] |
| 4 | CLI Batch | 5 | [ ] |
| 5 | Integración OC→OT | 3 | [ ] |
| - | Errores Negativos | 3 | [ ] |
| **TOTAL** | **21 Tests** | **20/21** | [ ] |

---

## 🧪 Testing Automatizado (Opcional)

Si implementas test suite en PHP:

```php
<?php
// tests/feature_batch_2.php

class FeatureBatch2Test {
    
    public function test_dashboard_carga() {
        $response = file_get_contents('http://localhost:8080/modules/web/sincronizacion_dashboard.php');
        assert(strpos($response, 'Dashboard') !== false);
        assert(strpos($response, 'stats-grid') !== false);
    }
    
    public function test_validacion_cruzada() {
        require 'includes/importacion_vehiculos.php';
        
        $data = [
            'placa' => 'TEST-001',
            'vin' => 'INVALID',  // Menos de 10 caracteres
        ];
        
        $result = importacion_validar_identificadores_cruzados($data);
        assert(!$result['valid']);
        assert(count($result['errors']) > 0);
    }
    
    public function test_cli_help() {
        exec('php scripts/cli/importacion_batch.php --help', $output, $code);
        assert($code === 0);
        assert(count($output) > 5);
    }
}

$test = new FeatureBatch2Test();
$test->test_dashboard_carga();
$test->test_validacion_cruzada();
$test->test_cli_help();
echo "✓ Todos los tests pasaron\n";
```

---

## 📋 Checklist Final

Antes de pasar a producción:

- [ ] Todas las pruebas manuales completadas
- [ ] Todos los endpoints API responden correctamente
- [ ] Los dashboards cargan sin errores
- [ ] CLI ejecuta sin warnings
- [ ] Auditoría registra correctamente
- [ ] Permisos se enforzan
- [ ] No hay SQL errors en logs
- [ ] Documentación está completa
- [ ] Ejemplos funcionan

---

**Si todo está ✓, está listo para producción**
