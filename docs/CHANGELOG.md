# FlotaControl — Changelog

## [3.7.0] — 2026-03-07

### Mejora Mayor — Objetivo 7: Dashboard Ejecutivo Mejorado

- **Dashboard completamente reescrito**: De estático server-side a dinámico con API dedicada.
- **API /api/dashboard.php**: Nuevo endpoint centralizado con KPIs, gráficos, listas y filtros.
- **Filtros dinámicos**: Por sucursal, vehículo y período (mes/trimestre/semestre/año).
- **6 KPIs mejorados**: Vehículos, Operadores, Combustible, Mantenimiento, Incidentes, Alertas.
- **Tendencias automáticas**: Comparativa % vs período anterior en gasto combustible y mantenimiento.
- **5 gráficos Chart.js**: Gasto mensual (línea doble), Top 10 vehículos (bar horizontal apilado), Distribución mantenimiento (doughnut), Incidentes mensuales (bar), Eficiencia operadores (bar horizontal).
- **3 paneles de listas**: Recordatorios próximos, OTs activas, Alertas activas (integrado con Centro de Alertas).
- **Responsive**: Grid adaptativo 1→2→3 columnas.

### API
- `dashboard.php`: Nuevo — KPIs con trends, 5 datasets de gráficos, 3 listas, filtros dinámicos.

### Documentación
- `docs/OBJ7_DASHBOARD_EJECUTIVO.md`

---

## [3.6.0] — 2026-03-02

### Nuevo Módulo — Objetivo 6: Centro de Alertas

- **Módulo alertas completo**: Nuevo centro de mando unificado para todas las alertas del sistema.
- **Escaneo automático**: 8 detectores (licencias, seguros, componentes, recordatorios, stock bajo, incidentes sin atender, contratos, mantenimientos vencidos).
- **Priorización inteligente**: 4 niveles (Baja, Normal, Alta, Urgente) con badges visuales y auto-detección de prioridad según urgencia.
- **Historial de cambios**: Timeline completo de cada alerta (creación, asignación, cambios de estado, notas).
- **Asignación de responsables**: Asignación directa a usuarios con notificación automática.
- **KPI cards**: Activas, Urgentes, Altas, Sin Asignar en tiempo real.
- **Filtros avanzados**: Por tipo (10), prioridad (4), estado (4), búsqueda libre.
- **Creación manual**: Formulario completo con selects dinámicos de vehículos y usuarios.
- **Anti-duplicación**: Verificación automática antes de crear alertas escaneadas.
- **Auto-resolución**: Alertas de recordatorios se auto-resuelven cuando el recordatorio cambia de estado.

### Migraciones
- **install.php §3.17**: 2 tablas (alertas con 10 tipos ENUM y 7 índices, alerta_historial con FK CASCADE).

### API
- `alertas.php`: Nuevo — scan, stats, historial, CRUD completo.

### Navegación
- Sidebar: nueva entrada "🚨 Centro de Alertas" en sección Gestión.

### Documentación
- `docs/OBJ6_CENTRO_ALERTAS.md`

---

## [3.5.0] — 2026-03-02

### Nuevas Funcionalidades — Objetivo 5: Operadores + Componentes + Proveedores + Sucursales

- **Capacitaciones de operadores**: Registro completo con tipo (Interna/Externa/Online), horas, vencimiento. CRUD completo.
- **Infracciones de operadores**: Multas, accidentes, violaciones. Monto, estado (Pendiente/Pagada/Contestada), referencia.
- **KPIs de desempeño**: 10 métricas por operador (asignaciones, km, incidentes, infracciones, capacitaciones, eficiencia km/L, días activo).
- **Inventario con movimientos**: Entradas, salidas, transferencias, ajustes. Stock consolidado auto-actualizado.
- **Stock mínimo y alertas**: Columna stock_minimo en catálogo. Badge visual con alertas de nivel bajo.
- **Alertas vencimiento componentes**: Detección automática de componentes por vencer (30/60 días). Badge en toolbar.
- **Evaluaciones de proveedores**: 4 dimensiones (calidad, puntualidad, precio, servicio) escala 1-5. Promedio auto-calculado.
- **Ranking de proveedores**: Tabla ordenada por promedio de evaluaciones.
- **Contratos de proveedores**: Registro con tipo, monto, fechas, estado. CRUD completo.
- **Dashboard comparativo de sucursales**: 4 gráficos Chart.js (vehículos/operadores, gasto mantenimiento, gasto combustible, incidentes).

### Migraciones
- **install.php §3.16**: 5 tablas (operador_capacitaciones, operador_infracciones, componente_movimientos, proveedor_evaluaciones, proveedor_contratos). 2 columnas (components.stock, components.stock_minimo).

### API
- `operadores.php`: +3 endpoints (capacitaciones, infracciones, kpis)
- `componentes.php`: +2 secciones (movimientos, alertas_vencimiento)
- `proveedores.php`: +4 endpoints (evaluaciones, contratos, ranking)
- `sucursales.php`: +1 endpoint (dashboard)

### Documentación
- `docs/OBJ5_OPERADORES_COMPONENTES_PROVEEDORES_SUCURSALES.md`

