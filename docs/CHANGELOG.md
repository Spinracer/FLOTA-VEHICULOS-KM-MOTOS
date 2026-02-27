# FlotaControl — Changelog

## [2.3.0] — 2026-02-27

### Nuevas Funcionalidades
- **Snapshots de Componentes por Asignación**: Al crear o cerrar una asignación se captura automáticamente un snapshot de todos los componentes del vehículo (tabla `assignment_component_snapshots`). Soporte para observaciones/daños en retorno con actualización de estado en `vehicle_components`.
- **Reglas de Cierre OT**: Al completar una OT se exige `exit_km` (≥ entry km) y resumen de trabajo. Se registra automáticamente el odómetro de salida. Nuevas columnas: `exit_km`, `resumen`, `completed_at`, `completed_by`.
- **Filtros Avanzados Mantenimientos**: Filtros por tipo, proveedor/taller, rango de costo y rango de fecha, además de los existentes (vehículo, estado, texto).
- **Alertas de Anomalías Combustible**: Endpoint `?action=anomalias` que detecta rendimiento bajo vs promedio móvil, cargas muy cercanas en tiempo (<24h), retroceso de odómetro y saltos inusuales. UI con modal de anomalías.
- **Mantenimiento Preventivo**: Nuevo módulo completo con tabla `preventive_intervals` para configurar intervalos por km/días por vehículo. Verificación de vencimientos con alertas visuales. Botón "Crear OT" desde alerta que genera OT automáticamente y actualiza el intervalo.
- **Settings ampliados**: `fuel.max_litros_evento` (máx litros por carga), `maintenance.umbral_aprobacion` (costo que requiere aprobación).
- **Validación max litros**: POST de combustible rechaza cargas que excedan el máximo configurado (sin override).

### Mejoras
- **Soft-delete universal**: Proveedores, mantenimientos, combustible, incidentes y recordatorios ahora usan soft-delete (`UPDATE deleted_at`) en vez de hard DELETE. Asignaciones se marcan como Cerradas. 7 GETs filtran `deleted_at IS NULL`.
- **Sidebar**: Nuevo enlace "📅 Preventivos" en sección Gestión.
- **UI OT**: Campos exit_km y resumen aparecen dinámicamente al seleccionar estado "Completado".

---

## [2.2.0] — 2026-02-27

### Nuevas Funcionalidades
- **Módulo de Componentes/Inventario**: Catálogo maestro de componentes (`components`) con tipos (tool, safety, document, card, accessory) + inventario por vehículo (`vehicle_components`). UI con tabs catálogo/vehículo, KPIs de estado, filtros.
- **Máquina de Estados OT**: Transiciones formales Pendiente → En proceso → Completado/Cancelado. Solo admin puede cancelar desde "En proceso". Transiciones producen audit_log automático y actualizan estado del vehículo.
- **Partidas de Mantenimiento**: Tabla `mantenimiento_items` con descripción, cantidad, unidad, precio unitario, subtotal calculado. El costo total de la OT se recalcula automáticamente desde partidas.
- **Bloqueo de edición**: Las OTs completadas no se pueden editar ni agregar partidas.
- **Endpoint de Auditoría**: Nuevo módulo `/auditoria.php` (solo admin) con filtros por entidad, acción, usuario, rango de fecha. Modal de detalle con vista antes/después en JSON.
- **12 componentes semilla**: Gato hidráulico, llave de ruedas, triángulo de seguridad, chaleco, extintor, botiquín, cables de arranque, tarjeta de circulación, póliza de seguro, verificación, llanta refacción, herramienta básica.

### Mejoras
- Sidebar: Nuevos enlaces para Componentes (sección Gestión) y Auditoría (sección Sistema, solo admin).
- Mantenimientos ENUM: Añadido estado `Cancelado` con migración de compatibilidad.
- Vehículos: Query de listado filtra `deleted_at IS NULL` en mantenimientos web.

