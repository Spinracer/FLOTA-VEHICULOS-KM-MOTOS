# Sistema de Gestión de Flota (Laravel + MySQL) — Plan de Implementación

> Objetivo: un sistema de flota de nivel productivo (tipo TuFlota) con reglas de negocio estrictas, control detallado de mantenimiento y combustible, bloqueos de asignación de vehículo, adjuntos (fotos/documentos), inventario de componentes/herramientas por vehículo y reportes exportables (CSV/XLSX/PDF).
>
> Stack objetivo: **PHP 8.2+**, **Laravel 10/11**, **MySQL 8**. Diseño API-first con UI consumiendo la API.
>
> Estado actual del repositorio: **aplicación PHP tradicional (sin Laravel)** con módulos CRUD operativos.

---

## 0) Decisiones técnicas (hacer primero)

### 0.1 Paquetes requeridos
- [ ] **Auth y API**: Laravel Sanctum
- [ ] **Exportaciones**: `maatwebsite/excel`
- [ ] **PDF**: `barryvdh/laravel-dompdf` o `spatie/browsershot`
- [ ] **Archivos/Media**: `spatie/laravel-medialibrary`
- [ ] **Auditoría**: `spatie/laravel-activitylog`
- [ ] **Permisos**: `spatie/laravel-permission`

### 0.2 Reglas de arquitectura no negociables
- [ ] Capa de servicios para reglas de negocio
- [ ] Policies/Guards para bloquear acciones prohibidas
- [ ] Auditoría en toda edición/anulación crítica
- [ ] Exportaciones desde queries filtradas y trazables

---

## 1) Base del proyecto y convenciones

### Subtareas
- [x] Definir requisitos de entorno (PHP, MySQL, variables de entorno en `.env`)
- [ ] Estándares de código (PSR-12, Pint, Larastan/PHPStan)
- [ ] Estructura Laravel (`app/Domain`, `app/Http/...`, `app/Exports`, etc.)
- [ ] Versionado de API (`/api/v1/...`)
- [ ] Formato unificado de respuestas y errores
- [ ] Mapeo global de excepciones

**Aceptación**
- API responde de forma consistente con validaciones y errores estandarizados.

---

## 2) Seguridad, roles, permisos y auditoría

### 2.1 Autenticación
- [x] Login/logout funcional (sesión PHP actual)
- [ ] Tokens (Sanctum)
- [ ] Rate limiting en auth
- [ ] Recuperación de contraseña (opcional)

### 2.2 RBAC
- [x] Roles base implementados (`coordinador_it`, `soporte`, `monitoreo`)
- [x] Permisos base por acción (`view/create/edit/delete`)
- [x] Protección de rutas sensibles (ej. usuarios admin)
- [x] Matriz de permisos granular por módulo objetivo (vehículos, asignaciones, mantenimiento, combustible, reportes)

### 2.3 Auditoría
- [x] Bitácora de cambios críticos (create/update/void/override)
- [x] Guardar before/after, usuario, IP, fecha
- [x] Endpoints de consulta de auditoría (módulo auditoria con UI admin)

**Aceptación**
- Todo cambio crítico es trazable a usuario y fecha.

---

## 3) Catálogos base y configuración del sistema

### 3.1 Catálogos
- [x] Categorías de gasto
- [x] Unidades (L, gal, pza, servicio)
- [x] Tipos de mantenimiento
- [x] Estados de vehículo del dominio objetivo
- [x] Servicios de taller

### 3.2 Configuración
- [x] Umbral de consumo anómalo (setting base)
- [x] Máximo de litros por evento (opcional)
- [x] Umbral de aprobación de mantenimiento
- [x] Intervalos preventivos por vehículo

**Aceptación**
- Formularios usan IDs de catálogo, sin texto libre.

---

## 4) Módulo de vehículos (perfil 360)

### 4.1 CRUD de vehículos
- [x] Tabla y CRUD de vehículos (placa, marca, modelo, año, combustible, estado, km, notas)
- [x] Soft-delete con bloqueo por historial
- [x] Endpoint perfil 360 con asignación activa, mantenimiento activo, último odómetro/combustible y totales

