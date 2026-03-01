# FlotaControl — Changelog

## [2.9.0] — 2026-03-01

### Nuevas Funcionalidades — Módulo 14: Mejoras para Escala

- **Incidentes Avanzados con Seguros**: 8 nuevos campos en incidentes (`aseguradora`, `poliza_numero`, `tiene_reclamo`, `estado_reclamo`, `monto_reclamo`, `fecha_reclamo`, `referencia_reclamo`, `notas_seguro`). UI con sección plegable de seguros, filtro por reclamo, modal de detalle con info del seguro del vehículo. Stats cards en listado (total, abiertos, con reclamo, costos, reclamos). Búsqueda por aseguradora/póliza. API `/api/incidentes.php?detail=X` para vista individual.

- **Sistema de Notificaciones**: Tabla `notificaciones` con tipos (info/alerta/exito/warning), destino por usuario o global. API `/api/notificaciones.php` (GET no leídas, PUT marcar leída/todas). Campana con badge en topbar, panel desplegable con notificaciones en tiempo real (polling 30s). Helper `includes/notifications.php` con funciones `notify_user()`, `notify_roles()`, `notify_all()`, `send_notification_email()`. Notificaciones automáticas: incidentes Alta/Crítica → coordinador_it/admin/soporte; OT completada/cancelada → coordinador_it/admin.

- **Multi-sucursal**: Tabla `sucursales` (nombre, dirección, ciudad, teléfono, responsable, activo). Columna `sucursal_id` añadida a vehiculos, operadores, usuarios. API CRUD `/api/sucursales.php` con protección contra borrado de sucursales con registros. Vista web `/sucursales.php` con CRUD completo, conteo de vehículos/operadores por sucursal. Filtro por sucursal en vehículos (API + UI). Selector de sucursal en formulario de vehículos. Seed "Matriz" automática. Entrada en menú de navegación.

### Mejoras
- **Menú de navegación**: Añadidas entradas Sucursales (Administración) y campana de notificaciones (topbar).
- **API v1 Router**: Añadidas rutas `notificaciones` y `sucursales`. Versión actualizada a 2.9.
- **install.php**: Migraciones automáticas para columnas de seguros en incidentes, tabla sucursales, tabla notificaciones, columna sucursal_id en vehiculos/operadores/usuarios.

---

## [2.8.0] — 2026-03-01

### Nuevas Funcionalidades
- **API v1 Versionada**: Nuevo router en `/api/v1/` que enruta a los 16 módulos API existentes. Soporta path routing (`/api/v1/vehiculos`) y query param fallback (`/api/v1/index.php?_resource=vehiculos`). Headers `X-API-Version: 1.0`.
- **OpenAPI 3.0 Spec**: Especificación completa en `/api/v1/openapi.json` con 17 paths, schemas, parámetros reutilizables y documentación de todos los endpoints.
- **Swagger UI**: Documentación interactiva en `/api/v1/docs` con tema dark personalizado, Swagger UI 5 desde CDN.
- **Health Check**: Endpoint `/api/v1/health` con status, versión, timestamp y conteo de endpoints.
- **API Index**: `/api/v1/` devuelve lista de endpoints disponibles con links a docs y health.

### Mejoras
- **`.htaccess` para Apache**: Rewrite rules para URLs limpias en `/api/v1/`.
- **docs/API.md**: Actualizada con formatos XLSX/PDF en exportaciones.

---

## [2.7.0] — 2026-03-01

### Nuevas Funcionalidades
- **Exportación XLSX**: Nuevo formato de exportación a Excel (tabla HTML con MIME nativo de Excel). Compatible con Excel, LibreOffice Calc y Google Sheets. Incluye encabezados estilizados, metadatos de generación y nombre de hoja personalizado.
- **Exportación PDF**: Vista HTML imprimible con estilos profesionales (orientación landscape, logo, metadatos, resumen). Barra de acciones con botón "Imprimir/Guardar PDF" y auto-print opcional. Oculta controles al imprimir.
- **Motor de exportación unificado**: Nueva función `export_dispatch()` en `includes/export.php` que despacha a CSV, XLSX o PDF según parámetro `format`. Los 4 tipos de reporte (combustible, mantenimiento, asignaciones, incidentes) soportan los 3 formatos.
- **Botones de exportación en UI**: Tres botones diferenciados (CSV verde, XLSX verde-Excel, PDF rojo) en la toolbar de reportes. PDF se abre en nueva pestaña para impresión nativa del navegador.

### Mejoras
- **API reportes.php**: Parámetro `format` ahora acepta `csv`, `xlsx` o `pdf`. Audit log registra el formato utilizado.
- **includes/export.php**: Refactorizado de 56 a ~210 líneas con 4 funciones exportadoras.

---

## [2.6.0] — 2026-03-01

