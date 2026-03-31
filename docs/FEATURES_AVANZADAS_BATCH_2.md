# Sprint Marzo 2026 - Features Avanzadas (Batch 2)

## 📌 Descripción General

Este documento describe las 4 features avanzadas implementadas en el segundo sprint de Marzo 2026:

1. **Dashboard de Sincronización OC↔OT** - Visualización, filtrado y estadísticas de sincronizaciones
2. **Reportes de Importación por Usuario** - Análisis de actividad y éxodo de importaciones
3. **Validación Cruzada VIN/Chasis/Motor** - Detección de inconsistencias en BD
4. **CLI para Importación Batch** - Automatización sin UI desde línea de comandos

---

## 1️⃣ Dashboard de Sincronización OC↔OT

### Ubicación
- **Web:** `/modules/web/sincronizacion_dashboard.php`
- **API:** `/modules/api/sincronizacion.php`
- **URL Acceso:** Agregado al menú principal como "📊 Sincronizaciones"

### Características
- ✅ Estadísticas en tarjetas (Total eventos, syncs, desyncs, OC/OT afectadas)
- ✅ Filtros por: estado evento, rango de fechas, tipo (sync/desync)
- ✅ Tabla historial con: fecha, tipo, vehículo, OC/OT, estado, usuario
- ✅ Paginación automática (50 por página)
- ✅ Links directos a OC y OT para drill-down

### Uso Web
```
1. Ir a menú principal
2. Seleccionar "📊 Sincronizaciones"
3. (Opcional) Aplicar filtros
4. Verificar historial o estadísticas
```

### Uso API
```bash
# Listar sincronizaciones
GET /api/sincronizacion.php?page=1&per_page=50

# Filtrar por tipo
GET /api/sincronizacion.php?tipo=sync&page=1

# Filtrar por rango
GET /api/sincronizacion.php?from=2026-01-01&to=2026-03-31&page=1

# Estadísticas
POST /api/sincronizacion.php?action=stats
```

### Respuesta Ejemplo (API)
```json
{
  "ok": true,
  "total": 125,
  "page": 1,
  "per_page": 50,
  "rows": [
    {
      "id": 1,
      "tipo": "sync",
      "usuario_nombre": "Admin",
      "oc_id": 5,
      "ot_id": 42,
      "items_sync": 3,
      "razon": "Agregados componentes",
      "created_at": "2026-03-30 14:30:00"
    }
  ]
}
```

### Permisos Requeridos
- `can('view', 'ordenes_compra')` ✓
- `can('view', 'mantenimientos')` ✓

---

## 2️⃣ Reportes de Importación por Usuario

### Ubicación
- **Web:** `/modules/web/importacion_reportes.php`
- **API:** `/modules/api/importacion_reportes.php`
- **URL Acceso:** Agregado bajo "Vehículos → Importar → Reportes"

### Características
- ✅ **Estadísticas Globales:** Total importaciones, insertados, actualizados, usuarios
- ✅ **Resumen por Usuario:** Tabla con actividad de cada importador
- ✅ **Historial Detallado:** Por importación individual
- ✅ **Filtros:** Usuario, resultado (exitosa/parcial/error), rango de fechas
- ✅ **Campos:** Archivo, campo clave usado, cantidad insertada/actualizada/errores, duración

### Uso Web
```
1. Ir a Vehículos → Importar → Reportes
2. Ver estadísticas globales y por usuario
3. (Opcional) Filtrar por usuario, resultado, fechas
4. Revisar historial detallado de cada importación
```

### Uso API
```bash
# Listar importaciones
GET /api/importacion_reportes.php?action=list&page=1&per_page=50

# Filtrar por usuario
GET /api/importacion_reportes.php?action=list&usuario_id=1&page=1

# Filtrar por resultado
GET /api/importacion_reportes.php?action=list&resultado=success&page=1

# Estadísticas globales
GET /api/importacion_reportes.php?action=stats&from=2026-01-01&to=2026-03-31
```

### Respuesta Ejemplo (Estadísticas)
```json
{
  "ok": true,
  "stats": {
    "total_importaciones": 25,
    "usuarios_activos": 3,
    "importaciones_exitosas": 22,
    "importaciones_parciales": 2,
    "importaciones_fallidas": 1,
    "total_insertados": 450,
    "total_actualizados": 85,
    "total_errores": 15
  }
}
```

### Permisos Requeridos
- `can('view', 'importacion_vehiculos')` ✓

---

## 3️⃣ Validación Cruzada VIN/Chasis/Motor

### Ubicación
- **Función:** `/includes/importacion_vehiculos.php` - `importacion_validar_identificadores_cruzados()`
- **Integración:** Automática en `importacion_ejecutar()`

### Características
- ✅ Valida formato alfanumérico de VIN, Chasis, Motor
- ✅ Detecta inconsistencias: si VIN X está asociado a Motor Y, rechaza Motor Z
- ✅ Búsqueda cruzada en BD contra valores existentes
- ✅ Mensajes de error específicos para cada inconsistencia