### 4.2 Fotos y documentos
- [x] Adjuntar múltiples fotos
- [x] Adjuntar documentos (seguro, permisos, etc.)
- [x] Validar tipo/tamaño de archivo
- [x] URLs firmadas o descarga controlada

### 4.3 Odómetro
- [x] Tabla `odometer_logs`
- [x] Bloqueo de odómetro decreciente con override justificado (API)
- [x] Auto-registro de odómetro en flujos críticos (vehículos/combustible/mantenimientos)
- [x] Actualización de `km_actual` del vehículo al registrar combustible/mantenimiento (parcial)

**Aceptación**
- Odómetro consistente en todos los flujos.

---

## 5) Inventario de componentes/herramientas por vehículo

### 5.1 Catálogo y mapeo
- [x] Catálogo `components` (tabla + CRUD + semillas)
- [x] Tabla `vehicle_components` (asignación por vehículo)
- [x] Tipos (`tool`, `safety`, `document`, `card`, `accessory`)
- [x] Datos de tarjetas (proveedor, vencimiento, estado, N° serie)

### 5.2 Checklist por asignación
- [x] Captura de faltantes/daños/fotos en entrega/retorno
- [x] Snapshot `assignment_component_snapshots`

**Aceptación**
- Trazabilidad de herramientas/tarjetas por evento.

---

## 6) Personal / conductores

- [x] CRUD de conductores/operadores (datos básicos)
- [x] Estado activo/inactivo/suspendido en operador
- [x] Documentos de licencia con adjuntos
- [x] Historial de asignaciones/combustible/incidentes por conductor
- [x] Regla: conductor inactivo no puede asignarse

**Aceptación**
- Perfil del conductor auditable con historial.

---

## 7) Asignaciones (con reglas de bloqueo)

### 7.1 Ciclo de vida
- [x] Crear asignación
- [x] Cerrar asignación
- [x] Historial completo sin sobrescritura

### 7.2 Reglas duras de bloqueo
- [x] No asignar si tiene asignación activa
- [x] No asignar si tiene mantenimiento activo
- [x] No asignar si vehículo bloqueado/fuera de servicio
- [x] Error con razón e ID del bloqueo
- [x] Override admin con justificación y auditoría

### 7.3 PDF opcional
- [x] PDF entrega/retorno con checklist y firmas

**Aceptación**
- No hay reasignación indebida sin override trazable.

---

## 8) Talleres autorizados y portal

- [x] CRUD de proveedores/talleres base (parcial)
- [x] Flag de “taller autorizado”
- [x] Cuentas de usuario tipo taller
- [x] Fronteras de permisos del taller

**Aceptación**
- Solo talleres autorizados registran mantenimiento.

---

## 9) Mantenimiento (OT + historial)

### 9.1 Orden de trabajo (OT)
- [x] Crear OT formal con máquina de estados completa (Pendiente→En proceso→Completado/Cancelado)
- [x] Registro de mantenimientos con estado básico (`Completado`, `En proceso`, `Pendiente`, `Cancelado`)
- [x] Adjuntos (diagnóstico, cotización, factura, fotos)

### 9.2 Ítems de mantenimiento
- [x] Tabla de partidas (cantidad, unidad, precio, subtotal) → `mantenimiento_items`
- [x] Totales calculados automáticamente (costo OT = SUM partidas)
- [x] Bloqueo de edición al completar

### 9.3 Programación preventiva
- [x] Recordatorios preventivos básicos por fecha (parcial)
- [x] Programación por km/días con vencimientos automáticos
- [x] Crear OT desde alerta en un clic

### 9.4 Reglas de cierre
- [x] `exit_km` obligatorio y validado
- [x] Resumen de trabajo obligatorio
- [x] Adjuntos obligatorios sobre umbral
- [x] Odómetro automático
- [x] Actualización de estado operativo según asignación

### 9.5 Historial y exportación
- [x] Listado histórico con filtros básicos (vehículo/texto)
- [x] Filtros avanzados (rango costo, taller, tipo, estado)
- [x] Exportar CSV / XLSX / PDF (todos los formatos implementados)

**Aceptación**
- OT como fuente única, exportable y auditable.

---

## 10) Combustible (detallado) + PDF de autorización