### Nuevas Funcionalidades
- **QR en documentos imprimibles**: Todos los PDFs (asignación, combustible, lote) ahora incluyen un código QR de verificación generado automáticamente con enlace al documento original.
- **Guardar PDF como adjunto**: Botón "💾 Guardar como adjunto" en la barra de impresión que captura el documento HTML y lo almacena como adjunto del registro correspondiente (asignación o carga de combustible).
- **Soporte DB Socket**: Conexión MySQL via socket Unix (`DB_SOCKET` en .env) para mayor compatibilidad en entornos de desarrollo.

### Mejoras
- **install.php**: Soporte para conexión via socket Unix.
- **includes/db.php**: Detección automática de socket vs TCP según configuración.

---

## [2.5.0] — 2026-02-27

### Nuevas Funcionalidades
- **Widget de Adjuntos Reutilizable (JS)**: Clase `AttachmentWidget` en app.js que se embebe en cualquier modal. Soporta multi-archivo, preview de pendientes, upload al guardar, descarga inline, eliminación soft-delete. Contratos: `.load()`, `.reset()`, `.uploadPending(id)`, `.hasPending()`.
- **Adjuntos integrados en Vehículos**: Widget en modal de edición y en Perfil 360. Adjuntar fotos, seguro, permisos y documentos de cada vehículo.
- **Adjuntos integrados en Mantenimientos**: Widget en modal OT y en vista de partidas. Adjuntar diagnóstico, cotización, factura y fotos por OT.
- **Adjuntos integrados en Combustible**: Widget en modal de carga. Adjuntar foto de recibo y odómetro.
- **Adjuntos integrados en Operadores**: Widget en modal. Adjuntar documentos de licencia, identificación, certificados.
- **Adjuntos obligatorios sobre umbral**: Setting `maintenance.umbral_adjuntos` (default $3000). Al completar una OT con costo ≥ umbral, se exige al menos un adjunto. Regla validada en API con mensaje descriptivo.
- **PDF Entrega/Retorno de Vehículo**: Vista HTML imprimible con datos de vehículo, operador, asignación, checklist de componentes (snapshot) y líneas de firma (operador, flota, administración). Botón 🖨️ en tabla de asignaciones.
- **PDF Autorización Combustible**: Vista HTML imprimible individual con datos de carga, vehículo, conductor, firmas(conductor, flota, contabilidad). Botón 🖨️ por registro. Soporte para impresión por lote (`?type=combustible_lote&ids=1,2,3`).
- **Módulo de Impresión**: Nuevo `/print.php` con CSS dedicado para impresión, header con folio y fecha, footer corporativo.

### Mejoras
- **Setting nuevo**: `maintenance.umbral_adjuntos` en install.php (seed $3000).
- **CSS de impresión**: Diseño profesional blanco/negro con @page letter, tablas, firmas y formato de folio.

---

## [2.4.0] — 2026-02-27

### Nuevas Funcionalidades
- **Matriz de Permisos Granular por Módulo**: Tabla `role_module_permissions` con permisos (view/create/edit/delete) por rol y módulo. UI admin en `/permisos.php` con tabs por rol, checkboxes por módulo y guardado masivo. Función `can_module(módulo, permiso)` en auth.php. Carga async de permisos en frontend via `userCanModule()`.
- **Sistema de Adjuntos Reutilizable**: Tabla `attachments` + helper `includes/attachments.php` con upload, listado, descarga y soft-delete. API genérica `/api/attachments.php` con soporte multi-archivo. Validación de tipo MIME, extensión y tamaño (10MB max). Carpeta `/uploads/` con .gitignore.
- **Dashboard Mejorado**: Nuevos paneles: alertas preventivas (vencidos/próximos) y OTs activas (pendientes/en proceso). Total 4 paneles informativos en dashboard.
- **Reporte de Overrides**: Nuevo tipo `?report=overrides` que consulta audit_logs por acciones de override. Muestra motivo, usuario, IP, entidad. Resúmenes por usuario y por entidad. UI en reportes con tabla.
- **Perfil Operador 360**: Nuevo tipo `?report=operador_360` con KPIs (asignaciones, litros, km, incidentes), historial de asignaciones, combustible vinculado e incidentes. UI con selector de operador y KPIs visuales.
- **Agrupaciones y Ordenamientos Avanzados**: Parámetros `group_by`, `order_by`, `order_dir` en reportes de combustible (vehiculo/mes/semana/proveedor/tipo_carga/metodo_pago) y mantenimiento (vehiculo/mes/semana/tipo/proveedor/estado). UI con toolbar de agrupación dinámico y tabla agrupada separada.
- **Pruebas Automatizadas**: Suite `tests/test_rules.php` con 10 secciones: auth, odómetro, bloqueo asignaciones, bloqueo combustible, máquina estados OT, reglas cierre, soft-delete, adjuntos, esquema BD, settings.

### Mejoras
- **Sidebar**: Nuevo enlace "🔐 Permisos" en sección Sistema (solo admin).
- **Frontend Permisos**: `loadModulePerms()` async + `userCanModule(mod, perm)` disponible globalmente.

---

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