### Reglas de Validación

```
VIN:
  - Rango: 10-20 caracteres alfanuméricos
  - Si existe en BD con diferente Chasis/Motor → FALLA
  
Chasis:
  - Rango: 5-30 caracteres alfanuméricos
  - Si existe en BD con diferente VIN → FALLA
  
Motor:
  - Rango: 3-30 caracteres alfanuméricos
  - Si existe en BD con diferente VIN/Chasis → FALLA
```

### Ejemplo de Validación
```php
$data = [
    'placa' => 'ABC-123',
    'vin' => 'WAUZZZ3B29N000001',
    'numero_chasis' => 'ABC-CHASIS-001',
    'numero_motor' => 'ABS-MOTOR-001'
];

$validacion = importacion_validar_identificadores_cruzados($data);

// Si VIN ya existe con diferente chasis en BD:
// $validacion['valid'] = false
// $validacion['errors'] = ["Inconsistencia: VIN ... asociado a Chasis XYZ pero se intenta..."]
```

### Permisos Requeridos
- Automático durante importación (sin restricción adicional)

---

## 4️⃣ CLI para Importación Batch

### Ubicación
- **Script:** `/scripts/cli/importacion_batch.php`
- **Tipo:** Executable PHP CLI

### Características
- ✅ Importación automatizada sin UI
- ✅ Auto-detección de mapping de columnas
- ✅ Soporte CSV y XLSX
- ✅ Validación previa (dry-run)
- ✅ Salida con colores y progreso
- ✅ Estadísticas finales y tasa de éxito

### Sintaxis
```bash
php scripts/cli/importacion_batch.php --archivo=RUTA [OPCIONES]
```

### Opciones
```
--archivo=RUTA              Ruta al archivo CSV o XLSX (OBLIGATORIO)
--usuario=ID                ID del usuario ejecutando (default: 1 - Admin)
--actualizar                Flag: actualizar existentes (default: crear)
--campo-clave=CAMPO         Campo clave: placa|vin|numero_chasis|numero_motor (default: placa)
--verbose                   Mostrar detalles del progreso
--dry-run                   Simular sin insertar (solo validación)
--help                      Mostrar ayuda
```

### Ejemplos de Uso

#### Importar nuevos vehículos
```bash
php scripts/cli/importacion_batch.php --archivo=vehiculos.csv
```

#### Actualizar por VIN (usuario específico)
```bash
php scripts/cli/importacion_batch.php \
  --archivo=vehiculos_update.xlsx \
  --usuario=1 \
  --actualizar \
  --campo-clave=vin
```

#### Validación previa (dry-run)
```bash
php scripts/cli/importacion_batch.php \
  --archivo=vehiculos.csv \
  --verbose \
  --dry-run
```

#### Con todos los detalles
```bash
php scripts/cli/importacion_batch.php \
  --archivo=vehiculos.csv \
  --usuario=2 \
  --actualizar \
  --campo-clave=numero_chasis \
  --verbose
```

### Output Ejemplo
```
╔════════════════════════════════════════════════════════════════╗
║ FLOTA-VEHICULOS: CLI para Importación Batch                   ║
╚════════════════════════════════════════════════════════════════╝

┌─────────────────────────────────────────────────────────────┐
│ Importación Batch de Vehículos v1.0                        │
└─────────────────────────────────────────────────────────────┘

📋 Configuración:
  • Archivo: vehiculos.csv
  • Usuario ID: 1
  • Modo: CREAR
  
✓ Usuario: Admin

⏳ Leyendo archivo...
✓ Archivo leído: 150 filas

🔗 Mapping de columnas:
  [0] 'placa' → placa
  [1] 'marca' → marca
  [2] 'modelo' → modelo
  [3] 'vin' → vin

⏳ Ejecutando importación...

────────────────────────────────────────────────────────────
📊 RESULTADOS:
────────────────────────────────────────────────────────────
Total filas:          150
Creados:              148 ✓
Actualizados:         0 
Errores:              2 ✗
────────────────────────────────────────────────────────────

📈 Tasa de éxito: 98.7%

✓ Proceso completado
```

### Integración con Sistemas Externos

```bash
#!/bin/bash
# Ejemplo: importar desde ERP cada noche

ARCHIVO="/tmp/vehiculos_export.csv"
USUARIO_ID=1

# Descargar desde ERP
curl -o "$ARCHIVO" https://erp.example.com/api/export/vehiculos

# Importar batch
php /path/to/FLOTA/scripts/cli/importacion_batch.php \
  --archivo="$ARCHIVO" \
  --usuario=$USUARIO_ID \
  --actualizar \
  --campo-clave=vin

# Enviar email si falló
if [ $? -ne 0 ]; then
  mail -s "Importación fallida" admin@flotacontrol.local < /tmp/error.log
fi
```