### 10.1 Registro
- [x] Registro de combustible (vehículo, litros, costo, total, km, proveedor, tipo, notas)
- [x] Driver/responsable explícito
- [x] Método de pago, número de recibo
- [x] Adjuntos (foto de recibo y odómetro)

### 10.2 Reglas de bloqueo
- [x] Bloquear carga si vehículo está en mantenimiento activo
- [x] Flujo de excepción con motivo/posible aprobación/auditoría

### 10.3 Consumo y anomalías
- [x] Cálculo de km/L por registro (en consulta, no persistido)
- [x] Cálculo de total automático y KPIs de litros/gasto
- [x] Promedio móvil persistido por vehículo
- [x] Alertas por anomalías (cargas muy cercanas, exceso de capacidad, odómetro sospechoso)

### 10.4 PDF autorización con firmas
- [x] PDF por registro y por lote
- [x] Líneas de firma (conductor, flota, contabilidad)
- [x] QR opcional
- [x] Guardar PDF generado como adjunto

**Aceptación**
- Control de combustible estricto, medible e imprimible.

---

## 11) Reportes y exportaciones (CSV/XLSX/PDF)

### 11.1 Motor reusable
- [x] `ReportQueryBuilder` con filtros (includes/export.php + modules/api/reportes.php)
- [x] `ReportExportService` con CSV, XLSX y PDF (export_dispatch)
- [ ] Colas para exportes grandes
- [x] Trazabilidad de exportes (audit_log en cada export)

### 11.2 Reportes mínimos
- [x] Costos de mantenimiento
- [x] Combustible (totales/km-L/costo por km)
- [x] Utilización de vehículos
- [x] Top vehículos más costosos
- [x] Desempeño por taller

### 11.3 Filtros
- [x] Filtros básicos en varios módulos (texto, vehículo, estado, paginación)
- [x] Filtros avanzados por rango de fecha (parcial, combustible)
- [x] Agrupaciones y ordenamientos avanzados

**Aceptación**
- Todos los reportes exportables y filtrables mínimo por vehículo + fecha.

---

## 12) Diseño de API REST + documentación

- [x] Endpoints REST base por módulo (`/api/*.php`)
- [x] Versionado `/api/v1/*` (router con fallback a query param)
- [ ] FormRequests/Resources (Laravel — pendiente migración)
- [x] Documentación automática (OpenAPI 3.0 + Swagger UI)

**Aceptación**
- Funcionalidad disponible vía API documentada.

---

## 13) Integridad, concurrencia y performance

- [x] Índices compuestos críticos (8 índices añadidos)
- [x] Transacciones en operaciones críticas (combustible, asignaciones, mantenimientos)
- [x] Restricción de únicos activos (asignación/mantenimiento)
- [x] Pruebas automatizadas de reglas duras
- [x] Restricciones básicas existentes (FK y `placa` única)

**Aceptación**
- Sin condiciones de carrera ni dobles activos.

---

## 14) Mejoras opcionales para escala

- [ ] Colas con Redis + Horizon
- [ ] Notificaciones (email/WhatsApp)
- [ ] Multi-sucursal
- [ ] Módulo de incidentes avanzados con seguros
- [x] Reporte de overrides

---

## Definición global de terminado

- [ ] Cada módulo tiene API + validación + políticas
- [ ] Cambios críticos auditados
- [ ] Adjuntos donde aplica
- [ ] Reglas de bloqueo aplicadas
- [ ] Reportes exportables y trazables
- [ ] PDFs con folio, fecha y firmas

---

## Orden sugerido de implementación por módulos (estricto)

1. Seguridad + RBAC + auditoría
2. Catálogos + vehículos + odómetro
3. Componentes/herramientas
4. Asignaciones (reglas de bloqueo)
5. Talleres
6. Mantenimiento (OT + historial)
7. Combustible (km/L + PDFs)
8. Reportes + exportaciones
9. Endurecimiento + pruebas + performance

---

## Nota de ejecución acordada

- Se trabajará **módulo por módulo**.
- **No se avanza al siguiente módulo** hasta cerrar completamente el actual (backend + reglas + UI + pruebas mínimas).
- Las casillas se actualizarán al terminar cada entregable verificable.