---

## [3.4.0] — 2026-03-02

### Nuevas Funcionalidades — Objetivo 4: Mejoras Combustible + Incidentes

- **Gráfico comparativo de combustible**: Bar charts de gasto y litros por período (mes/semana/día) con línea de precio promedio. Comparativa porcentual vs período anterior.
- **Indicador de eficiencia por vehículo**: Ranking con km/L y $/km. Modal dedicado con filtros de fecha. Mínimo 2 cargas para cálculo.
- **Adjuntos en incidentes**: AttachmentWidget integrado en crear/editar y detalle. Soporte multi-archivo.
- **Flujo de seguimiento por estados**: Máquina de estados formal (Abierto→En proceso→Cerrado + reabrir). Log automático de cambios. Notas manuales de seguimiento. Tabla `incidente_seguimientos`.
- **Dashboard de seguridad**: KPIs (total, abiertos, críticos, días prom. resolución), gráfico mensual, doughnut por severidad, top 10 vehículos. Filtro por año.
- **Campo prioridad en incidentes**: Baja, Normal, Alta, Urgente.
- **Tracking de resolución**: `resolved_at` y `resolved_by` automáticos al cerrar.

### Migraciones
- **install.php §3.15**: Tabla `incidente_seguimientos`. Columnas `resolved_at`, `resolved_by`, `prioridad` en incidentes.

### API
- `combustible.php`: +2 endpoints (chart_data, eficiencia)
- `incidentes.php`: +2 endpoints (seguimientos, dashboard), máquina de estados, prioridad

### Documentación
- `docs/OBJ4_COMBUSTIBLE_INCIDENTES.md` — Documentación completa del objetivo.

---

## [3.3.0] — 2026-03-02

### Nuevas Funcionalidades — Objetivo 3: Mejoras Asignaciones + Mantenimientos

- **Calendario visual de asignaciones**: Vista FullCalendar.js con toggle tabla/calendario, filtrado por vehículo, eventos coloreados por estado, vistas mensual/semanal/listado.
- **Checklist dinámico con plantillas**: Plantillas configurables de checklist (entrega/retorno/ambos) con N items ordenados. Selector de plantilla en modal de nueva asignación. Respuestas vinculadas a cada asignación. Compatible con checklist fijo existente.
- **Aprobaciones multinivel para OTs**: Activación automática por umbral de costo ($5,000 N1 / $15,000 N2). Bloqueo de transición Pendiente→En proceso si pendiente/rechazada. Botón de aprobación/rechazo directa en tabla. Contador de pendientes para coordinadores.
- **Componentes en partidas de mantenimiento**: Selector de componente vinculado al vehículo de la OT. Columna `component_id` en `mantenimiento_items`.

### Migraciones
- **install.php §3.12**: Tablas `checklist_plantillas`, `checklist_plantilla_items`, `asignacion_checklist_respuestas`. Plantilla estándar con 8 items pre-cargados.
- **install.php §3.13**: Tabla `mantenimiento_aprobaciones`. Columnas `requiere_aprobacion`, `aprobacion_estado` en mantenimientos. Settings de umbrales.
- **install.php §3.14**: Columnas `component_id` en mantenimiento_items, `plantilla_id` en asignaciones.

### API
- `asignaciones.php`: +4 sub-endpoints (calendar, checklist_plantillas, checklist_items, checklist_respuestas)
- `mantenimientos.php`: +3 sub-endpoints (aprobaciones, pending_approvals), approval gate, auto-trigger, component_id en items CRUD

### Dependencias
- **FullCalendar 6.1.10** (CDN) para vista de calendario de asignaciones.

### Documentación
- `docs/OBJ3_ASIGNACIONES_MANTENIMIENTOS.md` — Documentación completa del objetivo.

---

## [3.2.0] — 2026-03-02

### Nuevas Funcionalidades — Objetivo 2: Mejoras del Módulo Vehículos

- **Clasificación por etiquetas**: Sistema de etiquetas libres para vehículos. Tabla `vehiculo_etiquetas` con restricción UNIQUE. API CRUD (GET/POST/DELETE). UI con pills de colores en tabla, filtro por etiqueta en toolbar, y gestión en modal de edición.
- **Costo por kilómetro**: Cálculo automático `(gasto_mantenimiento + gasto_combustible) / km_actual`. Nuevo KPI destacado con borde accent en el Perfil 360. Incluye `gasto_total` agregado.
- **Historial visual de kilometraje**: Gráfica Chart.js (line chart con fill) en el Perfil 360. Últimos 30 registros de `odometer_logs`. Colores dark-mode friendly con eje Y formateado.
- **Estructura de telemetría**: Tabla `telemetria_logs` (BIGINT PK, tipo/valor/unidad, GPS lat/lon, fuente). Últimos 20 registros en perfil. Placeholder UI para futura integración GPS/OBD.
- **Campos financieros**: 3 nuevas columnas en vehículos (`costo_adquisicion`, `aseguradora`, `poliza_numero`). Formulario actualizado y visualización en Perfil 360.
- **KPIs ampliados en Perfil 360**: Grid de 5 KPIs (asignaciones, mantenimientos, litros, gasto total, costo/km). Datos adicionales del vehículo (adquisición, aseguradora, póliza).

