# Objetivo 9 — Rendimiento y Optimización

> **Versión**: 3.9.0  
> **Fecha**: 2026-03-08

---

## 1. Sistema de Caché (File-based)

### Archivo: `includes/cache.php`

Caché transparente basada en archivos planos. No requiere Redis ni Memcached.

| Parámetro | Valor |
|---|---|
| Directorio | `/tmp/flotacontrol_cache/` |
| TTL dashboard | 120 s |
| TTL alertas | 180 s |
| TTL reportes | 300 s |
| TTL stats | 60 s |
| TTL catálogo | 600 s |

### API de funciones

| Función | Descripción |
|---|---|
| `cache_get($key)` | Lee valor cacheado o `null` si expiró |
| `cache_set($key, $value, $category)` | Almacena valor con TTL según categoría |
| `cache_remember($key, $fn, $category)` | Lee o genera y cachea automáticamente |
| `cache_delete($key)` | Elimina una clave específica |
| `cache_invalidate_prefix($prefix)` | Elimina todas las claves con un prefijo |
| `cache_flush()` | Vacía todo el caché |
| `cache_stats()` | Devuelve conteo de archivos y tamaño total |
| `cache_cleanup()` | Elimina archivos expirados (probabilístico) |

### Invalidación automática

Se invalida `cache_invalidate_prefix('dashboard')` en cada operación de escritura (POST/PUT/DELETE) desde:
- `modules/api/combustible.php`
- `modules/api/mantenimientos.php`
- `modules/api/alertas.php`

Se invalida `cache_invalidate_prefix('alertas')` en operaciones de escritura y scan desde:
- `modules/api/alertas.php`

---

## 2. Índices de Optimización (§3.19)

7 índices compuestos añadidos en `install.php` §3.19:

| Tabla | Índice | Columnas | Propósito |
|---|---|---|---|
| combustible | `idx_comb_vehiculo_fecha` | `(vehiculo_id, fecha)` | KPIs y gráficos mensuales |
| mantenimientos | `idx_mant_proveedor` | `(proveedor_id)` | Reporte por taller |
| alertas | `idx_alerta_entidad_compuesta` | `(tipo, entidad, entidad_id, estado)` | Detección de duplicados en scan |
| asignaciones | `idx_asig_vehiculo_fechas` | `(vehiculo_id, start_at, end_at)` | JOIN temporal eficiente |
| vehiculos | `idx_veh_deleted_sucursal` | `(deleted_at, sucursal_id)` | Dashboard + listados |
| vehiculos | `idx_veh_estado` | `(estado)` | Filtros frecuentes |
| combustible | `idx_comb_vehiculo_km_rec` | `(vehiculo_id, km)` | Eficiencia km |

---

## 3. Optimización de Queries

### 3.1 Dashboard (`modules/api/dashboard.php`)

**Antes**: 19 queries por request, sin caché.  
**Después**: Respuesta completa cacheada con clave basada en filtros (sucursal + vehículo + período). TTL: 120s.

### 3.2 Alertas N+1 (`modules/api/alertas.php`)

**Antes**: `alertExists()` ejecutaba 1 query por cada alerta candidata durante el scan (N queries).  
**Después**: `loadExistingAlertKeys()` carga todas las alertas activas en un hash en memoria → `alertExistsBatch()` consulta el hash en O(1).

### 3.3 Alertas Stats

**Antes**: 4 queries COUNT independientes para estadísticas.  
**Después**: 1 query con `SUM(CASE WHEN ...)` para las 4 métricas. Resultado cacheado 180s.

---

## 4. Archivos involucrados

### Nuevos
- `includes/cache.php`

### Modificados
- `modules/api/dashboard.php` — cache_remember
- `modules/api/alertas.php` — N+1 fix, stats consolidation, cache
- `modules/api/combustible.php` — cache invalidation
- `modules/api/mantenimientos.php` — cache invalidation
- `install.php` — §3.19 migration (7 indexes)