---

## [2.1.0] — 2026-02-27

### Nuevas Funcionalidades
- **Motor de Reportes y Exportaciones**: Nuevo módulo completo de reportes con 5 tipos de reporte (combustible, mantenimiento, utilización vehículos, top costosos, desempeño talleres) y exportación CSV para combustible, mantenimiento, asignaciones e incidentes.
- **Perfil 360 de Vehículos**: Nuevo endpoint y modal que muestra asignación activa, mantenimiento activo, último odómetro, KPIs consolidados e historial reciente de mantenimientos y combustible.
- **Soft-delete**: Vehículos y operadores ya no se eliminan de la BD; se marcan con `deleted_at` preservando todo el historial.
- **Catálogos Dinámicos en UI**: Los selectores de tipo de mantenimiento y estado de vehículo ahora cargan opciones desde las tablas de catálogo en vez de tenerlas hardcoded en HTML.
- **Nuevo include `catalogos.php`**: Helper para cargar ítems activos de catálogos dinámicos.
- **Nuevo include `export.php`**: Motor de exportación CSV reutilizable.

### Correcciones de Bugs
- **Bug crítico combustible**: Corregido `$totalStmt->fetchColumn()` que se consumía antes de usarse, provocando total = 0 en la respuesta.
- **Bug N+1 combustible**: El cálculo de rendimiento km/L ya no hace una subconsulta por cada fila; ahora se obtiene `prev_km` en la query principal.
- **Filtros recordatorios/incidentes**: El estado ya no se concatena al texto de búsqueda. Se envía como parámetro dedicado (`estado=`).
- **Filtro estado mantenimientos**: Añadido filtro dedicado por estado en API y UI de mantenimientos.

### Mejoras de Integridad
- **Transacciones DB**: Las operaciones multi-query (INSERT + odómetro + km update) ahora usan `beginTransaction/commit/rollBack` en combustible, asignaciones y mantenimientos.
- **Índices compuestos**: Añadidos índices en `combustible.fecha`, `combustible(vehiculo_id, km)`, `mantenimientos.fecha`, `mantenimientos(vehiculo_id, estado)`, `incidentes.fecha`, `incidentes(vehiculo_id, estado)`, `recordatorios(fecha_limite, estado)`, `asignaciones.created_at`.
- **Columna `deleted_at`**: Añadida a 7 tablas principales para soporte de soft-delete.
- **Semilla ampliada**: Catálogo de tipos de mantenimiento incluye más opciones (Aceite y Filtros, Frenos, Llantas, Batería, Revisión general).

### Estructura
- Nuevo enlace "Reportes" en el sidebar de navegación.
- Carpeta `docs/` con documentación del proyecto (ARQUITECTURA.md, API.md, REGLAS_NEGOCIO.md, CHANGELOG.md).

---

## [2.0.0] — Versión base analizada

### Funcionalidades existentes
- Login/logout con sesiones PHP y 4 roles (coordinador_it, soporte, monitoreo, taller)
- RBAC con permisos por acción (view/create/edit/delete/manage_users/manage_permissions)
- Dashboard con 6 KPIs, gráficos de barra y alertas
- CRUD completo: vehículos, operadores, asignaciones, combustible, mantenimientos, incidentes, recordatorios, proveedores, catálogos, usuarios
- Reglas de bloqueo de asignación (asignación activa, mantenimiento activo, estado vehículo, operador inactivo)
- Bloqueo de carga de combustible por mantenimiento activo
- Override admin con justificación y auditoría
- Odómetro: validación no-decreciente, auto-registro en flujos críticos
- Auditoría: bitácora de cambios con before/after JSON
- Catálogos base: categorías de gasto, unidades, tipos mantenimiento, estados vehículo, servicios taller
- Configuración global (system_settings)
- Historial de operadores (asignaciones + combustible + incidentes)
- Rol taller con restricciones (solo su proveedor)