### Migraciones
- **install.php §3.9**: Tabla `vehiculo_etiquetas` con índice UNIQUE y por etiqueta.
- **install.php §3.10**: Tabla `telemetria_logs` con índices compuestos.
- **install.php §3.11**: Columnas `costo_adquisicion`, `aseguradora`, `poliza_numero` en vehiculos.

### Documentación
- `docs/OBJ2_VEHICULOS.md` — Documentación completa del objetivo.

---

## [3.1.0] — 2026-03-02

### Nuevas Funcionalidades — Objetivo 1: Tailwind CSS + Tema Dark/Light

- **Tailwind CSS integrado**: Framework CSS principal via Play CDN con configuración personalizada de colores, fuentes y breakpoints del sistema.
- **Tema oscuro/claro**: Toggle en topbar (🌙/☀️) con persistencia en `localStorage`. Tema oscuro por defecto. Variables CSS duales para ambos temas con paleta profesional diferenciada (dark: neón amarillo/cyan, light: indigo/teal).
- **Layout responsivo mejorado**: Sidebar oculto con hamburguesa en mobile (< 1024px), click-outside para cerrar, padding adaptativo en topbar y contenido.
- **Soporte 4K**: Media query `@media(min-width:2560px)` con grids expandidos, contenido centrado con max-width 2200px.
- **Login modernizado**: Página de login migrada completamente a Tailwind con clases utilitarias, soporte de tema y focus rings.
- **Dashboard con Tailwind grids**: KPI grid responsivo (2→3→6 columnas), secciones con `lg:grid-cols-2`.
- **Firma digital modernizada**: Página standalone `firma.php` migrada a Tailwind con diseño consistente.
- **Capa de compatibilidad CSS**: `style.css` reescrito como bridge entre clases custom existentes (16 módulos) y Tailwind, sin breaking changes.

### Documentación
- `docs/OBJ1_TAILWIND_CSS.md` — Documentación completa del objetivo.
- `docs/PLAN_MEJORAS.md` — Plan de trabajo con 9 objetivos e inventario completo.

---

## [3.0.0] — 2026-03-01

### Corrección de Errores
- **Mantenimientos PDF**: Añadido tipo `mantenimiento` al generador de PDF con detalles completos de OT, vehículo, partidas/refacciones, costos totales y bloque de firmas. Botón 🖨️ en listado.
- **Items en OT completada**: API ahora bloquea agregar/editar/eliminar items cuando la OT está en estado Completado. UI oculta botones de acción condicionalmente.
- **Subida de archivos**: Añadidos tipos MIME `text/html` y `application/octet-stream` a la whitelist. Fallback de MIME del navegador cuando `mime_content_type()` falla. Aumentado límite de upload a 20MB.
- **Permisos vacíos**: Añadidos módulos `sucursales` y `notificaciones` al array `$MODULOS` en API. Corregido `loadMatrix()` para usar helper `api()` con manejo de errores.
- **Intervalos preventivos**: Eliminada actualización prematura de `ultimo_km`/`ultima_fecha` al crear OT. Ahora se actualiza al completar la OT. Añadida verificación de OT duplicada antes de crear.
- **Filtros duplicados en mantenimientos**: Eliminadas adiciones duplicadas de filtros `$vid` y `$estado` en GET.
- **Asignaciones DELETE**: Corregido para cerrar correctamente asignaciones activas con `end_at` + `deleted_at`.
- **Variable `$id` indefinida**: Corregido `attachment_list('mantenimientos', $id)` → `attachment_list('mantenimientos', (int)$d['id'])` en PUT de mantenimientos.

### Nuevas Funcionalidades
- **Checklist de vehículos**: 6 nuevos campos en vehículos (`tiene_gata`, `tiene_herramientas`, `tiene_llanta_repuesto`, `tiene_bac_flota`, `revision_ok`, `detalles_checklist`). UI con checkboxes en grid + textarea de detalles. API actualizada en POST y PUT.
- **Checklist de asignaciones**: 12 campos de checklist (6 entrega + 6 retorno) en asignaciones. Auto-llenado desde perfil del vehículo al seleccionar. Checklist visible en modal de cierre.
- **Firma digital/física**: Sistema de firmas con 3 modalidades (ninguna, digital, física). Canvas de dibujo para firma digital con soporte mouse+touch. Generación de link externo con token para firma remota del operador. Página externa `firma.php` sin autenticación (token-based) optimizada para móvil. Campos: `firma_tipo`, `firma_data`, `firma_token`, `firma_fecha`, `firma_ip`.
- **PDF de asignaciones mejorado**: Checklist de entrega y retorno incluidos en el PDF. Firma digital del operador renderizada como imagen con metadatos (fecha, IP, tipo).

### Migraciones
- **install.php §3.7**: 6 columnas de checklist en `vehiculos`.
- **install.php §3.8**: 17 columnas en `asignaciones` (12 checklist + 5 firma).

---

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