### Permisos Requeridos
- Usuario debe tener: `can('create', 'importacion_vehiculos')`
- Si --actualizar: `can('edit', 'importacion_vehiculos')`

---

## 🔧 Integración Técnica

### Tablas BD Utilizadas

Todas las features usan tablas existentes:
- `audit_logs` — Para registrar sincronizaciones e importaciones
- `vehiculos` — Para validación de identificadores
- `ordenescompra` — Para estadísticas de sync
- `mantenimientos` — Para estadísticas de sync
- `usuarios` — Para filtrado y trazabilidad

### Índices Optimizados
```sql
-- Ya existen:
CREATE INDEX idx_audit_entidad ON audit_logs(entidad);
CREATE INDEX idx_audit_created ON audit_logs(created_at);
CREATE INDEX idx_audit_usuario ON audit_logs(usuario_id);
```

### Flujo de Sincronización (Auditoría)

```
1. Usuario agrega/modifica/elimina componente en OC
   ↓
2. Sistema verifica: OC.estado == 'Aprobada' && OT.estado != 'Completada'
   ↓
3. Si válido → Sincroniza items a OT
   ↓
4. Registra en audit_logs con:
   - entidad: 'oc_to_ot_sync'
   - accion: 'sync' | 'delete' | 'cancel'
   - meta: { orden_compra_id, mantenimiento_id, items_count, razon }
   ↓
5. Dashboard consulta audit_logs para historial
```

---

## 📊 Estadísticas y Métricas

Todas las features tracean automáticamente:
- Usuario responsable
- Fecha y hora exacta
- Identificadores afectados (OC/OT/Vehículo)
- Cantidad de items sincronizados o importados
- Razón o motivo
- Duración del proceso (para CLI)

---

## 🎯 Casos de Uso Reales

### 1. Control de Calidad de Importaciones
```
Coordinador IT necesita verificar:
- ¿Cuáles son los usuarios que más importan?
- ¿En qué estado falla el 5% de importaciones?
- ¿Cuál fue el último batch que entró?

→ Usa: Módulo "Reportes de Importación"
```

### 2. Auditoría de Sincronizaciones OC→OT
```
Gerente de talleres necesita:
- ¿Cuándo se sincronizaron los componentes última vez?
- ¿Qué usuario gestionó la sincronización?
- ¿Hay desincroniaciones pendientes?

→ Usa: Dashboard "Sincronización OC↔OT"
```

### 3. Automatización sin UI
```
ERP externo necesita:
- Bulk import de 1000 vehículos cada noche
- Auto-actualización por VIN (no duplicar)
- Sin intervención manual

→ Usa: CLI `importacion_batch.php` en cron job
```

### 4. Prevención de Datos Inconsistentes
```
Soporte técnico recibe:
- "No puedo actualizar vehículo, dice VIN inconsistente"
- Importación rechaza línea de vehículo

→ Usa: Validador cruzado `importacion_validar_identificadores_cruzados()`
```

---

## 🚨 Troubleshooting

### CLI no ejecuta
```bash
# Verificar permisos
ls -la scripts/cli/importacion_batch.php

# Si no tiene x (ejecutable):
chmod +x scripts/cli/importacion_batch.php

# Ejecutar con php explícitamente:
php scripts/cli/importacion_batch.php --help
```

### Validación cruzada rechaza VIN válido
```
Causas posibles:
1. VIN asociado ya existe en BD con Chasis diferente
2. Formato VIN inválido (no alfanumérico o fuera de rango)

Solución:
- Revisar BD: SELECT * FROM vehiculos WHERE vin = 'ABC...'
- Corregir en origen o usar --campo-clave distinto
```

### Dashboard vacío (sin sincronizaciones)
```
Causas posibles:
1. No hay registros en audit_logs con entidad='oc_to_ot_sync'
2. Aún no se han creado OC o OT
3. Las restricciones de estado previenen sincronizaciones

Solución:
- Crear OC en estado "Aprobada"
- Crear OT asociada
- Agregar componentes → debería sincronizar y registrarse
```

---

## 📚 Referencias

- [DEPLOY.md](../DEPLOY.md) - Instrucciones de despliegue
- [docs/API.md](../docs/API.md) - Documentación API completa
- [TESTING_PLAN.md](../TESTING_PLAN.md) - Plan de testing
- [CHANGELOG_IMPLEMENTACIONES.md](../CHANGELOG_IMPLEMENTACIONES.md) - Cambios v3.1.0

---

## ✅ Checklist de Implementación

- [x] Dashboard sincronización web + API
- [x] Reportes importación web + API
- [x] Validación cruzada VIN/Chasis/Motor
- [x] CLI importación batch
- [x] Tests manuales completados
- [x] Documentación completa

---

**Versión:** 3.2.0 Advanced  
**Fecha:** 30 de Marzo 2026  
**Status:** Ready for Production ✓
